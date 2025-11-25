<?php
if (!defined('ABSPATH')) exit;

/**
 * Dashboard stats
 */
add_action('wp_ajax_systemcore_dashboard_stats', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Not allowed']);
    }

    check_ajax_referer('systemcore_admin_nonce', 'nonce');

    global $wpdb;

    $table_queue = $wpdb->prefix . 'systemcore_queue';
    $table_logs  = $wpdb->prefix . 'systemcore_logs';

    $total_queue = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_queue");
    $pending     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_queue WHERE processed = 0");
    $processed   = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_queue WHERE processed = 1");
    $total_logs  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_logs");

    wp_send_json_success([
        'total_queue'     => $total_queue,
        'pending_queue'   => $pending,
        'processed_queue' => $processed,
        'total_logs'      => $total_logs,
        'last_fetch'      => get_option('systemcore_last_fetch', '—'),
        'last_publish'    => get_option('systemcore_last_publish', '—'),
    ]);
});
