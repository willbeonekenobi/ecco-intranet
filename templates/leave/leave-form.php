<?php
$user_id = get_current_user_id();

/**
 * IMPORTANT:
 * Replace this with your EXISTING Graph helper.
 * This function name is an example.
 */
$graph_user = function_exists('ecco_get_graph_user_profile')
    ? ecco_get_graph_user_profile($user_id)
    : [];
?>

<?php if (isset($_GET['leave_submitted'])) : ?>
    <div class="notice notice-success">Leave request submitted.</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ecco_leave_nonce'); ?>
    <input type="hidden" name="action" value="ecco_submit_leave">

    <p><strong>Name:</strong> <?php echo esc_html($graph_user['displayName'] ?? ''); ?></p>
    <p><strong>Email:</strong> <?php echo esc_html($graph_user['mail'] ?? ''); ?></p>

    <label>Leave Type</label>
    <select name="leave_type" required>
        <option value="Annual">Annual</option>
        <option value="Sick">Sick</option>
        <option value="Unpaid">Unpaid</option>
    </select>

    <label>Start Date</label>
    <input type="date" name="start_date" required>

    <label>End Date</label>
    <input type="date" name="end_date" required>

    <label>Reason</label>
    <textarea name="reason"></textarea>

    <button type="submit">Submit Leave Request</button>
</form>
