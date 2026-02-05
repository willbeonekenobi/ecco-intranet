<?php
if (!is_user_logged_in()) {
    echo '<p>Please log in to approve this leave request.</p>';
    return;
}

$me = function_exists('ecco_get_graph_user_profile') ? ecco_get_graph_user_profile() : [];
$my_email = strtolower(trim($me['mail'] ?? ''));

$requester = get_userdata($request->user_id);
$requester_name = $requester ? $requester->display_name : 'Unknown';
$requester_email = $requester ? $requester->user_email : '';

?>

<h2>Leave Request Approval</h2>
<?php
$requester = get_userdata($request->user_id);
$requester_name = $requester ? $requester->display_name : 'Unknown';
$requester_email = $requester ? $requester->user_email : '';
?>
<p><strong>Employee:</strong> <?php echo esc_html($requester_name); ?> (<?php echo esc_html($requester_email); ?>)</p>
<p><strong>Type:</strong> <?php echo esc_html($request->leave_type); ?></p>
<p><strong>Dates:</strong> <?php echo esc_html($request->start_date . ' → ' . $request->end_date); ?></p>
<p><strong>Reason:</strong><br><?php echo nl2br(esc_html($request->reason)); ?></p>
<p><strong>Status:</strong> <?php echo esc_html($request->status); ?></p>

<?php if (function_exists('ecco_current_user_can_approve_leave') && ecco_current_user_can_approve_leave($request)) : ?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ecco_leave_action_' . $request->id); ?>
    <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">

    <p>
        <label>Manager Comment (required)</label><br>
        <textarea name="manager_comment" required style="width:100%; min-height:120px;"></textarea>
    </p>

    <button name="action" value="ecco_leave_approve" class="button button-primary">Approve</button>
    <button name="action" value="ecco_leave_reject" class="button">Reject</button>
</form>

<?php else: ?>
<p>You are not authorized to approve or reject this leave request.</p>
<?php endif; ?>
