<?php
if (!defined('ABSPATH')) exit;

/**
 * Resolve effective manager for the current user
 */
function ecco_resolve_effective_manager() {

    $user_id = get_current_user_id();
    if (!$user_id) return null;

    // If user has manager override in user meta
    $manager_email = get_user_meta($user_id, 'ecco_manager_email', true);

    if ($manager_email) {
        return [
            'mail' => $manager_email
        ];
    }

    // Default: ask Microsoft Graph for manager
    if (!function_exists('ecco_graph_get')) return null;

    $manager = ecco_graph_get('/me/manager');

    if (!$manager) return null;

    return [
        'mail' => $manager['mail'] ?? null,
        'displayName' => $manager['displayName'] ?? null
    ];
}