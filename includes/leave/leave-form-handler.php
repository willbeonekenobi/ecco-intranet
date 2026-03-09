<?php
if (!defined('ABSPATH')) exit;

/* Load Graph client if needed */
if (!function_exists('ecco_graph_put')) {
    require_once WP_PLUGIN_DIR . '/ecco-intranet/includes/graph-client.php';
}

/* =========================================================
   HOOKS
========================================================= */

add_action('admin_post_ecco_submit_leave', 'ecco_handle_leave_submission');
add_action('admin_post_nopriv_ecco_submit_leave', 'ecco_handle_leave_submission');

add_action('admin_post_ecco_leave_approve', 'ecco_handle_leave_approve');
add_action('admin_post_ecco_leave_reject',  'ecco_handle_leave_reject');


/* =========================================================
   SUBMIT LEAVE REQUEST
========================================================= */

function ecco_handle_leave_submission() {

    if (!is_user_logged_in()) wp_die('Not allowed');
    check_admin_referer('ecco_leave_nonce');

    global $wpdb;

    /* ---------- Form Data ---------- */

    $user_id           = get_current_user_id();
    $leave_type        = sanitize_text_field($_POST['leave_type'] ?? '');
    $reason            = sanitize_textarea_field($_POST['reason'] ?? '');
    $requester_comment = sanitize_textarea_field($_POST['reason'] ?? '');
    $start_date        = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date          = sanitize_text_field($_POST['end_date'] ?? '');

    if (!$leave_type || !$start_date || !$end_date) {
        wp_die('Missing required fields.');
    }

    /* =========================================================
       CHECK IF LEAVE TYPE REQUIRES DOCUMENT
    ========================================================= */

    $leave_types    = get_option('ecco_leave_types', []);
    $requires_image = false;

    foreach ($leave_types as $lt) {
        if (($lt['label'] ?? '') === $leave_type) {
            $requires_image = !empty($lt['requires_image']);
            break;
        }
    }

    $attachment_url = null;

    /* =========================================================
       VALIDATE REQUIRED FILE
    ========================================================= */

    if ($requires_image) {

        if (empty($_FILES['leave_attachment']) ||
            $_FILES['leave_attachment']['error'] === UPLOAD_ERR_NO_FILE) {

            wp_die(
                'A supporting document is required for this leave type.',
                'Missing Supporting Document',
                ['response' => 400]
            );
        }

        if ($_FILES['leave_attachment']['error'] !== UPLOAD_ERR_OK) {
            wp_die('File upload failed. Please try again.');
        }
    }

    /* =========================================================
       UPLOAD TO SHAREPOINT (IF FILE PROVIDED)
    ========================================================= */

    if (!empty($_FILES['leave_attachment']['tmp_name'])) {

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

        $upload = ecco_graph_put(
            "/drives/{$drive_id}/root:/{$path}:/content",
            $contents,
            $file['type']
        );

        if (!$upload || empty($upload['webUrl'])) {
            wp_die('Failed to upload supporting document.');
        }

        $attachment_url = esc_url_raw($upload['webUrl']);
    }

    /* =========================================================
       RESOLVE MANAGER
    ========================================================= */

    $manager = ecco_resolve_effective_manager();

    /* =========================================================
       INSERT REQUEST
    ========================================================= */

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

    /* =========================================================
       EMAIL MANAGER
    ========================================================= */

    if (!empty($manager['mail'])) {

        $dashboard_url = site_url('/leave-dashboard/?request_id=' . $request_id);

        $subject = 'Leave request awaiting approval';

        $message = '
            <p>A new leave request has been submitted.</p>

            <p>
                <strong>Type:</strong> ' . esc_html($leave_type) . '<br>
                <strong>Dates:</strong> ' . esc_html($start_date) . ' → ' . esc_html($end_date) . '
            </p>';

        if (!empty($attachment_url)) {
            $message .= '
                <p>
                    <a href="' . esc_url($attachment_url) . '">
                        <strong>Supporting Documents</strong>
                    </a>
                </p>';
        }

        $message .= '
            <p>
                <a href="' . esc_url($dashboard_url) . '">
                    <strong>Review Request</strong>
                </a>
            </p>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($manager['mail'], $subject, $message, $headers);
    }

    /* Redirect back to form */

    wp_redirect(add_query_arg('leave_submitted', '1', wp_get_referer()));
    exit;
}


/* =========================================================
   CALCULATE LEAVE DAYS
========================================================= */

