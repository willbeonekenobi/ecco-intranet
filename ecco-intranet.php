<?php
/**
 * Plugin Name: ECCO Intranet
 * Description: Microsoft SSO powered intranet with SharePoint document libraries
 * Version: Alpha 1.0.0
 */

if (!defined('ABSPATH')) exit;

define('ECCO_PATH', plugin_dir_path(__FILE__));
define('ECCO_URL', plugin_dir_url(__FILE__));

require_once ECCO_PATH . 'includes/auth-microsoft.php';
require_once ECCO_PATH . 'includes/graph-client.php';
require_once ECCO_PATH . 'includes/graph-token-store.php';
require_once ECCO_PATH . 'includes/sharepoint.php';
require_once ECCO_PATH . 'includes/ajax.php';
require_once ECCO_PATH . 'includes/admin-settings.php';
require_once ECCO_PATH . 'includes/shortcodes.php';
require_once ECCO_PATH . 'includes/shortcodes-dashboard.php';
require_once ECCO_PATH . 'includes/leave-shortcode.php';
require_once ECCO_PATH . 'includes/leave/leave-loader.php';
require_once ECCO_PATH . 'includes/leave/leave-approval-shortcode.php';
require_once ECCO_PATH . 'includes/leave/leave-dashboard-shortcode.php';


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

/**
 * Prevent unauthenticated access to library pages
 */
add_action('template_redirect', function () {

    if (!is_page()) {
        return;
    }

    $post = get_post();
    if (!$post) {
        return;
    }

    // Protect pages that render SharePoint libraries
    if (has_shortcode($post->post_content, 'ecco_library')) {

        if (!ecco_is_authenticated()) {
            wp_redirect(site_url('/intranet'));
            exit;
        }
    }
});
/**
 * Plugin activation
 * Creates required database tables
 */
register_activation_hook(__FILE__, 'ecco_intranet_activate');

function ecco_intranet_activate() {

    if (function_exists('ecco_create_leave_table')) {
        ecco_create_leave_table();
    }

    if (function_exists('ecco_create_leave_balance_table')) {
        ecco_create_leave_balance_table();
    }
}

if (!function_exists('ecco_get_graph_user_profile')) {
    function ecco_get_graph_user_profile($user_id = null) {
        // Fetch the current signed-in user from Microsoft Graph
        $me = ecco_graph_get('/me');

        if (!$me) {
            return [];
        }

        return [
            'displayName' => $me['displayName'] ?? '',
            'mail'        => $me['mail'] ?? ($me['userPrincipalName'] ?? ''),
        ];
    }
}

if (!function_exists('ecco_get_graph_manager_profile')) {
    function ecco_get_graph_manager_profile() {
        $manager = ecco_graph_get('/me/manager');

        if (!$manager || isset($manager['error'])) {
            return null;
        }

        return [
            'id'          => $manager['id'] ?? null,
            'displayName' => $manager['displayName'] ?? null,
            'mail'        => $manager['mail'] ?? ($manager['userPrincipalName'] ?? null),
        ];
    }
}

if (!function_exists('ecco_resolve_effective_manager')) {
    function ecco_resolve_effective_manager() {
        $me = ecco_get_graph_user_profile();
        $manager = ecco_get_graph_manager_profile();

        // No manager in Entra
        if (!$manager || empty($manager['id'])) {
            return null;
        }

        // Self-managed edge case
        if (!empty($me['id']) && $me['id'] === $manager['id']) {
            return null;
        }

        return $manager;
    }
}

if (!function_exists('ecco_current_user_can_approve_leave')) {
    function ecco_current_user_can_approve_leave($request) {
        if (current_user_can('manage_options')) {
            return true; // WP admins always allowed
        }

        if (!function_exists('ecco_get_graph_user_profile')) {
            return false;
        }

        $me = ecco_get_graph_user_profile();
        $my_email = strtolower(trim($me['mail'] ?? ''));

        // If manager email matches current user email
        if (!empty($request->manager_email) && strtolower($request->manager_email) === $my_email) {
            return true;
        }

        // If no manager stored, allow self-approval ONLY if user is self-managed or no manager set in Entra
        if (empty($request->manager_email) && function_exists('ecco_get_graph_manager_profile')) {
            $manager = ecco_get_graph_manager_profile();

            if (!$manager || empty($manager['mail'])) {
                // No manager in Entra → self-managed allowed
                return true;
            }

            if (strtolower($manager['mail']) === $my_email) {
                // Self-managed manager scenario
                return true;
            }
        }

        return false;
    }
}

add_action('init', function () {
    if (is_user_logged_in() && function_exists('ecco_graph_get_token')) {
        $token = ecco_graph_get_token(get_current_user_id());

        if (!empty($token['access_token'])) {
            $_COOKIE['ecco_token'] = $token['access_token'];
        }
    }
});

add_action('init', function () {
    if (function_exists('ecco_create_leave_table')) {
        ecco_create_leave_table();
    }
});
