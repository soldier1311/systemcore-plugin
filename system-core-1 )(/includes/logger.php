<?php

if (!defined('ABSPATH')) exit;

class SystemCore_Logger {

    /**
     * Write log entry into DB
     */
    public static function log($level, $message, $source = 'system', $context = '') {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_logs';
        if (empty($table)) {
            return;
        }

        $level   = substr((string) $level,   0, 20);
        $source  = substr((string) $source,  0, 50);
        $message = (string) $message;
        $context = (string) $context;

        self::auto_cleanup();

        $wpdb->insert(
            $table,
            [
                'level'      => $level,
                'source'     => $source,
                'message'    => $message,
                'context'    => $context,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Delete logs older than 3 days (runs every 12 hours)
     */
    private static function auto_cleanup() {
        if (get_transient('systemcore_logs_cleanup')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'systemcore_logs';

        $wpdb->query("
            DELETE FROM {$table}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
        ");

        set_transient('systemcore_logs_cleanup', 1, 12 * HOUR_IN_SECONDS);
    }

    /** Shorthand helpers */

    public static function info($message, $source = 'system', $context = '') {
        self::log('info', $message, $source, $context);
    }

    public static function warning($message, $source = 'system', $context = '') {
        self::log('warning', $message, $source, $context);
    }

    public static function error($message, $source = 'system', $context = '') {
        self::log('error', $message, $source, $context);
    }

    public static function debug($message, $source = 'system', $context = '') {
        self::log('debug', $message, $source, $context);
    }

    public static function critical($message, $source = 'system', $context = '') {
        self::log('critical', $message, $source, $context);
    }
}
