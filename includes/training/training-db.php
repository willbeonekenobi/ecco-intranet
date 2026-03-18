<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   CREATE / UPGRADE TRAINING CERTIFICATIONS TABLE
   ========================================================= */

function ecco_create_training_table() {

    global $wpdb;

    $table   = $wpdb->prefix . 'ecco_training_certifications';
    $charset = $wpdb->get_charset_collate();

    /* dbDelta adds missing columns automatically but never drops them,
       so this is safe to run on both fresh installs and upgrades. */
    $sql = "CREATE TABLE $table (
        id                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id                      BIGINT UNSIGNED NOT NULL,
        employee_name                VARCHAR(255)    NOT NULL DEFAULT '',
        employee_email               VARCHAR(255)    NOT NULL DEFAULT '',
        course_name                  VARCHAR(255)    NOT NULL,
        date_completed               DATE            DEFAULT NULL,
        date_expiry                  DATE            DEFAULT NULL,
        certificate_url              VARCHAR(2083)   DEFAULT NULL,
        certificate_sharepoint_path  VARCHAR(2083)   DEFAULT NULL,
        reminder_sent_at             DATETIME        DEFAULT NULL,
        created_by                   BIGINT UNSIGNED DEFAULT NULL,
        created_at                   DATETIME        DEFAULT CURRENT_TIMESTAMP,
        updated_at                   DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    /* ---- Safe migration: add certificate_sharepoint_path if missing ----
       Covers sites upgraded from the initial build which had
       certificate_attachment_id instead of certificate_sharepoint_path.    */
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'certificate_sharepoint_path'")) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN certificate_sharepoint_path VARCHAR(2083) DEFAULT NULL AFTER certificate_url");
    }
}


/* =========================================================
   HELPER: IS CURRENT USER HR (or admin)?
   ========================================================= */

function ecco_current_user_is_hr() {
    if (!is_user_logged_in()) return false;
    if (current_user_can('manage_options')) return true;
    return get_user_meta(get_current_user_id(), 'ecco_is_hr', true) === '1';
}
