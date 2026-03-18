<?php
if (!defined('ABSPATH')) exit;

/*
=====================================================
 LOGBOOKS MODULE — Monthly Excel Logbooks
 Shortcodes:
   [ecco_logbook_upload]
   [ecco_logbook_status]
=====================================================
*/


/* =====================================================
   EMPLOYEE UPLOAD FORM
===================================================== */

add_shortcode('ecco_logbook_upload', function () {

    if (!is_user_logged_in()) {
        return '<p>Please log in.</p>';
    }

    // ✅ Default to PREVIOUS month (matches real workflow)
    $default_month = date('Y-m', strtotime('first day of last month'));

    // If form submitted, keep selected value
    $selected_month = $_POST['logbook_month'] ?? $default_month;

    ob_start(); ?>

    <h3>Upload Monthly Logbook</h3>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('ecco_logbook_upload'); ?>

        <label><strong>Select month:</strong></label><br>
        <input type="month"
               name="logbook_month"
               value="<?php echo esc_attr($selected_month); ?>"
               required>
        <br><br>

        <input type="file" name="logbook_file" accept=".xlsx,.xls" required>
        <br><br>

        <button type="submit" name="ecco_upload_logbook">
            Upload Logbook
        </button>
    </form>

    <?php

    if (isset($_POST['ecco_upload_logbook'])) {
        ecco_handle_logbook_upload();
    }

    return ob_get_clean();
});


/* =====================================================
   HANDLE UPLOAD
===================================================== */

function ecco_handle_logbook_upload() {

    if (!wp_verify_nonce($_POST['_wpnonce'], 'ecco_logbook_upload')) {
        echo "<p>Security check failed.</p>";
        return;
    }

    if (empty($_FILES['logbook_file']['tmp_name'])) {
        echo "<p>No file selected.</p>";
        return;
    }

    if (empty($_POST['logbook_month'])) {
        echo "<p>Please select a month.</p>";
        return;
    }

    $month = sanitize_text_field($_POST['logbook_month']);

    // Validate format YYYY-MM
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        echo "<p>Invalid month format.</p>";
        return;
    }

    $driveMap = ecco_get_drive_map();

    if (empty($driveMap['logbooks'])) {
        echo "<p>Logbooks library not configured.</p>";
        return;
    }

    $driveId = $driveMap['logbooks'];
    $user    = wp_get_current_user();

    $name = $user->display_name;

    $tmp  = $_FILES['logbook_file']['tmp_name'];
    $data = file_get_contents($tmp);

    $path = "$month/$name/logbook.xlsx";

    ecco_graph_put(
        "drives/$driveId/root:/$path:/content",
        $data,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    echo "<p style='color:green'>Logbook uploaded successfully for <strong>$month</strong>.</p>";
}
