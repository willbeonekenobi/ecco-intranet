<?php
if (!defined('ABSPATH')) exit;

add_action('ecco_training_cron','ecco_training_check');

function ecco_training_check(){

    global $wpdb;

    $rows = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}ecco_training
        WHERE expiry <= DATE_ADD(NOW(), INTERVAL 30 DAY)
        AND reminder_sent=0
    ");

    foreach($rows as $r){

        $user = get_user_by('id',$r->user_id);

        wp_mail(
            $user->user_email,
            'Training Expiring Soon',
            'Your '.$r->course.' certification expires soon.'
        );

        $wpdb->update(
            $wpdb->prefix.'ecco_training',
            ['reminder_sent'=>1],
            ['id'=>$r->id]
        );

    }
}

if (!wp_next_scheduled('ecco_training_cron')){
    wp_schedule_event(time(),'daily','ecco_training_cron');
}
