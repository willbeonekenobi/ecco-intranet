<?php

function ecco_login_url() {
    $tenant = get_option('ecco_tenant_id');
    $client = get_option('ecco_client_id');
    $redirect = admin_url('admin-ajax.php?action=ecco_callback');

    return "https://login.microsoftonline.com/$tenant/oauth2/v2.0/authorize?" . http_build_query([
        'client_id'     => $client,
        'response_type' => 'code',
        'redirect_uri'  => $redirect,
        'scope'         => 'openid profile email User.Read Sites.ReadWrite.All Files.ReadWrite.All',
    ]);
}

function ecco_is_authenticated() {
    return isset($_COOKIE['ecco_token']);
}

add_action('wp_ajax_nopriv_ecco_callback', function () {
    if (!isset($_GET['code'])) wp_die('No auth code');

    $response = wp_remote_post(
        "https://login.microsoftonline.com/" . get_option('ecco_tenant_id') . "/oauth2/v2.0/token",
        [
            'body' => [
                'client_id'     => get_option('ecco_client_id'),
                'client_secret' => get_option('ecco_client_secret'),
                'code'          => $_GET['code'],
                'redirect_uri'  => admin_url('admin-ajax.php?action=ecco_callback'),
                'grant_type'    => 'authorization_code',
            ]
        ]
    );

    $body = json_decode(wp_remote_retrieve_body($response), true);

    setcookie('ecco_token', $body['access_token'], time() + 3600, '/', '', true, true);

    wp_redirect(site_url('/intranet'));
    exit;
});
