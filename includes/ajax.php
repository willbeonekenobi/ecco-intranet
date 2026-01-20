<?php
// AJAX handlers for ECCO Intranet

/**
 * List files/folders (root or folder)
 */
add_action('wp_ajax_ecco_list_docs', 'ecco_ajax_list_docs');

function ecco_ajax_list_docs() {

    if (!isset($_POST['library'])) {
        wp_send_json_error('Missing library');
    }

    $key = sanitize_text_field($_POST['library']);
    $folder_id = !empty($_POST['folder'])
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

/**
 * Upload file (root or folder)
 */
add_action('wp_ajax_ecco_upload', 'ecco_ajax_upload');

function ecco_ajax_upload() {

    if (!isset($_FILES['file'], $_POST['library'])) {
        wp_send_json_error('Missing data');
    }

    $key = sanitize_text_field($_POST['library']);
    $folder_id = !empty($_POST['folder'])
        ? sanitize_text_field($_POST['folder'])
        : null;

    $file = $_FILES['file'];
    $drives = ecco_get_drive_map();

    if (!isset($drives[$key])) {
        wp_send_json_error('Invalid library');
    }

    $filename = sanitize_file_name($file['name']);

    $endpoint = $folder_id
        ? "drives/{$drives[$key]}/items/{$folder_id}:/$filename:/content"
        : "drives/{$drives[$key]}/root:/$filename:/content";

    $response = ecco_graph_put(
        $endpoint,
        file_get_contents($file['tmp_name']),
        $file['type']
    );

    if (!$response) {
        wp_send_json_error('Upload failed');
    }

    wp_send_json_success();
}
