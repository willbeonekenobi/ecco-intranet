<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/courses-db.php';
require_once __DIR__ . '/courses-pdf.php';
require_once __DIR__ . '/courses-ajax.php';
require_once __DIR__ . '/courses-admin.php';
require_once __DIR__ . '/courses-shortcode.php';

/* =========================================================
   INSTALL / UPGRADE TABLES ON FIRST USE
   ========================================================= */

add_action('init', function () {
    if (get_option('ecco_courses_db_version') !== '1.0') {
        ecco_create_courses_tables();
        update_option('ecco_courses_db_version', '1.0');
    }
});
