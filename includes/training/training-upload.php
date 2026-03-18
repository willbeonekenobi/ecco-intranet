<?php
if (!defined('ABSPATH')) exit;

add_action('admin_post_ecco_training_upload', 'ecco_training_upload');

function ecco_training_upload(){

    if (!is_user_logged_in()) wp_die();

    check_admin_referer('ecco_training_upload');

    require_once ECCO_PLUGIN_DIR.'includes/graph-upload.php';

    $file = $_FILES['certificate'];

    $upload = ecco_graph_upload_file(
        $file['tmp_name'],
        $file['name'],
        'TrainingCertificates'
    );

    if (empty($upload['url'])) wp_die('Upload failed');

    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix.'ecco_training',
        [
            'user_id'=>get_current_user_id(),
            'course'=>sanitize_text_field($_POST['course']),
            'completed'=>sanitize_text_field($_POST['completed']),
            'certificate_url'=>$upload['url']
        ]
    );

    wp_redirect(wp_get_referer());
    exit;
}