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

    $current_user_id = get_current_user_id();
$requests_table = $wpdb->prefix . 'ecco_leave_requests';

// Admins see everything
if (current_user_can('manage_options')) {
    $requests = $wpdb->get_results("SELECT * FROM {$requests_table} ORDER BY created_at DESC");
} else {
    // Non-admins: filter to own requests OR requests they manage
    $me = function_exists('ecco_get_graph_user_profile') ? ecco_get_graph_user_profile() : [];
    $my_email = strtolower(trim($me['mail'] ?? ''));

    $requests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$requests_table}
             WHERE user_id = %d
             OR (manager_email IS NOT NULL AND LOWER(manager_email) = %s)
             ORDER BY created_at DESC",
            $current_user_id,
            $my_email
        )
    );
}

    include plugin_dir_path(__DIR__) . '../templates/leave/leave-admin-list.php';
}
