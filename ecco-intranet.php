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





/* =========================================================
   ENQUEUE ASSETS
   ========================================================= */

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





/* =========================================================
   INTRANET SHORTCODE
   ========================================================= */

add_shortcode('ecco_intranet', function () {

    if (!ecco_is_authenticated()) {
        return '<p><a href="' . esc_url(ecco_login_url()) . '">Sign in with Microsoft</a></p>';
    }

    ecco_enqueue_assets();

    ob_start();
    include ECCO_PATH . 'templates/intranet.php';
    return ob_get_clean();
});





/* =========================================================
   PROTECT LIBRARY PAGES
   ========================================================= */

add_action('template_redirect', function () {

    if (!is_page()) return;

    $post = get_post();
    if (!$post) return;

    if (has_shortcode($post->post_content, 'ecco_library')) {

        if (!ecco_is_authenticated()) {
            wp_redirect(site_url('/intranet'));
            exit;
        }
    }
});





/* =========================================================
   PLUGIN ACTIVATION
   ========================================================= */

register_activation_hook(__FILE__, 'ecco_intranet_activate');

function ecco_intranet_activate() {

    if (function_exists('ecco_create_leave_table')) {
        ecco_create_leave_table();
    }

    if (function_exists('ecco_create_leave_balance_table')) {
        ecco_create_leave_balance_table();
    }

    if (function_exists('ecco_create_public_holidays_table')) {
        ecco_create_public_holidays_table();
    }

    // Safe schema upgrade
    if (function_exists('ecco_leave_maybe_upgrade_database')) {
        ecco_leave_maybe_upgrade_database();
    }
}





/* =========================================================
   SAFE DATABASE MIGRATION
   ========================================================= */

function ecco_leave_maybe_upgrade_database() {

    global $wpdb;

    $table = $wpdb->prefix . 'ecco_leave_requests';

    $exists = $wpdb->get_var(
        $wpdb->prepare("SHOW TABLES LIKE %s", $table)
    );

    if ($exists !== $table) return;

    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'requester_comment'")) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN requester_comment TEXT NULL AFTER reason");
    }

    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'created_at'")) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
    }

    update_option('ecco_leave_db_version', '1.1');
}





/* =========================================================
   MICROSOFT GRAPH HELPERS
   ========================================================= */

if (!function_exists('ecco_get_graph_user_profile')) {
    function ecco_get_graph_user_profile() {

        $me = ecco_graph_get('/me');
        if (!$me) return [];

        return [
            'displayName' => $me['displayName'] ?? '',
            'mail'        => $me['mail'] ?? ($me['userPrincipalName'] ?? ''),
        ];
    }
}

if (!function_exists('ecco_get_graph_manager_profile')) {
    function ecco_get_graph_manager_profile() {

        $manager = ecco_graph_get('/me/manager');

        if (!$manager || isset($manager['error'])) return null;

        return [
            'id'          => $manager['id'] ?? null,
            'displayName' => $manager['displayName'] ?? null,
            'mail'        => $manager['mail'] ?? ($manager['userPrincipalName'] ?? null),
        ];
    }
}

if (!function_exists('ecco_current_user_can_approve_leave')) {
    function ecco_current_user_can_approve_leave($request) {

        if (current_user_can('manage_options')) return true;

        $me = ecco_get_graph_user_profile();
        $my_email = strtolower(trim($me['mail'] ?? ''));

        if (!empty($request->manager_email) &&
            strtolower($request->manager_email) === $my_email) {
            return true;
        }

        return false;
    }
}


/* =========================================================
   EFFECTIVE MANAGER RESOLUTION (RESTORED)
   ========================================================= */

if (!function_exists('ecco_resolve_effective_manager')) {

    function ecco_resolve_effective_manager() {

        // Try Microsoft Graph manager first
        if (function_exists('ecco_get_graph_manager_profile')) {

            $manager = ecco_get_graph_manager_profile();

            if (!empty($manager['mail'])) {
                return $manager;
            }
        }

        // Fallback: no manager found
        return [
            'id'          => null,
            'displayName' => null,
            'mail'        => null
        ];
    }
}


/* =========================================================
   TOKEN COOKIE SETUP
   ========================================================= */

add_action('init', function () {

    if (is_user_logged_in() && function_exists('ecco_graph_get_token')) {

        $token = ecco_graph_get_token(get_current_user_id());

        if (!empty($token['access_token'])) {
            $_COOKIE['ecco_token'] = $token['access_token'];
        }
    }
});





/* =========================================================
   ENSURE TABLE EXISTS (SAFE FALLBACK)
   ========================================================= */

add_action('init', function () {

    if (function_exists('ecco_create_leave_table')) {
        ecco_create_leave_table();
    }
});





/* =========================================================
   AJAX — GET LEAVE PREVIEW (CORRECT VERSION)
   ========================================================= */

add_action('wp_ajax_ecco_get_leave_preview', 'ecco_get_leave_preview');

function ecco_get_leave_preview() {

    if (!is_user_logged_in()) wp_send_json_error();

    global $wpdb;

    $user_id   = get_current_user_id();
    $leaveType = sanitize_text_field($_POST['leave_type'] ?? '');
    $start     = sanitize_text_field($_POST['start_date'] ?? '');
    $end       = sanitize_text_field($_POST['end_date'] ?? '');

    if (!$leaveType || !$start || !$end) wp_send_json_error();

    $balance_table = $wpdb->prefix . 'ecco_leave_balances';

    $balance = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT balance FROM $balance_table
         WHERE user_id = %d AND leave_type = %s",
        $user_id,
        $leaveType
    ));

    // IMPORTANT: uses the SAME function as submission logic
    $days = ecco_calculate_leave_days($start, $end);

    wp_send_json_success([
        'balance'   => $balance,
        'days'      => $days,
        'remaining' => $balance - $days
    ]);
}
