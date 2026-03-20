<?php
/**
 * ECCO Courses – Frontend Shortcode
 * Usage: [ecco_courses]
 *
 * Views:
 *   /courses/              → course listing
 *   /courses/?course=slug  → single course (content + quiz)
 *   POST quiz submit       → results page
 */

if (!defined('ABSPATH')) exit;

/* =========================================================
   SHORTCODE REGISTRATION
   ========================================================= */

add_shortcode('ecco_courses', 'ecco_courses_shortcode');

function ecco_courses_shortcode() {

    if (!is_user_logged_in()) {
        wp_redirect(home_url('/'));
        exit;
    }

    // Enqueue front-end assets
    ecco_courses_enqueue_frontend();

    ob_start();

    $course_slug = isset($_GET['course']) ? sanitize_title($_GET['course']) : '';

    if ($course_slug) {
        ecco_courses_render_single($course_slug);
    } else {
        ecco_courses_render_listing();
    }

    return ob_get_clean();
}

/* =========================================================
   ENQUEUE ASSETS
   ========================================================= */

function ecco_courses_enqueue_frontend() {

    wp_enqueue_style(
        'ecco-courses-frontend',
        ECCO_URL . 'includes/courses/courses-frontend.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'ecco-courses-frontend',
        ECCO_URL . 'includes/courses/courses-frontend.js',
        ['jquery'],
        '1.0.0',
        true
    );

    wp_localize_script('ecco-courses-frontend', 'ECCO_Courses', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ecco_training_nonce'),
    ]);
}

/* =========================================================
   VIEW: COURSE LISTING
   ========================================================= */

function ecco_courses_render_listing() {

    $courses  = ecco_get_published_courses();
    $user_id  = get_current_user_id();
    $page_url = get_permalink();

    ?>
    <div class="ecco-courses-wrap">

        <div class="ecco-courses-header">
            <h2 class="ecco-courses-title">📚 Available Courses</h2>
            <p class="ecco-courses-subtitle">Select a course below to begin learning. A certificate is awarded upon passing.</p>
        </div>

        <?php if (empty($courses)) : ?>
            <div class="ecco-courses-empty">
                <p>No courses are available at the moment. Please check back later.</p>
            </div>
        <?php else : ?>

            <div class="ecco-courses-grid">
                <?php foreach ($courses as $course) :

                    $best_pass  = ecco_get_best_pass($course->id, $user_id);
                    $latest     = ecco_get_latest_attempt($course->id, $user_id);
                    $has_passed = !empty($best_pass);

                    if ($has_passed) {
                        $status_class = 'status-passed';
                        $status_label = '✅ Passed';
                    } elseif ($latest) {
                        $status_class = 'status-attempted';
                        $status_label = '🔄 In Progress';
                    } else {
                        $status_class = 'status-new';
                        $status_label = '🆕 New';
                    }

                    $course_url = add_query_arg('course', $course->slug, $page_url);
                ?>
                    <div class="ecco-course-card <?php echo esc_attr($status_class); ?>">

                        <div class="ecco-course-card-body">
                            <div class="ecco-course-status-badge <?php echo esc_attr($status_class); ?>">
                                <?php echo $status_label; ?>
                            </div>
                            <h3 class="ecco-course-card-title"><?php echo esc_html($course->title); ?></h3>
                            <?php if ($course->description) : ?>
                                <p class="ecco-course-card-desc"><?php echo esc_html($course->description); ?></p>
                            <?php endif; ?>

                            <div class="ecco-course-meta">
                                <span>🎯 Pass mark: <strong><?php echo (int) $course->pass_mark; ?>%</strong></span>
                                <?php if ($course->validity_months > 0) : ?>
                                    <span>🗓 Valid for: <strong><?php echo (int) $course->validity_months; ?> month<?php echo $course->validity_months > 1 ? 's' : ''; ?></strong></span>
                                <?php endif; ?>
                                <?php if ($has_passed && $best_pass->score !== null) : ?>
                                    <span>🏆 Best score: <strong><?php echo round((float) $best_pass->score, 1); ?>%</strong></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="ecco-course-card-footer">
                            <a href="<?php echo esc_url($course_url); ?>" class="ecco-course-btn <?php echo $has_passed ? 'btn-secondary' : 'btn-primary'; ?>">
                                <?php echo $has_passed ? '📖 Review Course' : '▶ Start Course'; ?>
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
    <?php
}

