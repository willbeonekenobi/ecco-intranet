<?php

function ecco_graph_get_access_token() {
    if (!is_user_logged_in()) return null;

    if (!function_exists('ecco_graph_get_token')) {
        require_once __DIR__ . '/graph-token-store.php';
    }

    $token = ecco_graph_get_token(get_current_user_id());

    return $token['access_token'] ?? null;
}

function ecco_graph_headers() {
    $token = ecco_graph_get_access_token();
    if (!$token) return null;

    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept'        => 'application/json'
    ];
}

/**
 * GET request
 */
function ecco_graph_get($endpoint) {
    $headers = ecco_graph_headers();
    if (!$headers) return null;

    $response = wp_remote_get(
        'https://graph.microsoft.com/v1.0/' . ltrim($endpoint, '/'),
        [
            'headers' => $headers,
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
 * POST request (upload session)
 */
function ecco_graph_post($endpoint, $body = []) {
    $headers = ecco_graph_headers();
    if (!$headers) return null;

    $headers['Content-Type'] = 'application/json';

    $response = wp_remote_post(
        'https://graph.microsoft.com/v1.0/' . ltrim($endpoint, '/'),
        [
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 60
        ]
    );

    if (is_wp_error($response)) {
        error_log('ECCO Graph POST error: ' . $response->get_error_message());
        return null;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

/**
 * PUT request (upload chunks)
 */
function ecco_graph_put_raw($url, $body, $content_range) {
    $headers = ecco_graph_headers();
    if (!$headers) return null;

    $headers['Content-Length'] = strlen($body);
    $headers['Content-Range']  = $content_range;

    $response = wp_remote_request(
        $url,
        [
            'method'  => 'PUT',
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 120
        ]
    );

    if (is_wp_error($response)) {
        error_log('ECCO Graph PUT error: ' . $response->get_error_message());
        return null;
    }

    return wp_remote_retrieve_response_code($response);
}
