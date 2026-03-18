<?php

/**
 * Logical library keys → display names
 */
function ecco_library_map() {
    return [
        'daily_journals'          => 'Daily Journals',
        'hr_docs'                 => 'Hr Documents',
        'policies'                => 'Policies and Procedures',
        'wiring'                  => 'Wiring Diagrams',
        'job_cards'               => 'Job cards',
        'maintenance'             => 'Maintenance Reports',
        'hse'                     => 'Health & Safety',
        'logbooks'                => 'LogBooks',
        'projectboards'           => 'Project Boards',
        'training_certificates'   => 'TrainingCertificates',
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
 * Discover document libraries (drives) and map by name.
 *
 * The cache is considered stale and is rebuilt automatically when any key
 * from ecco_library_map() is absent — prevents old cached maps from hiding
 * newly-added library mappings without requiring a manual cache clear.
 */
function ecco_get_drive_map($force_refresh = false) {

    if (!$force_refresh) {
        $cached = get_option('ecco_drive_map');
        if ($cached && is_array($cached) && count($cached)) {

            /* Stale-cache guard: rebuild if any wanted key is missing */
            $missing = array_diff_key(ecco_library_map(), $cached);
            if (empty($missing)) {
                return $cached;
            }
            // Fall through to force a rebuild
        }
    }

    $siteId = ecco_get_site_id();
    if (!$siteId) return null;

    $drives = ecco_graph_get("sites/$siteId/drives");
    if (!isset($drives['value'])) return null;

    $map       = [];
    $wanted    = ecco_library_map();
    $raw_names = [];

    foreach ($drives['value'] as $drive) {

        $driveName   = strtolower($drive['name']);
        $raw_names[] = $drive['name'] . ' [' . ($drive['id'] ?? '?') . ']';

        foreach ($wanted as $key => $name) {
            $wantedName = strtolower($name);
            if (strpos($driveName, $wantedName) !== false) {
                $map[$key] = $drive['id'];
            }
        }
    }

    update_option('ecco_drive_map',       $map);
    update_option('ecco_drive_raw_names', $raw_names); // used by diagnostics panel

    return $map;
}
