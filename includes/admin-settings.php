<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_options_page(
        'ECCO Intranet Settings',
        'ECCO Intranet',
        'manage_options',
        'ecco-intranet',
        'ecco_settings_page'
    );
});

add_action('admin_menu', function () {
    add_submenu_page(
        'options-general.php',
        'Public Holidays',
        'Public Holidays',
        'manage_options',
        'ecco-public-holidays',
        'ecco_public_holidays_page'
    );
});

function ecco_public_holidays_page() {

    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $table = $wpdb->prefix . 'ecco_public_holidays';

    /* --- Add Holiday --- */

    if (!empty($_POST['holiday_date'])) {

        check_admin_referer('ecco_add_holiday');

        $date = sanitize_text_field($_POST['holiday_date']);
        $name = sanitize_text_field($_POST['name']);

        $wpdb->insert($table, [
            'holiday_date' => $date,
            'name' => $name
        ]);

        echo '<div class="updated"><p>Holiday added.</p></div>';
    }

    /* --- Delete Holiday (SECURE) --- */

    if (!empty($_GET['delete']) && !empty($_GET['_wpnonce'])) {

        if (!current_user_can('manage_options')) return;

        $id = intval($_GET['delete']);

        if (wp_verify_nonce($_GET['_wpnonce'], 'ecco_delete_holiday_' . $id)) {

            $wpdb->delete($table, ['id' => $id]);

            echo '<div class="updated"><p>Holiday removed.</p></div>';

            // Prevent resubmission on refresh
            wp_redirect(admin_url('options-general.php?page=ecco-public-holidays'));
            exit;
        } else {
            echo '<div class="error"><p>Security check failed.</p></div>';
        }
    }

    $holidays = $wpdb->get_results("SELECT * FROM $table ORDER BY holiday_date ASC");
?>

<div class="wrap">
<h1>Public Holidays</h1>

<form method="post">
    <?php wp_nonce_field('ecco_add_holiday'); ?>

    <table class="form-table">
        <tr>
            <th>Date</th>
            <td><input type="date" name="holiday_date" required></td>
        </tr>
        <tr>
            <th>Name</th>
            <td><input type="text" name="name"></td>
        </tr>
    </table>

    <p><button class="button button-primary">Add Holiday</button></p>
</form>

<hr>

<h2>Existing Holidays</h2>

<table class="widefat">
<thead>
<tr>
<th>Date</th>
<th>Name</th>
<th></th>
</tr>
</thead>
<tbody>

<?php foreach ($holidays as $h): ?>
<tr>
<td><?php echo esc_html($h->holiday_date); ?></td>
<td><?php echo esc_html($h->name); ?></td>
<td>
<?php
$delete_url = wp_nonce_url(
    admin_url('options-general.php?page=ecco-public-holidays&delete=' . $h->id),
    'ecco_delete_holiday_' . $h->id
);
?>
<a href="<?php echo esc_url($delete_url); ?>"
   onclick="return confirm('Delete this holiday?')">
   Delete
</a>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

</div>

<?php
}

function ecco_settings_page() {

    // Default leave types
    $default_leave_types = [
        ['label' => 'Annual', 'requires_image' => false],
        ['label' => 'Sick', 'requires_image' => true],
        ['label' => 'Unpaid', 'requires_image' => false],
    ];

    $leave_types = get_option('ecco_leave_types', $default_leave_types);

    // Save settings
    if (isset($_POST['save']) && check_admin_referer('ecco_save_settings')) {
        update_option('ecco_tenant_id', sanitize_text_field($_POST['tenant']));
        update_option('ecco_client_id', sanitize_text_field($_POST['client']));
        update_option('ecco_client_secret', sanitize_text_field($_POST['secret']));

        $new_leave_types = [];

        if (!empty($_POST['leave_types'])) {
            foreach ($_POST['leave_types'] as $i => $label) {
                $label = sanitize_text_field($label);
                if (!$label) continue;

                $new_leave_types[] = [
                    'label' => $label,
                    'requires_image' => !empty($_POST['leave_requires_image'][$i]),
                ];
            }
        }

        update_option('ecco_leave_types', $new_leave_types ?: $default_leave_types);

        echo '<div class="updated"><p>Settings saved</p></div>';
    }

    // Reset SharePoint cache
    if (isset($_POST['reset_cache']) && check_admin_referer('ecco_reset_cache')) {
        delete_option('ecco_site_id');
        delete_option('ecco_drive_map');
        echo '<div class="updated"><p>SharePoint cache cleared</p></div>';
    }
?>
<div class="wrap">
    <h1>ECCO Intranet – Microsoft SSO</h1>

    <form method="post">
        <?php wp_nonce_field('ecco_save_settings'); ?>

        <table class="form-table">
            <tr>
                <th>Tenant ID</th>
                <td><input type="text" name="tenant" value="<?php echo esc_attr(get_option('ecco_tenant_id')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Client ID</th>
                <td><input type="text" name="client" value="<?php echo esc_attr(get_option('ecco_client_id')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Client Secret</th>
                <td><input type="password" name="secret" value="<?php echo esc_attr(get_option('ecco_client_secret')); ?>" class="regular-text"></td>
            </tr>
        </table>

        <h2>Leave Types</h2>
        <table class="widefat">
            <thead>
            <tr>
                <th>Label</th>
                <th>Requires Attachment?</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($leave_types as $i => $lt): ?>
                <tr>
                    <td>
                        <input type="text" name="leave_types[<?php echo $i; ?>]" value="<?php echo esc_attr($lt['label']); ?>">
                    </td>
                    <td>
                        <input type="checkbox" name="leave_requires_image[<?php echo $i; ?>]" <?php checked($lt['requires_image']); ?>>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td><input type="text" name="leave_types[]" placeholder="New leave type"></td>
                <td><input type="checkbox" name="leave_requires_image[]"></td>
            </tr>
            </tbody>
        </table>

        <p><button class="button button-primary" name="save">Save Settings</button></p>
    </form>

    <hr>

    <form method="post">
        <?php wp_nonce_field('ecco_reset_cache'); ?>
        <button class="button" name="reset_cache">Clear SharePoint Cache</button>
    </form>
</div>
<?php
}
