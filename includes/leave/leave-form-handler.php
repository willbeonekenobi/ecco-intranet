<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('ecco_graph_put')) {
    require_once WP_PLUGIN_DIR . '/ecco-intranet/includes/graph-client.php';
}

add_action('admin_post_ecco_submit_leave', 'ecco_handle_leave_submission');
add_action('admin_post_nopriv_ecco_submit_leave', 'ecco_handle_leave_submission');

add_action('admin_post_ecco_leave_approve', 'ecco_handle_leave_approve');
add_action('admin_post_ecco_leave_reject', 'ecco_handle_leave_reject');

function ecco_handle_leave_submission() {
    if (!is_user_logged_in()) wp_die('Not allowed');
    check_admin_referer('ecco_leave_nonce');

    global $wpdb;

    $leave_type  = sanitize_text_field($_POST['leave_type'] ?? '');
    $reason      = sanitize_textarea_field($_POST['reason'] ?? '');
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
        if (!$upload || empty($upload['webUrl'])) wp_die('Failed to upload supporting document.');

        $attachment_url = esc_url_raw($upload['webUrl']);
    }

    $manager = ecco_resolve_effective_manager();

    $wpdb->insert(
        $wpdb->prefix . 'ecco_leave_requests',
        [
            'user_id'           => get_current_user_id(),
            'leave_type'        => $leave_type,
            'start_date'        => sanitize_text_field($_POST['start_date']),
            'end_date'          => sanitize_text_field($_POST['end_date']),
            'reason'            => $reason,
            'requester_comment' => $reason,
            'manager_email'     => $manager['mail'] ?? null,
            'status'            => 'pending',
            'attachment_url'    => $attachment_url,
        ]
    );

    $request_id = $wpdb->insert_id;

    if (!empty($manager['mail'])) {
        $dashboard_url = site_url('/leave-dashboard/?request_id=' . $request_id);
        $doc_link = $attachment_url ? "<a href='{$attachment_url}'>Supporting document</a>" : '';

        wp_mail(
            $manager['mail'],
            'Leave request awaiting approval',
            "New leave request submitted.\n\nEmployee: {$me['displayName']}\nType: {$leave_type}\nDates: {$_POST['start_date']} → {$_POST['end_date']}\n\nReview and action it here:\n{$dashboard_url}\n{$doc_link}"
        );
    }

    wp_redirect(add_query_arg('leave_submitted', '1', wp_get_referer()));
    exit;
}

function ecco_handle_leave_approve() { ecco_handle_leave_action('approved'); }
function ecco_handle_leave_reject() { ecco_handle_leave_action('rejected'); }

function ecco_handle_leave_action($status) {
    if (!is_user_logged_in()) wp_die('Not allowed');

    $id = intval($_POST['request_id'] ?? 0);
    if (!$id) wp_die('Invalid request');

    check_admin_referer('ecco_leave_action_' . $id);

    $comment = sanitize_textarea_field($_POST['manager_comment'] ?? '');
    if (!$comment) wp_die('Manager comment required');

    global $wpdb;

    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ecco_leave_requests WHERE id = %d", $id
    ));

    if (!$request) wp_die('Request not found');

    if (!ecco_current_user_can_approve_leave($request)) wp_die('Not allowed');

    $wpdb->update($wpdb->prefix . 'ecco_leave_requests', ['status' => $status], ['id' => $id]);

    $wpdb->insert(
        $wpdb->prefix . 'ecco_leave_audit',
        [
            'leave_request_id' => $id,
            'action'           => $status,
            'actor_user_id'    => get_current_user_id(),
            'actor_email'      => wp_get_current_user()->user_email,
            'old_status'       => $request->status,
            'new_status'       => $status,
            'comment'          => $comment,
        ]
    );

    $requester = get_userdata($request->user_id);
    wp_mail(
        $requester->user_email,
        "Your leave request was {$status}",
        "Manager comment:\n{$comment}"
    );

    wp_redirect(wp_get_referer());
    exit;
}
