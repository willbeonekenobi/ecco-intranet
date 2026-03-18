<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){

    add_menu_page(
        'Training',
        'Training',
        'manage_options',
        'ecco-training',
        'ecco_training_admin'
    );

});

function ecco_training_admin(){

    global $wpdb;

    if (isset($_POST['save'])){

        $wpdb->update(
            $wpdb->prefix.'ecco_training',
            ['expiry'=>$_POST['expiry']],
            ['id'=>$_POST['id']]
        );

        echo "<div class='updated'><p>Saved</p></div>";
    }

    $rows = $wpdb->get_results("
        SELECT t.*, u.display_name
        FROM {$wpdb->prefix}ecco_training t
        JOIN {$wpdb->users} u ON u.ID=t.user_id
    ");

?>

<h1>Training Records</h1>

<table class="widefat">

<tr>
<th>Employee</th>
<th>Course</th>
<th>Completed</th>
<th>Expiry</th>
<th>Save</th>
</tr>

<?php foreach($rows as $r): ?>

<tr>
<form method="post">
<td><?php echo esc_html($r->display_name) ?></td>
<td><?php echo esc_html($r->course) ?></td>
<td><?php echo esc_html($r->completed) ?></td>

<td>
<input type="date" name="expiry"
value="<?php echo esc_attr($r->expiry) ?>">
</td>

<td>
<input type="hidden" name="id"
value="<?php echo $r->id ?>">
<button name="save">Save</button>
</td>

</form>
</tr>

<?php endforeach; ?>

</table>

<?php
}