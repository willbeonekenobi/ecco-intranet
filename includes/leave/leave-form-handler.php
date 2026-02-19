<?php
if (!defined('ABSPATH')) exit;

add_action('admin_post_ecco_submit_leave', 'ecco_handle_leave_submission');
add_action('admin_post_nopriv_ecco_submit_leave', 'ecco_handle_leave_submission');

function ecco_handle_leave_submission() {

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ecco_leave_nonce')) {
        wp_die('Security check failed.');
    }

    if (!is_user_logged_in()) {
        wp_die('You must be logged in.');
    }

    global $wpdb;

    $user_id    = get_current_user_id();
    $leave_type = sanitize_text_field($_POST['leave_type'] ?? '');
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date   = sanitize_text_field($_POST['end_date'] ?? '');

    if (!$leave_type || !$start_date || !$end_date) {
        wp_die('Missing required fields.');
    }

    /* ---------------------------------------------------------
       Calculate requested days
    --------------------------------------------------------- */

    $start = new DateTime($start_date);
    $end   = new DateTime($end_date);
    $days  = $start->diff($end)->days + 1;

    /* ---------------------------------------------------------
       Get user's balance from DB
    --------------------------------------------------------- */

    $table = $wpdb->prefix . 'ecco_leave_balances';

    $balance = $wpdb->get_var($wpdb->prepare(
        "SELECT balance
         FROM $table
         WHERE user_id = %d
         AND leave_type = %s
         LIMIT 1",
        $user_id,
        $leave_type
    ));

    if ($balance === null) {
        $balance = 0;
    }

    /* ---------------------------------------------------------
       BLOCK submission if insufficient balance
    --------------------------------------------------------- */

    if ($balance < $days) {

        wp_die(
            '<h2>Insufficient Leave Balance</h2>
             <p>You requested <strong>' . esc_html($days) . '</strong> day(s).</p>
             <p>Your available balance for <strong>' . esc_html($leave_type) . '</strong> is <strong>' . esc_html($balance) . '</strong> day(s).</p>
             <p>Please adjust your request.</p>
             <p><a href="javascript:history.back()">Go back</a></p>',
            'Leave Request Error',
            array('response' => 200)
        );
    }

    /* ---------------------------------------------------------
       EXISTING SAVE LOGIC (UNCHANGED)
    --------------------------------------------------------- */

    $leave_table = $wpdb->prefix . 'ecco_leave_requests';

    $wpdb->insert(
        $leave_table,
        array(
            'user_id'    => $user_id,
            'leave_type' => $leave_type,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'reason'     => sanitize_textarea_field($_POST['reason'] ?? ''),
            'status'     => 'Pending',
            'created_at' => current_time('mysql')
        )
    );

    /* ---------------------------------------------------------
       Redirect back to form (success)
    --------------------------------------------------------- */

    wp_redirect(add_query_arg('leave_submitted', '1', wp_get_referer()));
    exit;
}