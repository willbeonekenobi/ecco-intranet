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

    $month = date('Y-m');

    ob_start(); ?>

    <h3>Upload Monthly Logbook (<?php echo esc_html($month); ?>)</h3>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('ecco_logbook_upload'); ?>

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

    $driveMap = ecco_get_drive_map();

    if (empty($driveMap['logbooks'])) {
        echo "<p>Logbooks library not configured.</p>";
        return;
    }

    $driveId = $driveMap['logbooks'];
    $user    = wp_get_current_user();

    $name  = $user->display_name;
    $month = date('Y-m');

    $tmp  = $_FILES['logbook_file']['tmp_name'];
    $data = file_get_contents($tmp);

    $path = "$month/$name/logbook.xlsx";

    ecco_graph_put(
        "drives/$driveId/root:/$path:/content",
        $data,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    echo "<p style='color:green'>Logbook uploaded successfully.</p>";
}



/* =====================================================
   MANAGER DASHBOARD
===================================================== */

add_shortcode('ecco_logbook_status', function () {

    if (!ecco_user_is_manager()) {
        return '<p>You do not have permission.</p>';
    }

    /* ---------- HANDLE REMINDER SEND ---------- */

    if (isset($_POST['ecco_send_reminder'])) {

        if (!wp_verify_nonce($_POST['_wpnonce'], 'ecco_send_reminder')) {
            echo "<p>Security check failed.</p>";
        } else {

            $employeeName = sanitize_text_field($_POST['employee_name']);
            $month        = sanitize_text_field($_POST['month']);

            // Attempt to find WP user by display name
            $user = get_user_by('slug', sanitize_title($employeeName));

            if (!$user) {
                $user = get_user_by('email', $employeeName);
            }

            if ($user && !empty($user->user_email)) {

                $to = $user->user_email;

                $subject = "URGENT: Logbook Overdue — $month";

                $message = "
Dear $employeeName,

Your monthly logbook for $month has not been submitted.

This is now overdue and must be rectified immediately.

Please upload your logbook as soon as possible.

Regards,
Management
";

                wp_mail($to, $subject, $message);

                echo "<p style='color:green'><strong>Reminder sent to $employeeName.</strong></p>";

            } else {

                echo "<p style='color:red'>Could not determine email for $employeeName.</p>";
            }
        }
    }


    /* ---------- LOAD DATA ---------- */

    $driveMap = ecco_get_drive_map();
    $driveId  = $driveMap['logbooks'];

    $month = isset($_GET['month'])
        ? sanitize_text_field($_GET['month'])
        : date('Y-m');

    $employees = ecco_get_all_employees();

    $res = ecco_graph_get(
        "drives/$driveId/root:/$month:/children"
    );

    $folders = [];

    if (!empty($res['value'])) {
        foreach ($res['value'] as $item) {
            if (isset($item['folder'])) {
                $folders[strtolower($item['name'])] = $item;
            }
        }
    }

    ob_start(); ?>

    <h3>Logbook Status — <?php echo esc_html($month); ?></h3>

    <form method="get">
        <input type="month" name="month" value="<?php echo esc_attr($month); ?>">
        <button>View</button>
    </form>

    <br>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Status</th>
                <th>Uploaded</th>
                <th>Link</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>

    <?php

    foreach ($employees as $emp) {

        $name = $emp['name'];
        $key  = strtolower($name);

        /* ---------- UPLOADED ---------- */

        if (isset($folders[$key])) {

            $sub = ecco_graph_get(
                "drives/$driveId/root:/$month/$name:/children"
            );

            if (!empty($sub['value'])) {

                $file = $sub['value'][0];

                echo "<tr>";
                echo "<td>$name</td>";
                echo "<td style='color:green'><strong>Uploaded</strong></td>";
                echo "<td>{$file['lastModifiedDateTime']}</td>";
                echo "<td><a href='{$file['webUrl']}' target='_blank'>Open</a></td>";
                echo "<td>–</td>";
                echo "</tr>";

                continue;
            }
        }

        /* ---------- MISSING ---------- */

        echo "<tr>";
        echo "<td>$name</td>";
        echo "<td style='color:red'><strong>Missing</strong></td>";
        echo "<td>–</td>";
        echo "<td>–</td>";
        echo "<td>";

        ?>

        <form method="post" style="margin:0;">
            <?php wp_nonce_field('ecco_send_reminder'); ?>
            <input type="hidden" name="employee_name" value="<?php echo esc_attr($name); ?>">
            <input type="hidden" name="month" value="<?php echo esc_attr($month); ?>">
            <button type="submit" name="ecco_send_reminder">
                Send Reminder
            </button>
        </form>

        <?php

        echo "</td>";
        echo "</tr>";
    }

    ?>

        </tbody>
    </table>

    <?php

    return ob_get_clean();
});
