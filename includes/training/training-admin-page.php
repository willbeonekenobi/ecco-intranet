<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   ADMIN MENU: TRAINING CERTIFICATIONS
   ========================================================= */

add_action('admin_menu', function () {

    $hr_cap    = 'read';
    $admin_cap = 'manage_options';

    // Training — submenu of the Ecco Intranet hub
    add_submenu_page(
        'ecco-intranet-hub',
        'Training Certifications',
        'Training',
        $hr_cap,
        'ecco-training',
        'ecco_training_admin_page'
    );

    add_submenu_page(
        'ecco-intranet-hub',
        'Training: HR Users',
        'Training: HR Users',
        $admin_cap,
        'ecco-training-hr-users',
        'ecco_training_hr_users_page'
    );

    add_submenu_page(
        'ecco-intranet-hub',
        'Training: SP Diagnostics',
        'Training: SP Diagnostics',
        $admin_cap,
        'ecco-training-diagnostics',
        'ecco_training_diagnostics_page'
    );
});


/* =========================================================
   ADMIN PAGE: ALL CERTIFICATIONS
   ========================================================= */

function ecco_training_admin_page() {

    if (!ecco_current_user_is_hr()) {
        wp_die('You do not have permission to access this page.');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ecco_training_certifications';

    /* --- Handle add --- */
    if (!empty($_POST['ecco_add_training']) && check_admin_referer('ecco_add_training')) {

        $user_id        = intval($_POST['user_id'] ?? 0);
        $course_name    = sanitize_text_field($_POST['course_name'] ?? '');
        $date_completed = sanitize_text_field($_POST['date_completed'] ?? '');
        $date_expiry    = sanitize_text_field($_POST['date_expiry'] ?? '');

        if ($user_id && $course_name) {

            $user = get_userdata($user_id);

            $wpdb->insert($table, [
                'user_id'        => $user_id,
                'employee_name'  => $user ? $user->display_name : '',
                'employee_email' => $user ? $user->user_email   : '',
                'course_name'    => $course_name,
                'date_completed' => $date_completed ?: null,
                'date_expiry'    => $date_expiry    ?: null,
                'created_by'     => get_current_user_id(),
                'created_at'     => current_time('mysql'),
                'updated_at'     => current_time('mysql'),
            ]);

            echo '<div class="updated"><p>Certification record added.</p></div>';

        } else {
            echo '<div class="error"><p>Employee and Course Name are required.</p></div>';
        }
    }

    /* --- Handle delete --- */
    if (!empty($_GET['delete']) && !empty($_GET['_wpnonce'])) {

        $id = intval($_GET['delete']);

        if (wp_verify_nonce($_GET['_wpnonce'], 'ecco_delete_training_' . $id)) {

            // Note: file is kept in SharePoint as an audit trail
            $wpdb->delete($table, ['id' => $id]);
            echo '<div class="updated"><p>Record deleted.</p></div>';

            wp_redirect(admin_url('admin.php?page=ecco-training'));
            exit;
        }
    }

    /* --- Handle inline edit (expiry / completed dates via POST) --- */
    if (!empty($_POST['ecco_edit_training']) && check_admin_referer('ecco_edit_training')) {

        $id             = intval($_POST['record_id'] ?? 0);
        $date_expiry    = sanitize_text_field($_POST['date_expiry'] ?? '');
        $date_completed = sanitize_text_field($_POST['date_completed'] ?? '');
        $course_name    = sanitize_text_field($_POST['course_name'] ?? '');

        if ($id) {
            $wpdb->update($table, [
                'date_expiry'    => $date_expiry    ?: null,
                'date_completed' => $date_completed ?: null,
                'course_name'    => $course_name,
                'updated_at'     => current_time('mysql'),
            ], ['id' => $id]);
            echo '<div class="updated"><p>Record updated.</p></div>';
        }
    }

    /* --- Filters --- */
    $filter_user = intval($_GET['filter_user'] ?? 0);

    $query = "SELECT * FROM $table";
    if ($filter_user) {
        $query .= $wpdb->prepare(" WHERE user_id = %d", $filter_user);
    }
    $query .= " ORDER BY employee_name ASC, date_expiry ASC";

    $records = $wpdb->get_results($query);
    $users   = get_users(['orderby' => 'display_name']);
    $today   = new DateTime(current_time('Y-m-d'));

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Training Certifications</h1>
        <hr class="wp-header-end">

        <!-- ======= ADD RECORD FORM ======= -->
        <h2>Add New Certification</h2>
        <form method="post" style="background:#fff;padding:16px 20px;border:1px solid #ccd0d4;border-radius:4px;max-width:700px;margin-bottom:24px;">
            <?php wp_nonce_field('ecco_add_training'); ?>
            <input type="hidden" name="ecco_add_training" value="1">

            <table class="form-table" style="margin:0;">
                <tr>
                    <th style="width:160px;">Employee <span style="color:red;">*</span></th>
                    <td>
                        <select name="user_id" required style="min-width:240px;">
                            <option value="">— Select Employee —</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo esc_attr($u->ID); ?>">
                                    <?php echo esc_html($u->display_name . ' (' . $u->user_email . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Course Name <span style="color:red;">*</span></th>
                    <td><input type="text" name="course_name" required style="min-width:280px;" placeholder="e.g. Electrical Safety"></td>
                </tr>
                <tr>
                    <th>Date Completed</th>
                    <td><input type="date" name="date_completed"></td>
                </tr>
                <tr>
                    <th>Date of Expiry</th>
                    <td><input type="date" name="date_expiry"></td>
                </tr>
            </table>

            <p style="margin-top:12px;">
                <button class="button button-primary">Add Record</button>
            </p>
        </form>

        <!-- ======= FILTER ======= -->
        <form method="get" style="margin-bottom:16px;">
            <input type="hidden" name="page" value="ecco-training">
            <label><strong>Filter by Employee:</strong>
                <select name="filter_user" onchange="this.form.submit()" style="margin-left:8px;">
                    <option value="">— All Employees —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($filter_user, $u->ID); ?>>
                            <?php echo esc_html($u->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <!-- ======= RECORDS TABLE ======= -->
        <table class="widefat striped" id="ecco-training-table">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Course</th>
                <th>Date Completed</th>
                <th>Date of Expiry</th>
                <th>Status</th>
                <th>Certificate</th>
                <th>Reminder</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="8" style="text-align:center;color:#888;">No certification records found.</td></tr>
            <?php endif; ?>

            <?php foreach ($records as $r):

                /* --- Expiry status --- */
                $status_label = '<em style="color:#888;">No expiry set</em>';
                $row_class    = '';

                if ($r->date_expiry) {
                    $exp      = new DateTime($r->date_expiry);
                    $diff     = (int)$today->diff($exp)->days * ($exp >= $today ? 1 : -1);

                    if ($diff < 0) {
                        $status_label = '<span style="color:#c62828;font-weight:bold;">⛔ Expired</span>';
                        $row_class    = 'ecco-row-expired';
                    } elseif ($diff <= 30) {
                        $status_label = '<span style="color:#e65100;font-weight:bold;">⚠️ Expires in ' . $diff . ' day(s)</span>';
                        $row_class    = 'ecco-row-expiring';
                    } else {
                        $status_label = '<span style="color:#2e7d32;">✅ Valid (' . $diff . ' days left)</span>';
                    }
                }

                $delete_url = wp_nonce_url(
                    admin_url('admin.php?page=ecco-training&delete=' . $r->id),
                    'ecco_delete_training_' . $r->id
                );

                $last_reminder = $r->reminder_sent_at
                    ? '<br><small style="color:#888;">Last sent: ' . date('d M Y', strtotime($r->reminder_sent_at)) . '</small>'
                    : '';
            ?>
            <tr class="<?php echo esc_attr($row_class); ?>" id="ecco-training-row-<?php echo $r->id; ?>">
                <td>
                    <strong><?php echo esc_html($r->employee_name); ?></strong><br>
                    <small style="color:#888;"><?php echo esc_html($r->employee_email); ?></small>
                </td>
                <td>
                    <!-- Inline editable course name -->
                    <span class="ecco-editable"
                          data-id="<?php echo $r->id; ?>"
                          data-field="course_name"
                          title="Click to edit">
                        <?php echo esc_html($r->course_name); ?>
                    </span>
                </td>
                <td>
                    <span class="ecco-editable"
                          data-id="<?php echo $r->id; ?>"
                          data-field="date_completed"
                          data-type="date"
                          title="Click to edit">
                        <?php echo $r->date_completed
                            ? esc_html(date('d M Y', strtotime($r->date_completed)))
                            : '<em style="color:#aaa;">Not set</em>'; ?>
                    </span>
                </td>
                <td>
                    <!-- HR-only editable expiry -->
                    <span class="ecco-editable ecco-hr-field"
                          data-id="<?php echo $r->id; ?>"
                          data-field="date_expiry"
                          data-type="date"
                          title="Click to edit (HR only)">
                        <?php echo $r->date_expiry
                            ? esc_html(date('d M Y', strtotime($r->date_expiry)))
                            : '<em style="color:#aaa;">Not set</em>'; ?>
                    </span>
                </td>
                <td><?php echo $status_label; ?></td>
                <td id="ecco-cert-cell-<?php echo $r->id; ?>">
                    <?php if ($r->certificate_url): ?>
                        <a href="<?php echo esc_url($r->certificate_url); ?>"
                           target="_blank"
                           class="button button-small"
                           id="ecco-cert-link-<?php echo $r->id; ?>"
                           <?php if ($r->certificate_sharepoint_path): ?>
                           title="SharePoint: <?php echo esc_attr('TrainingCertificates/' . $r->certificate_sharepoint_path); ?>"
                           <?php endif; ?>>
                            📄 View
                        </a>
                        <?php if ($r->certificate_sharepoint_path): ?>
                        <br><small style="color:#888;font-size:11px;" title="<?php echo esc_attr('TrainingCertificates/' . $r->certificate_sharepoint_path); ?>">
                            📁 <?php echo esc_html(dirname($r->certificate_sharepoint_path)); ?>
                        </small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span id="ecco-cert-link-<?php echo $r->id; ?>"
                              style="color:#aaa;font-style:italic;font-size:12px;">
                            None uploaded
                        </span>
                    <?php endif; ?>
                    <label style="display:inline-block;margin-left:6px;cursor:pointer;vertical-align:top;margin-top:2px;"
                           title="Upload certificate to SharePoint">
                        <input type="file"
                               class="ecco-admin-cert-upload"
                               data-id="<?php echo $r->id; ?>"
                               accept=".pdf,.jpg,.jpeg,.png"
                               style="display:none;">
                        <span class="button button-small ecco-upload-trigger">📤 Upload</span>
                    </label>
                    <span class="ecco-admin-upload-status"
                          id="ecco-admin-upload-status-<?php echo $r->id; ?>"
                          style="display:block;font-size:11px;margin-top:3px;"></span>
                </td>
                <td>
                    <button class="button button-small ecco-send-reminder"
                            data-id="<?php echo $r->id; ?>"
                            data-name="<?php echo esc_attr($r->employee_name); ?>">
                        📧 Send Reminder
                    </button>
                    <?php echo $last_reminder; ?>
                </td>
                <td>
                    <a href="<?php echo esc_url($delete_url); ?>"
                       class="button button-small"
                       style="color:#c62828;"
                       onclick="return confirm('Delete this certification record for <?php echo esc_js($r->employee_name); ?>?')">
                        🗑 Delete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:16px;color:#888;font-size:13px;">
            💡 <strong>Tip:</strong> Click on any Course, Date Completed, or Date of Expiry cell to edit it inline.
            Expiry dates can only be edited by HR and admin users.
        </p>
    </div>

    <style>
    .ecco-editable {
        cursor: pointer;
        border-bottom: 1px dashed #aaa;
        padding-bottom: 1px;
        min-width: 80px;
        display: inline-block;
    }
    .ecco-editable:hover { border-bottom-color: #2271b1; color: #2271b1; }
    .ecco-row-expired  { background: #fff3f3 !important; }
    .ecco-row-expiring { background: #fff8ee !important; }
    #ecco-training-table th { white-space: nowrap; }
    .ecco-upload-trigger { cursor: pointer; vertical-align: middle; }
    .ecco-upload-trigger.disabled { opacity: .5; pointer-events: none; }
    </style>

    <script>
    jQuery(function($) {

        var nonce = '<?php echo wp_create_nonce('ecco_training_nonce'); ?>';

        /* ---- Inline editing ---- */
        $(document).on('click', '.ecco-editable', function() {
            var $span   = $(this);
            if ($span.find('input').length) return; // already editing

            var id    = $span.data('id');
            var field = $span.data('field');
            var type  = $span.data('type') || 'text';
            var cur   = $span.data('raw') || '';

            // Try to read the raw ISO value from a data-raw attr, fall back to text
            if (!cur && type === 'date') {
                // convert display text back to ISO for date input — not reliable, so just leave blank
                cur = '';
            }

            var $input = $('<input>')
                .attr('type', type)
                .val(cur)
                .css({width: type === 'date' ? '140px' : '180px', fontSize: '13px'});

            $span.html($input);
            $input.focus();

            $input.on('blur change', function() {
                var val = $(this).val();
                $.post(ajaxurl, {
                    action:    'ecco_training_save_record',
                    nonce:     nonce,
                    record_id: id,
                    field:     field,
                    value:     val
                }, function(res) {
                    if (res.success) {
                        var display = val;
                        if (type === 'date' && val) {
                            var d = new Date(val + 'T00:00:00');
                            display = d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
                        }
                        $span.data('raw', val).html(display || '<em style="color:#aaa;">Not set</em>');
                        // Reload to refresh status column
                        if (field === 'date_expiry') setTimeout(() => location.reload(), 400);
                    } else {
                        alert('Save failed: ' + res.data);
                        location.reload();
                    }
                });
            });
        });

        /* ---- Send reminder ---- */
        $(document).on('click', '.ecco-send-reminder', function() {
            var $btn = $(this);
            var id   = $btn.data('id');
            var name = $btn.data('name');

            if (!confirm('Send a renewal reminder email to ' + name + '?')) return;

            $btn.prop('disabled', true).text('Sending…');

            $.post(ajaxurl, {
                action:    'ecco_training_send_reminder',
                nonce:     nonce,
                record_id: id
            }, function(res) {
                if (res.success) {
                    $btn.text('✅ Sent');
                    $btn.closest('td').find('small').remove();
                    $btn.after('<br><small style="color:#888;">Last sent: just now</small>');
                } else {
                    alert('Error: ' + res.data);
                    $btn.prop('disabled', false).text('📧 Send Reminder');
                }
            });
        });

        /* ---- Certificate upload ---- */
        $(document).on('change', '.ecco-admin-cert-upload', function() {
            var $input   = $(this);
            var id       = $input.data('id');
            var file     = this.files[0];
            var $status  = $('#ecco-admin-upload-status-' + id);
            var $linkWrap = $('#ecco-cert-link-' + id);

            if (!file) return;

            $status.html('<em style="color:#666;">Uploading…</em>');
            $input.closest('label').find('.ecco-upload-trigger').addClass('disabled');

            var fd = new FormData();
            fd.append('action',      'ecco_training_upload_cert');
            fd.append('nonce',       nonce);
            fd.append('record_id',   id);
            fd.append('certificate', file);

            $.ajax({
                url:         ajaxurl,
                type:        'POST',
                data:        fd,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        $status.html('<span style="color:#2e7d32;">✅ Uploaded to SharePoint</span>');
                        var spNote = res.data.sp_path
                            ? '<br><small style="color:#888;font-size:11px;" title="TrainingCertificates/' + res.data.sp_path + '">📁 ' + res.data.sp_path.split('/').slice(0, -1).join('/') + '</small>'
                            : '';
                        $linkWrap.replaceWith(
                            '<a href="' + res.data.url + '" target="_blank" class="button button-small" id="ecco-cert-link-' + id + '">📄 View</a>' + spNote
                        );
                        $input.closest('label').find('.ecco-upload-trigger').removeClass('disabled');
                        $input.val('');
                        setTimeout(() => $status.html(''), 4000);
                    } else {
                        $status.html('<span style="color:#c62828;">Error: ' + res.data + '</span>');
                        $input.closest('label').find('.ecco-upload-trigger').removeClass('disabled');
                    }
                },
                error: function() {
                    $status.html('<span style="color:#c62828;">Upload failed.</span>');
                    $input.closest('label').find('.ecco-upload-trigger').removeClass('disabled');
                }
            });
        });

    });
    </script>
    <?php
}


/* =========================================================
   ADMIN PAGE: HR USERS
   ========================================================= */

function ecco_training_hr_users_page() {

    if (!current_user_can('manage_options')) return;

    if (isset($_POST['ecco_save_hr_users'])) {

        check_admin_referer('ecco_save_hr_users');

        $all_users = get_users(['fields' => 'ID']);

        foreach ($all_users as $uid) {
            $is_hr = isset($_POST['hr_user'][$uid]) ? '1' : '0';
            update_user_meta((int)$uid, 'ecco_is_hr', $is_hr);
        }

        echo '<div class="updated"><p>HR user settings saved.</p></div>';
    }

    $users = get_users(['orderby' => 'display_name']);
    ?>
    <div class="wrap">
        <h1>HR Users — Training Module</h1>
        <p>
            Users flagged as <strong>HR</strong> can add, edit, and delete certification records,
            set expiry dates, and send renewal reminder emails to employees.
            WordPress administrators always have full access.
        </p>

        <form method="post">
            <?php wp_nonce_field('ecco_save_hr_users'); ?>

            <table class="widefat striped" style="max-width:620px;">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th style="text-align:center;">HR Access</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $is_hr = get_user_meta($user->ID, 'ecco_is_hr', true) === '1';
                    ?>
                    <tr>
                        <td><?php echo esc_html($user->display_name); ?></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td style="text-align:center;">
                            <input type="checkbox"
                                   name="hr_user[<?php echo esc_attr($user->ID); ?>]"
                                   value="1"
                                   <?php checked($is_hr); ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:16px;">
                <button class="button button-primary" name="ecco_save_hr_users">Save Changes</button>
            </p>
        </form>
    </div>
    <?php
}


/* =========================================================
   ADMIN PAGE: SHAREPOINT DIAGNOSTICS
   ========================================================= */

function ecco_training_diagnostics_page() {

    if (!current_user_can('manage_options')) return;

    $refreshed = false;

    /* Force-refresh on button click */
    if (!empty($_POST['ecco_refresh_drives']) && check_admin_referer('ecco_refresh_drives')) {
        delete_option('ecco_drive_map');
        delete_option('ecco_drive_raw_names');
        ecco_get_drive_map(true);
        $refreshed = true;
    }

    $drive_map = get_option('ecco_drive_map', []);
    $raw_names = get_option('ecco_drive_raw_names', []);
    $wanted    = ecco_library_map();
    ?>
    <div class="wrap">
        <h1>SharePoint Library Diagnostics</h1>

        <?php if ($refreshed): ?>
            <div class="updated"><p>Drive list refreshed from SharePoint.</p></div>
        <?php endif; ?>

        <form method="post" style="margin-bottom:20px;">
            <?php wp_nonce_field('ecco_refresh_drives'); ?>
            <button class="button button-primary" name="ecco_refresh_drives">
                🔄 Refresh Drive List from SharePoint
            </button>
            <span style="margin-left:10px;color:#888;font-size:13px;">
                Forces a fresh call to SharePoint regardless of cache.
            </span>
        </form>

        <h2>All Discovered SharePoint Libraries</h2>
        <?php if (empty($raw_names)): ?>
            <p style="color:#c62828;">
                No libraries found yet. Click <strong>Refresh Drive List</strong> above,
                or make sure at least one user is logged in via Microsoft SSO first.
            </p>
        <?php else: ?>
            <table class="widefat striped" style="max-width:800px;">
                <thead>
                    <tr><th>Library Name (exact)</th><th>Drive ID</th></tr>
                </thead>
                <tbody>
                <?php foreach ($raw_names as $entry):
                    /* entry format: "Library Name [drive_id]" */
                    preg_match('/^(.+) \[([^\]]+)\]$/', $entry, $m);
                    $lib_name = $m[1] ?? $entry;
                    $drive_id = $m[2] ?? '—';
                ?>
                    <tr>
                        <td><code><?php echo esc_html($lib_name); ?></code></td>
                        <td>
                            <code style="font-size:11px;"><?php echo esc_html($drive_id); ?></code>
                            <button type="button"
                                    class="button button-small"
                                    style="margin-left:8px;"
                                    onclick="navigator.clipboard.writeText('<?php echo esc_js($drive_id); ?>').then(()=>this.textContent='✅ Copied')">
                                Copy
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2 style="margin-top:28px;">Library Key Mapping Status</h2>
        <table class="widefat striped" style="max-width:800px;">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Expected Library Name</th>
                    <th>Status</th>
                    <th>Mapped Drive ID</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($wanted as $key => $name): ?>
                <tr>
                    <td><code><?php echo esc_html($key); ?></code></td>
                    <td><?php echo esc_html($name); ?></td>
                    <td>
                        <?php if (!empty($drive_map[$key])): ?>
                            <span style="color:#2e7d32;font-weight:bold;">✅ Matched</span>
                        <?php else: ?>
                            <span style="color:#c62828;font-weight:bold;">❌ Not found</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code style="font-size:11px;">
                            <?php echo !empty($drive_map[$key]) ? esc_html($drive_map[$key]) : '—'; ?>
                        </code>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:20px;color:#666;font-size:13px;">
            If <strong>training_certificates</strong> shows ❌ Not found, the SharePoint library name
            does not match <code>TrainingCertificates</code> (case-insensitive partial match).
            Copy the correct Drive ID from the table above and paste it into
            <a href="<?php echo esc_url(admin_url('admin.php?page=ecco-intranet')); ?>">
                ECCO Intranet Settings → Training Drive ID
            </a>.
        </p>
    </div>
    <?php
}
