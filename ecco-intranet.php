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

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('ecco-intranet', ECCO_URL . 'assets/css/intranet.css');
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
});

add_shortcode('ecco_intranet', function () {
    if (!ecco_is_authenticated()) {
        wp_redirect(ecco_login_url());
        exit;
    }

    ob_start();
    include ECCO_PATH . 'templates/intranet.php';
    return ob_get_clean();
});


