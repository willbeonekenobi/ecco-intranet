<?php
// AJAX handlers for ECCO Intranet

/**
 * List files/folders
 */
add_action('wp_ajax_ecco_list_docs', function () {

    if (!isset($_POST['library'])) {
        wp_send_json_error('Missing library');
    }

    $key = sanitize_text_field($_POST['library']);
    $folder = !empty($_POST['folder']) ? sanitize_text_field($_POST['folder']) : null;

    $drives = ecco_get_drive_map();
    if (!isset($drives[$key])) {
        wp_send_json_error('Invalid library');
    }

    $endpoint = $folder
        ? "drives/{$drives[$key]}/items/{$folder}/children"
        : "drives/{$drives[$key]}/root/children";

    wp_send_json(ecco_graph_get($endpoint) ?: []);
});

/**
 * Small file upload
 */
add_action('wp_ajax_ecco_upload', function () {

    if (!isset($_FILES['file'], $_POST['library'])) {
        wp_send_json_error('Missing data');
    }

    $key    = sanitize_text_field($_POST['library']);
    $folder = !empty($_POST['folder']) ? sanitize_text_field($_POST['folder']) : null;
    $file   = $_FILES['file'];

    $conflict = $_POST['conflict'] ?? 'rename';
    if (!in_array($conflict, ['rename', 'replace', 'fail'], true)) {
        $conflict = 'rename';
    }

    $drives = ecco_get_drive_map();
    if (!isset($drives[$key])) {
        wp_send_json_error('Invalid library');
    }

    $filename = trim(wp_unslash($file['name']));

    $endpoint = $folder
        ? "drives/{$drives[$key]}/items/{$folder}:/$filename:/content?@name.conflictBehavior={$conflict}"
        : "drives/{$drives[$key]}/root:/$filename:/content?@name.conflictBehavior={$conflict}";

    $res = ecco_graph_put(
        $endpoint,
        file_get_contents($file['tmp_name']),
        $file['type']
    );

    if (!$res) {
        wp_send_json_error('Upload failed');
    }

    wp_send_json_success();
});

/**
 * Large file upload session
 */
add_action('wp_ajax_ecco_upload_session', function () {

    if (!isset($_POST['library'], $_POST['filename'], $_POST['filesize'])) {
        wp_send_json_error('Missing parameters');
    }

    $key      = sanitize_text_field($_POST['library']);
    $folder   = !empty($_POST['folder']) ? sanitize_text_field($_POST['folder']) : null;
    $filename = trim(wp_unslash($_POST['filename']));

    $conflict = $_POST['conflict'] ?? 'rename';
    if (!in_array($conflict, ['rename', 'replace', 'fail'], true)) {
        $conflict = 'rename';
    }

    $drives = ecco_get_drive_map();
    if (!isset($drives[$key])) {
        wp_send_json_error('Invalid library');
    }

    $endpoint = $folder
        ? "drives/{$drives[$key]}/items/{$folder}:/$filename:/createUploadSession"
        : "drives/{$drives[$key]}/root:/$filename:/createUploadSession";

    $session = ecco_graph_post($endpoint, [
        'item' => [
            '@microsoft.graph.conflictBehavior' => $conflict,
            'name' => $filename
        ]
    ]);

    if (empty($session['uploadUrl'])) {
        wp_send_json_error('Failed to create upload session');
    }

    wp_send_json_success(['uploadUrl' => $session['uploadUrl']]);
});

/**
 * File existence + metadata
 */
add_action('wp_ajax_ecco_file_exists', function () {

    if (!isset($_POST['library'], $_POST['filename'])) {
        wp_send_json_error('Missing parameters');
    }

    $key      = sanitize_text_field($_POST['library']);
    $filename = trim(wp_unslash($_POST['filename']));
    $folder   = !empty($_POST['folder']) ? sanitize_text_field($_POST['folder']) : null;

    $drives = ecco_get_drive_map();
    if (!isset($drives[$key])) {
        wp_send_json_error('Invalid library');
    }

    $endpoint = $folder
        ? "drives/{$drives[$key]}/items/{$folder}:/$filename"
        : "drives/{$drives[$key]}/root:/$filename";

    $file = ecco_graph_get($endpoint);

    if (!empty($file['id'])) {
        wp_send_json_success([
            'exists' => true,
            'size' => $file['size'] ?? 0,
            'lastModified' => $file['lastModifiedDateTime'] ?? null
        ]);
    }

    wp_send_json_success(['exists' => false]);
});


