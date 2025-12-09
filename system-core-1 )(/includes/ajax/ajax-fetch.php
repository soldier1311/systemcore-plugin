<?php
if (!defined('ABSPATH')) exit;

require_once SYSTEMCORE_PLUGIN_PATH . 'includes/security.php';

add_action('wp_ajax_systemcore_fetch_now', function () {

    // يعتمد على security.php (يفضّل أن يتحقق من nonce + صلاحية المستخدم)
    SystemCore_Security::verify();

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Not allowed']);
    }

    $lang = isset($_POST['lang']) ? sanitize_key(wp_unslash($_POST['lang'])) : 'ar';
    if (!in_array($lang, ['ar','fr','de'], true)) {
        $lang = 'ar';
    }

    if (!class_exists('SystemCore_Feed_Loader')) {
        wp_send_json_error(['message' => 'Feed Loader class not found']);
    }

    if (class_exists('SystemCore_Logger')) {
        SystemCore_Logger::info('Manual Fetch Trigger (AJAX)', 'fetch', wp_json_encode(['lang' => $lang]));
    }

    SystemCore_Feed_Loader::load_feeds($lang);

    wp_send_json_success(['message' => 'Fetch completed.', 'lang' => $lang]);
});
