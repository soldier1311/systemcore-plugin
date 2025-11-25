<?php
if (!defined('ABSPATH')) exit;

/**
 * Manual Fetch Now
 */
add_action('wp_ajax_systemcore_fetch_now', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Not allowed']);
    }

    check_ajax_referer('systemcore_admin_nonce', 'nonce');

    if (!class_exists('SystemCore_Feed_Loader')) {
        wp_send_json_error(['message' => 'Feed Loader class not found']);
    }

    if (class_exists('SystemCore_Logger')) {
        SystemCore_Logger::info('Manual Fetch Trigger (AJAX)', 'fetch');
    }

    SystemCore_Feed_Loader::load_feeds();

    wp_send_json_success(['message' => 'Fetch completed.']);
});
