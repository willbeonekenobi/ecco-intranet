<?php
if (!defined('ABSPATH')) exit;

/**
 * Get access token from cookie
 */
function ecco_graph_get_token_from_cookie() {

    $user_id = get_current_user_id();

    if (!$user_id) {
        error_log('ECCO Graph: No logged-in user');
        return null;
    }

    $token_data = ecco_graph_get_token($user_id);

    if (!$token_data || empty($token_data['access_token'])) {
        error_log('ECCO Graph: No stored token');
        return null;
    }

    // Check expiry
    if (!empty($token_data['expires_at']) && $token_data['expires_at'] <= time()) {
        error_log('ECCO Graph: Token expired');
        return null;
    }

    return $token_data['access_token'];
    }

/**
 * GET request
 */
function ecco_graph_get($endpoint) {
    $token = ecco_graph_get_token_from_cookie();
    if (!$token) return null;

    $response = wp_remote_get(
        'https://graph.microsoft.com/v1.0/' . ltrim($endpoint, '/'),
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 60
        ]
    );

    if (is_wp_error($response)) {
        error_log('ECCO Graph GET error: ' . $response->get_error_message());
        return null;
    }

    $code = wp_remote_retrieve_response_code($response);
    $resp_body = wp_remote_retrieve_body($response);
    $json = json_decode($resp_body, true);

    if ($code >= 400) {
        error_log("ECCO Graph GET HTTP {$code}: {$resp_body}");
        return null;
    }

    return $json;
}

/**
 * PUT request (file upload)
 */
function ecco_graph_put($endpoint, $body, $content_type = 'application/octet-stream') {
    $token = ecco_graph_get_token_from_cookie();
    if (!$token) return null;

    // Ensure body is string (important fix)
    if (is_array($body)) {
        $body = json_encode($body);
    }

    $response = wp_remote_request(
        'https://graph.microsoft.com/v1.0/' . ltrim($endpoint, '/'),
        [
            'method'  => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
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
    $resp_body = wp_remote_retrieve_body($response);
    $json = json_decode($resp_body, true);

    if ($code >= 400) {
        error_log("ECCO Graph PUT HTTP {$code}: {$resp_body}");
        return null;
    }

    return $json;
}

/**
 * POST request (folder creation, etc.)
 */
function ecco_graph_post($endpoint, $body = []) {
    $token = ecco_graph_get_token_from_cookie();
    if (!$token) return null;

    $response = wp_remote_post(
        'https://graph.microsoft.com/v1.0/' . ltrim($endpoint, '/'),
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ],
            'body'    => json_encode($body),
            'timeout' => 60
        ]
    );

    if (is_wp_error($response)) {
        error_log('ECCO Graph POST error: ' . $response->get_error_message());
        return null;
    }

    $code = wp_remote_retrieve_response_code($response);
    $resp_body = wp_remote_retrieve_body($response);
    $json = json_decode($resp_body, true);

    if ($code >= 400) {
        error_log("ECCO Graph POST HTTP {$code}: {$resp_body}");
        return null;
    }

    return $json;
}

/**
 * Ensure folder path exists
 */
function ecco_graph_ensure_folder($path) {

    $parts = array_filter(explode('/', trim($path, '/')));
    $parent_id = 'root';

    foreach ($parts as $folder) {

        $existing = ecco_graph_get(
            "/me/drive/items/{$parent_id}/children?\$filter=name eq '{$folder}'"
        );

        if (!empty($existing['value'][0]['id'])) {
            $parent_id = $existing['value'][0]['id'];
            continue;
        }

        $created = ecco_graph_post(
            "/me/drive/items/{$parent_id}/children",
            [
                'name'   => $folder,
                'folder' => new stdClass(),
                '@microsoft.graph.conflictBehavior' => 'fail'
            ]
        );

        if (empty($created['id'])) {
            error_log('ECCO folder create failed: ' . print_r($created, true));
            return false;
        }

        $parent_id = $created['id'];
    }

    return true;
}
