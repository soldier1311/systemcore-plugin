<?php
if (!defined('ABSPATH')) exit;

class SystemCore_AJAX_Logs {

    public static function init() {

        // Load logs (last 3 days)
        add_action('wp_ajax_systemcore_load_logs_last3days', [__CLASS__, 'load_logs']);

        // Clear logs
        add_action('wp_ajax_systemcore_clear_logs', [__CLASS__, 'clear_logs']);
    }

    /**
     * Load logs from last 72 hours
     */
    public static function load_logs() {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_logs';

        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            wp_send_json([
                'success' => false,
                'data'    => "Logs table does not exist."
            ]);
        }

        $rows = $wpdb->get_results("
            SELECT id, level, context, message, created_at
            FROM {$table}
            WHERE created_at >= (NOW() - INTERVAL 72 HOUR)
            ORDER BY id DESC
            LIMIT 500
        ");

        wp_send_json([
            'success' => true,
            'data'    => $rows
        ]);
    }

    /**
     * Clear all logs
     */
    public static function clear_logs() {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_logs';

        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            wp_send_json([
                'success' => false,
                'data'    => "Logs table does not exist."
            ]);
        }

        $wpdb->query("TRUNCATE TABLE {$table}");

        wp_send_json([
            'success' => true,
            'data'    => "Logs cleared."
        ]);
    }
}

SystemCore_AJAX_Logs::init();
