<?php

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

    // Save settings
    if (isset($_POST['save']) && check_admin_referer('ecco_save_settings')) {
        update_option('ecco_tenant_id', sanitize_text_field($_POST['tenant']));
        update_option('ecco_client_id', sanitize_text_field($_POST['client']));
        update_option('ecco_client_secret', sanitize_text_field($_POST['secret']));
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
                    <td>
                        <input type="text" name="tenant"
                               value="<?php echo esc_attr(get_option('ecco_tenant_id')); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th>Client ID</th>
                    <td>
                        <input type="text" name="client"
                               value="<?php echo esc_attr(get_option('ecco_client_id')); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th>Client Secret</th>
                    <td>
                        <input type="password" name="secret"
                               value="<?php echo esc_attr(get_option('ecco_client_secret')); ?>"
                               class="regular-text">
                    </td>
                </tr>
            </table>

            <p>
                <button class="button button-primary" name="save">
                    Save Settings
                </button>
            </p>
        </form>

        <hr>

        <form method="post">
            <?php wp_nonce_field('ecco_reset_cache'); ?>

            <p>
                <button class="button" name="reset_cache">
                    Clear SharePoint Cache
                </button>
            </p>
        </form>
    </div>
    <?php
}
