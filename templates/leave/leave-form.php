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
        <option value="">Select leave type</option>
        <?php foreach ($leave_types as $lt): ?>
            <option value="<?php echo esc_attr($lt['label']); ?>"
                data-requires-image="<?php echo !empty($lt['requires_image']) ? '1' : '0'; ?>">
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
    <input type="date" name="start_date" id="ecco_start_date" required>

    <label>End Date</label>
    <input type="date" name="end_date" id="ecco_end_date" required>

    <label>Reason</label>
    <textarea name="reason"></textarea>

    <!-- ===== Leave Balance Preview ===== -->
    <div id="ecco_leave_preview" style="margin-top:20px;padding:15px;border:1px solid #ccc;background:#f9f9f9;">
        <h4>Leave Balance Preview</h4>

        <p>Current Balance:
            <strong><span id="ecco_current_balance">—</span></strong>
        </p>

        <p>Requested Days (working days):
            <strong><span id="ecco_requested_days">0</span></strong>
        </p>

        <p>Balance After Approval:
            <strong><span id="ecco_remaining_balance">—</span></strong>
        </p>
    </div>

    <button type="submit">Submit Leave Request</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const leaveType = document.getElementById('ecco_leave_type');
    const startDate = document.getElementById('ecco_start_date');
    const endDate = document.getElementById('ecco_end_date');

    leaveType.addEventListener('change', function () {

        const requiresImage = this.options[this.selectedIndex]?.dataset.requiresImage === '1';
        document.getElementById('ecco_leave_attachment_wrap').style.display =
            requiresImage ? 'block' : 'none';

        updatePreview();
    });

    startDate.addEventListener('change', updatePreview);
    endDate.addEventListener('change', updatePreview);

    function updatePreview() {

        if (!leaveType.value || !startDate.value || !endDate.value) return;

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'ecco_get_leave_preview',
                leave_type: leaveType.value,
                start_date: startDate.value,
                end_date: endDate.value
            })
        })
        .then(res => res.json())
        .then(data => {

            if (!data.success) return;

            document.getElementById('ecco_current_balance').textContent =
                data.data.balance.toFixed(1);

            document.getElementById('ecco_requested_days').textContent =
                data.data.days;

            document.getElementById('ecco_remaining_balance').textContent =
                data.data.remaining.toFixed(1);

            document.getElementById('ecco_remaining_balance').style.color =
                data.data.remaining < 0 ? 'red' : '';
        });
    }

});
</script>
