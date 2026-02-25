<?php
if (!defined('ABSPATH')) exit;

/*
=====================================================
 LOGBOOKS MODULE — Monthly Excel Logbooks
=====================================================
*/


/* =====================================================
   EMPLOYEE UPLOAD FORM
===================================================== */

add_shortcode('ecco_logbook_upload', function () {

    if (!is_user_logged_in()) return '<p>Please log in.</p>';

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

    $data = file_get_contents($_FILES['logbook_file']['tmp_name']);
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


    /* ---------- HANDLE REMINDER ---------- */

    if (isset($_POST['ecco_send_reminder'])) {

        if (!wp_verify_nonce($_POST['_wpnonce'], 'ecco_send_reminder')) {
            echo "<p>Security check failed.</p>";
        } else {

            $employeeId   = intval($_POST['employee_id'] ?? 0);
            $employeeName = trim($_POST['employee_name'] ?? '');
            $month        = sanitize_text_field($_POST['month'] ?? date('Y-m'));

            $user = false;

            /* ⭐ 1. Try ID (best method) */
            if ($employeeId > 0) {
                $user = get_user_by('id', $employeeId);
            }

            /* ⭐ 2. Try exact display name match */
            if (!$user && $employeeName !== '') {

                $users = get_users([
                    'meta_key'   => 'display_name',
                    'meta_value' => $employeeName,
                    'number'     => 1
                ]);

                if (!empty($users)) {
                    $user = $users[0];
                }
            }

            /* ⭐ 3. Search all users by display name */
            if (!$user && $employeeName !== '') {

                $users = get_users();

                foreach ($users as $u) {
                    if ($u->display_name === $employeeName) {
                        $user = $u;
                        break;
                    }
                }
            }

            /* ---------- SEND EMAIL ---------- */

            if ($user && !empty($user->user_email)) {

                $to = $user->user_email;

                $subject = "URGENT: Logbook Overdue — $month";

                $message = "
Dear {$user->display_name},

Your monthly logbook for $month has not been submitted.

This is now overdue and must be rectified immediately.

Please upload your logbook as soon as possible.

Regards,
Management
";

                $sent = wp_mail($to, $subject, $message);

                if ($sent) {
                    echo "<p style='color:green'><strong>Reminder sent to {$user->display_name}.</strong></p>";
                } else {
                    echo "<p style='color:red'>Mail failed to send.</p>";
                }

            } else {

                echo "<p style='color:red'>Could not determine email for {$employeeName}.</p>";
            }
        }
    }


    /* ---------- LOAD DATA ---------- */

    $driveMap = ecco_get_drive_map();
    $driveId  = $driveMap['logbooks'];

    $month = sanitize_text_field($_GET['month'] ?? date('Y-m'));

    $employees = ecco_get_all_employees();

    $res = ecco_graph_get("drives/$driveId/root:/$month:/children");

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

        $name = $emp['name'] ?? '';
        $id   = $emp['id'] ?? 0;

        $key = strtolower($name);

        /* ---------- UPLOADED ---------- */

        if (isset($folders[$key])) {

            $sub = ecco_graph_get("drives/$driveId/root:/$month/$name:/children");

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
            <input type="hidden" name="employee_id" value="<?php echo esc_attr($id); ?>">
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
