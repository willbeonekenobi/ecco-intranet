<?php
if (!defined('ABSPATH')) exit;

/**
 * Leave request shortcode
 */
add_shortcode('ecco_leave_request', function () {

    // Must be logged in via Microsoft
    if (!function_exists('ecco_is_authenticated') || !ecco_is_authenticated()) {
        return '<p>Please <a href="' . esc_url(ecco_login_url()) . '">sign in</a> to request leave.</p>';
    }

    // Enqueue assets
    wp_enqueue_style(
        'ecco-leave',
        ECCO_URL . 'assets/css/leave.css'
    );

    wp_enqueue_script(
        'ecco-leave',
        ECCO_URL . 'assets/js/leave.js',
        ['jquery'],
        null,
        true
    );

    wp_localize_script('ecco-leave', 'ECCO_LEAVE', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ecco_leave_nonce')
    ]);

    ob_start();
    include ECCO_PATH . 'templates/leave-request.php';
    return ob_get_clean();
});
