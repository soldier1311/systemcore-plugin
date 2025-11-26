<?php

if (!defined('ABSPATH')) exit;

class SystemCore_Logger {

    /**
     * $level   : info / warning / error / debug / critical
     * $source  : fetch / scraper / ai / publisher / scheduler / system ...
     * $message : نص مختصر
     * $context : تفاصيل إضافية (JSON أو نص عادي)
     */
    public static function log($level, $message, $source = 'system', $context = '') {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_logs';

        // أمان بسيط للقيم
        $level   = substr((string) $level,   0, 20);
        $source  = substr((string) $source,  0, 50);
        $message = (string) $message;
        $context = (string) $context;

        if (empty($table)) {
            return;
        }

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

    // اختصارات جاهزة

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
}
