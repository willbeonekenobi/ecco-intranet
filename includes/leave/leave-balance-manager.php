<?php
if (!defined('ABSPATH')) exit;

/**
 * Create Leave Balance Table
 */
function ecco_create_leave_balance_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'ecco_leave_balances';
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        leave_type VARCHAR(100) NOT NULL,
        balance FLOAT NOT NULL DEFAULT 0,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_leave (user_id, leave_type)
    ) $charset_collate;";

    dbDelta($sql);
}


/**
 * Get Leave Types From Settings
 */
function ecco_get_leave_types() {

    $types = get_option('ecco_leave_types', []);

    if (!is_array($types)) {
        return [];
    }

    return $types;
}


/**
 * Default Balances
 */
function ecco_get_default_leave_balances() {
    return [
        'Annual'     => 21,
        'Personal'   => 30,
        'Sick'       => 10,
        'Maternity'  => 120,
        'Paternity'  => 120,
    ];
}


/**
 * Initialize Leave Balances For User
 */
function ecco_initialize_user_leave_balances($user_id) {

    global $wpdb;
    $table = $wpdb->prefix . 'ecco_leave_balances';

    $leave_types = ecco_get_leave_types();
    $defaults = ecco_get_default_leave_balances();

    if (empty($leave_types) || !is_array($leave_types)) {
        return;
    }

    foreach ($leave_types as $type_data) {

        if (!is_array($type_data) || empty($type_data['label'])) {
            continue;
        }

        $type = trim($type_data['label']);

        if (empty($type)) {
            continue;
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND leave_type = %s",
            $user_id,
            $type
        ));

        if (!$exists) {

            $default_balance = $defaults[$type] ?? 0;

            $wpdb->insert($table, [
                'user_id'      => $user_id,
                'leave_type'   => $type,
                'balance'      => $default_balance,
                'last_updated' => current_time('mysql')
            ]);
        }
    }
}


/**
 * Auto Initialize When User Registers
 */
add_action('user_register', function($user_id) {
    ecco_initialize_user_leave_balances($user_id);
});

/**
 * Check If Current User Is HR / Management (via Graph)
 */
function ecco_user_is_hr_or_management() {

    if (current_user_can('manage_options')) {
        return true;
    }

    $groups = ecco_graph_get('/me/memberOf');

    if (!$groups || empty($groups['value'])) {
        return false;
    }

    foreach ($groups['value'] as $group) {

        $name = strtolower($group['displayName'] ?? '');

        if (strpos($name, 'hr') !== false || 
            strpos($name, 'management') !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Add HR Leave Balance Admin Page
 */
add_action('admin_menu', function() {

    if (!ecco_user_is_hr_or_management()) {
        return;
    }

    add_menu_page(
        'Leave Balances',
        'Leave Balances',
        'read',
        'ecco-leave-balances',
        'ecco_render_leave_balance_admin',
        'dashicons-groups',
        30
    );
});

function ecco_render_leave_balance_admin() {

    if (!ecco_user_is_hr_or_management()) {
        wp_die('Access denied');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ecco_leave_balances';

    $users = get_users();

    echo '<div class="wrap">';
    echo '<h1>Leave Balances</h1>';

    echo '<form method="post">';
    echo '<select name="user_id">';
    foreach ($users as $user) {
        echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . '</option>';
    }
    echo '</select>';
    submit_button('Load');
    echo '</form>';

    if (isset($_POST['user_id'])) {

        $user_id = intval($_POST['user_id']);
        ecco_initialize_user_leave_balances($user_id);

        $balances = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));

        echo '<form method="post">';
        echo '<input type="hidden" name="save_user_id" value="' . $user_id . '">';

        foreach ($balances as $row) {
            echo '<p>';
            echo '<strong>' . esc_html($row->leave_type) . '</strong><br>';
            echo '<input type="number" step="0.5" name="balance[' . esc_attr($row->id) . ']" value="' . esc_attr($row->balance) . '">';
            echo '</p>';
        }

        submit_button('Save Balances');
        echo '</form>';
    }

    if (isset($_POST['save_user_id']) && isset($_POST['balance'])) {

        foreach ($_POST['balance'] as $id => $value) {

            $wpdb->update(
                $table,
                [
                    'balance' => floatval($value),
                    'last_updated' => current_time('mysql')
                ],
                ['id' => intval($id)]
            );
        }

        echo '<div class="updated"><p>Balances Updated</p></div>';
    }

    echo '</div>';
}