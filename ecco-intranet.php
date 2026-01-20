<?php
/**
 * Plugin Name: ECCO Intranet
 * Description: Microsoft SSO powered intranet with SharePoint document libraries
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

define('ECCO_PATH', plugin_dir_path(__FILE__));
define('ECCO_URL', plugin_dir_url(__FILE__));

require_once ECCO_PATH . 'includes/auth-microsoft.php';
require_once ECCO_PATH . 'includes/graph-client.php';
require_once ECCO_PATH . 'includes/sharepoint.php';
require_once ECCO_PATH . 'includes/ajax.php';
require_once ECCO_PATH . 'includes/admin-settings.php';

/**
 * Enqueue assets ONLY when intranet is rendered
 */
function ecco_enqueue_assets() {
    wp_enqueue_style(
        'ecco-intranet',
        ECCO_URL . 'assets/css/intranet.css'
    );

    wp_enqueue_script(
        'ecco-intranet',
        ECCO_URL . 'assets/js/intranet.js',
        ['jquery'],
        null,
        true
    );

    wp_localize_script('ecco-intranet', 'ECCO', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ecco_nonce')
    ]);
}

/**
 * Intranet shortcode
 */
add_shortcode('ecco_intranet', function () {

    // If not authenticated, show login link (NO redirect here)
    if (!ecco_is_authenticated()) {
        return '<p><a href="' . esc_url(ecco_login_url()) . '">Sign in with Microsoft</a></p>';
    }

    ecco_enqueue_assets();

    ob_start();
    include ECCO_PATH . 'templates/intranet.php';
    return ob_get_clean();
});
require_once ECCO_PATH . 'includes/graph-client.php';
require_once ECCO_PATH . 'includes/ajax.php';