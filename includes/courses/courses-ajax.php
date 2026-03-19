<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   HELPER: verify nonce + admin cap
   ========================================================= */

function ecco_courses_admin_check(string $action = 'ecco_courses_admin_nonce'): bool {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied.');
        return false;
    }
    if (!check_ajax_referer($action, 'nonce', false)) {
        wp_send_json_error('Security check failed.');
        return false;
    }
    return true;
}


/* =========================================================
   AJAX: SAVE (ADD / UPDATE) QUESTION
   ========================================================= */

add_action('wp_ajax_ecco_save_course_question', 'ecco_ajax_save_course_question');

function ecco_ajax_save_course_question() {

    if (!ecco_courses_admin_check()) return;

    $course_id   = intval($_POST['course_id']   ?? 0);
    $question_id = intval($_POST['question_id'] ?? 0);
    $type        = sanitize_key($_POST['type']  ?? '');
    $text        = sanitize_textarea_field(wp_unslash($_POST['question_text'] ?? ''));
    $options_raw = sanitize_textarea_field(wp_unslash($_POST['options']       ?? ''));
    $correct     = sanitize_textarea_field(wp_unslash($_POST['correct_answer'] ?? ''));
    $points      = max(1, min(10, intval($_POST['points'] ?? 1)));

    $allowed_types = ['true_false', 'multiple_choice', 'fill_blank'];
    if (!$course_id || !$text || !in_array($type, $allowed_types, true)) {
        wp_send_json_error('Invalid input.');
    }

    /* Validate & sanitize options JSON */
    $options_json = null;
    if ($type === 'multiple_choice') {
        $opts = json_decode($options_raw, true);
        if (!is_array($opts) || count($opts) < 2) {
            wp_send_json_error('Multiple choice questions need at least 2 options.');
        }
        $opts = array_values(array_map('sanitize_text_field', $opts));
        $options_json = wp_json_encode($opts);
        $correct = (string) max(0, min(count($opts) - 1, intval($correct)));
    }

    global $wpdb;
    $t = $wpdb->prefix . 'ecco_course_questions';

    if ($question_id > 0) {
        /* Update existing */
        $wpdb->update($t, [
            'type'           => $type,
            'question_text'  => $text,
            'options'        => $options_json,
            'correct_answer' => $correct,
            'points'         => $points,
        ], ['id' => $question_id, 'course_id' => $course_id]);

        wp_send_json_success([
            'id'            => $question_id,
            'type'          => $type,
            'question_text' => $text,
            'options'       => $options_json,
            'correct_answer'=> $correct,
            'points'        => $points,
        ]);

    } else {
        /* Insert new — append at end */
        $max_order = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(question_order),0)+1 FROM $t WHERE course_id = %d",
            $course_id
        ));
        $wpdb->insert($t, [
            'course_id'      => $course_id,
            'question_order' => $max_order,
            'type'           => $type,
            'question_text'  => $text,
            'options'        => $options_json,
            'correct_answer' => $correct,
            'points'         => $points,
        ]);

        wp_send_json_success([
            'id'            => (int) $wpdb->insert_id,
            'type'          => $type,
            'question_text' => $text,
            'options'       => $options_json,
            'correct_answer'=> $correct,
            'points'        => $points,
        ]);
    }
}


/* =========================================================
   AJAX: DELETE QUESTION
   ========================================================= */

add_action('wp_ajax_ecco_delete_course_question', 'ecco_ajax_delete_course_question');

function ecco_ajax_delete_course_question() {

    if (!ecco_courses_admin_check()) return;

    $id = intval($_POST['question_id'] ?? 0);
    if (!$id) wp_send_json_error('Invalid ID.');

    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'ecco_course_questions', ['id' => $id]);
    wp_send_json_success('Deleted.');
}


/* =========================================================
   AJAX: REORDER QUESTIONS
   ========================================================= */

add_action('wp_ajax_ecco_reorder_course_questions', 'ecco_ajax_reorder_course_questions');

