<?php
if (!defined('ABSPATH')) exit;

function ecco_graph_store_token($user_id, $token_data) {
    if (!$user_id || empty($token_data['access_token'])) return;

    if (empty($token_data['expires_at']) && !empty($token_data['expires_in'])) {
        $token_data['expires_at'] = time() + intval($token_data['expires_in']) - 60;
    }

    update_user_meta($user_id, '_ecco_graph_token', $token_data);
}

function ecco_graph_get_token($user_id) {

    $token = get_user_meta($user_id, '_ecco_graph_token', true);

    if (!is_array($token) || empty($token['access_token'])) {
        return null;
    }

    // If token still valid → return it
    if (!empty($token['expires_at']) && $token['expires_at'] > time()) {
        return $token;
    }

    // Try refresh
    if (!empty($token['refresh_token'])) {

        $new = ecco_graph_refresh_token($token['refresh_token']);

        if ($new && !empty($new['access_token'])) {

            ecco_graph_store_token($user_id, $new);

            return get_user_meta($user_id, '_ecco_graph_token', true);
        }
    }

    error_log('ECCO Graph: Token expired and refresh failed');

    return null;
}

function ecco_graph_clear_token($user_id) {
    delete_user_meta($user_id, '_ecco_graph_token');
}

function ecco_graph_refresh_token($refresh_token) {

    $tenant  = get_option('ecco_tenant_id');
    $client  = get_option('ecco_client_id');
    $secret  = get_option('ecco_client_secret');

    $response = wp_remote_post(
        "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
        [
            'body' => [
                'client_id'     => $client,
                'client_secret' => $secret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
                'scope'         => 'https://graph.microsoft.com/.default'
            ],
            'timeout' => 60
        ]
    );

    if (is_wp_error($response)) {
        error_log('ECCO Graph refresh error: ' . $response->get_error_message());
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['access_token'])) {
        error_log('ECCO Graph refresh failed: ' . print_r($body, true));
        return null;
    }

    $body['expires_at'] = time() + intval($body['expires_in']) - 60;

    return $body;
}
