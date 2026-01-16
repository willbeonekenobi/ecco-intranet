<?php

add_action('wp_ajax_ecco_list_docs', function () {
    check_ajax_referer('ecco_nonce', 'nonce');

    $key = sanitize_text_field($_POST['library']);
    $drives = ecco_get_drive_map();

    if (!isset($drives[$key])) {
        wp_send_json_error('Library not found');
    }

    $data = ecco_graph_get(
        "drives/{$drives[$key]}/root/children"
    );

    wp_send_json($data);
});

add_action('wp_ajax_ecco_upload', function () {
    check_ajax_referer('ecco_nonce', 'nonce');

    $key  = sanitize_text_field($_POST['library']);
    $file = $_FILES['file'];
    $drives = ecco_get_drive_map();

    if (!isset($drives[$key])) {
        wp_send_json_error('Library not found');
    }

    $upload = wp_remote_request(
        "https://graph.microsoft.com/v1.0/drives/{$drives[$key]}/root:/{$file['name']}:/content",
        [
            'method'  => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $_COOKIE['ecco_token'],
                'Content-Type'  => $file['type']
            ],
            'body' => file_get_contents($file['tmp_name'])
        ]
    );

    wp_send_json_success();
});
