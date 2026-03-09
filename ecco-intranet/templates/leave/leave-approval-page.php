<?php
if (!defined('ABSPATH')) exit;

if (!ecco_user_is_manager()) {
    echo "<p>You do not have permission.</p>";
    return;
}

$employee = sanitize_text_field($_GET['employee'] ?? '');
$email    = sanitize_email($_GET['email'] ?? '');
$start    = sanitize_text_field($_GET['start'] ?? '');
$end      = sanitize_text_field($_GET['end'] ?? '');

if (!$employee || !$email) {
    echo "<p>Invalid request.</p>";
    return;
}

/* ================================
   HANDLE ACTION
================================ */

if (isset($_POST['leave_action'])) {

    if (!wp_verify_nonce($_POST['_wpnonce'], 'ecco_leave_action')) {
        echo "<p>Security check failed.</p>";
        return;
    }

    $action = sanitize_text_field($_POST['leave_action']);

    if ($action === 'approve') {
        $status = 'APPROVED';
    } elseif ($action === 'reject') {
        $status = 'REJECTED';
    } else {
        return;
    }

    /* ================================
       ✉️ EMAIL EMPLOYEE
    ================================= */

    $subject = "Your Leave Request — {$status}";

    $message = "
Dear {$employee},

Your leave request has been {$status}.

Start: {$start}
End: {$end}

Regards,
Management
";

    wp_mail($email, $subject, $message);

    echo "<p style='color:green'><strong>Leave {$status}.</strong></p>";
}

?>

<h3>Leave Request — <?php echo esc_html($employee); ?></h3>

<p><strong>Dates:</strong> <?php echo esc_html("$start → $end"); ?></p>

<form method="post">
    <?php wp_nonce_field('ecco_leave_action'); ?>

    <button name="leave_action" value="approve">
        Approve
    </button>

    <button name="leave_action" value="reject">
        Reject
    </button>
</form>
