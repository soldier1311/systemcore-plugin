<?php
if (!defined('ABSPATH')) exit;

require_once SYSTEMCORE_PLUGIN_PATH . 'includes/security.php';

add_action('wp_ajax_systemcore_manual_publish', function () {

    SystemCore_Security::verify();

    if (!class_exists('SystemCore_Publisher')) {
        wp_send_json_error(['message' => 'Publisher class not loaded']);
    }

    try {
        $opts = function_exists('systemcore_get_publisher_settings')
            ? systemcore_get_publisher_settings()
            : [];

        $lang = isset($opts['default_lang']) ? SystemCore_Security::clean($opts['default_lang']) : 'ar';

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
