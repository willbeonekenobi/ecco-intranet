<?php
if (!defined('ABSPATH')) exit;

add_shortcode('ecco_leave_dashboard', 'ecco_leave_dashboard_shortcode');

function ecco_leave_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to view leave requests.</p>';
    }

    global $wpdb;
    $current_user_id = get_current_user_id();

    // Fetch current user profile
    $me = function_exists('ecco_get_graph_user_profile') ? ecco_get_graph_user_profile() : [];
    $my_email = strtolower($me['mail'] ?? '');

    $table_requests = $wpdb->prefix . 'ecco_leave_requests';

    // Admin / Manager: fetch relevant requests
    $requests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_requests
             WHERE user_id = %d OR (manager_email IS NOT NULL AND LOWER(manager_email) = %s)
             ORDER BY created_at DESC",
            $current_user_id,
            $my_email
        )
    );

    if (!$requests) {
        return '<p>No leave requests found.</p>';
    }

    ob_start();
    ?>
    <table class="widefat">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Dates</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Manager Comment</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $r) : 
            $can_approve = function_exists('ecco_current_user_can_approve_leave') 
                            && ecco_current_user_can_approve_leave($r);
            ?>
            <tr>
                <td><?php echo esc_html(get_userdata($r->user_id)->display_name); ?></td>
                <td><?php echo esc_html($r->leave_type); ?></td>
                <td><?php echo esc_html($r->start_date . ' → ' . $r->end_date); ?></td>
                <td><?php echo nl2br(esc_html($r->reason)); ?></td>
                <td><?php echo esc_html($r->status); ?></td>
                <td>
                    <?php
                    global $wpdb;
                    $comments = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT comment, action, actor_email, created_at FROM {$wpdb->prefix}ecco_leave_audit
                             WHERE leave_request_id = %d
                             ORDER BY created_at ASC",
                            $r->id
                        )
                    );
                    if ($comments) {
                        echo '<ul>';
                        foreach ($comments as $c) {
                            echo '<li><strong>' . esc_html($c->actor_email) . ':</strong> ' 
                                 . esc_html($c->comment) 
                                 . ' (' . esc_html($c->action) . ')</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<em>No comments yet</em>';
                    }
                    ?>
                </td>
                <td>
                    <?php if ($can_approve && $r->status === 'pending') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('ecco_leave_action_' . $r->id); ?>
                            <input type="hidden" name="request_id" value="<?php echo esc_attr($r->id); ?>">
                            <textarea name="manager_comment" required placeholder="Comment"></textarea><br>
                            <button name="action" value="ecco_leave_approve" class="button button-primary">Approve</button>
                            <button name="action" value="ecco_leave_reject" class="button">Reject</button>
                        </form>
                    <?php else: ?>
                        <button class="button" disabled>Action</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}
