<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   ADMIN MENUS
   ========================================================= */

add_action('admin_menu', function () {

    add_submenu_page(
        'ecco-training',
        'Courses',
        'Courses',
        'manage_options',
        'ecco-courses',
        'ecco_courses_dispatch_page'
    );
});


/* =========================================================
   PAGE DISPATCHER  (list  |  new  |  edit)
   ========================================================= */

function ecco_courses_dispatch_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to manage courses.');
    }

    $action = sanitize_key($_GET['action'] ?? 'list');
    $id     = intval($_GET['id'] ?? 0);

    switch ($action) {
        case 'edit':
        case 'new':
            ecco_courses_edit_page($action === 'edit' ? $id : 0);
            break;
        default:
            ecco_courses_list_page();
    }
}


/* =========================================================
   COURSE LIST PAGE
   ========================================================= */

function ecco_courses_list_page() {
    global $wpdb;
    $t = $wpdb->prefix . 'ecco_courses';

    /* Handle delete */
    if (!empty($_GET['delete']) && !empty($_GET['_wpnonce'])) {
        $del_id = intval($_GET['delete']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'ecco_delete_course_' . $del_id)) {
            $wpdb->delete($wpdb->prefix . 'ecco_course_questions', ['course_id' => $del_id]);
            $wpdb->delete($wpdb->prefix . 'ecco_course_attempts',  ['course_id' => $del_id]);
            $wpdb->delete($t, ['id' => $del_id]);
            echo '<div class="updated notice is-dismissible"><p>Course deleted.</p></div>';
        }
    }

    $courses = $wpdb->get_results("SELECT * FROM $t ORDER BY title ASC");
    $qt      = $wpdb->prefix . 'ecco_course_questions';
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Courses</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ecco-courses&action=new')); ?>"
           class="page-title-action">+ Add New Course</a>
        <hr class="wp-header-end">

        <?php if (empty($courses)): ?>
            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:40px;text-align:center;color:#888;margin-top:24px;">
                <p style="font-size:16px;margin-bottom:12px;">No courses yet.</p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ecco-courses&action=new')); ?>"
                   class="button button-primary">Create your first course</a>
            </div>
        <?php else: ?>
        <table class="widefat striped" style="margin-top:20px;">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Questions</th>
                    <th>Pass Mark</th>
                    <th>Validity</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $c):
                $q_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $qt WHERE course_id = %d", $c->id
                ));
                $del_url = wp_nonce_url(
                    admin_url('admin.php?page=ecco-courses&delete=' . $c->id),
                    'ecco_delete_course_' . $c->id
                );
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($c->title); ?></strong>
                    <?php if ($c->description): ?>
                        <br><small style="color:#888;"><?php echo esc_html(wp_trim_words($c->description, 12)); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($c->status === 'published'): ?>
                        <span style="color:#2e7d32;font-weight:600;">● Published</span>
                    <?php else: ?>
                        <span style="color:#888;">○ Draft</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $q_count ?: '<em style="color:#aaa;">None</em>'; ?></td>
                <td><?php echo esc_html($c->pass_mark); ?>%</td>
                <td><?php echo $c->validity_months ? esc_html($c->validity_months) . ' months' : 'Never expires'; ?></td>
                <td><?php echo esc_html(date('d M Y', strtotime($c->updated_at))); ?></td>
                <td>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ecco-courses&action=edit&id=' . $c->id)); ?>"
                       class="button button-small">✏️ Edit</a>
                    <a href="<?php echo esc_url($del_url); ?>"
                       class="button button-small"
                       style="color:#c62828;margin-left:4px;"
                       onclick="return confirm('Delete course <?php echo esc_js($c->title); ?>?\n\nThis will also delete all questions and attempt records.')">
                        🗑 Delete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}


/* =========================================================
   COURSE EDIT / NEW PAGE
   ========================================================= */

