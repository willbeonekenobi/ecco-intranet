<?php
if (!defined('ABSPATH')) exit;

add_shortcode('ecco_leave_dashboard', function () {

    if (!is_user_logged_in()) return '<p>You must be logged in.</p>';

    global $wpdb;

    $user_id = get_current_user_id();
    $is_admin = current_user_can('manage_options');

    if ($is_admin) {
        $requests = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ecco_leave_requests ORDER BY id DESC");
    } else {
        $me = ecco_get_graph_user_profile();
        $my_email = strtolower($me['mail'] ?? '');

        $requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ecco_leave_requests 
                 WHERE user_id = %d OR manager_email = %s
                 ORDER BY id DESC",
                $user_id,
                $my_email
            )
        );
    }

    if (!$requests) return '<p>No leave requests found.</p>';

    ob_start(); ?>

    <table class="ecco-leave-table">
        <thead>
        <tr>
            <th>Employee</th>
            <th>Type</th>
            <th>Dates</th>
            <th>Status</th>
            <th>Document</th>
            <th>Manager Comment</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>

        <?php foreach ($requests as $r):
            $disabled = in_array($r->status, ['approved','rejected'], true);
        ?>
            <tr>
                <td><?php echo esc_html(get_userdata($r->user_id)->display_name ?? ''); ?></td>
                <td><?php echo esc_html($r->leave_type); ?></td>
                <td><?php echo esc_html("{$r->start_date} → {$r->end_date}"); ?></td>
                <td><strong><?php echo esc_html(ucfirst($r->status)); ?></strong></td>

                <td>
                    <?php if ($r->attachment_url): ?>
                        <a href="<?php echo esc_url($r->attachment_url); ?>" target="_blank">View document</a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>

                <td>
                    <?php if (!empty($r->manager_comment)) : ?>
                        <em><?php echo nl2br(esc_html($r->manager_comment)); ?></em>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>

                <td>
                    <?php if (!$disabled && ecco_current_user_can_approve_leave($r)) : ?>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('ecco_leave_action_' . $r->id); ?>
                            <input type="hidden" name="action" value="ecco_leave_approve">
                            <input type="hidden" name="request_id" value="<?php echo esc_attr($r->id); ?>">
                            <textarea name="manager_comment" required></textarea>
                            <button type="submit">Approve</button>
                        </form>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('ecco_leave_action_' . $r->id); ?>
                            <input type="hidden" name="action" value="ecco_leave_reject">
                            <input type="hidden" name="request_id" value="<?php echo esc_attr($r->id); ?>">
                            <textarea name="manager_comment" required></textarea>
                            <button type="submit">Reject</button>
                        </form>

                    <?php else : ?>
                        <button disabled>Actioned</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        </tbody>
    </table>

    <?php
    return ob_get_clean();
});
