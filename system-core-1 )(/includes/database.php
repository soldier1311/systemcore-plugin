<?php
if (!defined('ABSPATH')) exit;

class SystemCore_Database {

    const DB_VERSION = '1.0.0';
    const OPTION_KEY = 'systemcore_db_version';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'maybe_update']);
    }

    public static function maybe_update() {

        $installed = get_option(self::OPTION_KEY, '');

        if ($installed !== self::DB_VERSION) {

            self::create_tables();
            self::upgrade_tables();

            update_option(self::OPTION_KEY, self::DB_VERSION);
        }
    }

    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        /* ==========================
           QUEUE
        ========================== */
        $table = $wpdb->prefix . 'systemcore_queue';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            feed_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            url TEXT NOT NULL,
            title TEXT NULL,
            lang VARCHAR(10) NOT NULL DEFAULT 'ar',

            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            priority INT NOT NULL DEFAULT 0,
            attempts INT NOT NULL DEFAULT 0,

            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            error_message LONGTEXT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            started_at DATETIME NULL DEFAULT NULL,
            finished_at DATETIME NULL DEFAULT NULL,

            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_feed (feed_id),
            KEY idx_priority (priority)
        ) {$charset};";

        dbDelta($sql);

        /* ==========================
           LOGS
        ========================== */
        $table = $wpdb->prefix . 'systemcore_logs';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(50) NOT NULL DEFAULT 'info',
            source VARCHAR(50) NOT NULL DEFAULT 'system',
            message LONGTEXT NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset};";

        dbDelta($sql);

        /* ==========================
           FEED SOURCES
        ========================== */
        $table = $wpdb->prefix . 'systemcore_feed_sources';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_name VARCHAR(255) NOT NULL,
            feed_url TEXT NOT NULL,
            feed_type VARCHAR(20) NOT NULL DEFAULT 'rss',

            xml_root_path VARCHAR(255) NULL,
            xml_mapping LONGTEXT NULL,

            json_root_path VARCHAR(255) NULL,
            json_mapping LONGTEXT NULL,

            priority_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
            is_main_source TINYINT(1) NOT NULL DEFAULT 0,

            last_checked DATETIME NULL DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_feed_type (feed_type),
            KEY idx_active (active),
            KEY idx_priority_level (priority_level),
            KEY idx_main_source (is_main_source)
        ) {$charset};";

        dbDelta($sql);

        /* ==========================
           DEDUPE
        ========================== */
        $table = $wpdb->prefix . 'systemcore_dedupe';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url TEXT NOT NULL,
            title TEXT NULL,

            title_hash VARCHAR(64) NULL,
            content_hash VARCHAR(64) NULL,
            image_hash VARCHAR(64) NULL,

            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_type VARCHAR(20) NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_title_hash (title_hash),
            KEY idx_content_hash (content_hash),
            KEY idx_image_hash (image_hash),
            KEY idx_created_at (created_at)
        ) {$charset};";

        dbDelta($sql);
    }

    public static function upgrade_tables() {
        global $wpdb;

        $queue = $wpdb->prefix . 'systemcore_queue';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$queue}'") === $queue) {

            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$queue} LIKE 'title'");
            if (!$exists) {
                $wpdb->query("ALTER TABLE {$queue} ADD COLUMN title TEXT NULL AFTER url");
            }
        }
    }
}

SystemCore_Database::init();
