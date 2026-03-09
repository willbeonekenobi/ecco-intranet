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

    <table class="ecco-leave-table" style="width:100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Dates</th>
                <th>Days Requested</th>
                <th>Balance Before</th>
                <th>Balance After</th>
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

            // Calculate leave days excluding weekends & public holidays
            $days_requested = function_exists('ecco_calculate_leave_days') 
                ? ecco_calculate_leave_days($r->start_date, $r->end_date) 
                : 0;

            $balance_row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ecco_leave_balances 
                 WHERE user_id = %d AND leave_type = %s",
                $r->user_id,
                $r->leave_type
            ));

            $current_balance = $balance_row->balance ?? 0;

            // The DB balance is already deducted when a request is approved.
            // So:
            //   approved  → balance_after  = current DB value (deducted)
            //               balance_before = current DB value + days used
            //   pending / rejected → balance has NOT been touched
            //               balance_before = current DB value
            //               balance_after  = current DB value - days (projected)
            if ( $r->status === 'approved' ) {
                $balance_after  = $current_balance;
                $balance_before = $current_balance + $days_requested;
            } else {
                $balance_before = $current_balance;
                $balance_after  = max( 0, $current_balance - $days_requested );
            }

        ?>

            <tr>
                <td><?php echo esc_html(get_userdata($r->user_id)->display_name ?? ''); ?></td>
                <td><?php echo esc_html($r->leave_type); ?></td>
                <td><?php echo esc_html($r->start_date . ' → ' . $r->end_date); ?></td>
                <td><?php echo esc_html($days_requested); ?></td>
                <td><?php echo esc_html($balance_before); ?></td>
                <td><?php echo esc_html($balance_after); ?></td>

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
                    <?php if ( ! $disabled && function_exists( 'ecco_current_user_can_approve_leave' ) && ecco_current_user_can_approve_leave( $r ) ) :

                        $is_own_request = ( (int) get_current_user_id() === (int) $r->user_id );
                    ?>

                        <?php if ( $is_own_request ) : ?>
                            <p style="margin:0 0 6px;font-size:12px;color:#666;">
                                <em>🔑 Self-approval — you are flagged as a self-manager</em>
                            </p>
                        <?php endif; ?>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'ecco_leave_action_' . $r->id ); ?>
                            <input type="hidden" name="request_id" value="<?php echo esc_attr( $r->id ); ?>">
                            <textarea name="manager_comment" required placeholder="Comment (required)" style="width:100%;min-height:50px;margin-bottom:4px;"></textarea>
                            <button type="submit" name="action" value="ecco_leave_approve"
                                style="background:#2e7d32;color:#fff;border:none;padding:5px 10px;border-radius:3px;cursor:pointer;margin-right:4px;">
                                ✅ Approve
                            </button>
                            <button type="submit" name="action" value="ecco_leave_reject"
                                style="background:#c62828;color:#fff;border:none;padding:5px 10px;border-radius:3px;cursor:pointer;"
                                onclick="return confirm('Reject this leave request?')">
                                ❌ Reject
                            </button>
                        </form>

                    <?php elseif ( $disabled ) : ?>
                        <em style="color:#888;">—</em>
                    <?php else : ?>
                        <em style="color:#aaa;">No action available</em>
                    <?php endif; ?>
                </td>

                <td><strong><?php echo esc_html(ucfirst($r->status)); ?></strong></td>
            </tr>

        <?php endforeach; ?>

        </tbody>
    </table>

    <style>
        .ecco-leave-table th, .ecco-leave-table td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        .ecco-leave-table textarea {
            width: 100%;
            min-height: 50px;
        }
        .ecco-leave-table button {
            margin-top: 5px;
            margin-right: 5px;
            padding: 4px 8px;
        }
    </style>

    <?php
    return ob_get_clean();
});
