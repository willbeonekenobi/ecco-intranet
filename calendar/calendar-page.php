<?php
if (!defined('ABSPATH')) exit;

function ecco_render_group_calendar_page() {
    ob_start();
    ?>
    <div id="ecco-calendar-wrapper">
        <h2>Group Calendar</h2>

        <select id="ecco-group-selector">
            <option value="">Select Group</option>
        </select>

        <div id="ecco-calendar"></div>
    </div>

    <?php
    return ob_get_clean();
}

add_shortcode('ecco_group_calendar', 'ecco_render_group_calendar_page');