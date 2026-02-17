<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/leave-db.php';
require_once __DIR__ . '/leave-form-shortcode.php';
require_once __DIR__ . '/leave-form-handler.php';
require_once __DIR__ . '/leave-admin-page.php';
//require_once __DIR__ . '/leave-balance-admin.php';
//require_once __DIR__ . '/leave-balance-admin.php';
require_once __DIR__ . '/leave-balance-manager.php';


/* =========================================================
   PUBLIC HOLIDAYS TABLE
========================================================= */

function ecco_create_public_holidays_table() {

    global $wpdb;

    $table = $wpdb->prefix . 'ecco_public_holidays';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        holiday_date DATE NOT NULL,
        name VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY holiday_date (holiday_date)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}