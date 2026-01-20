<?php

/**
 * GET request to Microsoft Graph
 */
function ecco_graph_get($endpoint) {
    if (!isset($_COOKIE['ecco_token'])) {
        return null;
    }

    $response = wp_remote_get(
        'https://graph.microsoft.com/v1.0/' . ltrim($endpoint, '/'),
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $_COOKIE['ecco_token']
            ],
            'timeout' => 60
        ]
    );

    if (is_wp_error($response)) {
        error_log('ECCO Graph GET error: ' . $response->get_error_message());
        return null;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

/**
 * PUT request to Microsoft Graph (used for uploads)
 */
function ecco_graph_put($endpoint, $body, $content_type = 'application/octet-stream') {
    if (!isset($_COOKIE['ecco_token'])) {
        return null;
    }

    $response = wp_remote_request(
        'https://graph.microsoft.com/v1.0/' . ltrim($endpoint, '/'),
        [
            'method'  => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $_COOKIE['ecco_token'],
                'Content-Type'  => $content_type
            ],
            'body'    => $body,
            'timeout' => 120
        ]
    );

    if (is_wp_error($response)) {
        error_log('ECCO Graph PUT error: ' . $response->get_error_message());
        return null;
    }

    $code = wp_remote_retrieve_response_code($response);

    if ($code !== 200 && $code !== 201) {
        error_log(
            'ECCO upload failed (' . $code . '): ' .
            wp_remote_retrieve_body($response)
        );
        return null;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}
