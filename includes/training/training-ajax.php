<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   SHAREPOINT UPLOAD HELPER (site drive, not /me/drive)
   Uploads a file into the TrainingCertificates library under
   {EmployeeName}/{CourseName}/{filename}
   Returns array['web_url','sp_path'] on success, WP_Error on failure.
   ========================================================= */

function ecco_training_upload_to_sharepoint($tmp_path, $original_name, $employee_name, $course_name) {

    /* Resolve the TrainingCertificates drive ID.
       Try cached map first; auto-retry with a forced refresh if missing. */
    $drive_map = ecco_get_drive_map();

    if (empty($drive_map['training_certificates'])) {
        $drive_map = ecco_get_drive_map(true); // force refresh
    }

    if (empty($drive_map['training_certificates'])) {

        $raw      = get_option('ecco_drive_raw_names', []);
        $raw_list = $raw ? implode(', ', $raw) : 'none returned';
        $manual   = get_option('ecco_training_drive_id');

        if ($manual) {
            $drive_map['training_certificates'] = $manual;
        } else {
            return new WP_Error(
                'no_drive',
                'Could not find the TrainingCertificates SharePoint library. ' .
                'Libraries found: ' . $raw_list . '. ' .
                'Check the exact library name or set the Drive ID manually under ECCO Intranet settings.'
            );
        }
    }

    $drive_id = $drive_map['training_certificates'];

    /* Build clean folder path: EmployeeName/CourseName/timestamp_filename */
    $folder_employee = sanitize_title($employee_name);
    $folder_course   = sanitize_title($course_name);
    $safe_filename   = date('Ymd-His') . '_' . sanitize_file_name($original_name);
    $sp_path         = "{$folder_employee}/{$folder_course}/{$safe_filename}";

    /* Read file bytes */
    $contents = file_get_contents($tmp_path);
    if ($contents === false) {
        return new WP_Error('read_fail', 'Could not read the uploaded file.');
    }

    /* Detect MIME type */
    $finfo     = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime_type = $finfo ? finfo_file($finfo, $tmp_path) : 'application/octet-stream';
    if ($finfo) finfo_close($finfo);

    /* PUT to SharePoint — Graph creates intermediate folders automatically */
    $result = ecco_graph_put(
        "/drives/{$drive_id}/root:/{$sp_path}:/content",
        $contents,
        $mime_type
    );

    if (empty($result['webUrl'])) {
        error_log('ECCO Training SP upload failed: ' . print_r($result, true));
        return new WP_Error('upload_fail', 'SharePoint upload failed. Check error logs for details.');
    }

    return [
        'web_url' => esc_url_raw($result['webUrl']),
        'sp_path' => $sp_path,
    ];
}


/* =========================================================
   AJAX: CERTIFICATE UPLOAD (Employee + HR)
   Called from both the frontend shortcode AND the WP admin page.
   ========================================================= */

add_action('wp_ajax_ecco_training_upload_cert', 'ecco_ajax_training_upload_cert');

