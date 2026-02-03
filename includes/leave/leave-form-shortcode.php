<?php
if (!defined('ABSPATH')) exit;

add_shortcode('ecco_leave_request', 'ecco_leave_request_shortcode');

function ecco_leave_request_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to submit a leave request.</p>';
    }

    ob_start();
    include plugin_dir_path(__DIR__) . '../templates/leave/leave-form.php';
    return ob_get_clean();
}
