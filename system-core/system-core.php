<?php
/**
 * Plugin Name: SystemCore
 * Description: Internal system module.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;
define('SYSTEMCORE_PATH', plugin_dir_path(__FILE__));
define('SYSTEMCORE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SYSTEMCORE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load init.php
require_once SYSTEMCORE_PLUGIN_PATH . 'includes/init.php';


// Load CSS & JS only for SystemCore pages
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'systemcore') !== false) {

        wp_enqueue_style(
            'systemcore-admin',
            SYSTEMCORE_PLUGIN_URL . 'assets/systemcore-admin.css'
        );

        wp_enqueue_script(
            'systemcore-admin',
            SYSTEMCORE_PLUGIN_URL . 'assets/systemcore-admin.js?v=5',
            ['jquery'],
            time(), // أفضل حل، يجبر المتصفح تحميل النسخة الجديدة دائماً
            true
        );


        // مهم جداً — هذا الذي كان ناقص!
        wp_localize_script(
            'systemcore-admin',
            'SystemCoreAdmin',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('systemcore_admin_nonce'),
            ]
        );
    }
});
