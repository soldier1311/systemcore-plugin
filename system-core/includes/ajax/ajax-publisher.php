<?php
if (!defined('ABSPATH')) exit;

/**
 * Manual Publish Batch (AJAX)
 * Called from dashboard "Publish Batch" button
 */
add_action('wp_ajax_systemcore_manual_publish', function () {

    // Permission check
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Not allowed']);
    }

    // Security nonce
    check_ajax_referer('systemcore_admin_nonce', 'nonce');

    // Ensure publisher class exists
    if (!class_exists('SystemCore_Publisher')) {
        wp_send_json_error(['message' => 'Publisher class not loaded']);
    }

    try {
        /** Load publisher settings */
        $opts = function_exists('systemcore_get_publisher_settings')
            ? systemcore_get_publisher_settings()
            : [];

        $lang = $opts['default_lang'] ?? 'ar';

        /** Run batch */
        $result = SystemCore_Publisher::run_batch($lang);

        wp_send_json_success([
            'message'   => $result['message'] ?? 'Batch finished',
            'published' => $result['published'] ?? [],
            'success'   => $result['success'] ?? true,
        ]);

    } catch (\Throwable $e) {

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::error(
                'Publisher fatal error: ' . $e->getMessage(),
                'publisher'
            );
        }

        wp_send_json_error([
            'message' => 'Publisher error: ' . $e->getMessage(),
        ]);
    }
});