function ecco_courses_edit_page(int $course_id = 0) {
    global $wpdb;
    $t  = $wpdb->prefix . 'ecco_courses';
    $is_new = ($course_id === 0);

    /* ── Handle Save ─────────────────────────────────────── */
    if (!empty($_POST['ecco_save_course']) && check_admin_referer('ecco_save_course')) {

        $title    = sanitize_text_field($_POST['course_title']    ?? '');
        $desc     = sanitize_textarea_field($_POST['course_desc'] ?? '');
        $content  = wp_kses_post(wp_unslash($_POST['course_content'] ?? ''));
        $pass_mark = max(1, min(100, intval($_POST['pass_mark']   ?? 70)));
        $validity  = max(0, intval($_POST['validity_months']      ?? 0));
        $status    = in_array($_POST['course_status'] ?? '', ['draft','published']) ? $_POST['course_status'] : 'draft';

        if (!$title) {
            echo '<div class="error notice"><p>Course title is required.</p></div>';
        } else {

            if ($is_new) {
                $slug = ecco_courses_unique_slug($title);
                $wpdb->insert($t, [
                    'title'           => $title,
                    'slug'            => $slug,
                    'description'     => $desc,
                    'content'         => $content,
                    'pass_mark'       => $pass_mark,
                    'validity_months' => $validity,
                    'status'          => $status,
                    'created_by'      => get_current_user_id(),
                    'created_at'      => current_time('mysql'),
                    'updated_at'      => current_time('mysql'),
                ]);
                $course_id = (int) $wpdb->insert_id;
                $is_new    = false;
                echo '<div class="updated notice is-dismissible"><p>Course created. You can now add questions below.</p></div>';
            } else {
                $slug = ecco_courses_unique_slug($title, $course_id);
                $wpdb->update($t, [
                    'title'           => $title,
                    'slug'            => $slug,
                    'description'     => $desc,
                    'content'         => $content,
                    'pass_mark'       => $pass_mark,
                    'validity_months' => $validity,
                    'status'          => $status,
                    'updated_at'      => current_time('mysql'),
                ], ['id' => $course_id]);
                echo '<div class="updated notice is-dismissible"><p>Course saved.</p></div>';
            }
        }
    }

    /* ── Load course (if editing) ────────────────────────── */
    $course    = $course_id ? ecco_get_course($course_id) : null;
    $questions = $course_id ? ecco_get_course_questions($course_id) : [];

    /* Enqueue WP media for image uploads in the editor */
    wp_enqueue_media();

    $back_url = admin_url('admin.php?page=ecco-courses');
    $page_title = $is_new ? 'Add New Course' : ('Edit Course: ' . esc_html($course->title ?? ''));
    $nonce_val  = wp_create_nonce('ecco_courses_admin_nonce');
    ?>
    <div class="wrap">
        <h1><?php echo $page_title; ?>
            <a href="<?php echo esc_url($back_url); ?>" style="font-size:13px;margin-left:14px;font-weight:400;">← Back to Courses</a>
        </h1>
        <hr class="wp-header-end">

        <!-- ========================================
             COURSE SETTINGS FORM
             ======================================== -->
        <form method="post" id="ecco-course-form">
            <?php wp_nonce_field('ecco_save_course'); ?>
            <input type="hidden" name="ecco_save_course" value="1">

            <div style="display:flex;gap:24px;align-items:flex-start;margin-top:20px;">

                <!-- LEFT COLUMN: Content editor -->
                <div style="flex:1;min-width:0;">

                    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px 24px;margin-bottom:20px;">
                        <h2 style="margin-top:0;font-size:16px;padding-bottom:10px;border-bottom:1px solid #eee;">Course Details</h2>

                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:140px;">Title <span style="color:red">*</span></th>
                                <td><input type="text" name="course_title" value="<?php echo esc_attr($course->title ?? ''); ?>"
                                           style="width:100%;font-size:16px;padding:8px 10px;" required
                                           placeholder="e.g. Electrical Safety Fundamentals"></td>
                            </tr>
                            <tr>
                                <th>Short Description</th>
                                <td><textarea name="course_desc" rows="3"
                                              style="width:100%;"
                                              placeholder="Brief description shown on the course card (2-3 sentences)"><?php echo esc_textarea($course->description ?? ''); ?></textarea></td>
                            </tr>
                        </table>
                    </div>

                    <!-- CONTENT EDITOR -->
                    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px 24px;margin-bottom:20px;">
                        <h2 style="margin-top:0;font-size:16px;padding-bottom:10px;border-bottom:1px solid #eee;">
                            Course Content
                            <small style="font-weight:400;color:#666;"> — Use the editor to add text, images, tables, and headings.</small>
                        </h2>
                        <?php
                        wp_editor(
                            $course->content ?? '',
                            'course_content',
                            [
                                'textarea_name' => 'course_content',
                                'textarea_rows' => 22,
                                'media_buttons' => true,
                                'tinymce'       => [
                                    'toolbar1' => 'formatselect bold italic | bullist numlist blockquote | link unlink | wp_more | image | wp_adv',
                                    'toolbar2' => 'strikethrough alignleft aligncenter alignright alignjustify | forecolor | pastetext removeformat | undo redo',
                                ],
                            ]
                        );
                        ?>
                    </div>

                </div><!-- /left column -->

                <!-- RIGHT COLUMN: Settings sidebar -->
                <div style="width:280px;flex-shrink:0;">

                    <!-- Publish box -->
                    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px 18px;margin-bottom:16px;">
                        <h3 style="margin:0 0 14px;font-size:14px;border-bottom:1px solid #eee;padding-bottom:8px;">Publish</h3>

                        <label style="display:block;margin-bottom:10px;font-size:13px;">
                            <strong>Status</strong><br>
                            <select name="course_status" style="margin-top:4px;width:100%;">
                                <option value="draft"     <?php selected($course->status ?? 'draft', 'draft'); ?>>Draft</option>
                                <option value="published" <?php selected($course->status ?? 'draft', 'published'); ?>>Published</option>
                            </select>
                        </label>

                        <button class="button button-primary" style="width:100%;margin-top:4px;" type="submit">
                            <?php echo $is_new ? '💾 Create Course' : '💾 Save Changes'; ?>
                        </button>
                    </div>

                    <!-- Assessment settings -->
                    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px 18px;margin-bottom:16px;">
                        <h3 style="margin:0 0 14px;font-size:14px;border-bottom:1px solid #eee;padding-bottom:8px;">Assessment Settings</h3>

                        <label style="display:block;margin-bottom:12px;font-size:13px;">
                            <strong>Pass Mark</strong>
                            <span style="color:#888;font-weight:400;"> (%)</span><br>
                            <input type="number" name="pass_mark" min="1" max="100"
                                   value="<?php echo esc_attr($course->pass_mark ?? 70); ?>"
                                   style="margin-top:4px;width:80px;">
                        </label>

                        <label style="display:block;font-size:13px;">
                            <strong>Certificate Validity</strong>
                            <span style="color:#888;font-weight:400;"> (months)</span><br>
                            <input type="number" name="validity_months" min="0" max="120"
                                   value="<?php echo esc_attr($course->validity_months ?? 0); ?>"
                                   style="margin-top:4px;width:80px;">
                            <br><small style="color:#888;">0 = never expires</small>
                        </label>
                    </div>

                    <?php if (!$is_new && $course): ?>
                    <!-- Quick info -->
                    <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;padding:14px 16px;font-size:12px;color:#666;">
                        <strong>Slug:</strong> <code><?php echo esc_html($course->slug); ?></code><br>
                        <strong>Questions:</strong> <?php echo count($questions); ?><br>
                        <strong>Updated:</strong> <?php echo esc_html(date('d M Y H:i', strtotime($course->updated_at))); ?>
                    </div>
                    <?php endif; ?>

                </div><!-- /sidebar -->
            </div><!-- /columns -->
        </form>

        <?php if (!$is_new && $course_id): ?>
        <!-- ========================================
             ASSESSMENT QUESTIONS BUILDER
             ======================================== -->
        <div id="ecco-questions-section" style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px 24px;margin-top:4px;">
            <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #eee;padding-bottom:12px;margin-bottom:20px;">
                <h2 style="margin:0;font-size:16px;">Assessment Questions</h2>
                <button id="ecco-add-q-btn" class="button button-primary" type="button">+ Add Question</button>
            </div>

            <!-- Add Question Panel (hidden by default) -->
            <div id="ecco-add-q-panel"
                 style="display:none;background:#f0f6ff;border:1px solid #c0d8f8;border-radius:6px;padding:20px;margin-bottom:24px;">
                <h3 style="margin:0 0 16px;font-size:14px;">New Question</h3>

                <label style="display:block;margin-bottom:12px;">
                    <strong>Question Type</strong><br>
                    <select id="ecco-q-type" style="margin-top:4px;min-width:220px;" onchange="eccoUpdateQFields()">
                        <option value="true_false">True / False</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="fill_blank">Fill in the Blank</option>
                    </select>
                </label>

                <label style="display:block;margin-bottom:12px;" id="ecco-q-text-wrap">
                    <strong>Question Text</strong>
                    <span id="ecco-blank-hint" style="display:none;color:#1565c0;font-weight:400;"> — use <code>[blank]</code> where the blank should appear</span><br>
                    <textarea id="ecco-q-text" rows="3" style="margin-top:4px;width:100%;max-width:640px;" placeholder="Enter the question…"></textarea>
                </label>

                <!-- True/False -->
                <div id="ecco-tf-fields">
                    <label style="display:block;margin-bottom:12px;"><strong>Correct Answer</strong><br>
                        <label style="margin-top:4px;display:inline-flex;align-items:center;gap:6px;margin-right:20px;">
                            <input type="radio" name="ecco-tf-answer" value="true" checked> True
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;">
                            <input type="radio" name="ecco-tf-answer" value="false"> False
                        </label>
                    </label>
                </div>

                <!-- Multiple Choice -->
                <div id="ecco-mc-fields" style="display:none;">
                    <strong>Answer Options</strong>
                    <p style="margin:4px 0 10px;font-size:12px;color:#666;">
                        Click "Correct" on the right option. Minimum 2, maximum 6.
                    </p>
                    <div id="ecco-mc-options">
                        <!-- options injected by JS -->
                    </div>
                    <button type="button" id="ecco-add-option-btn" class="button button-small"
                            style="margin-top:8px;" onclick="eccoAddMcOption()">+ Add Option</button>
                </div>

                <!-- Fill in the Blank -->
                <div id="ecco-fib-fields" style="display:none;">
                    <label style="display:block;margin-bottom:10px;">
                        <strong>Correct Answer</strong><br>
                        <input type="text" id="ecco-fib-answer" style="margin-top:4px;min-width:280px;"
                               placeholder="e.g. mitochondria">
                    </label>
                    <label style="display:block;margin-bottom:12px;">
                        <strong>Alternate Acceptable Answers</strong>
                        <span style="color:#888;font-weight:400;"> (comma-separated, optional)</span><br>
                        <input type="text" id="ecco-fib-alts" style="margin-top:4px;min-width:280px;"
                               placeholder="e.g. the mitochondria, Mitochondria">
                    </label>
                </div>

                <label style="display:inline-block;margin-top:4px;margin-bottom:16px;">
                    <strong>Points</strong>
                    <input type="number" id="ecco-q-points" value="1" min="1" max="10"
                           style="margin-left:8px;width:60px;">
                </label>

                <div>
                    <button type="button" class="button button-primary" onclick="eccoSaveQuestion(0)">💾 Save Question</button>
                    <button type="button" class="button" style="margin-left:8px;" onclick="eccoCloseAddPanel()">Cancel</button>
                </div>
                <div id="ecco-add-q-msg" style="margin-top:10px;font-size:13px;"></div>
            </div>

            <!-- Questions List -->
            <div id="ecco-questions-list">
                <?php if (empty($questions)): ?>
                <p id="ecco-no-questions" style="color:#888;font-style:italic;">
                    No questions yet. Click "Add Question" to build the assessment.
                </p>
                <?php else: ?>
                <p id="ecco-no-questions" style="display:none;color:#888;font-style:italic;">
                    No questions yet. Click "Add Question" to build the assessment.
                </p>
                <?php endif; ?>

                <table id="ecco-q-table" class="widefat"
                       style="<?php echo empty($questions) ? 'display:none;' : ''; ?>">
                    <thead>
                    <tr>
                        <th style="width:36px;">#</th>
                        <th>Question</th>
                        <th style="width:130px;">Type</th>
                        <th style="width:60px;">Points</th>
                        <th style="width:110px;">Order</th>
                        <th style="width:110px;">Actions</th>
                    </tr>
                    </thead>
                    <tbody id="ecco-q-tbody">
                    <?php foreach ($questions as $i => $q):
                        $type_label = [
                            'true_false'      => 'True / False',
                            'multiple_choice' => 'Multiple Choice',
                            'fill_blank'      => 'Fill in Blank',
                        ][$q->type] ?? $q->type;
                        $preview = esc_html(wp_trim_words(strip_tags($q->question_text), 12));
                    ?>
                    <tr id="ecco-qrow-<?php echo $q->id; ?>" data-id="<?php echo $q->id; ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo $preview; ?></td>
                        <td><span style="font-size:12px;background:#e8f0fe;color:#1a73e8;padding:2px 8px;border-radius:20px;">
                            <?php echo esc_html($type_label); ?>
                        </span></td>
                        <td><?php echo esc_html($q->points); ?></td>
                        <td>
                            <button type="button" class="button button-small"
                                    onclick="eccoMoveQuestion(<?php echo $q->id; ?>,'up')">▲</button>
                            <button type="button" class="button button-small"
                                    onclick="eccoMoveQuestion(<?php echo $q->id; ?>,'down')">▼</button>
                        </td>
                        <td>
                            <button type="button" class="button button-small"
                                    onclick="eccoEditQuestion(<?php echo $q->id; ?>)">✏️</button>
                            <button type="button" class="button button-small"
                                    style="color:#c62828;margin-left:4px;"
                                    onclick="eccoDeleteQuestion(<?php echo $q->id; ?>)">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div><!-- /questions-list -->

            <p style="margin-top:16px;font-size:12px;color:#888;">
                💡 Use ▲ ▼ to reorder questions. Questions are immediately saved to the database when added.
            </p>
        </div>
        <?php endif; ?>

    </div><!-- /wrap -->

    <!-- Edit Question Modal -->
    <div id="ecco-edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:6px;padding:28px 32px;width:600px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.2);">
            <h3 style="margin-top:0;">Edit Question</h3>
            <input type="hidden" id="ecco-edit-q-id" value="">

            <label style="display:block;margin-bottom:12px;">
                <strong>Type</strong><br>
                <select id="ecco-edit-q-type" style="margin-top:4px;min-width:220px;" onchange="eccoUpdateEditFields()">
                    <option value="true_false">True / False</option>
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="fill_blank">Fill in the Blank</option>
                </select>
            </label>

            <label style="display:block;margin-bottom:12px;">
                <strong>Question Text</strong>
                <span id="ecco-edit-blank-hint" style="display:none;color:#1565c0;font-weight:400;"> — use <code>[blank]</code></span><br>
                <textarea id="ecco-edit-q-text" rows="3" style="margin-top:4px;width:100%;"></textarea>
            </label>

            <!-- True/False edit -->
            <div id="ecco-edit-tf-fields">
                <strong>Correct Answer</strong><br>
                <label style="display:inline-flex;align-items:center;gap:6px;margin-right:20px;margin-top:4px;">
                    <input type="radio" name="ecco-edit-tf-answer" value="true"> True
                </label>
                <label style="display:inline-flex;align-items:center;gap:6px;">
                    <input type="radio" name="ecco-edit-tf-answer" value="false"> False
                </label>
            </div>

            <!-- MC edit -->
            <div id="ecco-edit-mc-fields" style="display:none;">
                <strong>Options</strong>
                <div id="ecco-edit-mc-options" style="margin-top:8px;"></div>
                <button type="button" class="button button-small" style="margin-top:8px;"
                        onclick="eccoAddEditMcOption()">+ Add Option</button>
            </div>

            <!-- FIB edit -->
            <div id="ecco-edit-fib-fields" style="display:none;">
                <label style="display:block;margin-bottom:10px;">
                    <strong>Correct Answer</strong><br>
                    <input type="text" id="ecco-edit-fib-answer" style="margin-top:4px;min-width:280px;">
                </label>
                <label style="display:block;margin-bottom:12px;">
                    <strong>Alternate Answers</strong><br>
                    <input type="text" id="ecco-edit-fib-alts" style="margin-top:4px;min-width:280px;">
                </label>
            </div>

            <label style="display:inline-block;margin-top:12px;margin-bottom:16px;">
                <strong>Points</strong>
                <input type="number" id="ecco-edit-q-points" value="1" min="1" max="10"
                       style="margin-left:8px;width:60px;">
            </label>

            <div>
                <button type="button" class="button button-primary" onclick="eccoSaveEditedQuestion()">💾 Save</button>
                <button type="button" class="button" style="margin-left:8px;" onclick="eccoCloseEditModal()">Cancel</button>
            </div>
            <div id="ecco-edit-q-msg" style="margin-top:10px;font-size:13px;"></div>
        </div>
    </div>

    <script>
    var eccoCourseId = <?php echo (int) $course_id; ?>;
    var eccoAdminNonce = <?php echo json_encode($nonce_val); ?>;
    var eccoAjaxUrl   = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;

    /* ── MC option counter ──────────────────────────────── */
    var eccoMcOptionCount = 2;

    /* Initialise MC options on page load */
    (function() {
        var wrap = document.getElementById('ecco-mc-options');
        if (!wrap) return;
        wrap.innerHTML = '';
        eccoMcOptionCount = 0;
        eccoAddMcOption();
        eccoAddMcOption();
    })();

    function eccoAddMcOption(text, isCorrect) {
        var wrap = document.getElementById('ecco-mc-options');
        var idx  = eccoMcOptionCount++;
        var checked = isCorrect ? 'checked' : '';
        var div = document.createElement('div');
        div.id  = 'ecco-mc-opt-' + idx;
        div.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:6px;';
        div.innerHTML =
            '<input type="radio" name="ecco-mc-correct" value="' + idx + '" ' + checked + ' title="Mark as correct answer">' +
            '<input type="text" class="ecco-mc-opt-text" placeholder="Option ' + (idx+1) + '" style="flex:1;padding:5px 8px;" value="' + (text||'').replace(/"/g,'&quot;') + '">' +
            '<button type="button" class="button button-small" style="color:#c62828;" onclick="eccoRemoveMcOption(' + idx + ')">✕</button>';
        wrap.appendChild(div);
    }

    function eccoRemoveMcOption(idx) {
        var el = document.getElementById('ecco-mc-opt-' + idx);
        var wrap = document.getElementById('ecco-mc-options');
        if (wrap.children.length <= 2) { alert('Minimum 2 options required.'); return; }
        if (el) el.remove();
    }

    function eccoUpdateQFields() {
        var type = document.getElementById('ecco-q-type').value;
        document.getElementById('ecco-tf-fields').style.display  = type === 'true_false'      ? '' : 'none';
        document.getElementById('ecco-mc-fields').style.display  = type === 'multiple_choice' ? '' : 'none';
        document.getElementById('ecco-fib-fields').style.display = type === 'fill_blank'      ? '' : 'none';
        document.getElementById('ecco-blank-hint').style.display = type === 'fill_blank'      ? '' : 'none';
    }

    function eccoCloseAddPanel() {
        document.getElementById('ecco-add-q-panel').style.display = 'none';
        document.getElementById('ecco-add-q-msg').textContent = '';
    }

    document.getElementById('ecco-add-q-btn').addEventListener('click', function() {
        var panel = document.getElementById('ecco-add-q-panel');
        panel.style.display = panel.style.display === 'none' ? '' : 'none';
    });

    /* ── Collect add-panel form data ────────────────────── */
    function eccoCollectAddData() {
        var type    = document.getElementById('ecco-q-type').value;
        var text    = document.getElementById('ecco-q-text').value.trim();
        var points  = parseInt(document.getElementById('ecco-q-points').value) || 1;
        var options = null, correct = '';

        if (type === 'true_false') {
            var sel = document.querySelector('input[name="ecco-tf-answer"]:checked');
            correct = sel ? sel.value : 'true';

        } else if (type === 'multiple_choice') {
            var opts = [];
            document.querySelectorAll('#ecco-mc-options .ecco-mc-opt-text').forEach(function(inp) {
                opts.push(inp.value.trim());
            });
            var corr = document.querySelector('input[name="ecco-mc-correct"]:checked');
            // map radio value to sequential index within visible options
            var allOpts = Array.from(document.querySelectorAll('#ecco-mc-options [id^="ecco-mc-opt-"]'));
            var correctIdx = corr ? allOpts.indexOf(document.getElementById('ecco-mc-opt-' + corr.value)) : 0;
            options = opts;
            correct = String(correctIdx);

        } else if (type === 'fill_blank') {
            var ans  = document.getElementById('ecco-fib-answer').value.trim();
            var alts = document.getElementById('ecco-fib-alts').value.trim();
            correct  = alts ? ans + ',' + alts : ans;
        }

        return { type, text, options: options ? JSON.stringify(options) : null, correct, points };
    }

    function eccoSaveQuestion(editId) {
        var data = eccoCollectAddData();
        if (!data.text) { document.getElementById('ecco-add-q-msg').innerHTML = '<span style="color:red">Question text is required.</span>'; return; }
        if (data.type === 'multiple_choice') {
            var opts = JSON.parse(data.options || '[]');
            if (opts.some(function(o){return !o;})) { document.getElementById('ecco-add-q-msg').innerHTML = '<span style="color:red">All option fields must be filled in.</span>'; return; }
        }

        var btn = document.querySelector('#ecco-add-q-panel .button-primary');
        btn.textContent = 'Saving…'; btn.disabled = true;

        jQuery.post(eccoAjaxUrl, {
            action:        'ecco_save_course_question',
            nonce:         eccoAdminNonce,
            course_id:     eccoCourseId,
            question_id:   0,
            type:          data.type,
            question_text: data.text,
            options:       data.options || '',
            correct_answer: data.correct,
            points:        data.points
        }, function(res) {
            btn.textContent = '💾 Save Question'; btn.disabled = false;
            if (res.success) {
                document.getElementById('ecco-add-q-msg').innerHTML = '<span style="color:green">✅ Question added.</span>';
                eccoAddQuestionRow(res.data);
                /* reset form */
                document.getElementById('ecco-q-text').value = '';
                document.getElementById('ecco-fib-answer') && (document.getElementById('ecco-fib-answer').value='');
                document.getElementById('ecco-fib-alts')   && (document.getElementById('ecco-fib-alts').value='');
                var wrap = document.getElementById('ecco-mc-options');
                wrap.innerHTML=''; eccoMcOptionCount=0; eccoAddMcOption(); eccoAddMcOption();
                setTimeout(function(){document.getElementById('ecco-add-q-msg').textContent='';},3000);
            } else {
                document.getElementById('ecco-add-q-msg').innerHTML = '<span style="color:red">Error: ' + res.data + '</span>';
            }
        });
    }

    function eccoAddQuestionRow(q) {
        var table = document.getElementById('ecco-q-table');
        var tbody = document.getElementById('ecco-q-tbody');
        var noQ   = document.getElementById('ecco-no-questions');
        var typeLabels = { true_false:'True / False', multiple_choice:'Multiple Choice', fill_blank:'Fill in Blank' };
        var label = typeLabels[q.type] || q.type;
        var preview = q.question_text.length > 80 ? q.question_text.substring(0,77)+'…' : q.question_text;
        var rowNum = tbody.querySelectorAll('tr').length + 1;

        var tr = document.createElement('tr');
        tr.id = 'ecco-qrow-' + q.id;
        tr.dataset.id = q.id;
        tr.innerHTML =
            '<td>' + rowNum + '</td>' +
            '<td>' + eccoEscHtml(preview) + '</td>' +
            '<td><span style="font-size:12px;background:#e8f0fe;color:#1a73e8;padding:2px 8px;border-radius:20px;">' + eccoEscHtml(label) + '</span></td>' +
            '<td>' + q.points + '</td>' +
            '<td>' +
              '<button type="button" class="button button-small" onclick="eccoMoveQuestion(' + q.id + ',\'up\')">▲</button>' +
              '<button type="button" class="button button-small" onclick="eccoMoveQuestion(' + q.id + ',\'down\')">▼</button>' +
            '</td>' +
            '<td>' +
              '<button type="button" class="button button-small" onclick="eccoEditQuestion(' + q.id + ')">✏️</button>' +
              '<button type="button" class="button button-small" style="color:#c62828;margin-left:4px;" onclick="eccoDeleteQuestion(' + q.id + ')">🗑</button>' +
            '</td>';
        tbody.appendChild(tr);
        table.style.display = '';
        noQ.style.display = 'none';
    }

    function eccoDeleteQuestion(id) {
        if (!confirm('Delete this question?')) return;
        jQuery.post(eccoAjaxUrl, {
            action:      'ecco_delete_course_question',
            nonce:       eccoAdminNonce,
            question_id: id
        }, function(res) {
            if (res.success) {
                var row = document.getElementById('ecco-qrow-' + id);
                if (row) row.remove();
                eccoRenumberRows();
                var tbody = document.getElementById('ecco-q-tbody');
                if (!tbody.querySelectorAll('tr').length) {
                    document.getElementById('ecco-q-table').style.display = 'none';
                    document.getElementById('ecco-no-questions').style.display = '';
                }
            } else { alert('Error: ' + res.data); }
        });
    }

    function eccoMoveQuestion(id, dir) {
        var tbody = document.getElementById('ecco-q-tbody');
        var row   = document.getElementById('ecco-qrow-' + id);
        if (!row) return;
        if (dir === 'up' && row.previousElementSibling) {
            tbody.insertBefore(row, row.previousElementSibling);
        } else if (dir === 'down' && row.nextElementSibling) {
            tbody.insertBefore(row.nextElementSibling, row);
        }
        eccoRenumberRows();
        var order = Array.from(tbody.querySelectorAll('tr')).map(function(r){return r.dataset.id;});
        jQuery.post(eccoAjaxUrl, {
            action:    'ecco_reorder_course_questions',
            nonce:     eccoAdminNonce,
            course_id: eccoCourseId,
            order:     order
        });
    }

    function eccoRenumberRows() {
        Array.from(document.getElementById('ecco-q-tbody').querySelectorAll('tr'))
            .forEach(function(row, i) { row.cells[0].textContent = i + 1; });
    }

    /* ── Edit question modal ─────────────────────────────── */
    var eccoEditMcCount = 0;
    var eccoQuestionsData = <?php echo json_encode(
        array_map(function($q) {
            return [
                'id'             => $q->id,
                'type'           => $q->type,
                'question_text'  => $q->question_text,
                'options'        => $q->options,
                'correct_answer' => $q->correct_answer,
                'points'         => $q->points,
            ];
        }, $questions)
    , JSON_UNESCAPED_UNICODE); ?>;

    function eccoEditQuestion(id) {
        var q = eccoQuestionsData.find(function(x){return x.id == id;});
        if (!q) { alert('Could not load question data.'); return; }

        document.getElementById('ecco-edit-q-id').value    = q.id;
        document.getElementById('ecco-edit-q-type').value  = q.type;
        document.getElementById('ecco-edit-q-text').value  = q.question_text;
        document.getElementById('ecco-edit-q-points').value = q.points;

        eccoUpdateEditFields();

        if (q.type === 'true_false') {
            var sel = document.querySelector('input[name="ecco-edit-tf-answer"][value="' + q.correct_answer + '"]');
            if (sel) sel.checked = true;
        } else if (q.type === 'multiple_choice') {
            var opts = q.options ? JSON.parse(q.options) : [];
            var correctIdx = parseInt(q.correct_answer) || 0;
            var wrap = document.getElementById('ecco-edit-mc-options');
            wrap.innerHTML = ''; eccoEditMcCount = 0;
            opts.forEach(function(opt, i) { eccoAddEditMcOption(opt, i === correctIdx); });
            if (opts.length < 2) { eccoAddEditMcOption(); eccoAddEditMcOption(); }
        } else if (q.type === 'fill_blank') {
            var parts = q.correct_answer.split(',');
            document.getElementById('ecco-edit-fib-answer').value = parts[0] ? parts[0].trim() : '';
            document.getElementById('ecco-edit-fib-alts').value   = parts.slice(1).join(',').trim();
        }

        var modal = document.getElementById('ecco-edit-modal');
        modal.style.display = 'flex';
    }

    function eccoCloseEditModal() {
        document.getElementById('ecco-edit-modal').style.display = 'none';
        document.getElementById('ecco-edit-q-msg').textContent = '';
    }

    document.getElementById('ecco-edit-modal').addEventListener('click', function(e) {
        if (e.target === this) eccoCloseEditModal();
    });

    function eccoUpdateEditFields() {
        var type = document.getElementById('ecco-edit-q-type').value;
        document.getElementById('ecco-edit-tf-fields').style.display  = type === 'true_false'      ? '' : 'none';
        document.getElementById('ecco-edit-mc-fields').style.display  = type === 'multiple_choice' ? '' : 'none';
        document.getElementById('ecco-edit-fib-fields').style.display = type === 'fill_blank'      ? '' : 'none';
        document.getElementById('ecco-edit-blank-hint').style.display = type === 'fill_blank'      ? '' : 'none';
    }

    function eccoAddEditMcOption(text, isCorrect) {
        var wrap = document.getElementById('ecco-edit-mc-options');
        var idx  = eccoEditMcCount++;
        var checked = isCorrect ? 'checked' : '';
        var div = document.createElement('div');
        div.id  = 'ecco-edit-mc-opt-' + idx;
        div.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:6px;';
        div.innerHTML =
            '<input type="radio" name="ecco-edit-mc-correct" value="' + idx + '" ' + checked + '>' +
            '<input type="text" class="ecco-edit-mc-opt-text" placeholder="Option ' + (idx+1) + '" style="flex:1;padding:5px 8px;" value="' + (text||'').replace(/"/g,'&quot;') + '">' +
            '<button type="button" class="button button-small" style="color:#c62828;" onclick="eccoRemoveEditMcOption(' + idx + ')">✕</button>';
        wrap.appendChild(div);
    }

    function eccoRemoveEditMcOption(idx) {
        var wrap = document.getElementById('ecco-edit-mc-options');
        if (wrap.children.length <= 2) { alert('Minimum 2 options required.'); return; }
        var el = document.getElementById('ecco-edit-mc-opt-' + idx);
        if (el) el.remove();
    }

    function eccoSaveEditedQuestion() {
        var id     = parseInt(document.getElementById('ecco-edit-q-id').value);
        var type   = document.getElementById('ecco-edit-q-type').value;
        var text   = document.getElementById('ecco-edit-q-text').value.trim();
        var points = parseInt(document.getElementById('ecco-edit-q-points').value) || 1;
        var options = null, correct = '';

        if (type === 'true_false') {
            var sel = document.querySelector('input[name="ecco-edit-tf-answer"]:checked');
            correct = sel ? sel.value : 'true';
        } else if (type === 'multiple_choice') {
            var opts = [];
            document.querySelectorAll('#ecco-edit-mc-options .ecco-edit-mc-opt-text').forEach(function(inp){ opts.push(inp.value.trim()); });
            var corr = document.querySelector('input[name="ecco-edit-mc-correct"]:checked');
            var allOpts = Array.from(document.querySelectorAll('#ecco-edit-mc-options [id^="ecco-edit-mc-opt-"]'));
            var correctIdx = corr ? allOpts.indexOf(document.getElementById('ecco-edit-mc-opt-' + corr.value)) : 0;
            options = opts; correct = String(correctIdx);
        } else if (type === 'fill_blank') {
            var ans  = document.getElementById('ecco-edit-fib-answer').value.trim();
            var alts = document.getElementById('ecco-edit-fib-alts').value.trim();
            correct  = alts ? ans + ',' + alts : ans;
        }

        if (!text) { document.getElementById('ecco-edit-q-msg').innerHTML='<span style="color:red">Question text is required.</span>'; return; }

        var btn = document.querySelector('#ecco-edit-modal .button-primary');
        btn.textContent = 'Saving…'; btn.disabled = true;

        jQuery.post(eccoAjaxUrl, {
            action:         'ecco_save_course_question',
            nonce:          eccoAdminNonce,
            course_id:      eccoCourseId,
            question_id:    id,
            type:           type,
            question_text:  text,
            options:        options ? JSON.stringify(options) : '',
            correct_answer: correct,
            points:         points
        }, function(res) {
            btn.textContent = '💾 Save'; btn.disabled = false;
            if (res.success) {
                /* Update local data cache */
                var qi = eccoQuestionsData.findIndex(function(x){return x.id==id;});
                if (qi !== -1) {
                    eccoQuestionsData[qi] = Object.assign(eccoQuestionsData[qi], {
                        type, question_text: text,
                        options: options ? JSON.stringify(options) : null,
                        correct_answer: correct, points
                    });
                }
                /* Update table row preview */
                var typeLabels = { true_false:'True / False', multiple_choice:'Multiple Choice', fill_blank:'Fill in Blank' };
                var row = document.getElementById('ecco-qrow-' + id);
                if (row) {
                    row.cells[1].textContent = text.length > 80 ? text.substring(0,77)+'…' : text;
                    row.cells[2].querySelector('span').textContent = typeLabels[type] || type;
                    row.cells[3].textContent = points;
                }
                eccoCloseEditModal();
            } else {
                document.getElementById('ecco-edit-q-msg').innerHTML = '<span style="color:red">Error: ' + res.data + '</span>';
            }
        });
    }

    function eccoEscHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* Sync TinyMCE before form submit */
    document.getElementById('ecco-course-form').addEventListener('submit', function() {
        if (typeof tinyMCE !== 'undefined') { tinyMCE.triggerSave(); }
    });
    </script>
    <?php
}
