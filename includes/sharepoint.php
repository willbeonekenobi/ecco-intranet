<?php

/**
 * Logical library keys → display names
 */
function ecco_library_map() {
    return [
        'daily_journals' => 'Daily Journals',
        'hr_docs'        => 'Hr Documents',
        'policies'       => 'Policies and Procedures',
        'wiring'         => 'Wiring Diagrams',
        'job_cards'      => 'Job cards',
        'maintenance'    => 'Maintenance Reports',
        'hse'            => 'Health & Safety',
        'logbooks'        => 'LogBooks',
    ];
}

/**
 * Resolve the SharePoint Site ID (cached)
 */
function ecco_get_site_id() {
    $cached = get_option('ecco_site_id');
    if ($cached) return $cached;

    $site = ecco_graph_get(
        'sites/eccoaccesssystems22.sharepoint.com:/'
    );

    if (!isset($site['id'])) return null;

    update_option('ecco_site_id', $site['id']);
    return $site['id'];
}

/**
 * Discover document libraries (drives) and map by name
 */
function ecco_get_drive_map() {
    $cached = get_option('ecco_drive_map');
    if ($cached) return $cached;

    $siteId = ecco_get_site_id();
    if (!$siteId) return null;

    $drives = ecco_graph_get("sites/$siteId/drives");
    if (!isset($drives['value'])) return null;

    $map = [];
    $wanted = ecco_library_map();

    foreach ($drives['value'] as $drive) {
        foreach ($wanted as $key => $name) {
            if (strcasecmp($drive['name'], $name) === 0) {
                $map[$key] = $drive['id'];
            }
        }
    }

    update_option('ecco_drive_map', $map);
    return $map;
}
