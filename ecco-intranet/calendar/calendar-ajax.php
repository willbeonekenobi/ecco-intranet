<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   GRAPH TOKEN HELPER
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
   GRAPH REQUEST HELPER
   ========================================================= */

function ecco_graph_request($method, $endpoint, $body = null) {

    $token = ecco_get_graph_token();

    if (!$token) {
        return false;
    }

    $args = [
        'method' => $method,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json'
        ],
        'timeout' => 60
    ];

    if ($body) {
        $args['body'] = json_encode($body);
    }

    $response = wp_remote_request(
        "https://graph.microsoft.com/v1.0{$endpoint}",
        $args
    );

    if (is_wp_error($response)) {
        return false;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}


/* =========================================================
   FETCH USER GROUPS
   ========================================================= */

add_action('wp_ajax_ecco_get_groups', 'ecco_get_groups');

function ecco_get_groups() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    $endpoint = "/me/memberOf?\$select=id,displayName,groupTypes";
    $groups = [];

    do {

        $response = ecco_graph_request('GET', $endpoint);

        if (!$response) {
            wp_send_json_error('Graph request failed');
        }

        if (!empty($response['value'])) {

            foreach ($response['value'] as $item) {

                if (empty($item['groupTypes']) || !in_array('Unified', $item['groupTypes'])) {
                    continue;
                }

                $groups[] = [
                    'id'    => $item['id'],
                    'title' => $item['displayName']
                ];
            }

        }

        $endpoint = isset($response['@odata.nextLink'])
            ? str_replace('https://graph.microsoft.com/v1.0', '', $response['@odata.nextLink'])
            : null;

    } while ($endpoint);

    wp_send_json_success($groups);
}


/* =========================================================
   FETCH GROUP EVENTS (calendarView for recurring events)
   ========================================================= */

add_action('wp_ajax_ecco_get_group_events', 'ecco_get_group_events');

function ecco_get_group_events() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    if (empty($_POST['group_id'])) {
        wp_send_json_error('Missing group ID');
    }

    $group_id = sanitize_text_field($_POST['group_id']);

    // Visible range (1 year window)
    $start = gmdate('Y-m-d\T00:00:00\Z', strtotime('-6 months'));
    $end   = gmdate('Y-m-d\T00:00:00\Z', strtotime('+6 months'));

    $endpoint = "/groups/{$group_id}/calendarView?startDateTime={$start}&endDateTime={$end}";

    $response = ecco_graph_request('GET', $endpoint);

    if (!$response) {
        wp_send_json_error('Graph request failed');
    }

    $events = [];

    if (!empty($response['value'])) {

        foreach ($response['value'] as $event) {

            $start = null;
            $end   = null;

            if (!empty($event['start']['dateTime'])) {
                $start = (new DateTime($event['start']['dateTime']))
                    ->setTimezone(new DateTimeZone('Africa/Johannesburg'))
                    ->format('c');
            }

            if (!empty($event['end']['dateTime'])) {
                $end = (new DateTime($event['end']['dateTime']))
                    ->setTimezone(new DateTimeZone('Africa/Johannesburg'))
                    ->format('c');
            }

            $events[] = [
                'id'    => $event['id'],
                'title' => $event['subject'] ?? '(No title)',
                'start' => $start,
                'end'   => $end,
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

    $group_id = sanitize_text_field($_POST['group_id']);
    $title    = sanitize_text_field($_POST['title']);
    $start    = sanitize_text_field($_POST['start']);
    $end      = sanitize_text_field($_POST['end']);

    $body = [

        "subject" => $title,

        "start" => [
            "dateTime" => $start,
            "timeZone" => "South Africa Standard Time"
        ],

        "end" => [
            "dateTime" => $end,
            "timeZone" => "South Africa Standard Time"
        ]

    ];

    $response = ecco_graph_request(
        'POST',
        "/groups/{$group_id}/events",
        $body
    );

    if (!$response) {
        wp_send_json_error('Graph create failed');
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

    $group_id = sanitize_text_field($_POST['group_id']);
    $event_id = sanitize_text_field($_POST['event_id']);
    $title    = sanitize_text_field($_POST['title']);
    $start    = sanitize_text_field($_POST['start']);
    $end      = sanitize_text_field($_POST['end']);

    $body = [

        "subject" => $title,

        "start" => [
            "dateTime" => $start,
            "timeZone" => "South Africa Standard Time"
        ],

        "end" => [
            "dateTime" => $end,
            "timeZone" => "South Africa Standard Time"
        ]

    ];

    $response = ecco_graph_request(
        'PATCH',
        "/groups/{$group_id}/events/{$event_id}",
        $body
    );

    if (!$response) {
        wp_send_json_error('Update failed');
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

    $group_id = sanitize_text_field($_POST['group_id']);
    $event_id = sanitize_text_field($_POST['event_id']);

    $response = ecco_graph_request(
        'DELETE',
        "/groups/{$group_id}/events/{$event_id}"
    );

    if ($response === false) {
        wp_send_json_error('Delete failed');
    }

    wp_send_json_success();
}

