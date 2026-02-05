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
    return is_array($token) ? $token : null;
}

function ecco_graph_clear_token($user_id) {
    delete_user_meta($user_id, '_ecco_graph_token');
}
