<?php
// AJAX handlers for ECCO Intranet

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper: get drive map with auto-refresh if needed
 */
function ecco_get_drive_map_safe($library) {

    $drives = ecco_get_drive_map();

    // If library not found, try refreshing cache once
    if (empty($drives[$library])) {
        $drives = ecco_get_drive_map(true);
    }

    return $drives;
}


/**
 * List files / folders (WITH DATE FIELDS)
 */
add_action('wp_ajax_ecco_list_docs', function () {

    if (empty($_POST['library'])) {
        wp_send_json_error('Missing library');
    }

    $library = sanitize_text_field($_POST['library']);
    $folder  = !empty($_POST['folder']) ? sanitize_text_field($_POST['folder']) : null;

    $drives = ecco_get_drive_map_safe($library);

    if (empty($drives[$library])) {
        wp_send_json_error('Invalid library');
    }

    // Explicitly request date fields from Graph
    $select = '?$select=id,name,webUrl,folder,file,createdDateTime,lastModifiedDateTime,fileSystemInfo';

    $endpoint = $folder
        ? "drives/{$drives[$library]}/items/{$folder}/children{$select}"
        : "drives/{$drives[$library]}/root/children{$select}";

    $data = ecco_graph_get($endpoint);

    // Return raw Graph response (your JS expects res.value)
    wp_send_json($data ?: ['value' => []]);
});


/**
 * Create upload session
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

    $drives = ecco_get_drive_map_safe($library);

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
 * Check if file exists + metadata
 */
add_action('wp_ajax_ecco_file_exists', function () {

    if (empty($_POST['library']) || empty($_POST['filename'])) {
        wp_send_json_error('Missing parameters');
    }

    $library  = sanitize_text_field($_POST['library']);
    $filename = trim(wp_unslash($_POST['filename']));
    $folder   = !empty($_POST['folder']) ? sanitize_text_field($_POST['folder']) : null;

    $drives = ecco_get_drive_map_safe($library);

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