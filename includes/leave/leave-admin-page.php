<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'ecco_register_leave_admin_page');

function ecco_register_leave_admin_page() {
    add_menu_page(
        'Leave Requests',
        'Leave Requests',
        'manage_options',
        'ecco-leave-requests',
        'ecco_render_leave_admin_page',
        'dashicons-calendar-alt',
        25
    );
}

function ecco_render_leave_admin_page() {
    global $wpdb;

    $requests = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}ecco_leave_requests ORDER BY created_at DESC"
    );

    include plugin_dir_path(__DIR__) . '../templates/leave/leave-admin-list.php';
}
