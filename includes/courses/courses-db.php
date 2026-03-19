<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   CREATE / UPGRADE COURSES TABLES
   ========================================================= */

function ecco_create_courses_tables() {

    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    /* ── ecco_courses ──────────────────────────────────────
       One row per training course.
    ─────────────────────────────────────────────────────── */
    $t_courses = $wpdb->prefix . 'ecco_courses';
    dbDelta("CREATE TABLE $t_courses (
        id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title               VARCHAR(255)    NOT NULL,
        slug                VARCHAR(255)    NOT NULL DEFAULT '',
        description         TEXT            DEFAULT NULL,
        content             LONGTEXT        DEFAULT NULL,
        pass_mark           TINYINT UNSIGNED NOT NULL DEFAULT 70,
        validity_months     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        status              ENUM('draft','published') NOT NULL DEFAULT 'draft',
        created_by          BIGINT UNSIGNED DEFAULT NULL,
        created_at          DATETIME        DEFAULT CURRENT_TIMESTAMP,
        updated_at          DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status (status),
        UNIQUE KEY slug (slug)
    ) $charset;");

    /* ── ecco_course_questions ─────────────────────────────
       Questions belonging to a course.
       type        : true_false | multiple_choice | fill_blank
       options     : JSON array of strings (MC options)
       correct_answer :
         true_false    → "true" or "false"
         multiple_choice → zero-based index as string ("0","1",…)
         fill_blank    → comma-separated acceptable answers
    ─────────────────────────────────────────────────────── */
    $t_questions = $wpdb->prefix . 'ecco_course_questions';
    dbDelta("CREATE TABLE $t_questions (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        course_id       BIGINT UNSIGNED NOT NULL,
        question_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        type            ENUM('true_false','multiple_choice','fill_blank') NOT NULL,
        question_text   TEXT            NOT NULL,
        options         TEXT            DEFAULT NULL,
        correct_answer  VARCHAR(1000)   NOT NULL DEFAULT '',
        points          TINYINT UNSIGNED NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY course_id (course_id)
    ) $charset;");

    /* ── ecco_course_attempts ──────────────────────────────
       One row per quiz attempt by a user.
    ─────────────────────────────────────────────────────── */
    $t_attempts = $wpdb->prefix . 'ecco_course_attempts';
    dbDelta("CREATE TABLE $t_attempts (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        course_id       BIGINT UNSIGNED NOT NULL,
        user_id         BIGINT UNSIGNED NOT NULL,
        answers         LONGTEXT        DEFAULT NULL,
        score           DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
        passed          TINYINT(1)      NOT NULL DEFAULT 0,
        completed_at    DATETIME        DEFAULT CURRENT_TIMESTAMP,
        cert_generated  TINYINT(1)      NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY course_user (course_id, user_id)
    ) $charset;");
}


/* =========================================================
   HELPERS
   ========================================================= */

/** Return a single course row by ID, or null. */
function ecco_get_course(int $id): ?object {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ecco_courses WHERE id = %d",
        $id
    ));
}

/** Return a single course row by slug, or null. */
function ecco_get_course_by_slug(string $slug): ?object {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ecco_courses WHERE slug = %s",
        $slug
    ));
}

/** Return all published courses ordered by title. */
function ecco_get_published_courses(): array {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}ecco_courses WHERE status = 'published' ORDER BY title ASC"
    ) ?: [];
}

/** Return ordered questions for a course. */
function ecco_get_course_questions(int $course_id): array {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ecco_course_questions
         WHERE course_id = %d ORDER BY question_order ASC, id ASC",
        $course_id
    )) ?: [];
}

/** Return the best (highest score) passed attempt for a user+course, or null. */
function ecco_get_best_pass(int $course_id, int $user_id): ?object {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ecco_course_attempts
         WHERE course_id = %d AND user_id = %d AND passed = 1
         ORDER BY score DESC, completed_at DESC LIMIT 1",
        $course_id, $user_id
    ));
}

/** Return the most recent attempt (any pass/fail) for a user+course, or null. */
function ecco_get_latest_attempt(int $course_id, int $user_id): ?object {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ecco_course_attempts
         WHERE course_id = %d AND user_id = %d
         ORDER BY completed_at DESC LIMIT 1",
        $course_id, $user_id
    ));
}

/** Generate a unique URL-safe slug from a title. */
function ecco_courses_unique_slug(string $title, int $exclude_id = 0): string {
    global $wpdb;
    $base = sanitize_title($title);
    $slug = $base;
    $i    = 1;
    while (true) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ecco_courses WHERE slug = %s AND id != %d",
            $slug, $exclude_id
        ));
        if (!$exists) break;
        $slug = $base . '-' . (++$i);
    }
    return $slug;
}
