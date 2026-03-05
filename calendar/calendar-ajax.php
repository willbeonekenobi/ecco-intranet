<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   HELPER — GET GRAPH ACCESS TOKEN
   ========================================================= */

function ecco_get_graph_token() {

    if (!function_exists('ecco_graph_get_token')) {
        return false;
    }

    $user_id = get_current_user_id();
    $token_data = ecco_graph_get_token($user_id);

    if (empty($token_data['access_token'])) {
        return false;
    }

    return $token_data['access_token'];
}


/* =========================================================
   FETCH USER'S MICROSOFT 365 GROUPS
   ========================================================= */

add_action('wp_ajax_ecco_get_groups', 'ecco_get_groups');

function ecco_get_groups() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    $access_token = ecco_get_graph_token();

    if (!$access_token) {
        wp_send_json_error('No access token');
    }

    $endpoint = 'https://graph.microsoft.com/v1.0/me/memberOf?$select=id,displayName,groupTypes';
    $groups = [];

    while ($endpoint) {

        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['value'])) {

            foreach ($body['value'] as $item) {

                if (empty($item['groupTypes']) || !in_array('Unified', $item['groupTypes'])) {
                    continue;
                }

                $groups[] = [
                    'id'    => $item['id'],
                    'title' => $item['displayName']
                ];
            }

        }

        $endpoint = $body['@odata.nextLink'] ?? null;
    }

    wp_send_json_success($groups);
}


/* =========================================================
   FETCH GROUP EVENTS
   ========================================================= */

add_action('wp_ajax_ecco_get_group_events', 'ecco_get_group_events');

function ecco_get_group_events() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    if (empty($_POST['group_id'])) {
        wp_send_json_error('Missing group ID');
    }

    $access_token = ecco_get_graph_token();

    if (!$access_token) {
        wp_send_json_error('No access token');
    }

    $group_id = sanitize_text_field($_POST['group_id']);

    $endpoint = "https://graph.microsoft.com/v1.0/groups/{$group_id}/events";

    $response = wp_remote_get($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token
        ]
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    $events = [];

    if (!empty($body['value'])) {

        foreach ($body['value'] as $event) {

            $events[] = [
                'id'    => $event['id'],
                'title' => $event['subject'] ?? '(No title)',
                'start' => $event['start']['dateTime'] ?? null,
                'end'   => $event['end']['dateTime'] ?? null,
                'allDay'=> $event['isAllDay'] ?? false
            ];

        }
    }

    wp_send_json_success($events);
}


/* =========================================================
   CREATE EVENT
   ========================================================= */

add_action('wp_ajax_ecco_create_event', 'ecco_create_event');

function ecco_create_event() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    $access_token = ecco_get_graph_token();

    if (!$access_token) {
        wp_send_json_error('No access token');
    }

    $group_id = sanitize_text_field($_POST['group_id']);
    $title    = sanitize_text_field($_POST['title']);
    $start    = sanitize_text_field($_POST['start']);
    $end      = sanitize_text_field($_POST['end']);

    $endpoint = "https://graph.microsoft.com/v1.0/groups/{$group_id}/events";

    $body = [
        "subject" => $title,
        "start" => [
            "dateTime" => $start,
            "timeZone" => "UTC"
        ],
        "end" => [
            "dateTime" => $end,
            "timeZone" => "UTC"
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($body)
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    wp_send_json_success();
}


/* =========================================================
   UPDATE EVENT
   ========================================================= */

add_action('wp_ajax_ecco_update_event', 'ecco_update_event');

function ecco_update_event() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    $access_token = ecco_get_graph_token();

    if (!$access_token) {
        wp_send_json_error('No access token');
    }

    $group_id = sanitize_text_field($_POST['group_id']);
    $event_id = sanitize_text_field($_POST['event_id']);
    $title    = sanitize_text_field($_POST['title']);
    $start    = sanitize_text_field($_POST['start']);
    $end      = sanitize_text_field($_POST['end']);

    $endpoint = "https://graph.microsoft.com/v1.0/groups/{$group_id}/events/{$event_id}";

    $body = [
        "subject" => $title,
        "start" => [
            "dateTime" => $start,
            "timeZone" => "UTC"
        ],
        "end" => [
            "dateTime" => $end,
            "timeZone" => "UTC"
        ]
    ];

    $response = wp_remote_request($endpoint, [
        'method' => 'PATCH',
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($body)
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    wp_send_json_success();
}


/* =========================================================
   DELETE EVENT
   ========================================================= */

add_action('wp_ajax_ecco_delete_event', 'ecco_delete_event');

function ecco_delete_event() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    $access_token = ecco_get_graph_token();

    if (!$access_token) {
        wp_send_json_error('No access token');
    }

    $group_id = sanitize_text_field($_POST['group_id']);
    $event_id = sanitize_text_field($_POST['event_id']);

    $endpoint = "https://graph.microsoft.com/v1.0/groups/{$group_id}/events/{$event_id}";

    $response = wp_remote_request($endpoint, [
        'method' => 'DELETE',
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token
        ]
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    wp_send_json_success();
}