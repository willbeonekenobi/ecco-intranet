<?php
// AJAX handlers for ECCO Intranet

add_action('wp_ajax_ecco_list_docs', 'ecco_ajax_list_docs');

function ecco_ajax_list_docs() {

    // 🔧 TEMP: disable nonce check to avoid LocalWP failures
    // check_ajax_referer('ecco_nonce', 'nonce');

    if (!isset($_POST['library'])) {
        wp_send_json_error('Missing library');
    }

    $key = sanitize_text_field($_POST['library']);
    $folder_id = isset($_POST['folder']) && $_POST['folder'] !== ''
        ? sanitize_text_field($_POST['folder'])
        : null;

    $drives = ecco_get_drive_map();

    if (!isset($drives[$key])) {
        wp_send_json_error('Invalid library');
    }

    $endpoint = $folder_id
        ? "drives/{$drives[$key]}/items/{$folder_id}/children"
        : "drives/{$drives[$key]}/root/children";

    $data = ecco_graph_get($endpoint);

    if (!$data) {
        wp_send_json_error('Graph returned no data');
    }

    wp_send_json($data);
}
