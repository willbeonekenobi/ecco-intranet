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
    if (isset($_POST['save'])) {
        update_option('ecco_tenant_id', sanitize_text_field($_POST['tenant']));
        update_option('ecco_client_id', sanitize_text_field($_POST['client']));
        update_option('ecco_client_secret', sanitize_text_field($_POST['secret']));
        echo '<div class="updated"><p>Settings saved</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>ECCO Intranet – Microsoft SSO</h1>

        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Tenant ID</th>
                    <td><input type="text" name="tenant" value="<?= esc_attr(get_option('ecco_tenant_id')) ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Client ID</th>
                    <td><input type="text" name="client" value="<?= esc_attr(get_option('ecco_client_id')) ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Client Secret</th>
                    <td><input type="password" name="secret" value="<?= esc_attr(get_option('ecco_client_secret')) ?>" class="regular-text"></td>
                </tr>
            </table>

            <p>
                <button class="button button-primary" name="save">Save Settings</button>
            </p>
        </form>
    </div>
    <?php
}
