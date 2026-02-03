<?php
if (!defined('ABSPATH')) exit;

add_action('admin_post_ecco_submit_leave', 'ecco_handle_leave_submission');
add_action('admin_post_nopriv_ecco_submit_leave', 'ecco_handle_leave_submission');

add_action('admin_post_ecco_leave_approve', 'ecco_handle_leave_approve');
add_action('admin_post_ecco_leave_reject', 'ecco_handle_leave_reject');

function ecco_handle_leave_submission() {
    if (!is_user_logged_in()) {
        wp_die('Not allowed');
    }

    check_admin_referer('ecco_leave_nonce');

    global $wpdb;

    $manager = function_exists('ecco_resolve_effective_manager')
        ? ecco_resolve_effective_manager()
        : null;

    $wpdb->insert(
        $wpdb->prefix . 'ecco_leave_requests',
        [
            'user_id'       => get_current_user_id(),
            'leave_type'    => sanitize_text_field($_POST['leave_type']),
            'start_date'    => sanitize_text_field($_POST['start_date']),
            'end_date'      => sanitize_text_field($_POST['end_date']),
            'reason'        => sanitize_textarea_field($_POST['reason']),
            'manager_email' => $manager['mail'] ?? null,
        ]
    );

    if (!empty($manager['mail'])) {
        wp_mail(
            $manager['mail'],
            'New Leave Request',
            'A new leave request has been submitted and requires your approval.'
        );
    }

    wp_redirect(add_query_arg('leave_submitted', '1', wp_get_referer()));
    exit;
}

function ecco_handle_leave_approve() {
    ecco_handle_leave_action('approved');
}

function ecco_handle_leave_reject() {
    ecco_handle_leave_action('rejected');
}

function ecco_handle_leave_action($status) {
    if (!is_user_logged_in()) {
        wp_die('Not allowed');
    }

    $id = intval($_POST['request_id'] ?? 0);
    if (!$id) {
        wp_die('Invalid request');
    }

    check_admin_referer('ecco_leave_action_' . $id);

    global $wpdb;

    $request = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ecco_leave_requests WHERE id = %d", $id)
    );

    if (!$request) {
        wp_die('Request not found');
    }

    if (!function_exists('ecco_current_user_can_approve_leave') || !ecco_current_user_can_approve_leave($request)) {
        wp_die('You are not allowed to perform this action.');
    }

    $wpdb->update(
        $wpdb->prefix . 'ecco_leave_requests',
        ['status' => $status],
        ['id' => $id]
    );

    wp_redirect(wp_get_referer());
    exit;
}
