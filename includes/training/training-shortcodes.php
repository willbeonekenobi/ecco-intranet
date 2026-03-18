<?php
if (!defined('ABSPATH')) exit;

add_shortcode('ecco_training', function(){

    if (!is_user_logged_in()) return '';

    wp_enqueue_style('ecco-training');

    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ecco_training WHERE user_id=%d",
            get_current_user_id()
        )
    );

    ob_start();
?>

<form method="post" enctype="multipart/form-data"
      action="<?php echo admin_url('admin-post.php'); ?>">

    <?php wp_nonce_field('ecco_training_upload'); ?>

    <input type="hidden" name="action" value="ecco_training_upload">

    <h3>Upload Certification</h3>

    <input name="course" placeholder="Course Name" required>
    <input type="date" name="completed" required>
    <input type="file" name="certificate" required>

    <button>Upload</button>

</form>

<table class="ecco-training-table">

<tr>
<th>Employee</th>
<th>Course</th>
<th>Completed</th>
<th>Expiry</th>
<th>Certificate</th>
<th>Reminder</th>
</tr>

<?php foreach($rows as $r): ?>

<tr>

<td><?php echo wp_get_current_user()->display_name ?></td>
<td><?php echo esc_html($r->course) ?></td>
<td><?php echo esc_html($r->completed) ?></td>
<td><?php echo esc_html($r->expiry) ?></td>

<td>
<a href="<?php echo esc_url($r->certificate_url) ?>" target="_blank">
View
</a>
</td>

<td>
<button class="ecco-training-reminder"
data-id="<?php echo $r->id ?>">Email</button>
</td>

</tr>

<?php endforeach; ?>

</table>

<script>

document.querySelectorAll('.ecco-training-reminder').forEach(b=>{
    b.onclick=()=>{

        fetch(ajaxurl,{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=ecco_training_reminder&id='+b.dataset.id
        }).then(()=>alert('Reminder sent'))

    }
})

</script>

<?php
return ob_get_clean();

});