<?php
/**
 * Plugin Name: ECCO Intranet
 * Description: Microsoft SSO powered intranet with SharePoint document libraries
 * Version: Alpha 1.0.1
 */

if (!defined('ABSPATH')) exit;

/* =========================================================
   CONSTANTS
   ========================================================= */

define('ECCO_PATH', plugin_dir_path(__FILE__));
define('ECCO_URL', plugin_dir_url(__FILE__));

/* =========================================================
   START SESSION (Required for Delegated Graph Tokens)
   ========================================================= */

if (!session_id()) {
    session_start();
}

/* =========================================================
   LOAD CORE FILES (ORDER MATTERS)
   ========================================================= */


/* --- Authentication + Graph Layer --- */
require_once ECCO_PATH . 'includes/auth-microsoft.php';
require_once ECCO_PATH . 'includes/graph-client.php';
require_once ECCO_PATH . 'includes/graph-token-store.php';

/* --- SharePoint + Core AJAX --- */
require_once ECCO_PATH . 'includes/sharepoint.php';
require_once ECCO_PATH . 'includes/ajax.php';


/* --- Admin + Shortcodes --- */
require_once ECCO_PATH . 'includes/admin-settings.php';
require_once ECCO_PATH . 'includes/shortcodes.php';
require_once ECCO_PATH . 'includes/shortcodes-dashboard.php';
require_once ECCO_PATH . 'includes/leave-shortcode.php';
require_once ECCO_PATH . 'includes/logbooks-status-shortcode.php';
require_once ECCO_PATH . 'includes/logbooks-module.php';

/* --- Leave Module --- */
require_once ECCO_PATH . 'includes/leave/leave-loader.php';

/* --- Training Module --- */
require_once ECCO_PATH . 'includes/training/training-loader.php';

/* --- Courses Module (LMS + Certificates) --- */
require_once ECCO_PATH . 'includes/courses/courses-loader.php';
require_once ECCO_PATH . 'includes/leave/leave-approval-shortcode.php';
require_once ECCO_PATH . 'includes/leave/leave-dashboard-shortcode.php';
require_once ECCO_PATH . 'includes/leave/manager-resolver.php';

/* --- Calendar Module (LOAD AFTER GRAPH FILES) --- */
require_once ECCO_PATH . 'calendar/calendar-page.php';
require_once ECCO_PATH . 'calendar/calendar-ajax.php';


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
        filemtime(ECCO_PATH . 'assets/js/intranet.js'),
        true
    );

    wp_localize_script('ecco-intranet', 'ECCO', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ecco_nonce')
    ]);
}

/* --- Calendar Assets --- */

function ecco_calendar_assets() {

    wp_enqueue_script(
        'fullcalendar-js',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'ecco-calendar-js',
        ECCO_URL . 'calendar/calendar.js',
        ['jquery', 'fullcalendar-js'],
        filemtime(ECCO_PATH . 'calendar/calendar.js'),
        true
    );

    /* Build timezone list for the calendar modal dropdown */
    $tz_options = [
        'South Africa Standard Time'      => 'South Africa (UTC+2)',
        'Egypt Standard Time'             => 'Egypt (UTC+2)',
        'E. Africa Standard Time'         => 'East Africa (UTC+3)',
        'W. Central Africa Standard Time' => 'West Central Africa (UTC+1)',
        'UTC'                             => 'UTC (UTC+0)',
        'GMT Standard Time'               => 'London / GMT (UTC+0)',
        'W. Europe Standard Time'         => 'W. Europe (UTC+1)',
        'Romance Standard Time'           => 'Madrid / Brussels (UTC+1)',
        'E. Europe Standard Time'         => 'Eastern Europe (UTC+2)',
        'FLE Standard Time'               => 'Helsinki / Kyiv (UTC+2)',
        'GTB Standard Time'               => 'Athens / Bucharest (UTC+2)',
        'Eastern Standard Time'           => 'New York (UTC-5)',
        'Central Standard Time'           => 'Chicago (UTC-6)',
        'Mountain Standard Time'          => 'Denver (UTC-7)',
        'Pacific Standard Time'           => 'Los Angeles (UTC-8)',
        'SA Eastern Standard Time'        => 'Buenos Aires (UTC-3)',
        'India Standard Time'             => 'India (UTC+5:30)',
        'China Standard Time'             => 'China (UTC+8)',
        'Tokyo Standard Time'             => 'Tokyo (UTC+9)',
        'AUS Eastern Standard Time'       => 'Sydney (UTC+10)',
        'New Zealand Standard Time'       => 'Auckland (UTC+12)',
        'Arab Standard Time'              => 'Riyadh (UTC+3)',
        'Arabian Standard Time'           => 'Dubai / Abu Dhabi (UTC+4)',
        'Iran Standard Time'              => 'Tehran (UTC+3:30)',
    ];

    wp_localize_script(
        'ecco-calendar-js',
        'eccoCalendar',
        [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('ecco_calendar_nonce'),
            'defaultTz'      => get_option('ecco_calendar_timezone', 'South Africa Standard Time'),
            'timezones'      => $tz_options,
        ]
    );

    wp_enqueue_style(
        'ecco-calendar-css',
        ECCO_URL . 'calendar/calendar.css'
    );
}

add_action('wp_enqueue_scripts', function () {

    if (!is_page()) return;

    global $post;
    if (!$post) return;

    if (has_shortcode($post->post_content, 'ecco_group_calendar')) {
        ecco_calendar_assets();
    }

});


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

    if (function_exists('ecco_leave_maybe_upgrade_database')) {
        ecco_leave_maybe_upgrade_database();
    }

    if (function_exists('ecco_create_training_table')) {
        ecco_create_training_table();
    }

    if (function_exists('ecco_create_courses_tables')) {
        ecco_create_courses_tables();
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


/* =========================================================
   DEBUG: GRAPH GROUP TEST
   ========================================================= */

add_action('wp_ajax_ecco_debug_groups', function() {

    $response = ecco_graph_request('GET', 'groups');

    wp_send_json($response);
});

add_action('http_api_debug', function($response, $context, $class, $args, $url) {
    error_log('HTTP DEBUG URL: ' . $url);
    error_log(print_r($args, true));
}, 10, 5);


/* =========================================================
   SAFE TRAINING TABLE MIGRATION
   Creates the table on first load if it doesn't exist yet
   (covers sites that don't re-run the activation hook)
   ========================================================= */

add_action('init', function() {
    if (get_option('ecco_training_db_version') !== '1.0') {
        if (function_exists('ecco_create_training_table')) {
            ecco_create_training_table();
            update_option('ecco_training_db_version', '1.0');
        }
    }
});

