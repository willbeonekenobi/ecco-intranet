<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   SHORTCODE: [ecco_training]
   - Employees: view their own certifications, upload certs
   - HR / Admin: view all employees + full management
   ========================================================= */

add_shortcode('ecco_training', 'ecco_training_shortcode');

function ecco_training_shortcode($atts) {

    if (!is_user_logged_in()) {
        wp_redirect(home_url('/'));
        exit;
    }

    global $wpdb;
    $table    = $wpdb->prefix . 'ecco_training_certifications';
    $is_hr    = ecco_current_user_is_hr();
    $user_id  = get_current_user_id();
    $today    = new DateTime(current_time('Y-m-d'));
    $nonce    = wp_create_nonce('ecco_training_nonce');

    /* ----------- Load records ----------- */

    if ($is_hr) {
        $filter_user = intval($_GET['training_user'] ?? 0);
        if ($filter_user) {
            $records = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY employee_name ASC, date_expiry ASC",
                $filter_user
            ));
        } else {
            $records = $wpdb->get_results(
                "SELECT * FROM $table ORDER BY employee_name ASC, date_expiry ASC"
            );
        }
        $users = get_users(['orderby' => 'display_name']);
    } else {
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY date_expiry ASC",
            $user_id
        ));
    }

    ob_start();
    ?>
    <div class="ecco-training-wrap">

        <?php if ($is_hr): ?>
        <!-- =========== HR HEADER + ADD FORM =========== -->
        <div class="ecco-training-hr-header">
            <h2>Training Certifications <span class="ecco-badge-hr">HR View</span></h2>

            <!-- Filter -->
            <form method="get" class="ecco-training-filter">
                <label>Filter by employee:
                    <select name="training_user" onchange="this.form.submit()">
                        <option value="">— All employees —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo esc_attr($u->ID); ?>"
                                <?php selected($filter_user ?? 0, $u->ID); ?>>
                                <?php echo esc_html($u->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>

        </div>

        <?php else: ?>
        <!-- =========== EMPLOYEE HEADER =========== -->
        <h2>My Training Certifications</h2>
        <?php endif; ?>

        <!-- =========== MAIN TABLE =========== -->
        <?php if (empty($records)): ?>
            <div class="ecco-training-empty">
                <p><?php echo $is_hr ? 'No certification records found.' : 'You have no certification records on file yet. Contact HR to have your courses added.'; ?></p>
            </div>
        <?php else: ?>

        <div class="ecco-training-table-wrap">
            <table class="ecco-training-table" id="ecco-training-frontend-table">
                <thead>
                <tr>
                    <?php if ($is_hr): ?><th>Employee</th><?php endif; ?>
                    <th>Course</th>
                    <th>Date Completed</th>
                    <th>Date of Expiry</th>
                    <th>Status</th>
                    <th>Certificate</th>
                    <th><?php echo $is_hr ? 'Renewal Reminder' : 'Upload Certificate'; ?></th>
                    <?php if ($is_hr): ?><th>Actions</th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r):

                    /* Expiry status */
                    $status_label = '<span class="ecco-status-badge ecco-status-none">—</span>';
                    $row_class    = '';

                    if ($r->date_expiry) {
                        $exp   = new DateTime($r->date_expiry);
                        $diff  = (int)$today->diff($exp)->days * ($exp >= $today ? 1 : -1);

                        if ($diff < 0) {
                            $status_label = '<span class="ecco-status-badge ecco-status-expired">Expired</span>';
                            $row_class    = 'ecco-tr-expired';
                        } elseif ($diff <= 30) {
                            $status_label = '<span class="ecco-status-badge ecco-status-expiring">Expires in ' . $diff . 'd</span>';
                            $row_class    = 'ecco-tr-expiring';
                        } else {
                            $status_label = '<span class="ecco-status-badge ecco-status-valid">Valid</span>';
                        }
                    }

                    $last_reminder_txt = $r->reminder_sent_at
                        ? '<div class="ecco-reminder-note">Last: ' . date('d M Y', strtotime($r->reminder_sent_at)) . '</div>'
                        : '';
                ?>
                <tr class="<?php echo esc_attr($row_class); ?>" id="ecco-tr-<?php echo $r->id; ?>">

                    <?php if ($is_hr): ?>
                    <td>
                        <div class="ecco-employee-cell">
                            <strong><?php echo esc_html($r->employee_name); ?></strong>
                            <span><?php echo esc_html($r->employee_email); ?></span>
                        </div>
                    </td>
                    <?php endif; ?>

                    <!-- Course (HR editable) -->
                    <td>
                        <?php if ($is_hr): ?>
                            <span class="ecco-inline-edit"
                                  data-id="<?php echo $r->id; ?>"
                                  data-field="course_name"
                                  data-type="text"
                                  title="Click to edit">
                                <?php echo esc_html($r->course_name); ?>
                            </span>
                        <?php else: ?>
                            <?php echo esc_html($r->course_name); ?>
                        <?php endif; ?>
                    </td>

                    <!-- Date Completed (HR editable) -->
                    <td>
                        <?php if ($is_hr): ?>
                            <span class="ecco-inline-edit"
                                  data-id="<?php echo $r->id; ?>"
                                  data-field="date_completed"
                                  data-type="date"
                                  data-raw="<?php echo esc_attr($r->date_completed ?? ''); ?>"
                                  title="Click to edit">
                                <?php echo $r->date_completed
                                    ? esc_html(date('d M Y', strtotime($r->date_completed)))
                                    : '<em>Not set</em>'; ?>
                            </span>
                        <?php else: ?>
                            <?php echo $r->date_completed
                                ? esc_html(date('d M Y', strtotime($r->date_completed)))
                                : '<em>—</em>'; ?>
                        <?php endif; ?>
                    </td>

                    <!-- Date of Expiry (HR-ONLY editable) -->
                    <td>
                        <?php if ($is_hr): ?>
                            <span class="ecco-inline-edit ecco-hr-only"
                                  data-id="<?php echo $r->id; ?>"
                                  data-field="date_expiry"
                                  data-type="date"
                                  data-raw="<?php echo esc_attr($r->date_expiry ?? ''); ?>"
                                  title="HR: Click to edit expiry date">
                                <?php echo $r->date_expiry
                                    ? esc_html(date('d M Y', strtotime($r->date_expiry)))
                                    : '<em>Not set</em>'; ?>
                            </span>
                        <?php else: ?>
                            <strong><?php echo $r->date_expiry
                                ? esc_html(date('d M Y', strtotime($r->date_expiry)))
                                : '<em>—</em>'; ?></strong>
                        <?php endif; ?>
                    </td>

                    <td><?php echo $status_label; ?></td>

                    <!-- Certificate link -->
                    <td class="ecco-cert-cell" id="ecco-cert-<?php echo $r->id; ?>">
                        <?php if ($r->certificate_url): ?>
                            <a href="<?php echo esc_url($r->certificate_url); ?>"
                               target="_blank"
                               class="ecco-cert-link">
                               📄 View
                            </a>
                        <?php else: ?>
                            <span class="ecco-no-cert">None</span>
                        <?php endif; ?>
                    </td>

                    <!-- Reminder (HR) / Upload (Employee) -->
                    <td>
                        <?php if ($is_hr): ?>
                            <button class="ecco-btn ecco-btn-sm ecco-send-reminder-btn"
                                    data-id="<?php echo $r->id; ?>"
                                    data-name="<?php echo esc_attr($r->employee_name); ?>">
                                📧 Remind
                            </button>
                            <?php echo $last_reminder_txt; ?>
                        <?php else: ?>
                            <!-- Employee uploads their own cert -->
                            <label class="ecco-upload-label">
                                <input type="file"
                                       class="ecco-cert-upload"
                                       data-id="<?php echo $r->id; ?>"
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       style="display:none;">
                                <span class="ecco-btn ecco-btn-sm ecco-btn-upload">📤 Upload</span>
                            </label>
                            <div class="ecco-upload-status" id="ecco-upload-status-<?php echo $r->id; ?>"></div>
                        <?php endif; ?>
                    </td>

                    <!-- HR actions -->
                    <?php if ($is_hr): ?>
                    <td>
                        <button class="ecco-btn ecco-btn-sm ecco-btn-danger ecco-delete-btn"
                                data-id="<?php echo $r->id; ?>"
                                data-name="<?php echo esc_attr($r->employee_name . ' / ' . $r->course_name); ?>">
                            🗑
                        </button>
                    </td>
                    <?php endif; ?>

                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div><!-- /.ecco-training-table-wrap -->

        <?php if (!$is_hr): ?>
        <p class="ecco-training-note">
            💡 Only HR can set your expiry dates. You can upload your certificate directly from this page.
        </p>
        <?php else: ?>
        <p class="ecco-training-note">
            💡 Click any <strong>Course</strong>, <strong>Date Completed</strong>, or <strong>Date of Expiry</strong> cell to edit it inline.
        </p>
        <?php endif; ?>

        <?php endif; // end records check ?>
    </div><!-- /.ecco-training-wrap -->

    <!-- ====== STYLES ====== -->
    <style>
    .ecco-training-wrap { font-family: inherit; }

    /* Header */
    .ecco-training-hr-header {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .ecco-training-hr-header h2 { margin: 0; flex: 1; }
    .ecco-badge-hr {
        background: #1565c0;
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 20px;
        vertical-align: middle;
        margin-left: 8px;
    }

    /* Filter */
    .ecco-training-filter { display: inline-flex; align-items: center; gap: 8px; }
    .ecco-training-filter select { padding: 6px 10px; border-radius: 4px; border: 1px solid #ccc; }

    /* Buttons */
    .ecco-btn {
        display: inline-block;
        padding: 7px 14px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: opacity .15s;
        line-height: 1.4;
        text-decoration: none;
    }
    .ecco-btn:hover { opacity: .85; }
    .ecco-btn-primary  { background: #1565c0; color: #fff; }
    .ecco-btn-ghost    { background: transparent; color: #555; border: 1px solid #ccc; }
    .ecco-btn-sm       { padding: 4px 10px; font-size: 12px; }
    .ecco-btn-upload   { background: #e8f0fe; color: #1a73e8; }
    .ecco-btn-danger   { background: #fce4ec; color: #c62828; }

    /* Table */
    .ecco-training-table-wrap { overflow-x: auto; }
    .ecco-training-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        overflow: hidden;
    }
    .ecco-training-table th {
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
    .ecco-training-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
    }
    .ecco-training-table tr:last-child td { border-bottom: none; }
    .ecco-training-table tr:hover td { background: #fafafa; }

    /* Row states */
    .ecco-tr-expired  td { background: #fff5f5 !important; }
    .ecco-tr-expiring td { background: #fffaf0 !important; }

    /* Employee cell */
    .ecco-employee-cell { display: flex; flex-direction: column; }
    .ecco-employee-cell span { font-size: 12px; color: #888; }

    /* Status badges */
    .ecco-status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .ecco-status-valid    { background: #e8f5e9; color: #2e7d32; }
    .ecco-status-expiring { background: #fff3e0; color: #e65100; }
    .ecco-status-expired  { background: #ffebee; color: #c62828; }
    .ecco-status-none     { background: #f5f5f5; color: #aaa; }

    /* Inline edit */
    .ecco-inline-edit {
        cursor: pointer;
        border-bottom: 1px dashed #bbb;
        padding-bottom: 1px;
        display: inline-block;
        min-width: 60px;
    }
    .ecco-inline-edit:hover { border-bottom-color: #1565c0; color: #1565c0; }
    .ecco-inline-edit input { font-size: 13px; padding: 3px 6px; border: 1px solid #bbb; border-radius: 3px; }
    .ecco-hr-only { border-bottom-style: solid; border-bottom-color: #1565c0; }

    /* Cert link */
    .ecco-cert-link { color: #1a73e8; text-decoration: none; font-weight: 600; }
    .ecco-cert-link:hover { text-decoration: underline; }
    .ecco-no-cert { color: #bbb; font-size: 13px; }

    /* Upload label */
    .ecco-upload-label { cursor: pointer; }
    .ecco-upload-status { font-size: 12px; margin-top: 4px; color: #666; }

    /* Reminder note */
    .ecco-reminder-note { font-size: 12px; color: #888; margin-top: 4px; }

    /* Empty state */
    .ecco-training-empty {
        background: #f9f9f9;
        border: 1px dashed #ddd;
        border-radius: 6px;
        padding: 32px;
        text-align: center;
        color: #888;
    }

    /* Note */
    .ecco-training-note { margin-top: 12px; font-size: 13px; color: #888; }

    /* Modal */
    .ecco-training-modal {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.45);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ecco-training-modal-inner {
        background: #fff;
        border-radius: 8px;
        padding: 28px 32px;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    }
    .ecco-training-modal-inner h3 { margin-top: 0; margin-bottom: 20px; }
    .ecco-training-modal-inner label {
        display: block;
        margin-bottom: 14px;
        font-size: 13px;
        font-weight: 600;
        color: #333;
    }
    .ecco-training-modal-inner input,
    .ecco-training-modal-inner select {
        display: block;
        width: 100%;
        margin-top: 5px;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }
    .ecco-training-row { display: flex; gap: 16px; }
    .ecco-training-row label { flex: 1; }
    .ecco-training-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    #ecco-training-modal-msg {
        margin-bottom: 12px;
        font-size: 13px;
    }
    </style>

    <!-- ====== SCRIPTS ====== -->
    <script>
    (function($) {

        var nonce    = <?php echo json_encode($nonce); ?>;
        var isHR     = <?php echo $is_hr ? 'true' : 'false'; ?>;
        var ajaxurl  = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;

        /* ================================================
           INLINE EDITING (HR)
           ================================================ */
        $(document).on('click', '.ecco-inline-edit', function() {
            var $span = $(this);
            if ($span.find('input').length) return;

            var id    = $span.data('id');
            var field = $span.data('field');
            var type  = $span.data('type') || 'text';
            var raw   = $span.data('raw') || '';

            var $input = $('<input type="' + type + '">')
                .val(raw)
                .css({ width: type === 'date' ? '140px' : '180px', fontSize: '13px' });

            $span.empty().append($input);
            $input.focus();

            function doSave() {
                var val = $input.val();
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
                        $span.data('raw', val).html(display || '<em>Not set</em>');
                        if (field === 'date_expiry') {
                            setTimeout(() => location.reload(), 400);
                        }
                    } else {
                        alert('Save failed: ' + res.data);
                        $span.html(raw || '<em>Not set</em>');
                    }
                });
            }

            $input.on('blur', doSave);
            $input.on('keydown', function(e) {
                if (e.key === 'Enter') { $(this).blur(); }
                if (e.key === 'Escape') { $span.html(raw || '<em>Not set</em>'); }
            });
        });

        /* ================================================
           SEND REMINDER (HR)
           ================================================ */
        $(document).on('click', '.ecco-send-reminder-btn', function() {
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
                    $btn.closest('td').find('.ecco-reminder-note').remove();
                    $btn.after('<div class="ecco-reminder-note">Last: just now</div>');
                    setTimeout(() => {
                        $btn.prop('disabled', false).text('📧 Remind');
                    }, 3000);
                } else {
                    alert('Error: ' + res.data);
                    $btn.prop('disabled', false).text('📧 Remind');
                }
            });
        });

        /* ================================================
           CERTIFICATE UPLOAD (Employee + HR)
           ================================================ */
        $(document).on('change', '.ecco-cert-upload', function() {
            var $input   = $(this);
            var id       = $input.data('id');
            var file     = this.files[0];
            var $status  = $('#ecco-upload-status-' + id);
            var $certCell = $('#ecco-cert-' + id);

            if (!file) return;

            $status.html('<em>Uploading…</em>');

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
                        $status.html('<span style="color:green;">✅ Saved to SharePoint</span>');
                        $certCell.html(
                            '<a href="' + res.data.url + '" target="_blank" class="ecco-cert-link">📄 View</a>'
                        );
                        $input.val(''); // reset so the same file can be re-uploaded if needed
                        setTimeout(() => $status.html(''), 4000);
                    } else {
                        $status.html('<span style="color:red;">❌ ' + res.data + '</span>');
                    }
                },
                error: function() {
                    $status.html('<span style="color:red;">Upload failed.</span>');
                }
            });
        });

        /* ================================================
           DELETE RECORD (HR)
           ================================================ */
        $(document).on('click', '.ecco-delete-btn', function() {
            var $btn = $(this);
            var id   = $btn.data('id');
            var name = $btn.data('name');

            if (!confirm('Delete the certification record for:\n' + name + '\n\nThis cannot be undone.')) return;

            $btn.prop('disabled', true);

            $.post(ajaxurl, {
                action:    'ecco_training_delete_record',
                nonce:     nonce,
                record_id: id
            }, function(res) {
                if (res.success) {
                    $('#ecco-tr-' + id).fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert('Error: ' + res.data);
                    $btn.prop('disabled', false);
                }
            });
        });

    })(jQuery);
    </script>
    <?php

    return ob_get_clean();
}
