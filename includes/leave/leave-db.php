<?php
if (!defined('ABSPATH')) exit;

function ecco_create_leave_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'ecco_leave_requests';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        leave_type VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        reason TEXT,
        manager_email VARCHAR(255) DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

if (function_exists('ecco_create_leave_audit_table')) {
    ecco_create_leave_audit_table();
}

function ecco_create_leave_audit_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'ecco_leave_audit';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    leave_request_id BIGINT(20) UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    actor_user_id BIGINT(20) UNSIGNED NOT NULL,
    actor_email VARCHAR(255) DEFAULT NULL,
    old_status VARCHAR(50) DEFAULT NULL,
    new_status VARCHAR(50) NOT NULL,
    comment TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY leave_request_id (leave_request_id)
) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