function ecco_ajax_reorder_course_questions() {

    if (!ecco_courses_admin_check()) return;

    $course_id = intval($_POST['course_id'] ?? 0);
    $order     = array_map('intval', (array) ($_POST['order'] ?? []));

    if (!$course_id || empty($order)) wp_send_json_error('Invalid input.');

    global $wpdb;
    $t = $wpdb->prefix . 'ecco_course_questions';

    foreach ($order as $pos => $qid) {
        $wpdb->update($t, ['question_order' => $pos], ['id' => $qid, 'course_id' => $course_id]);
    }

    wp_send_json_success('Reordered.');
}


/* =========================================================
   AJAX: SUBMIT QUIZ
   Called from the front-end shortcode.
   Scores the quiz, generates a certificate if passed,
   uploads it to SharePoint, and records everything in DB.
   ========================================================= */

add_action('wp_ajax_ecco_submit_quiz',        'ecco_ajax_submit_quiz');
add_action('wp_ajax_nopriv_ecco_submit_quiz', 'ecco_ajax_submit_quiz');

function ecco_ajax_submit_quiz() {

    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in to take this assessment.');
    }

    check_ajax_referer('ecco_training_nonce', 'nonce');

    $course_id = intval($_POST['course_id'] ?? 0);
    if (!$course_id) wp_send_json_error('Invalid course.');

    $course = ecco_get_course($course_id);
    if (!$course || $course->status !== 'published') {
        wp_send_json_error('Course not found.');
    }

    $questions = ecco_get_course_questions($course_id);
    if (empty($questions)) {
        wp_send_json_error('This course has no questions configured yet.');
    }

    /* ── Collect and sanitise answers ─────────────────────
       Submitted as: answers[{question_id}] = "value"
    ─────────────────────────────────────────────────────── */
    $raw_answers = json_decode(stripslashes($_POST['answers'] ?? '{}'), true) ?: [];
    $answers     = [];
    foreach ($raw_answers as $qid => $val) {
        $answers[intval($qid)] = sanitize_text_field((string) $val);
    }

    /* ── Score ───────────────────────────────────────────── */
    $total_points  = 0;
    $earned_points = 0;
    $results       = [];   // per-question feedback for the response

    foreach ($questions as $q) {
        $total_points += $q->points;
        $submitted     = $answers[$q->id] ?? '';
        $correct       = false;

        switch ($q->type) {

            case 'true_false':
                $correct = (strtolower(trim($submitted)) === strtolower(trim($q->correct_answer)));
                $results[$q->id] = [
                    'submitted'      => $submitted,
                    'correct_answer' => $q->correct_answer,
                    'correct'        => $correct,
                    'question_text'  => $q->question_text,
                    'type'           => $q->type,
                ];
                break;

            case 'multiple_choice':
                $opts = json_decode($q->options ?? '[]', true) ?: [];
                // submitted = selected index; correct_answer = correct index
                $correct = (trim($submitted) === trim($q->correct_answer));
                $correct_text = $opts[(int) $q->correct_answer] ?? '';
                $submitted_text = $opts[(int) $submitted] ?? '(no answer)';
                $results[$q->id] = [
                    'submitted'       => $submitted_text,
                    'correct_answer'  => $correct_text,
                    'correct'         => $correct,
                    'question_text'   => $q->question_text,
                    'type'            => $q->type,
                    'options'         => $opts,
                    'submitted_index' => $submitted,
                    'correct_index'   => $q->correct_answer,
                ];
                break;

            case 'fill_blank':
                $acceptable = array_map('trim', explode(',', strtolower($q->correct_answer)));
                $correct    = in_array(strtolower(trim($submitted)), $acceptable, true);
                $results[$q->id] = [
                    'submitted'      => $submitted,
                    'correct_answer' => explode(',', $q->correct_answer)[0],  // primary answer
                    'correct'        => $correct,
                    'question_text'  => $q->question_text,
                    'type'           => $q->type,
                ];
                break;
        }

        if ($correct) $earned_points += $q->points;
    }

    $score  = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0;
    $passed = $score >= $course->pass_mark;

    /* ── Record attempt ─────────────────────────────────── */
    global $wpdb;
    $t_attempts = $wpdb->prefix . 'ecco_course_attempts';

    $wpdb->insert($t_attempts, [
        'course_id'    => $course_id,
        'user_id'      => get_current_user_id(),
        'answers'      => wp_json_encode($answers),
        'score'        => $score,
        'passed'       => $passed ? 1 : 0,
        'completed_at' => current_time('mysql'),
        'cert_generated' => 0,
    ]);
    $attempt_id = (int) $wpdb->insert_id;

    /* ── Generate certificate if passed ─────────────────── */
    $cert_url     = null;
    $cert_sp_path = null;

    if ($passed) {

        $user          = get_userdata(get_current_user_id());
        $emp_name      = $user ? $user->display_name : 'Employee';
        $emp_email     = $user ? $user->user_email   : '';
        $date_str      = date('d F Y', current_time('timestamp'));
        $cert_id_str   = 'ECCO-' . date('Y') . '-' . str_pad($attempt_id, 5, '0', STR_PAD_LEFT);

        /* Expiry date */
        $valid_until = '';
        $expiry_date = null;
        if ($course->validity_months > 0) {
            $expiry_ts   = strtotime("+{$course->validity_months} months", current_time('timestamp'));
            $expiry_date = date('Y-m-d', $expiry_ts);
            $valid_until = date('d F Y', $expiry_ts);
        }

        /* Generate PDF */
        $pdf_path = ecco_generate_certificate_pdf([
            'employee_name'  => $emp_name,
            'course_name'    => $course->title,
            'date_completed' => $date_str,
            'score'          => $score,
            'valid_until'    => $valid_until,
            'site_name'      => get_bloginfo('name'),
            'cert_id'        => $cert_id_str,
        ]);

        if ($pdf_path && file_exists($pdf_path)) {

            /* Upload to SharePoint (uses existing training upload helper) */
            if (function_exists('ecco_training_upload_to_sharepoint')) {

                $filename = $cert_id_str . '_' . sanitize_title($emp_name) . '.pdf';
                $sp_result = ecco_training_upload_to_sharepoint(
                    $pdf_path,
                    $filename,
                    $emp_name,
                    $course->title
                );

                if (!is_wp_error($sp_result)) {
                    $cert_url     = $sp_result['web_url'];
                    $cert_sp_path = $sp_result['sp_path'];
                } else {
                    error_log('ECCO Courses: SharePoint upload failed: ' . $sp_result->get_error_message());
                    /* Fallback: serve directly via WordPress media */
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    $upload = wp_upload_bits($filename, null, file_get_contents($pdf_path));
                    if (!$upload['error']) {
                        $cert_url = $upload['url'];
                    }
                }

            } else {
                /* No SharePoint configured: save to WP uploads */
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $filename = $cert_id_str . '_' . sanitize_title($emp_name) . '.pdf';
                $upload   = wp_upload_bits($filename, null, file_get_contents($pdf_path));
                if (!$upload['error']) $cert_url = $upload['url'];
            }

            @unlink($pdf_path);  // clean up temp file
        }

        /* ── Upsert ecco_training_certifications record ──── */
        $t_certs = $wpdb->prefix . 'ecco_training_certifications';

        /* Check for existing record for this user + course */
        $existing_cert = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $t_certs WHERE user_id = %d AND course_name = %s",
            get_current_user_id(), $course->title
        ));

        $cert_data = [
            'user_id'                    => get_current_user_id(),
            'employee_name'              => $emp_name,
            'employee_email'             => $emp_email,
            'course_name'                => $course->title,
            'date_completed'             => current_time('Y-m-d'),
            'date_expiry'                => $expiry_date,
            'certificate_url'            => $cert_url,
            'certificate_sharepoint_path'=> $cert_sp_path,
            'updated_at'                 => current_time('mysql'),
        ];

        if ($existing_cert) {
            $wpdb->update($t_certs, $cert_data, ['id' => $existing_cert->id]);
        } else {
            $cert_data['created_by'] = get_current_user_id();
            $cert_data['created_at'] = current_time('mysql');
            $wpdb->insert($t_certs, $cert_data);
        }

        /* Mark attempt as cert generated */
        if ($cert_url) {
            $wpdb->update($t_attempts, ['cert_generated' => 1], ['id' => $attempt_id]);
        }
    }

    /* ── Build response ─────────────────────────────────── */
    wp_send_json_success([
        'score'       => $score,
        'pass_mark'   => (int) $course->pass_mark,
        'passed'      => $passed,
        'earned'      => $earned_points,
        'total'       => $total_points,
        'cert_url'    => $cert_url,
        'results'     => $results,
        'attempt_id'  => $attempt_id,
    ]);
}
