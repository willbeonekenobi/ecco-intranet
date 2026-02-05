<?php
if (!defined('ABSPATH')) exit;

add_shortcode('ecco_leave_approval', 'ecco_leave_approval_shortcode');

function ecco_leave_approval_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to approve this leave request.</p>';
    }

    $request_id = intval($_GET['request_id'] ?? 0);
    if (!$request_id) {
        return '<p>Invalid request.</p>';
    }

    global $wpdb;
    $request = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ecco_leave_requests WHERE id = %d", $request_id)
    );

    if (!$request) {
        return '<p>Request not found.</p>';
    }

    if (!function_exists('ecco_current_user_can_approve_leave') || !ecco_current_user_can_approve_leave($request)) {
        return '<p>You do not have permission to approve this request.</p>';
    }

    ob_start();
    include plugin_dir_path(__DIR__) . '../templates/leave/leave-approval-page.php';
    return ob_get_clean();
}
