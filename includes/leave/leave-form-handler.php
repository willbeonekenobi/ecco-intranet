<?php
if (!defined('ABSPATH')) exit;

// Ensure Graph client is loaded
if (!function_exists('ecco_graph_put')) {
    require_once WP_PLUGIN_DIR . '/ecco-intranet/includes/graph-client.php';
}

add_action('admin_post_ecco_submit_leave', 'ecco_handle_leave_submission');
add_action('admin_post_nopriv_ecco_submit_leave', 'ecco_handle_leave_submission');

add_action('admin_post_ecco_leave_approve', 'ecco_handle_leave_approve');
add_action('admin_post_ecco_leave_reject', 'ecco_handle_leave_reject');

/**
 * Submit a leave request
 */
function ecco_handle_leave_submission() {
    if (!is_user_logged_in()) wp_die('Not allowed');
    check_admin_referer('ecco_leave_nonce');

    global $wpdb;

    $leave_type  = sanitize_text_field($_POST['leave_type'] ?? '');
    $leave_types = get_option('ecco_leave_types', []);
    $requires_image = false;

    foreach ($leave_types as $lt) {
        if (($lt['label'] ?? '') === $leave_type) {
            $requires_image = !empty($lt['requires_image']);
            break;
        }
    }

    // Always resolve user profile for email + folder naming
    if (!function_exists('ecco_get_graph_user_profile')) {
        wp_die('Graph profile function missing.');
    }

    $me = ecco_get_graph_user_profile();
    if (empty($me['displayName'])) {
        wp_die('Unable to resolve Microsoft profile.');
    }

    $attachment_url = null;

    if ($requires_image) {

        if (empty($_FILES['leave_attachment'])) {
            wp_die('This leave type requires a supporting document.');
        }

        $file = $_FILES['leave_attachment'];

        if (!empty($file['error'])) {
            wp_die('Upload failed with PHP error code: ' . intval($file['error']));
        }

        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            wp_die('Upload failed. Temporary file missing.');
        }

        $contents = file_get_contents($file['tmp_name']);
        if ($contents === false) {
            wp_die('Unable to read uploaded file.');
        }

        $display = sanitize_title($me['displayName']);
        $month   = date('Y-m');
        $library = 'Leave-Documents';

        if (function_exists('ecco_graph_ensure_folder')) {
            ecco_graph_ensure_folder("{$library}/{$display}/{$month}");
        }

        $path = "{$library}/{$display}/{$month}/" . sanitize_file_name($file['name']);

        $drive_id = get_option('ecco_leave_drive_id');
        if (empty($drive_id)) {
            wp_die('Leave document library not configured.');
        }

        $upload = ecco_graph_put(
            "/drives/{$drive_id}/root:/{$path}:/content",
            $contents,
            $file['type'] ?: 'application/octet-stream'
        );

        if (!$upload || empty($upload['webUrl'])) {
            error_log('ECCO SharePoint upload failed: ' . print_r($upload, true));
            wp_die('Failed to upload file to SharePoint.');
        }

        $attachment_url = esc_url_raw($upload['webUrl']);
    }

    $manager = function_exists('ecco_resolve_effective_manager')
        ? ecco_resolve_effective_manager()
        : null;

    $result = $wpdb->insert(
        $wpdb->prefix . 'ecco_leave_requests',
        [
            'user_id'        => get_current_user_id(),
            'leave_type'     => $leave_type,
            'start_date'     => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date'       => sanitize_text_field($_POST['end_date'] ?? ''),
            'reason'         => sanitize_textarea_field($_POST['reason'] ?? ''),
            'manager_email'  => $manager['mail'] ?? null,
            'status'         => 'pending',
            'attachment_url' => $attachment_url,
        ],
        ['%d','%s','%s','%s','%s','%s','%s','%s']
    );

    if ($result === false) {
        error_log('ECCO DB ERROR: ' . $wpdb->last_error);
        wp_die('Leave request failed to save to database.');
    }

    // Notify manager
if (!empty($manager['mail'])) {

    $dashboard_url = site_url('/leave-dashboard/');
    $request_id = $wpdb->insert_id;

    $doc_line = '';
    if (!empty($attachment_url)) {
        $doc_line = '<p><strong>Supporting document:</strong> 
            <a href="' . esc_url($attachment_url) . '" target="_blank">View supporting document</a>
        </p>';
    }

    $body = '
        <p>A new leave request requires your approval.</p>

        <p>
            <strong>Employee:</strong> ' . esc_html($me['displayName']) . '<br>
            <strong>Leave type:</strong> ' . esc_html($leave_type) . '<br>
            <strong>Dates:</strong> ' . esc_html($_POST['start_date']) . ' → ' . esc_html($_POST['end_date']) . '
        </p>

        <p>
            <a href="' . esc_url($dashboard_url) . '" target="_blank">
                Review and action it here
            </a>
        </p>

        ' . $doc_line . '
    ';

    wp_mail(
        $manager['mail'],
        'Leave request awaiting your approval',
        $body,
        ['Content-Type: text/html; charset=UTF-8']
    );
}


    wp_redirect(add_query_arg('leave_submitted', '1', wp_get_referer()));
    exit;
}

/**
 * Approve
 */
function ecco_handle_leave_approve() {
    ecco_handle_leave_action('approved');
}

/**
 * Reject
 */
function ecco_handle_leave_reject() {
    ecco_handle_leave_action('rejected');
}

/**
 * Approve / Reject core handler
 */
function ecco_handle_leave_action($status) {
    if (!is_user_logged_in()) wp_die('Not allowed');

    $id = intval($_POST['request_id'] ?? 0);
    if (!$id) wp_die('Invalid request.');

    if (!isset($_POST['_wpnonce'])) {
        wp_die('Security check failed (nonce missing).');
    }

    check_admin_referer('ecco_leave_action_' . $id);

    $comment = sanitize_textarea_field($_POST['manager_comment'] ?? '');
    if (empty($comment)) {
        wp_die('Manager comment is required.');
    }

    global $wpdb;

    $request = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ecco_leave_requests WHERE id = %d", $id)
    );

    if (!$request) {
        wp_die('Leave request not found.');
    }

    if (!function_exists('ecco_current_user_can_approve_leave') || !ecco_current_user_can_approve_leave($request)) {
        wp_die('You are not allowed to action this request.');
    }

    $updated = $wpdb->update(
        $wpdb->prefix . 'ecco_leave_requests',
        ['status' => $status],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    if ($updated === false) {
        error_log('ECCO DB UPDATE ERROR: ' . $wpdb->last_error);
        wp_die('Failed to update leave request.');
    }

    // Notify requester
    $requester = get_userdata($request->user_id);
    if ($requester && !empty($requester->user_email)) {
        wp_mail(
            $requester->user_email,
            "Your leave request was {$status}",
            "Your leave request has been {$status}.\n\nManager comment:\n{$comment}"
        );
    }

    wp_redirect(wp_get_referer());
    exit;
}
