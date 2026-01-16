<?php

function ecco_graph_get($endpoint) {
    if (!isset($_COOKIE['ecco_token'])) return null;

    $response = wp_remote_get(
        "https://graph.microsoft.com/v1.0/$endpoint",
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $_COOKIE['ecco_token']
            ]
        ]
    );

    return json_decode(wp_remote_retrieve_body($response), true);
}