function ecco_ajax_training_upload_cert() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in.');
    }

    check_ajax_referer('ecco_training_nonce', 'nonce');

    $id = intval($_POST['record_id'] ?? 0);
    if (!$id) wp_send_json_error('Invalid record ID.');

    global $wpdb;
    $table  = $wpdb->prefix . 'ecco_training_certifications';
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

    if (!$record) wp_send_json_error('Record not found.');

    /* Permission: HR uploads for anyone; employees only their own record */
    if (!ecco_current_user_is_hr() && (int)$record->user_id !== get_current_user_id()) {
        wp_send_json_error('Permission denied.');
    }

    /* Validate file present */
    if (empty($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES['certificate']['error'] ?? -1;
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload size limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was received.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary folder missing on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by a server extension.',
        ];
        wp_send_json_error($msgs[$code] ?? 'Upload error (code ' . $code . ').');
    }

    $file = $_FILES['certificate'];

    /* Validate extension */
    $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed_ext, true)) {
        wp_send_json_error('Only PDF, JPG, and PNG files are allowed.');
    }

    /* Validate real MIME type */
    $finfo      = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $real_mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png'];
    if ($real_mime && !in_array($real_mime, $allowed_mime, true)) {
        wp_send_json_error('Invalid file type detected. Only PDF, JPG, and PNG are accepted.');
    }

    /* Upload to SharePoint */
    $result = ecco_training_upload_to_sharepoint(
        $file['tmp_name'],
        $file['name'],
        $record->employee_name,
        $record->course_name
    );

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    /* Persist URL + SharePoint path */
    $wpdb->update(
        $table,
        [
            'certificate_url'             => $result['web_url'],
            'certificate_sharepoint_path' => $result['sp_path'],
            'updated_at'                  => current_time('mysql'),
        ],
        ['id' => $id]
    );

    wp_send_json_success([
        'url'     => $result['web_url'],
        'sp_path' => $result['sp_path'],
    ]);
}


/* =========================================================
   AJAX: SEND RENEWAL REMINDER EMAIL
   ========================================================= */

add_action('wp_ajax_ecco_training_send_reminder', 'ecco_ajax_training_send_reminder');

function ecco_ajax_training_send_reminder() {

    if (!ecco_current_user_is_hr()) {
        wp_send_json_error('Permission denied.');
    }

    check_ajax_referer('ecco_training_nonce', 'nonce');

    $id = intval($_POST['record_id'] ?? 0);
    if (!$id) wp_send_json_error('Invalid record ID.');

    global $wpdb;
    $table  = $wpdb->prefix . 'ecco_training_certifications';
    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

    if (!$record) wp_send_json_error('Record not found.');

    $to     = sanitize_email($record->employee_email);
    $name   = esc_html($record->employee_name);
    $course = esc_html($record->course_name);
    $expiry = $record->date_expiry ? date('d M Y', strtotime($record->date_expiry)) : 'Not set';

    $header_color = '#1565c0';
    $status_text  = 'Please ensure your certification is kept up to date.';

    if ($record->date_expiry) {
        $today    = new DateTime(current_time('Y-m-d'));
        $exp_date = new DateTime($record->date_expiry);
        $diff     = (int)$today->diff($exp_date)->days * ($exp_date >= $today ? 1 : -1);

        if ($diff < 0) {
            $header_color = '#c62828';
            $status_text  = 'Your certification has <strong>expired</strong>. Please renew it as soon as possible.';
        } elseif ($diff <= 30) {
            $header_color = '#e65100';
            $status_text  = "Your certification expires in <strong>{$diff} day(s)</strong>. Please arrange renewal urgently.";
        } else {
            $header_color = '#2e7d32';
            $status_text  = "Your certification expires in <strong>{$diff} day(s)</strong> on {$expiry}. Please plan your renewal.";
        }
    }

    $site_name  = get_bloginfo('name');
    $subject    = "Training Renewal Reminder: {$course}";
    $upload_url = site_url('/training/');

    $message = '<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.12);">
  <div style="background:' . $header_color . ';padding:20px 28px;">
    <h2 style="color:#fff;margin:0;font-size:20px;">Training Certification Reminder</h2>
  </div>
  <div style="padding:24px 28px;">
    <p style="margin:0 0 16px;">Hi <strong>' . $name . '</strong>,</p>
    <p style="margin:0 0 20px;">' . $status_text . '</p>
    <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:20px;">
      <tr style="background:#f9f9f9;">
        <td style="padding:8px 12px;border:1px solid #e0e0e0;font-weight:bold;width:40%;">Course</td>
        <td style="padding:8px 12px;border:1px solid #e0e0e0;">' . $course . '</td>
      </tr>
      <tr>
        <td style="padding:8px 12px;border:1px solid #e0e0e0;font-weight:bold;">Expiry Date</td>
        <td style="padding:8px 12px;border:1px solid #e0e0e0;">' . $expiry . '</td>
      </tr>
    </table>
    <p style="margin:0 0 20px;">
      Once renewed, please upload your updated certificate directly to the
      <a href="' . esc_url($upload_url) . '" style="color:#1565c0;">Training Portal</a>
      so HR can update your records.
    </p>
    <p style="margin:0;color:#888;font-size:13px;">
      This is an automated reminder from the ' . esc_html($site_name) . ' Intranet training system.
    </p>
  </div>
</div>
</body>
</html>';

    $sent = wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);

    if ($sent) {
        $wpdb->update($table, ['reminder_sent_at' => current_time('mysql')], ['id' => $id]);
        wp_send_json_success('Reminder sent to ' . $to);
    } else {
        wp_send_json_error('Failed to send email. Check your mail configuration.');
    }
}


