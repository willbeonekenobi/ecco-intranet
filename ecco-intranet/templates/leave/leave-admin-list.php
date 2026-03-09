<div class="wrap">
    <h1>Leave Requests</h1>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Manager</th>
                <th>Type</th>
                <th>Dates</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
                <th>Audit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r) : ?>
                <tr>
                    <td><?php echo esc_html($r->user_id); ?></td>
                    <td><?php echo esc_html($r->manager_email ?: 'Self-managed / None'); ?></td>
                    <td><?php echo esc_html($r->leave_type); ?></td>
                    <td><?php echo esc_html($r->start_date . ' → ' . $r->end_date); ?></td>
                    <td><?php echo esc_html($r->status); ?></td>
                    <td><?php echo esc_html($r->created_at); ?></td>

                    <!-- Actions column -->
                    <td>
                        <?php if (function_exists('ecco_current_user_can_approve_leave') && ecco_current_user_can_approve_leave($r)) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                <?php wp_nonce_field('ecco_leave_action_' . $r->id); ?>
                                <input type="hidden" name="action" value="ecco_leave_approve">
                                <input type="hidden" name="request_id" value="<?php echo esc_attr($r->id); ?>">
                                <button class="button button-primary">Approve</button>
                            </form>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                <?php wp_nonce_field('ecco_leave_action_' . $r->id); ?>
                                <input type="hidden" name="action" value="ecco_leave_reject">
                                <input type="hidden" name="request_id" value="<?php echo esc_attr($r->id); ?>">
                                <button class="button">Reject</button>
                            </form>
                        <?php else : ?>
                            <em>No permission</em>
                        <?php endif; ?>
                    </td>

                    <!-- Audit column -->
                    <td>
                        <details>
                            <summary>View</summary>
                            <?php
                            global $wpdb;
                            $logs = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT * FROM {$wpdb->prefix}ecco_leave_audit WHERE leave_request_id = %d ORDER BY created_at DESC",
                                    $r->id
                                )
                            );

                            if ($logs) {
                                echo '<ul style="margin:8px 0;">';
                                foreach ($logs as $log) {
                                    echo '<li>';
                                    echo esc_html("{$log->created_at} – {$log->action} by {$log->actor_email}");
                                    echo '</li>';
                                }
                                echo '</ul>';
                            } else {
                                echo '<em>No actions yet</em>';
                            }
                            ?>
                        </details>
                    </td>

                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
