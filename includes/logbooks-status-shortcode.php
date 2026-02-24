<?php
if (!defined('ABSPATH')) exit;

/**
 * Manager Logbook Status Dashboard
 * Shortcode: [ecco_logbook_status]
 */

add_shortcode('ecco_logbook_status', 'ecco_render_logbook_status');

function ecco_render_logbook_status() {

    if (!ecco_user_is_manager()) {
        return '<p>You do not have permission to view this page.</p>';
    }

    $driveMap = ecco_get_drive_map();
    if (empty($driveMap['logbooks'])) {
        return '<p>Logbooks library not found.</p>';
    }

    $driveId = $driveMap['logbooks'];

    // Month selector (YYYY-MM)
    $month = isset($_GET['month'])
        ? sanitize_text_field($_GET['month'])
        : date('Y-m');

    $folderPath = $month;

    // Get employee list (adjust if needed)
    $employees = ecco_get_all_employees();

    // Get employee folders for the month
    $response = ecco_graph_get(
        "drives/$driveId/root:/$folderPath:/children"
    );

    if (empty($response['value'])) {
        return "<p>No logbook folders found for $month.</p>";
    }

    // Map folders by name
    $folders = [];
    foreach ($response['value'] as $item) {
        if (isset($item['folder'])) {
            $folders[strtolower($item['name'])] = $item;
        }
    }

    ob_start();
    ?>

    <h3>Logbook Status — <?php echo esc_html($month); ?></h3>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Status</th>
                <th>Uploaded</th>
                <th>Link</th>
            </tr>
        </thead>
        <tbody>

    <?php

    foreach ($employees as $emp) {

        $name = $emp['name'];
        $key  = strtolower($name);

        if (isset($folders[$key])) {

            // Check inside employee folder
            $sub = ecco_graph_get(
                "drives/$driveId/root:/$folderPath/$name:/children"
            );

            if (!empty($sub['value'])) {

                $file = $sub['value'][0];

                echo "<tr>";
                echo "<td>" . esc_html($name) . "</td>";
                echo "<td style='color:green'><strong>Uploaded</strong></td>";
                echo "<td>" . esc_html($file['lastModifiedDateTime']) . "</td>";
                echo "<td><a href='" . esc_url($file['webUrl']) . "' target='_blank'>Open</a></td>";
                echo "</tr>";

            } else {

                ecco_missing_row($name);
            }

        } else {

            ecco_missing_row($name);
        }
    }

    ?>

        </tbody>
    </table>

    <?php

    return ob_get_clean();
}

/**
 * Helper: missing row
 */
function ecco_missing_row($name) {

    echo "<tr>";
    echo "<td>" . esc_html($name) . "</td>";
    echo "<td style='color:red'><strong>Missing</strong></td>";
    echo "<td>-</td>";
    echo "<td>-</td>";
    echo "</tr>";
}

/**
 * Manager check (uses WP role)
 * Adjust if you use Azure groups
 */
function ecco_user_is_manager() {

    $user = wp_get_current_user();

    if (!$user || empty($user->roles)) return false;

    return in_array('manager', $user->roles);
}

/**
 * Employee list
 * Replace with your real source if needed
 */
function ecco_get_all_employees() {

    $users = get_users(['role__not_in' => ['administrator']]);

    $list = [];

    foreach ($users as $u) {
        $list[] = [
            'name' => $u->display_name
        ];
    }

    return $list;
}