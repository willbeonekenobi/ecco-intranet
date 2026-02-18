<?php
if (!defined('ABSPATH')) exit;

add_shortcode('ecco_leave_dashboard', function () {

    if (!is_user_logged_in()) {
        return '<p>You must be logged in.</p>';
    }

    global $wpdb;

    $user_id  = get_current_user_id();
    $is_admin = current_user_can('manage_options');
    $filter   = sanitize_text_field($_GET['status'] ?? '');

    $where = '1=1';
    if ($filter && in_array($filter, ['pending','approved','rejected'], true)) {
        $where .= $wpdb->prepare(" AND status = %s", $filter);
    }

    if ($is_admin) {
        $requests = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ecco_leave_requests WHERE {$where} ORDER BY id DESC");
    } else {
        $me = function_exists('ecco_get_graph_user_profile') ? ecco_get_graph_user_profile() : [];
        $my_email = strtolower($me['mail'] ?? '');

        $requests = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ecco_leave_requests 
                 WHERE {$where} AND (user_id = %d OR manager_email = %s)
                 ORDER BY id DESC",
                $user_id,
                $my_email
            )
        );
    }

    if (!$requests) {
        return '<p>No leave requests found.</p>';
    }
    ob_start(); ?>

    <form method="get" style="margin-bottom:15px;">
        <strong>Filter:</strong>
        <select name="status" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="pending"  <?php selected($filter, 'pending'); ?>>Pending</option>
            <option value="approved" <?php selected($filter, 'approved'); ?>>Approved</option>
            <option value="rejected" <?php selected($filter, 'rejected'); ?>>Rejected</option>
        </select>
    </form>

    <table class="ecco-leave-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Dates</th>
                <th>Document</th>
                <th>Requester Comment</th>
                <th>Manager Comment</th>
                <th>Action</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>

        <?php foreach ($requests as $r): 

            $audit = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ecco_leave_audit 
                     WHERE leave_request_id = %d 
                     ORDER BY id DESC 
                     LIMIT 1",
                    $r->id
                )
            );

            $manager_comment = $audit->comment ?? '';
            $requester_comment = $r->requester_comment ?? $r->comments ?? '';

            $disabled = in_array($r->status, ['approved','rejected'], true);
        ?>

            <tr>
                <td><?php echo esc_html(get_userdata($r->user_id)->display_name ?? ''); ?></td>
                <td><?php echo esc_html($r->leave_type); ?></td>
                <td><?php echo esc_html($r->start_date . ' → ' . $r->end_date); ?></td>

                <td>
                    <?php if (!empty($r->attachment_url)) : ?>
                        <a href="<?php echo esc_url($r->attachment_url); ?>" target="_blank">View</a>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>

                <td><?php echo $requester_comment ? nl2br(esc_html($requester_comment)) : '—'; ?></td>
                <td><?php echo $manager_comment ? nl2br(esc_html($manager_comment)) : '—'; ?></td>

                <td>
                    <?php if (!$disabled && function_exists('ecco_current_user_can_approve_leave') && ecco_current_user_can_approve_leave($r)) : ?>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('ecco_leave_action_' . $r->id); ?>
                            <input type="hidden" name="request_id" value="<?php echo esc_attr($r->id); ?>">
                            <textarea name="manager_comment" required placeholder="Manager comment"></textarea>
                            <button type="submit" name="action" value="ecco_leave_approve">Approve</button>
                            <button type="submit" name="action" value="ecco_leave_reject">Reject</button>
                        </form>

                    <?php else : ?>
                        <em>Actioned</em>
                    <?php endif; ?>
                </td>

                <td><strong><?php echo esc_html(ucfirst($r->status)); ?></strong></td>
            </tr>

        <?php endforeach; ?>

        </tbody>
    </table>

    <?php
    return ob_get_clean();
});
