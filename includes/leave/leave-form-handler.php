<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('ecco_graph_put')) {
    require_once WP_PLUGIN_DIR . '/ecco-intranet/includes/graph-client.php';
}

add_action('admin_post_ecco_submit_leave', 'ecco_handle_leave_submission');
add_action('admin_post_nopriv_ecco_submit_leave', 'ecco_handle_leave_submission');

add_action('admin_post_ecco_leave_approve', 'ecco_handle_leave_approve');
add_action('admin_post_ecco_leave_reject', 'ecco_handle_leave_reject');


/* =========================================================
   SUBMIT LEAVE REQUEST
========================================================= */

function ecco_handle_leave_submission() {

    if (!is_user_logged_in()) wp_die('Not allowed');
    check_admin_referer('ecco_leave_nonce');

    global $wpdb;

    $user_id            = get_current_user_id();
    $leave_type         = sanitize_text_field($_POST['leave_type'] ?? '');
    $reason             = sanitize_textarea_field($_POST['reason'] ?? '');
    $requester_comment  = sanitize_textarea_field($_POST['reason'] ?? ''); // same field from form
    $start_date         = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date           = sanitize_text_field($_POST['end_date'] ?? '');

    if (!$leave_type || !$start_date || !$end_date) {
        wp_die('Missing required fields.');
    }

    /* ---------- Attachment check ---------- */

    $leave_types = get_option('ecco_leave_types', []);
    $requires_image = false;

    foreach ($leave_types as $lt) {
        if (($lt['label'] ?? '') === $leave_type) {
            $requires_image = !empty($lt['requires_image']);
            break;
        }
    }

    $attachment_url = null;

    if ($requires_image && !empty($_FILES['leave_attachment']['tmp_name'])) {

        $file = $_FILES['leave_attachment'];
        $contents = file_get_contents($file['tmp_name']);
        if (!$contents) wp_die('Unable to read uploaded file.');

        $me = ecco_get_graph_user_profile();
        if (!$me) wp_die('Unable to resolve Microsoft profile.');

        $display = sanitize_title($me['displayName']);
        $month   = date('Y-m');
        $library = 'Leave-Documents';

        if (function_exists('ecco_graph_ensure_folder')) {
            ecco_graph_ensure_folder("{$library}/{$display}/{$month}");
        }

        $drive_id = get_option('ecco_leave_drive_id');
        if (!$drive_id) wp_die('Leave document library not configured.');

        $path = "{$library}/{$display}/{$month}/" . sanitize_file_name($file['name']);

        $upload = ecco_graph_put("/drives/{$drive_id}/root:/{$path}:/content", $contents, $file['type']);

        if (!$upload || empty($upload['webUrl'])) {
            wp_die('Failed to upload supporting document.');
        }

        $attachment_url = esc_url_raw($upload['webUrl']);
    }

    /* ---------- Manager ---------- */

    $manager = ecco_resolve_effective_manager();

    /* ---------- INSERT INTO ecco_leave_requests ---------- */

    $table = $wpdb->prefix . 'ecco_leave_requests';

    $inserted = $wpdb->insert(
        $table,
        [
            'user_id'           => $user_id,
            'leave_type'        => $leave_type,
            'start_date'        => $start_date,
            'end_date'          => $end_date,
            'reason'            => $reason,
            'requester_comment' => $requester_comment,
            'manager_email'     => $manager['mail'] ?? null,
            'status'            => 'pending',
            'attachment_url'    => $attachment_url,
            'created_at'        => current_time('mysql')
        ]
    );

    if ($inserted === false) {
        wp_die('Database insert failed: ' . $wpdb->last_error);
    }

    $request_id = $wpdb->insert_id;

    /* ---------- Notify manager ---------- */

    if (!empty($manager['mail'])) {

        $dashboard_url = site_url('/leave-dashboard/?request_id=' . $request_id);

        wp_mail(
            $manager['mail'],
            'Leave request awaiting approval',
            "A new leave request has been submitted.\n\n" .
            "Type: {$leave_type}\n" .
            "Dates: {$start_date} → {$end_date}\n\n" .
            "Review: {$dashboard_url}"
        );
    }

    wp_redirect(add_query_arg('leave_submitted', '1', wp_get_referer()));
    exit;
}


function ecco_calculate_leave_days($start_date, $end_date) {

    global $wpdb;

    $table = $wpdb->prefix . 'ecco_public_holidays';

    $start = new DateTime($start_date);
    $end   = new DateTime($end_date);
    $end->modify('+1 day');

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $days = 0;

    foreach ($period as $date) {

        $dayOfWeek = $date->format('N'); // 1=Mon, 7=Sun

        /* Skip weekends */
        if ($dayOfWeek >= 6) continue;

        /* Skip public holidays */

        $holiday = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE holiday_date = %s",
            $date->format('Y-m-d')
        ));

        if ($holiday) continue;

        $days++;
    }

    return $days;
}

/* =========================================================
   APPROVE / REJECT
========================================================= */

function ecco_handle_leave_approve() { ecco_handle_leave_action('approved'); }
function ecco_handle_leave_reject()  { ecco_handle_leave_action('rejected'); }

function ecco_handle_leave_action($status) {

    if (!is_user_logged_in()) wp_die('Not allowed');

    $id = intval($_POST['request_id'] ?? 0);
    if (!$id) wp_die('Invalid request');

    check_admin_referer('ecco_leave_action_' . $id);

    $comment = sanitize_textarea_field($_POST['manager_comment'] ?? '');
    if (!$comment) wp_die('Manager comment required');

    global $wpdb;

    $requests_table = $wpdb->prefix . 'ecco_leave_requests';
    $audit_table    = $wpdb->prefix . 'ecco_leave_audit';
    $balance_table  = $wpdb->prefix . 'ecco_leave_balances';

    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $requests_table WHERE id = %d",
        $id
    ));

    if (!$request) wp_die('Request not found');

    if (!ecco_current_user_can_approve_leave($request)) wp_die('Not allowed');

    /* ---------- Update status ---------- */

    $wpdb->update(
        $requests_table,
        ['status' => $status],
        ['id' => $id]
    );

    /* ---------- Audit ---------- */

    $wpdb->insert(
        $audit_table,
        [
            'leave_request_id' => $id,
            'action'           => $status,
            'actor_user_id'    => get_current_user_id(),
            'actor_email'      => wp_get_current_user()->user_email,
            'old_status'       => $request->status,
            'new_status'       => $status,
            'comment'          => $comment, // FIXED
            'created_at'       => current_time('mysql')
        ]
    );

    /* ---------- AUTO DEDUCT ON APPROVAL ---------- */

    if ($status === 'approved') {

        $days = ecco_calculate_leave_days(
    $request->start_date,
    $request->end_date
);

        $balance_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $balance_table WHERE user_id = %d AND leave_type = %s",
            $request->user_id,
            $request->leave_type
        ));

        if ($balance_row) {

            $new_balance = $balance_row->balance - $days;

            if ($new_balance < 0) {
                wp_die('Insufficient leave balance.');
            }

            $wpdb->update(
                $balance_table,
                [
                    'balance'      => $new_balance,
                    'last_updated' => current_time('mysql')
                ],
                ['id' => $balance_row->id]
            );
        }
    }

    /* ---------- Notify employee ---------- */

    $requester = get_userdata($request->user_id);

    if ($requester) {
        wp_mail(
            $requester->user_email,
            "Your leave request was {$status}",
            "Manager comment:\n{$comment}"
        );
    }

    wp_redirect(wp_get_referer());
    exit;
}

