<?php

if (!defined('ABSPATH')) exit;

class SystemCore_Database {

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'create_tables']);
    }

    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        /*
        ---------------------------------------------------
        1) NEW Queue Table — wp_systemcore_queue
        ---------------------------------------------------
        */
        $queue_table = $wpdb->prefix . 'systemcore_queue';

        $sql = "CREATE TABLE $queue_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            feed_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            url TEXT NOT NULL,
            lang VARCHAR(10) NOT NULL DEFAULT 'ar',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            error_message LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_feed (feed_id)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);


        /*
        ---------------------------------------------------
        2) Logs Table — wp_systemcore_logs
        ---------------------------------------------------
        */
        $logs_table = $wpdb->prefix . 'systemcore_logs';
        
        $sql = "CREATE TABLE $logs_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(50) NOT NULL DEFAULT 'info',
            source VARCHAR(50) NOT NULL DEFAULT 'system',
            message LONGTEXT NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";
        
        dbDelta($sql);


        /*
        ---------------------------------------------------
        3) Feed Sources Table — wp_systemcore_feed_sources
        ---------------------------------------------------
        */
        $feed_sources_table = $wpdb->prefix . 'systemcore_feed_sources';

        $sql = "CREATE TABLE $feed_sources_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_name VARCHAR(255) NOT NULL,
            feed_url TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        dbDelta($sql);
    }

}

SystemCore_Database::init();