/* =========================================================
   AJAX: HR INLINE SAVE (course name, dates)
   ========================================================= */

add_action('wp_ajax_ecco_training_save_record', 'ecco_ajax_training_save_record');

function ecco_ajax_training_save_record() {

    if (!ecco_current_user_is_hr()) {
        wp_send_json_error('Permission denied.');
    }

    check_ajax_referer('ecco_training_nonce', 'nonce');

    $id    = intval($_POST['record_id'] ?? 0);
    $field = sanitize_key($_POST['field'] ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');

    $allowed = ['date_expiry', 'date_completed', 'course_name'];
    if (!$id || !in_array($field, $allowed, true)) {
        wp_send_json_error('Invalid request.');
    }

    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'ecco_training_certifications',
        [$field => $value ?: null, 'updated_at' => current_time('mysql')],
        ['id'   => $id]
    );

    wp_send_json_success('Saved.');
}


/* =========================================================
   AJAX: ADD NEW RECORD (HR)
   ========================================================= */

add_action('wp_ajax_ecco_training_add_record', 'ecco_ajax_training_add_record');

function ecco_ajax_training_add_record() {

    if (!ecco_current_user_is_hr()) {
        wp_send_json_error('Permission denied.');
    }

    check_ajax_referer('ecco_training_nonce', 'nonce');

    $user_id        = intval($_POST['user_id'] ?? 0);
    $course_name    = sanitize_text_field($_POST['course_name'] ?? '');
    $date_completed = sanitize_text_field($_POST['date_completed'] ?? '');
    $date_expiry    = sanitize_text_field($_POST['date_expiry'] ?? '');

    if (!$user_id || !$course_name) {
        wp_send_json_error('Employee and course name are required.');
    }

    $user = get_userdata($user_id);
    if (!$user) wp_send_json_error('User not found.');

    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'ecco_training_certifications',
        [
            'user_id'        => $user_id,
            'employee_name'  => $user->display_name,
            'employee_email' => $user->user_email,
            'course_name'    => $course_name,
            'date_completed' => $date_completed ?: null,
            'date_expiry'    => $date_expiry    ?: null,
            'created_by'     => get_current_user_id(),
            'created_at'     => current_time('mysql'),
            'updated_at'     => current_time('mysql'),
        ]
    );

    if ($wpdb->last_error) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }

    wp_send_json_success(['id' => $wpdb->insert_id]);
}


/* =========================================================
   AJAX: DELETE RECORD (HR)
   Note: file is intentionally kept in SharePoint as audit trail.
   ========================================================= */

add_action('wp_ajax_ecco_training_delete_record', 'ecco_ajax_training_delete_record');

function ecco_ajax_training_delete_record() {

    if (!ecco_current_user_is_hr()) {
        wp_send_json_error('Permission denied.');
    }

    check_ajax_referer('ecco_training_nonce', 'nonce');

    $id = intval($_POST['record_id'] ?? 0);
    if (!$id) wp_send_json_error('Invalid record ID.');

    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'ecco_training_certifications', ['id' => $id]);

    wp_send_json_success('Record deleted.');
}