function ecco_calculate_leave_days($start_date, $end_date) {

    global $wpdb;

    $table = $wpdb->prefix . 'ecco_public_holidays';

    $start = new DateTime($start_date);
    $end   = new DateTime($end_date);
    $end->modify('+1 day');

    $period = new DatePeriod($start, new DateInterval('P1D'), $end);

    $days = 0;

    foreach ($period as $date) {

        $dow = $date->format('N');

        if ($dow >= 6) continue;

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
   APPROVE / REJECT HANDLERS
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

    /* Update status */

    $wpdb->update($requests_table, ['status' => $status], ['id' => $id]);

    /* Audit */

    $wpdb->insert(
        $audit_table,
        [
            'leave_request_id' => $id,
            'action'           => $status,
            'actor_user_id'    => get_current_user_id(),
            'actor_email'      => wp_get_current_user()->user_email,
            'old_status'       => $request->status,
            'new_status'       => $status,
            'comment'          => $comment,
            'created_at'       => current_time('mysql')
        ]
    );

    /* Deduct balance on approval */

    if ($status === 'approved') {

        $days = ecco_calculate_leave_days($request->start_date, $request->end_date);

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

    /* =========================================================
       NOTIFY REQUESTER — always send, including self-approved requests
    ========================================================= */

    $requester = get_userdata( $request->user_id );

    if ( $requester && ! empty( $requester->user_email ) ) {

        $status_label  = ucfirst( $status );
        $status_colour = ( $status === 'approved' ) ? '#2e7d32' : '#c62828';
        $status_icon   = ( $status === 'approved' ) ? '✅' : '❌';

        $actor      = wp_get_current_user();
        $actor_name = ! empty( $actor->display_name ) ? $actor->display_name : 'Your manager';

        $days_requested = ecco_calculate_leave_days( $request->start_date, $request->end_date );

        $subject = "{$status_icon} Your leave request has been {$status_label}";

        $message = '<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.12);">

  <div style="background:' . esc_attr( $status_colour ) . ';padding:20px 28px;">
    <h2 style="color:#fff;margin:0;font-size:20px;">Leave Request ' . esc_html( $status_label ) . '</h2>
  </div>

  <div style="padding:24px 28px;">
    <p style="margin:0 0 16px;">Hi <strong>' . esc_html( $requester->display_name ) . '</strong>,</p>
    <p style="margin:0 0 20px;">
      Your leave request has been <strong>' . esc_html( $status ) . '</strong>
      by <strong>' . esc_html( $actor_name ) . '</strong>.
    </p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:20px;">
      <tr style="background:#f9f9f9;">
        <td style="padding:8px 12px;border:1px solid #e0e0e0;font-weight:bold;">Leave Type</td>
        <td style="padding:8px 12px;border:1px solid #e0e0e0;">' . esc_html( $request->leave_type ) . '</td>
      </tr>
      <tr>
        <td style="padding:8px 12px;border:1px solid #e0e0e0;font-weight:bold;">Dates</td>
        <td style="padding:8px 12px;border:1px solid #e0e0e0;">' . esc_html( $request->start_date . ' → ' . $request->end_date ) . '</td>
      </tr>
      <tr style="background:#f9f9f9;">
        <td style="padding:8px 12px;border:1px solid #e0e0e0;font-weight:bold;">Working Days</td>
        <td style="padding:8px 12px;border:1px solid #e0e0e0;">' . esc_html( $days_requested ) . '</td>
      </tr>
      <tr>
        <td style="padding:8px 12px;border:1px solid #e0e0e0;font-weight:bold;">Decision</td>
        <td style="padding:8px 12px;border:1px solid #e0e0e0;color:' . esc_attr( $status_colour ) . ';font-weight:bold;">' . esc_html( $status_label ) . '</td>
      </tr>
    </table>
' . ( $comment
    ? '<div style="background:#f9f9f9;border-left:4px solid ' . esc_attr( $status_colour ) . ';padding:12px 16px;margin-bottom:20px;">
      <strong>Comment from ' . esc_html( $actor_name ) . ':</strong><br>
      <span style="white-space:pre-line;">' . nl2br( esc_html( $comment ) ) . '</span>
    </div>'
    : '' ) . '
    <p style="margin:0;color:#888;font-size:13px;">This is an automated notification from the ECCO Intranet leave system.</p>
  </div>

</div>
</body>
</html>';

        wp_mail(
            $requester->user_email,
            $subject,
            $message,
            [ 'Content-Type: text/html; charset=UTF-8' ]
        );
    }

    wp_redirect( wp_get_referer() );
    exit;
}


/* =========================================================
   AJAX: LEAVE BALANCE PREVIEW
   Called by the inline JS in templates/leave/leave-form.php
   whenever the user changes leave type, start date, or end date.
========================================================= */

add_action( 'wp_ajax_ecco_get_leave_preview', 'ecco_ajax_get_leave_preview' );

function ecco_ajax_get_leave_preview() {

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Not logged in' );
    }

    $leave_type = sanitize_text_field( $_POST['leave_type'] ?? '' );
    $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
    $end_date   = sanitize_text_field( $_POST['end_date']   ?? '' );

    if ( ! $leave_type || ! $start_date || ! $end_date ) {
        wp_send_json_error( 'Missing fields' );
    }

    global $wpdb;

    $user_id = get_current_user_id();

    /* Current balance for this leave type */
    $balance_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT balance FROM {$wpdb->prefix}ecco_leave_balances
         WHERE user_id = %d AND leave_type = %s",
        $user_id,
        $leave_type
    ) );

    $balance = $balance_row ? (float) $balance_row->balance : 0.0;

    /* Working days requested (excludes weekends + public holidays) */
    $days = function_exists( 'ecco_calculate_leave_days' )
        ? (int) ecco_calculate_leave_days( $start_date, $end_date )
        : 0;

    $remaining = $balance - $days;

    wp_send_json_success( [
        'balance'   => $balance,
        'days'      => $days,
        'remaining' => $remaining,
    ] );
}
