<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$graph_user = function_exists('ecco_get_graph_user_profile') ? ecco_get_graph_user_profile() : [];

$leave_types = get_option('ecco_leave_types', []);
?>

<?php if (isset($_GET['leave_submitted'])) : ?>
    <div class="notice notice-success">Leave request submitted.</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field('ecco_leave_nonce'); ?>
    <input type="hidden" name="action" value="ecco_submit_leave">

    <p><strong>Name:</strong> <?php echo esc_html($graph_user['displayName'] ?? ''); ?></p>
    <p><strong>Email:</strong> <?php echo esc_html($graph_user['mail'] ?? ''); ?></p>

    <label>Leave Type</label>
    <select name="leave_type" id="ecco_leave_type" required>
        <?php foreach ($leave_types as $lt): ?>
            <option value="<?php echo esc_attr($lt['label']); ?>" data-requires-image="<?php echo $lt['requires_image'] ? '1' : '0'; ?>">
                <?php echo esc_html($lt['label']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <div id="ecco_leave_attachment_wrap" style="display:none;margin-top:10px;">
        <label>Supporting Document</label>
        <input type="file" name="leave_attachment" accept="image/*,.pdf">
        <p><em>Required for this leave type.</em></p>
    </div>

    <label>Start Date</label>
    <input type="date" name="start_date" required>

    <label>End Date</label>
    <input type="date" name="end_date" required>

    <label>Reason</label>
    <textarea name="reason"></textarea>

    <button type="submit">Submit Leave Request</button>
</form>

<script>
document.getElementById('ecco_leave_type').addEventListener('change', function () {
    const requiresImage = this.options[this.selectedIndex].dataset.requiresImage === '1';
    document.getElementById('ecco_leave_attachment_wrap').style.display = requiresImage ? 'block' : 'none';
});
</script>
