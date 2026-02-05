<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/graph-token-store.php';
require_once __DIR__ . '/graph-client.php';

function ecco_login_url() {
    $tenant   = get_option('ecco_tenant_id');
    $client   = get_option('ecco_client_id');
    $redirect = admin_url('admin-ajax.php?action=ecco_callback');

    return "https://login.microsoftonline.com/$tenant/oauth2/v2.0/authorize?" . http_build_query([
        'client_id'     => $client,
        'response_type' => 'code',
        'redirect_uri'  => $redirect,
        'scope'         => 'openid profile email offline_access User.Read Sites.ReadWrite.All Files.ReadWrite.All',
        'prompt'        => 'select_account',
    ]);
}

function ecco_is_authenticated() {
    if (!is_user_logged_in()) return false;

    $token = ecco_graph_get_token(get_current_user_id());
    return !empty($token['access_token']);
}

add_action('wp_ajax_nopriv_ecco_callback', 'ecco_handle_graph_callback');
add_action('wp_ajax_ecco_callback', 'ecco_handle_graph_callback');

function ecco_handle_graph_callback() {
    if (empty($_GET['code'])) {
        wp_die('No auth code returned from Microsoft');
    }

    $response = wp_remote_post(
        "https://login.microsoftonline.com/" . get_option('ecco_tenant_id') . "/oauth2/v2.0/token",
        [
            'body' => [
                'client_id'     => get_option('ecco_client_id'),
                'client_secret' => get_option('ecco_client_secret'),
                'code'          => sanitize_text_field($_GET['code']),
                'redirect_uri'  => admin_url('admin-ajax.php?action=ecco_callback'),
                'grant_type'    => 'authorization_code',
            ],
            'timeout' => 60
        ]
    );

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['access_token'])) {
        error_log('ECCO OAuth failed: ' . print_r($body, true));
        wp_die('Microsoft login failed');
    }

    // 🔐 Temporarily set token for profile lookup
    $_COOKIE['ecco_token'] = $body['access_token'];

    $me = ecco_graph_get('me');

    if (empty($me['mail']) && empty($me['userPrincipalName'])) {
        wp_die('Unable to resolve Microsoft user profile');
    }

    $email = strtolower($me['mail'] ?? $me['userPrincipalName']);
    $name  = $me['displayName'] ?? $email;

    // 👤 Create or login WordPress user
    $user = get_user_by('email', $email);

    if (!$user) {
        $username = sanitize_user(current(explode('@', $email)));

        if (username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false);
        }

        $user_id = wp_create_user($username, wp_generate_password(32), $email);
        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $name,
        ]);

        $user = get_user_by('id', $user_id);
    }

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    // 💾 Persist Graph token
    ecco_graph_store_token($user->ID, [
        'access_token'  => $body['access_token'],
        'refresh_token' => $body['refresh_token'] ?? null,
        'expires_in'    => $body['expires_in'] ?? 3600,
    ]);

    wp_redirect(site_url('/intranet/?connected=1'));
    exit;
}
