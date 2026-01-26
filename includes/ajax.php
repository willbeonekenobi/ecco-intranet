<?php
// AJAX handlers for ECCO Intranet

if (!defined('ABSPATH')) {
    exit;
}

/**
 * List files / folders
 */
add_action('wp_ajax_ecco_list_docs', function () {

    if (empty($_POST['library'])) {
        wp_send_json_error('Missing library');
    }

    $library = sanitize_text_field($_POST['library']);
    $folder  = !empty($_POST['folder']) ? sanitize_text_field($_POST['folder']) : null;

    $drives = ecco_get_drive_map();
    if (empty($drives[$library])) {
        wp_send_json_error('Invalid library');
    }

    $endpoint = $folder
        ? "drives/{$drives[$library]}/items/{$folder}/children"
        : "drives/{$drives[$library]}/root/children";

    $data = ecco_graph_get($endpoint);
    wp_send_json($data ?: []);
});

/**
 * Create upload session (USED FOR ALL FILES)
 */
add_action('wp_ajax_ecco_upload_session', function () {

    if (empty($_POST['library']) || empty($_POST['filename']) || empty($_POST['filesize'])) {
        wp_send_json_error('Missing parameters');
    }

    $library  = sanitize_text_field($_POST['library']);
    $folder   = !empty($_POST['folder']) ? sanitize_text_field($_POST['folder']) : null;
    $filename = trim(wp_unslash($_POST['filename']));

    $conflict = $_POST['conflict'] ?? 'rename';
    if (!in_array($conflict, ['rename', 'replace', 'fail'], true)) {
        $conflict = 'rename';
    }

    $drives = ecco_get_drive_map();
    if (empty($drives[$library])) {
        wp_send_json_error('Invalid library');
    }

    $endpoint = $folder
        ? "drives/{$drives[$library]}/items/{$folder}:/$filename:/createUploadSession"
        : "drives/{$drives[$library]}/root:/$filename:/createUploadSession";

    $session = ecco_graph_post($endpoint, [
        'item' => [
            '@microsoft.graph.conflictBehavior' => $conflict,
            'name' => $filename,
        ],
    ]);

    if (empty($session['uploadUrl'])) {
        wp_send_json_error('Failed to create upload session');
    }

    wp_send_json_success([
        'uploadUrl' => $session['uploadUrl'],
    ]);
});

/**
 * Check if file exists + return metadata
 */
add_action('wp_ajax_ecco_file_exists', function () {

    if (empty($_POST['library']) || empty($_POST['filename'])) {
        wp_send_json_error('Missing parameters');
    }

    $library  = sanitize_text_field($_POST['library']);
    $filename = trim(wp_unslash($_POST['filename']));
    $folder   = !empty($_POST['folder']) ? sanitize_text_field($_POST['folder']) : null;

    $drives = ecco_get_drive_map();
    if (empty($drives[$library])) {
        wp_send_json_error('Invalid library');
    }

    $endpoint = $folder
        ? "drives/{$drives[$library]}/items/{$folder}:/$filename"
        : "drives/{$drives[$library]}/root:/$filename";

    $file = ecco_graph_get($endpoint);

    if (!empty($file['id'])) {
        wp_send_json_success([
            'exists'        => true,
            'size'          => $file['size'] ?? 0,
            'lastModified'  => $file['lastModifiedDateTime'] ?? null,
        ]);
    }

    wp_send_json_success([
        'exists' => false,
    ]);
});
