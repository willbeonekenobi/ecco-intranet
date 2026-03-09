<?php
if (!defined('ABSPATH')) exit;

/**
 * ECCO single document library shortcode
 *
 * Usage:
 * [ecco_library library="daily_journals"]
 */
add_shortcode('ecco_library', function ($atts) {

    // Must be authenticated
    if (!ecco_is_authenticated()) {
        return '<p><a href="' . esc_url(ecco_login_url()) . '">Sign in with Microsoft</a></p>';
    }

    $atts = shortcode_atts([
        'library' => ''
    ], $atts);

    if (empty($atts['library'])) {
        return '<p><strong>ECCO:</strong> No document library specified.</p>';
    }

    // Load JS/CSS only when shortcode is used
    ecco_enqueue_assets();

    ob_start();
    ?>
    <section class="ecco-library" data-library="<?php echo esc_attr($atts['library']); ?>">

        <div class="doc-toolbar" style="margin-bottom:10px;">
            <input type="file" class="upload" />

            <select class="ecco-conflict">
                <option value="overwrite">Overwrite existing</option>
                <option value="rename">Rename if exists</option>
                <option value="cancel">Cancel if exists</option>
            </select>

            <button type="button" class="upload-btn button button-primary">
                Upload
            </button>
        </div>

        <div class="doc-list"></div>

    </section>
    <?php
    return ob_get_clean();
});
