<?php
if (!defined('ABSPATH')) exit;

/* Load Graph client if needed */
if (!function_exists('ecco_graph_put')) {
    require_once WP_PLUGIN_DIR . '/ecco-intranet/includes/graph-client.php';
}

/* =========================================================
   HOOKS
========================================================= */

add_action('admin_post_ecco_submit_leave', 'ecco_handle_leave_submission');
add_action('admin_post_nopriv_ecco_submit_leave', 'ecco_handle_leave_submission');

add_action('admin_post_ecco_leave_approve', 'ecco_handle_leave_approve');
add_action('admin_post_ecco_leave_reject',  'ecco_handle_leave_reject');

/* ⭐ Inject holidays + preview JS into frontend */
add_action('wp_footer', 'ecco_leave_preview_script');


/* =========================================================
   SUBMIT LEAVE REQUEST
========================================================= */

function ecco_handle_leave_submission() {

    if (!is_user_logged_in()) wp_die('Not allowed');
    check_admin_referer('ecco_leave_nonce');

    global $wpdb;

    $user_id           = get_current_user_id();
    $leave_type        = sanitize_text_field($_POST['leave_type'] ?? '');
    $reason            = sanitize_textarea_field($_POST['reason'] ?? '');
    $requester_comment = sanitize_textarea_field($_POST['reason'] ?? '');
    $start_date        = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date          = sanitize_text_field($_POST['end_date'] ?? '');

    if (!$leave_type || !$start_date || !$end_date) {
        wp_die('Missing required fields.');
    }

    /* ⭐ CALCULATE WORKING DAYS */
    $days = ecco_calculate_leave_days($start_date, $end_date);

    /* ⭐ CHECK BALANCE */
    $balance_table = $wpdb->prefix . 'ecco_leave_balances';

    $balance_row = $wpdb->get_row($wpdb->prepare(
        "SELECT balance FROM $balance_table
         WHERE user_id = %d AND leave_type = %s",
        $user_id,
        $leave_type
    ));

    $balance = $balance_row ? (float)$balance_row->balance : 0;

    if ($balance < $days) {
        wp_die(
            "<h2>Insufficient Leave Balance</h2>
             <p>You requested <strong>$days</strong> working day(s).</p>
             <p>Your balance for <strong>$leave_type</strong> is <strong>$balance</strong>.</p>
             <p><a href='javascript:history.back()'>Go back</a></p>",
            'Leave Request Error',
            ['response' => 200]
        );
    }

    /* INSERT REQUEST */

    $manager = ecco_resolve_effective_manager();
    $table   = $wpdb->prefix . 'ecco_leave_requests';

    $wpdb->insert(
        $table,
        [
            'user_id'           => $user_id,
            'leave_type'        => $leave_type,
            'start_date'        => $start_date,
            'end_date'          => $end_date,
            'reason'            => $reason,
            'requester_comment' => $requester_comment,
            'manager_email'     => $manager['mail'] ?? null,
            'status'            => 'pending',
            'created_at'        => current_time('mysql')
        ]
    );

    wp_redirect(add_query_arg('leave_submitted', '1', wp_get_referer()));
    exit;
}


/* =========================================================
   ⭐ WORKING DAYS CALCULATION (BACKEND)
========================================================= */

function ecco_calculate_leave_days($start_date, $end_date) {

    global $wpdb;

    $table = $wpdb->prefix . 'ecco_public_holidays';

    $holiday_dates = $wpdb->get_col(
        "SELECT holiday_date FROM $table"
    );

    $holiday_lookup = array_flip(array_map('trim', $holiday_dates));

    $start = new DateTime($start_date);
    $end   = new DateTime($end_date);
    $end->modify('+1 day');

    $period = new DatePeriod($start, new DateInterval('P1D'), $end);

    $days = 0;

    foreach ($period as $date) {

        $dow = $date->format('N'); // 6,7 = weekend
        if ($dow >= 6) continue;

        $current = $date->format('Y-m-d');

        if (isset($holiday_lookup[$current])) continue;

        $days++;
    }

    return $days;
}


/* =========================================================
   ⭐ FRONTEND PREVIEW SCRIPT (REAL FIX)
========================================================= */