/* =========================================================
   VIEW: SINGLE COURSE (Content + Quiz)
   ========================================================= */

function ecco_courses_render_single($slug) {

    $course = ecco_get_course_by_slug($slug);

    if (!$course || $course->status !== 'published') {
        echo '<div class="ecco-courses-notice"><p>Course not found. <a href="' . esc_url(remove_query_arg('course')) . '">← Back to courses</a></p></div>';
        return;
    }

    $user_id   = get_current_user_id();
    $questions = ecco_get_course_questions($course->id);
    $best_pass = ecco_get_best_pass($course->id, $user_id);
    $back_url  = remove_query_arg('course');

    ?>
    <div class="ecco-courses-wrap">

        <a href="<?php echo esc_url($back_url); ?>" class="ecco-back-link">← Back to courses</a>

        <div class="ecco-single-course">

            <!-- Course header -->
            <div class="ecco-course-content-header">
                <h2><?php echo esc_html($course->title); ?></h2>
                <?php if ($course->description) : ?>
                    <p class="ecco-course-lead"><?php echo esc_html($course->description); ?></p>
                <?php endif; ?>
                <div class="ecco-course-meta-bar">
                    <span>🎯 Pass mark: <strong><?php echo (int) $course->pass_mark; ?>%</strong></span>
                    <?php if ($course->validity_months > 0) : ?>
                        <span>🗓 Certificate valid: <strong><?php echo (int) $course->validity_months; ?> months</strong></span>
                    <?php endif; ?>
                    <span>❓ Questions: <strong><?php echo count($questions); ?></strong></span>
                </div>
            </div>

            <?php if ($best_pass) : ?>
                <div class="ecco-alert ecco-alert-success">
                    ✅ You have already passed this course with a score of <strong><?php echo round((float) $best_pass->score, 1); ?>%</strong>.
                    You may retake it below or <a href="<?php echo esc_url($back_url); ?>">return to the course list</a>.
                </div>
            <?php endif; ?>

            <!-- Course content -->
            <?php if ($course->content) : ?>
                <div class="ecco-course-body">
                    <?php echo apply_filters('the_content', $course->content); ?>
                </div>
            <?php endif; ?>

            <!-- Quiz -->
            <?php if (!empty($questions)) : ?>
                <div class="ecco-quiz-section">
                    <h3 class="ecco-quiz-heading">📝 Knowledge Check</h3>
                    <p class="ecco-quiz-intro">Answer all questions below and submit to receive your result. You need <strong><?php echo (int) $course->pass_mark; ?>%</strong> or more to pass.</p>

                    <form id="ecco-quiz-form" class="ecco-quiz-form" novalidate>
                        <?php wp_nonce_field('ecco_training_nonce', 'ecco_quiz_nonce'); ?>
                        <input type="hidden" name="course_id" value="<?php echo (int) $course->id; ?>">

                        <?php foreach ($questions as $i => $q) :
                            $q_num    = $i + 1;
                            $options  = !empty($q->options) ? json_decode($q->options, true) : [];
                        ?>
                            <div class="ecco-question-block" data-qid="<?php echo (int) $q->id; ?>">
                                <div class="ecco-question-header">
                                    <span class="ecco-q-num">Q<?php echo $q_num; ?></span>
                                    <p class="ecco-q-text"><?php echo esc_html($q->question_text); ?></p>
                                </div>

                                <?php if ($q->type === 'true_false') : ?>
                                    <div class="ecco-answers ecco-tf-answers">
                                        <label class="ecco-radio-label">
                                            <input type="radio" name="answer[<?php echo (int) $q->id; ?>]" value="true" required>
                                            <span>True</span>
                                        </label>
                                        <label class="ecco-radio-label">
                                            <input type="radio" name="answer[<?php echo (int) $q->id; ?>]" value="false">
                                            <span>False</span>
                                        </label>
                                    </div>

                                <?php elseif ($q->type === 'multiple_choice') : ?>
                                    <div class="ecco-answers ecco-mc-answers">
                                        <?php foreach ($options as $idx => $opt) : ?>
                                            <label class="ecco-radio-label">
                                                <input type="radio" name="answer[<?php echo (int) $q->id; ?>]" value="<?php echo $idx; ?>" required>
                                                <span><?php echo esc_html($opt); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                <?php elseif ($q->type === 'fill_blank') : ?>
                                    <div class="ecco-answers ecco-fib-answers">
                                        <input
                                            type="text"
                                            name="answer[<?php echo (int) $q->id; ?>]"
                                            class="ecco-fib-input"
                                            placeholder="Type your answer…"
                                            required
                                            autocomplete="off"
                                        >
                                    </div>
                                <?php endif; ?>

                            </div><!-- .ecco-question-block -->
                        <?php endforeach; ?>

                        <div class="ecco-quiz-actions">
                            <button type="submit" class="ecco-btn-submit" id="ecco-quiz-submit">
                                Submit Answers
                            </button>
                            <span class="ecco-quiz-spinner" id="ecco-quiz-spinner" style="display:none;">⏳ Marking…</span>
                        </div>

                    </form><!-- #ecco-quiz-form -->

                </div><!-- .ecco-quiz-section -->

            <?php else : ?>
                <div class="ecco-alert ecco-alert-info">This course has no quiz questions yet.</div>
            <?php endif; ?>

            <!-- Results panel (populated via JS after submit) -->
            <div id="ecco-quiz-results" class="ecco-quiz-results" style="display:none;"></div>

        </div><!-- .ecco-single-course -->
    </div><!-- .ecco-courses-wrap -->

    <script>
    /* inline data for JS */
    window.EccoCourseData = {
        courseId : <?php echo (int) $course->id; ?>,
        passmark  : <?php echo (int) $course->pass_mark; ?>,
        backUrl   : <?php echo wp_json_encode($back_url); ?>
    };
    </script>
    <?php
}

