<?php
if (!defined('ABSPATH')) exit;

/**
 * ECCO Intranet Dashboard (links only)
 *
 * /intranet page
 */
add_shortcode('ecco_dashboard', function () {

    if (!ecco_is_authenticated()) {
        return '<p><a href="' . esc_url(ecco_login_url()) . '">Sign in with Microsoft</a></p>';
    }

    $libraries = [
        'daily-journals'      => 'Daily Journals',
        'hr-documents'        => 'HR Documents',
        'policies-and-procedures'            => 'Policies and Procedures',
        'wiring-diagrams'     => 'Wiring Diagrams',
        'job-cards'           => 'Job Cards',
        'maintenance-reports' => 'Maintenance Reports',
        'health-safety'       => 'Health & Safety',
        'logbooks'            => 'Logbooks',
    ];

    ob_start();
    ?>
    <div class="ecco-dashboard">

        <h2>ECCO Intranet</h2>

        <div class="ecco-dashboard-grid">
            <?php foreach ($libraries as $slug => $label): ?>
                <a class="ecco-dashboard-tile"
                   href="<?php echo esc_url(site_url('/' . $slug)); ?>">
                    <span class="ecco-tile-icon">📁</span>
                    <span class="ecco-tile-label"><?php echo esc_html($label); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

    </div>
    <?php
    return ob_get_clean();
});
