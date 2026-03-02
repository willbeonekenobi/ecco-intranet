<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_ecco_get_groups', 'ecco_get_groups');
add_action('wp_ajax_ecco_get_events', 'ecco_get_events');
add_action('wp_ajax_ecco_save_event', 'ecco_save_event');
add_action('wp_ajax_ecco_delete_event', 'ecco_delete_event');

function ecco_get_access_token() {
    // Use your existing token logic here
    return get_option('ecco_ms_access_token');
}

function ecco_graph_request($method, $endpoint, $body = null) {
    $token = ecco_get_access_token();

    $args = [
        'method'  => $method,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json'
        ]
    ];

    if ($body) {
        $args['body'] = json_encode($body);
    }

    $response = wp_remote_request(
        "https://graph.microsoft.com/v1.0/" . $endpoint,
        $args
    );

    return json_decode(wp_remote_retrieve_body($response), true);
}

function ecco_get_groups() {
    $groups = ecco_graph_request(
        'GET',
        "groups?\$filter=groupTypes/any(c:c eq 'Unified')"
    );
    wp_send_json($groups['value']);
}

function ecco_get_events() {
    $group_id = sanitize_text_field($_POST['group_id']);

    $events = ecco_graph_request(
        'GET',
        "groups/$group_id/events"
    );

    wp_send_json($events['value']);
}

function ecco_save_event() {
    $group_id = sanitize_text_field($_POST['group_id']);
    $event_id = sanitize_text_field($_POST['event_id']);
    $data     = json_decode(stripslashes($_POST['event_data']), true);

    if ($event_id) {
        $result = ecco_graph_request(
            'PATCH',
            "groups/$group_id/events/$event_id",
            $data
        );
    } else {
        $result = ecco_graph_request(
            'POST',
            "groups/$group_id/events",
            $data
        );
    }

    wp_send_json($result);
}

function ecco_delete_event() {
    $group_id = sanitize_text_field($_POST['group_id']);
    $event_id = sanitize_text_field($_POST['event_id']);

    ecco_graph_request(
        'DELETE',
        "groups/$group_id/events/$event_id"
    );

    wp_send_json_success();
}