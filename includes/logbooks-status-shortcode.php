<?php
if (!defined('ABSPATH')) exit;

/* =====================================================
   MANAGER LOGBOOK STATUS DASHBOARD
   Shortcode: [ecco_logbook_status]
===================================================== */

add_shortcode('ecco_logbook_status', 'ecco_render_logbook_status');

function ecco_render_logbook_status() {

    if (!ecco_user_is_manager()) {
        return '<p>You do not have permission to view this page.</p>';
    }

    $driveMap = ecco_get_drive_map();
    if (empty($driveMap['logbooks'])) {
        return '<p>Logbooks library not found. Please check your SharePoint configuration.</p>';
    }

    $driveId = $driveMap['logbooks'];

    /* ---- Handle reminder email ---- */
    $reminder_message = '';

    if (isset($_POST['ecco_send_reminder']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ecco_logbook_reminder')) {

        $employee_name = sanitize_text_field($_POST['employee_name'] ?? '');
        $month         = sanitize_text_field($_POST['month'] ?? '');

        /* Try to find the WP user by display name, then by login */
        $user = null;
        $all  = get_users(['search_columns' => ['display_name'], 'search' => $employee_name]);
        if (!empty($all)) $user = $all[0];

        if ($user && !empty($user->user_email)) {

            $to      = $user->user_email;
            $subject = "URGENT: Logbook Overdue — {$month}";

            $message = '<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;">
<div style="max-width:540px;margin:0 auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.12);">
  <div style="background:#c62828;padding:20px 28px;">
    <h2 style="color:#fff;margin:0;font-size:20px;">Logbook Overdue</h2>
  </div>
  <div style="padding:24px 28px;">
    <p>Hi <strong>' . esc_html($employee_name) . '</strong>,</p>
    <p>Your monthly logbook for <strong>' . esc_html($month) . '</strong> has not been submitted and is now overdue.</p>
    <p>Please upload your logbook as soon as possible.</p>
    <p style="margin:0;color:#888;font-size:13px;">This is an automated reminder from the ' . esc_html(get_bloginfo('name')) . ' Intranet.</p>
  </div>
</div>
</body>
</html>';

            wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);

            $reminder_message = '<div class="ecco-logbook-notice ecco-notice-success">Reminder sent to <strong>' . esc_html($employee_name) . '</strong> (' . esc_html($to) . ').</div>';

        } else {
            $reminder_message = '<div class="ecco-logbook-notice ecco-notice-error">Could not find an email address for <strong>' . esc_html($employee_name) . '</strong>.</div>';
        }
    }

    /* ---- Month selection ---- */
    $month = isset($_GET['month'])
        ? sanitize_text_field($_GET['month'])
        : date('Y-m', strtotime('first day of last month'));

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m', strtotime('first day of last month'));
    }

    /* ---- Fetch employee folders for the month from SharePoint ---- */
    $res     = ecco_graph_get("drives/{$driveId}/root:/{$month}:/children");
    $folders = [];

    if (!empty($res['value'])) {
        foreach ($res['value'] as $item) {
            if (isset($item['folder'])) {
                $folders[strtolower(trim($item['name']))] = $item;
            }
        }
    }

    /* ---- Employee list (all WP users, excluding current user if desired) ---- */
    $employees = ecco_get_all_employees();

    /* ---- Build display month label ---- */
    $month_label = date('F Y', strtotime($month . '-01'));

    ob_start();
    ?>

    <div class="ecco-logbook-wrap">

        <div class="ecco-logbook-header">
            <h2>📋 Logbook Dashboard — <?php echo esc_html($month_label); ?></h2>

            <form method="get" class="ecco-month-form">
                <label>
                    <strong>Select month:</strong>
                    <input type="month"
                           name="month"
                           value="<?php echo esc_attr($month); ?>"
                           onchange="this.form.submit()">
                </label>
            </form>
        </div>

        <?php echo $reminder_message; ?>

        <?php if (empty($employees)): ?>
            <p style="color:#888;">No employees found.</p>
        <?php else: ?>

        <?php
        /* Pre-count for summary */
        $uploaded_count = 0;
        $missing_count  = 0;
        $row_data       = [];

        foreach ($employees as $emp) {
            $name    = $emp['name'];
            $key     = strtolower(trim($name));
            $web_url = null;
            $mod_dt  = null;

            if (isset($folders[$key])) {
                $sub = ecco_graph_get("drives/{$driveId}/root:/{$month}/{$name}:/children");
                if (!empty($sub['value'])) {
                    $file    = $sub['value'][0];
                    $web_url = $file['webUrl'] ?? null;
                    $mod_dt  = $file['lastModifiedDateTime'] ?? null;
                }
            }

            if ($web_url) {
                $uploaded_count++;
            } else {
                $missing_count++;
            }

            $row_data[] = ['name' => $name, 'url' => $web_url, 'modified' => $mod_dt];
        }
        ?>

        <!-- Summary bar -->
        <div class="ecco-logbook-summary">
            <span class="ecco-logbook-pill ecco-pill-uploaded">✅ <?php echo $uploaded_count; ?> uploaded</span>
            <span class="ecco-logbook-pill ecco-pill-missing">❌ <?php echo $missing_count; ?> missing</span>
            <span class="ecco-logbook-pill ecco-pill-total">👤 <?php echo count($row_data); ?> total</span>
        </div>

        <div class="ecco-logbook-table-wrap">
        <table class="ecco-logbook-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Status</th>
                    <th>Last Uploaded</th>
                    <th>Logbook</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($row_data as $row):
                $uploaded = !empty($row['url']);
                $mod_label = '';
                if ($row['modified']) {
                    $dt        = new DateTime($row['modified']);
                    $mod_label = $dt->format('d M Y, H:i');
                }
            ?>
            <tr class="<?php echo $uploaded ? 'ecco-row-uploaded' : 'ecco-row-missing'; ?>">
                <td><?php echo esc_html($row['name']); ?></td>
                <td>
                    <?php if ($uploaded): ?>
                        <span class="ecco-status-badge ecco-status-uploaded">Uploaded</span>
                    <?php else: ?>
                        <span class="ecco-status-badge ecco-status-missing">Missing</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $mod_label ? esc_html($mod_label) : '<span style="color:#bbb;">—</span>'; ?></td>
                <td>
                    <?php if ($uploaded): ?>
                        <a href="<?php echo esc_url($row['url']); ?>" target="_blank" class="ecco-logbook-link">
                            📄 Open
                        </a>
                    <?php else: ?>
                        <span style="color:#bbb;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$uploaded): ?>
                    <form method="post" style="margin:0;" class="ecco-reminder-form">
                        <?php wp_nonce_field('ecco_logbook_reminder'); ?>
                        <input type="hidden" name="employee_name" value="<?php echo esc_attr($row['name']); ?>">
                        <input type="hidden" name="month"         value="<?php echo esc_attr($month); ?>">
                        <button type="submit" name="ecco_send_reminder" class="ecco-btn-remind">
                            📧 Send Reminder
                        </button>
                    </form>
                    <?php else: ?>
                        <span style="color:#bbb;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div><!-- /.ecco-logbook-table-wrap -->

        <?php endif; ?>

    </div><!-- /.ecco-logbook-wrap -->

    <style>
    .ecco-logbook-wrap { font-family: inherit; }

    .ecco-logbook-header {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 16px;
    }
    .ecco-logbook-header h2 { margin: 0; flex: 1; }

    .ecco-month-form label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }
    .ecco-month-form input[type="month"] {
        padding: 6px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
    }

    /* Notices */
    .ecco-logbook-notice {
        padding: 10px 16px;
        border-radius: 4px;
        margin-bottom: 16px;
        font-size: 14px;
    }
    .ecco-notice-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
    .ecco-notice-error   { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }

    /* Summary pills */
    .ecco-logbook-summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .ecco-logbook-pill {
        display: inline-block;
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }
    .ecco-pill-uploaded { background: #e8f5e9; color: #2e7d32; }
    .ecco-pill-missing  { background: #ffebee; color: #c62828; }
    .ecco-pill-total    { background: #e3f2fd; color: #1565c0; }

    /* Table */
    .ecco-logbook-table-wrap { overflow-x: auto; }
    .ecco-logbook-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        overflow: hidden;
    }
    .ecco-logbook-table th {
        background: #f5f7fa;
        padding: 10px 14px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #666;
        border-bottom: 1px solid #e0e0e0;
        white-space: nowrap;
    }
    .ecco-logbook-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
    }
    .ecco-logbook-table tr:last-child td { border-bottom: none; }
    .ecco-row-missing td { background: #fffafa; }
    .ecco-row-uploaded:hover td, .ecco-row-missing:hover td { background: #fafafa; }

    /* Status badges */
    .ecco-status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }
    .ecco-status-uploaded { background: #e8f5e9; color: #2e7d32; }
    .ecco-status-missing  { background: #ffebee; color: #c62828; }

    /* Logbook link */
    .ecco-logbook-link {
        color: #1a73e8;
        text-decoration: none;
        font-weight: 600;
    }
    .ecco-logbook-link:hover { text-decoration: underline; }

    /* Remind button */
    .ecco-btn-remind {
        background: #fff3e0;
        color: #e65100;
        border: 1px solid #ffcc80;
        border-radius: 4px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
    }
    .ecco-btn-remind:hover { background: #ffe0b2; }
    </style>

    <?php
    return ob_get_clean();
}


/* =====================================================
   HELPER: MANAGER / ADMIN PERMISSION CHECK
===================================================== */

function ecco_user_is_manager() {

    if (!is_user_logged_in()) return false;

    /* Administrators always have access */
    if (current_user_can('manage_options')) return true;

    $user = wp_get_current_user();
    if (!$user || empty($user->roles)) return false;

    /* Allow the custom 'manager' role if it exists */
    return in_array('manager', $user->roles, true);
}


/* =====================================================
   HELPER: GET ALL EMPLOYEES
   Returns every WP user as a name-keyed array.
   Excludes nobody — managers/admins appear in the list
   too so their logbooks are also tracked.
===================================================== */

function ecco_get_all_employees() {

    $users = get_users([
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'fields'  => ['ID', 'display_name'],
    ]);

    $list = [];
    foreach ($users as $u) {
        $list[] = ['name' => $u->display_name];
    }

    return $list;
}
