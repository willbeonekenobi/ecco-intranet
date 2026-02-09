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
<?php }
