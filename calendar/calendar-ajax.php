<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_ecco_get_groups', 'ecco_get_groups');

function ecco_get_groups() {

    // 1️⃣ Must be logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    // 2️⃣ Token function must exist
    if (!function_exists('ecco_graph_get_token')) {
        wp_send_json_error('Token store not loaded');
    }

    $user_id   = get_current_user_id();
    $tokenData = ecco_graph_get_token($user_id);

    if (empty($tokenData['access_token'])) {
        wp_send_json_error('No access token found');
    }

    $access_token = $tokenData['access_token'];

    $endpoint = 'https://graph.microsoft.com/v1.0/me/memberOf?$select=id,displayName,groupTypes,securityEnabled';
    $groups   = [];

    while ($endpoint) {

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json'
            ],
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code >= 400) {
            wp_send_json_error([
                'graph_code' => $code,
                'graph_body' => $json
            ]);
        }

        if (!empty($json['value'])) {

            foreach ($json['value'] as $item) {

                // Only include actual Groups (skip directory roles etc.)
                if (!isset($item['displayName'])) {
                    continue;
                }

                // Optional: only include M365 groups or security groups
                $is_m365 = !empty($item['groupTypes']) && in_array('Unified', $item['groupTypes']);

                if (!$is_m365) {
                    continue; // Only include Microsoft 365 groups
                }

                $groups[] = [
                    'id'    => $item['id'],
                    'title' => $item['displayName'],
                ];
            }
        }

        // Handle pagination
        $endpoint = isset($json['@odata.nextLink']) ? $json['@odata.nextLink'] : null;
    }

    if (empty($groups)) {
        wp_send_json_error('User is not a member of any valid groups');
    }

    wp_send_json_success($groups);
}

add_action('wp_ajax_ecco_get_group_events', 'ecco_get_group_events');

function ecco_get_group_events() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    if (empty($_POST['group_id'])) {
        wp_send_json_error('Missing group ID');
    }

    $group_id = sanitize_text_field($_POST['group_id']);

    $response = ecco_graph_get("/groups/{$group_id}/calendar/events");

    if (!$response || empty($response['value'])) {
        wp_send_json_error('No events found');
    }

    $events = [];

    foreach ($response['value'] as $event) {

        $events[] = [
            'title' => $event['subject'],
            'start' => $event['start']['dateTime'],
            'end'   => $event['end']['dateTime'],
        ];
    }

    wp_send_json_success($events);
}