/* =========================================================
   INLINE CSS  (written to <head> via wp_head so no extra
   HTTP request; keeps the module self-contained)
   ========================================================= */

add_action('wp_head', 'ecco_courses_inline_styles');

function ecco_courses_inline_styles() {

    // Only output when the shortcode page is being viewed
    global $post;
    if (!$post || !has_shortcode($post->post_content, 'ecco_courses')) return;

    ?>
    <style id="ecco-courses-style">

    /* ---- Wrapper ---- */
    .ecco-courses-wrap { max-width: 1100px; margin: 0 auto; padding: 20px 16px; font-family: inherit; }

    /* ---- Page header ---- */
    .ecco-courses-header { margin-bottom: 28px; border-bottom: 2px solid #e2e8f0; padding-bottom: 16px; }
    .ecco-courses-title  { font-size: 1.8rem; font-weight: 700; color: #1a202c; margin: 0 0 6px; }
    .ecco-courses-subtitle { color: #718096; margin: 0; font-size: 1rem; }

    /* ---- Empty state ---- */
    .ecco-courses-empty  { background:#f7fafc; border:1px dashed #cbd5e0; border-radius:8px; padding:40px; text-align:center; color:#718096; }

    /* ---- Course Grid ---- */
    .ecco-courses-grid   { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 22px; }

    /* ---- Course Card ---- */
    .ecco-course-card    { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow .2s, transform .2s; }
    .ecco-course-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.1); transform: translateY(-2px); }
    .ecco-course-card.status-passed   { border-top: 4px solid #38a169; }
    .ecco-course-card.status-attempted{ border-top: 4px solid #d69e2e; }
    .ecco-course-card.status-new      { border-top: 4px solid #4299e1; }

    .ecco-course-card-body   { padding: 20px 20px 0; flex: 1; }
    .ecco-course-card-footer { padding: 16px 20px 20px; }

    .ecco-course-status-badge { display: inline-block; font-size: .75rem; font-weight: 600; padding: 3px 10px; border-radius: 20px; margin-bottom: 10px; }
    .ecco-course-status-badge.status-passed    { background: #c6f6d5; color: #22543d; }
    .ecco-course-status-badge.status-attempted { background: #fefcbf; color: #744210; }
    .ecco-course-status-badge.status-new       { background: #bee3f8; color: #2a4365; }

    .ecco-course-card-title { font-size: 1.1rem; font-weight: 700; color: #1a202c; margin: 0 0 8px; }
    .ecco-course-card-desc  { font-size: .9rem; color: #4a5568; line-height: 1.5; margin: 0 0 12px; }
    .ecco-course-meta       { font-size: .82rem; color: #718096; display: flex; flex-wrap: wrap; gap: 10px; padding: 10px 0; border-top: 1px solid #f0f0f0; }

    /* ---- Buttons ---- */
    .ecco-course-btn   { display: inline-block; padding: 10px 20px; border-radius: 7px; font-size: .9rem; font-weight: 600; text-decoration: none; text-align: center; width: 100%; box-sizing: border-box; transition: opacity .15s; }
    .ecco-course-btn:hover { opacity: .85; text-decoration: none; }
    .btn-primary  { background: #2b6cb0; color: #fff; }
    .btn-secondary{ background: #e2e8f0; color: #2d3748; }

    /* ---- Back link ---- */
    .ecco-back-link { display: inline-flex; align-items: center; gap: 4px; color: #4299e1; text-decoration: none; font-weight: 600; font-size: .9rem; margin-bottom: 20px; }
    .ecco-back-link:hover { text-decoration: underline; }

    /* ---- Single course ---- */
    .ecco-single-course { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .ecco-course-content-header { padding: 28px 32px 22px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(135deg, #1a365d 0%, #2b6cb0 100%); color: #fff; }
    .ecco-course-content-header h2 { margin: 0 0 8px; font-size: 1.7rem; font-weight: 700; }
    .ecco-course-lead  { margin: 0 0 14px; opacity: .9; font-size: 1rem; }
    .ecco-course-meta-bar { display: flex; flex-wrap: wrap; gap: 16px; font-size: .85rem; opacity: .9; }

    /* ---- Alerts ---- */
    .ecco-alert { padding: 14px 20px; border-radius: 8px; margin: 20px 28px; font-size: .95rem; }
    .ecco-alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
    .ecco-alert-info    { background: #bee3f8; color: #2a4365; border: 1px solid #90cdf4; }
    .ecco-alert-error   { background: #fed7d7; color: #742a2a; border: 1px solid #fc8181; }

    /* ---- Course body content ---- */
    .ecco-course-body { padding: 28px 32px; font-size: 1rem; line-height: 1.75; color: #2d3748; border-bottom: 1px solid #e2e8f0; }
    .ecco-course-body img { max-width: 100%; height: auto; border-radius: 6px; margin: 12px 0; }
    .ecco-course-body h2,.ecco-course-body h3,.ecco-course-body h4 { color: #1a202c; margin-top: 1.6em; }

    /* ---- Quiz section ---- */
    .ecco-quiz-section  { padding: 28px 32px; }
    .ecco-quiz-heading  { font-size: 1.35rem; font-weight: 700; color: #1a202c; margin: 0 0 6px; }
    .ecco-quiz-intro    { color: #718096; font-size: .95rem; margin: 0 0 22px; }

    /* ---- Question block ---- */
    .ecco-question-block { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 18px; transition: background .2s; }
    .ecco-question-block.is-correct   { background: #f0fff4; border-color: #9ae6b4; }
    .ecco-question-block.is-incorrect { background: #fff5f5; border-color: #fc8181; }

    .ecco-question-header { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px; }
    .ecco-q-num  { background: #2b6cb0; color: #fff; border-radius: 50%; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; flex-shrink: 0; margin-top: 1px; }
    .ecco-q-text { margin: 0; font-size: 1rem; font-weight: 600; color: #2d3748; line-height: 1.5; }

    /* ---- Answer options ---- */
    .ecco-answers { display: flex; flex-direction: column; gap: 10px; }
    .ecco-tf-answers { flex-direction: row; gap: 16px; }
    .ecco-radio-label { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 7px; font-size: .95rem; transition: border-color .15s, background .15s; }
    .ecco-radio-label:hover { border-color: #4299e1; background: #ebf8ff; }
    .ecco-radio-label input[type="radio"] { margin: 0; accent-color: #2b6cb0; }
    .ecco-radio-label.selected { border-color: #2b6cb0; background: #ebf8ff; }
    .ecco-radio-label.correct  { border-color: #38a169 !important; background: #f0fff4 !important; color: #22543d; }
    .ecco-radio-label.wrong    { border-color: #e53e3e !important; background: #fff5f5 !important; color: #742a2a; }

    .ecco-fib-input { width: 100%; max-width: 400px; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 7px; font-size: .95rem; outline: none; transition: border-color .15s; }
    .ecco-fib-input:focus { border-color: #4299e1; box-shadow: 0 0 0 2px rgba(66,153,225,.2); }

    /* ---- Feedback ---- */
    .ecco-question-feedback { margin-top: 10px; font-size: .875rem; font-weight: 600; }
    .ecco-question-feedback.correct   { color: #22543d; }
    .ecco-question-feedback.incorrect { color: #742a2a; }
    .ecco-correct-answer { font-size: .85rem; color: #718096; margin-top: 4px; }

    /* ---- Submit / spinner ---- */
    .ecco-quiz-actions { display: flex; align-items: center; gap: 16px; margin-top: 24px; }
    .ecco-btn-submit   { background: #2b6cb0; color: #fff; border: none; padding: 12px 32px; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .15s; }
    .ecco-btn-submit:hover   { background: #2c5282; }
    .ecco-btn-submit:disabled{ opacity: .6; cursor: not-allowed; }
    .ecco-quiz-spinner { color: #718096; font-size: .95rem; }

    /* ---- Results panel ---- */
    .ecco-quiz-results { padding: 0 32px 32px; }
    .ecco-result-banner { padding: 22px 26px; border-radius: 12px; text-align: center; margin-bottom: 22px; }
    .ecco-result-banner.passed  { background: #c6f6d5; border: 1px solid #9ae6b4; color: #22543d; }
    .ecco-result-banner.failed  { background: #fed7d7; border: 1px solid #fc8181; color: #742a2a; }
    .ecco-result-banner h3      { font-size: 1.6rem; margin: 0 0 6px; }
    .ecco-result-banner p       { margin: 0; font-size: 1rem; opacity: .85; }
    .ecco-cert-link { display: inline-block; margin-top: 14px; padding: 10px 24px; background: #2b6cb0; color: #fff; border-radius: 7px; text-decoration: none; font-weight: 700; }
    .ecco-cert-link:hover { background: #2c5282; color: #fff; }
    .ecco-retake-btn { display: inline-block; margin-top: 14px; padding: 10px 24px; background: #e53e3e; color: #fff; border-radius: 7px; text-decoration: none; font-weight: 700; cursor: pointer; border: none; font-size: 1rem; }
    .ecco-retake-btn:hover { background: #c53030; }

    /* ---- Notice ---- */
    .ecco-courses-notice { padding: 20px 24px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; color: #856404; }

    /* ---- Responsive ---- */
    @media (max-width: 640px) {
        .ecco-courses-grid { grid-template-columns: 1fr; }
        .ecco-course-content-header, .ecco-course-body, .ecco-quiz-section, .ecco-quiz-results { padding-left: 18px; padding-right: 18px; }
        .ecco-tf-answers { flex-direction: column; }
    }

    </style>
    <?php
}

/* =========================================================
   INLINE JS (quiz submission + results rendering)
   ========================================================= */

add_action('wp_footer', 'ecco_courses_inline_js');

function ecco_courses_inline_js() {

    global $post;
    if (!$post || !has_shortcode($post->post_content, 'ecco_courses')) return;
    if (empty($_GET['course'])) return;

    ?>
    <script id="ecco-courses-js">
    (function($){
        'use strict';

        var cfg = window.EccoCourseData || {};

        /* ----- Highlight selected radio labels ----- */
        $(document).on('change', '.ecco-radio-label input[type="radio"]', function(){
            var $group = $('input[name="' + $(this).attr('name') + '"]').closest('.ecco-radio-label');
            $group.removeClass('selected');
            $(this).closest('.ecco-radio-label').addClass('selected');
        });

        /* ----- Quiz form submit ----- */
        $('#ecco-quiz-form').on('submit', function(e){
            e.preventDefault();

            // Basic validation: ensure all questions answered
            var allAnswered = true;
            $('.ecco-question-block').each(function(){
                var $block = $(this);
                var qid = $block.data('qid');
                var val = $('[name="answer[' + qid + ']"]', $block).filter(function(){ return $(this).val().trim() !== ''; });
                var radio = $('input[type="radio"][name="answer[' + qid + ']"]:checked', $block);
                var text  = $('input[type="text"][name="answer[' + qid + ']"]', $block);

                if (radio.length === 0 && text.length === 0) {
                    allAnswered = false;
                }
                if (text.length > 0 && text.val().trim() === '') {
                    allAnswered = false;
                    text.css('border-color','#e53e3e');
                }
            });

            if (!allAnswered) {
                alert('Please answer all questions before submitting.');
                return;
            }

            var $btn     = $('#ecco-quiz-submit');
            var $spinner = $('#ecco-quiz-spinner');
            $btn.prop('disabled', true);
            $spinner.show();

            // Build answers object
            var formData = $(this).serializeArray();
            var answers  = {};
            $.each(formData, function(i, f){
                var m = f.name.match(/^answer\[(\d+)\]$/);
                if (m) answers[m[1]] = f.value;
            });

            $.ajax({
                url  : ECCO_Courses.ajax,
                type : 'POST',
                data : {
                    action    : 'ecco_submit_quiz',
                    nonce     : ECCO_Courses.nonce,
                    course_id : cfg.courseId,
                    answers   : JSON.stringify(answers)
                },
                success: function(resp){
                    $btn.prop('disabled', false);
                    $spinner.hide();

                    if (resp.success) {
                        renderResults(resp.data);
                    } else {
                        alert('Error: ' + (resp.data || 'Could not submit quiz. Please try again.'));
                    }
                },
                error: function(){
                    $btn.prop('disabled', false);
                    $spinner.hide();
                    alert('A network error occurred. Please try again.');
                }
            });
        });

        /* ----- Render per-question feedback ----- */
        function renderResults(data){

            // Annotate each question block
            $.each(data.results, function(qid, r){
                var $block = $('.ecco-question-block[data-qid="' + qid + '"]');
                $block.addClass(r.correct ? 'is-correct' : 'is-incorrect');

                // Mark radio options
                $block.find('.ecco-radio-label').each(function(){
                    var v = $(this).find('input').val();
                    if (v === String(r.user_answer)) $(this).addClass(r.correct ? 'correct' : 'wrong');
                    if (!r.correct && v === String(r.correct_answer)) $(this).addClass('correct');
                });

                // Colour FIB input
                var $fib = $block.find('.ecco-fib-input');
                if ($fib.length) {
                    $fib.css('border-color', r.correct ? '#38a169' : '#e53e3e');
                }

                // Remove existing feedback, then append
                $block.find('.ecco-question-feedback, .ecco-correct-answer').remove();
                var fbHtml = '<p class="ecco-question-feedback ' + (r.correct ? 'correct' : 'incorrect') + '">'
                           + (r.correct ? '✅ Correct!' : '❌ Incorrect') + '</p>';
                if (!r.correct && r.correct_answer !== undefined && r.correct_answer !== null) {
                    fbHtml += '<p class="ecco-correct-answer">Correct answer: <strong>' + escHtml(String(r.correct_answer_display || r.correct_answer)) + '</strong></p>';
                }
                $block.append(fbHtml);
            });

            // Disable form
            $('#ecco-quiz-form input, #ecco-quiz-form textarea').prop('disabled', true);
            $('#ecco-quiz-submit').hide();

            // Results banner
            var passed   = data.passed;
            var score    = parseFloat(data.score).toFixed(1);
            var certHtml = '';

            if (passed && data.cert_url) {
                certHtml = '<a href="' + escHtml(data.cert_url) + '" target="_blank" class="ecco-cert-link">📄 Download Certificate</a> ';
            }

            var retakeHtml = '<button class="ecco-retake-btn" onclick="window.location.reload()">🔄 Retake Course</button>';

            var bannerHtml = '<div class="ecco-result-banner ' + (passed ? 'passed' : 'failed') + '">'
                + '<h3>' + (passed ? '🎉 Congratulations — You Passed!' : '😔 Not Quite — You Did Not Pass') + '</h3>'
                + '<p>Your score: <strong>' + score + '%</strong> &nbsp;|&nbsp; Pass mark: <strong>' + cfg.passmark + '%</strong></p>'
                + (passed ? '<p>Your certificate has been generated and saved.</p>' : '<p>Review the course material and try again.</p>')
                + certHtml + retakeHtml
                + '</div>';

            var $results = $('#ecco-quiz-results');
            $results.html(bannerHtml).show();

            // Scroll to results
            $('html, body').animate({ scrollTop: $results.offset().top - 80 }, 500);
        }

        function escHtml(str){
            return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

    })(jQuery);
    </script>
    <?php
}