function ecco_leave_preview_script() {

    global $wpdb;

    $table = $wpdb->prefix . 'ecco_public_holidays';
    $holidays = $wpdb->get_col("SELECT holiday_date FROM $table");

    ?>

<script>
window.eccoPublicHolidays = <?php echo json_encode($holidays); ?>;

document.addEventListener('DOMContentLoaded', function () {

    const startInput = document.getElementById('start_date');
    const endInput   = document.getElementById('end_date');
    const typeSelect = document.getElementById('ecco_leave_type');
    const previewBox = document.getElementById('leave_balance_preview');

    if (!startInput || !endInput || !typeSelect || !previewBox) return;

    function workingDays(start, end) {

        let current = new Date(start);
        const last  = new Date(end);
        let days = 0;

        while (current <= last) {

            const day = current.getDay();

            const yyyy = current.getFullYear();
            const mm   = String(current.getMonth()+1).padStart(2,'0');
            const dd   = String(current.getDate()).padStart(2,'0');

            const dateStr = `${yyyy}-${mm}-${dd}`;

            if (day !== 0 && day !== 6 &&
                !window.eccoPublicHolidays.includes(dateStr)) {

                days++;
            }

            current.setDate(current.getDate()+1);
        }

        return days;
    }

    function updatePreview() {

        const start = startInput.value;
        const end   = endInput.value;

        if (!start || !end) {
            previewBox.textContent = '— days';
            return;
        }

        const requested = workingDays(start, end);

        const option = typeSelect.options[typeSelect.selectedIndex];
        const balance = parseFloat(option.dataset.balance || 0);

        previewBox.textContent =
            `${requested} working day(s) — Balance after: ${balance - requested}`;
    }

    startInput.addEventListener('change', updatePreview);
    endInput.addEventListener('change', updatePreview);
    typeSelect.addEventListener('change', updatePreview);

});
</script>

<?php
}


/* =========================================================
   APPROVE / REJECT HANDLERS
========================================================= */

function ecco_handle_leave_approve() { ecco_handle_leave_action('approved'); }
function ecco_handle_leave_reject()  { ecco_handle_leave_action('rejected'); }

function ecco_handle_leave_action($status) {

    if (!is_user_logged_in()) wp_die('Not allowed');

    $id = intval($_POST['request_id'] ?? 0);
    if (!$id) wp_die('Invalid request');

    check_admin_referer('ecco_leave_action_' . $id);

    $comment = sanitize_textarea_field($_POST['manager_comment'] ?? '');
    if (!$comment) wp_die('Manager comment required');

    global $wpdb;

    $requests_table = $wpdb->prefix . 'ecco_leave_requests';
    $audit_table    = $wpdb->prefix . 'ecco_leave_audit';
    $balance_table  = $wpdb->prefix . 'ecco_leave_balances';

    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $requests_table WHERE id = %d",
        $id
    ));

    if (!$request) wp_die('Request not found');
    if (!ecco_current_user_can_approve_leave($request)) wp_die('Not allowed');

    $wpdb->update($requests_table, ['status' => $status], ['id' => $id]);

    $wpdb->insert(
        $audit_table,
        [
            'leave_request_id' => $id,
            'action'           => $status,
            'actor_user_id'    => get_current_user_id(),
            'actor_email'      => wp_get_current_user()->user_email,
            'old_status'       => $request->status,
            'new_status'       => $status,
            'comment'          => $comment,
            'created_at'       => current_time('mysql')
        ]
    );

    if ($status === 'approved') {

        $days = ecco_calculate_leave_days($request->start_date, $request->end_date);

        $balance_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $balance_table
             WHERE user_id = %d AND leave_type = %s",
            $request->user_id,
            $request->leave_type
        ));

        if ($balance_row) {

            $new_balance = $balance_row->balance - $days;

            if ($new_balance < 0) wp_die('Insufficient leave balance.');

            $wpdb->update(
                $balance_table,
                [
                    'balance'      => $new_balance,
                    'last_updated' => current_time('mysql')
                ],
                ['id' => $balance_row->id]
            );
        }
    }

    wp_redirect(wp_get_referer());
    exit;
}

add_action('wp_ajax_ecco_calculate_leave_days_ajax', function () {

    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
    }

    $start = sanitize_text_field($_POST['start_date'] ?? '');
    $end   = sanitize_text_field($_POST['end_date'] ?? '');

    if (!$start || !$end) {
        wp_send_json_error('Missing dates');
    }

    $days = ecco_calculate_leave_days($start, $end);

    wp_send_json_success(['days' => $days]);
});
