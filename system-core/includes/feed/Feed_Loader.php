<?php
if (!defined('ABSPATH')) exit;

class SystemCore_Feed_Loader {

    /**
     * Entry point: load feeds for a given language
     */
    public static function load_feeds($lang = 'ar') {

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::info('Feed Loader: start', 'feed', wp_json_encode(['lang' => $lang]));
        }

        global $wpdb;

        $queue_table = $wpdb->prefix . 'systemcore_queue';
        $feeds_table = $wpdb->prefix . 'systemcore_feed_sources';

        // Ensure tables exist
        $queue_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $queue_table));
        $feeds_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $feeds_table));

        if ($queue_exists !== $queue_table || $feeds_exists !== $feeds_table) {
            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::error(
                    'Feed Loader: missing tables',
                    'feed',
                    wp_json_encode([
                        'queue_table' => $queue_table,
                        'feeds_table' => $feeds_table,
                    ])
                );
            }
            return;
        }

        // Cleanup queue (pending older than 12 hours)
        $wpdb->query(
            "DELETE FROM {$queue_table}
             WHERE status='pending'
             AND created_at < (NOW() - INTERVAL 12 HOUR)"
        );

        // Load active feed sources
        $feeds = $wpdb->get_results(
            "SELECT * FROM {$feeds_table}
             WHERE active = 1
             ORDER BY priority_level ASC"
        );

        if (empty($feeds)) {
            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::warning('Feed Loader: no active feeds found', 'feed');
            }
            return;
        }

        foreach ($feeds as $feed) {

            $allowed_langs = self::get_allowed_languages($feed);
            if (!in_array($lang, $allowed_langs, true)) {
                continue;
            }

            $feed_url = !empty($feed->feed_url) ? trim((string) $feed->feed_url) : '';
            if (empty($feed_url)) {
                continue;
            }

            $feed_type = !empty($feed->feed_type)
                ? strtolower(trim((string) $feed->feed_type))
                : 'rss';

            switch ($feed_type) {
                case 'xml':
                    SystemCore_Feed_Processors::process_xml_feed($feed, $lang, $queue_table);
                    break;

                case 'json':
                    SystemCore_Feed_Processors::process_json_feed($feed, $lang, $queue_table);
                    break;

                case 'rss':
                default:
                    SystemCore_Feed_Processors::process_rss_feed($feed, $lang, $queue_table);
                    break;
            }
        }

        // Enforce queue limits
        if (class_exists('SystemCore_Priority_Engine')
            && method_exists('SystemCore_Priority_Engine', 'enforce_queue_limits')) {

            SystemCore_Priority_Engine::enforce_queue_limits();
        }

        /**
         * ⬅⬅ تسجيل آخر تشغيل للنظام
         * هذا السطر هو الوحيد المطلوب
         */
        update_option('systemcore_last_feed_loader_run', current_time('timestamp'));

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::info(
                'Feed Loader: completed',
                'feed',
                wp_json_encode(['lang' => $lang])
            );
        }
    }

    /**
     * Map feed priority / main-source flags to allowed languages
     */
    protected static function get_allowed_languages($feed) {

        $level = isset($feed->priority_level) ? (int) $feed->priority_level : 4;

        if (!empty($feed->is_main_source) || $level === 0) {
            return ['ar', 'fr', 'de'];
        }

        if ($level === 1) {
            return ['ar', 'fr', 'de'];
        }

        if ($level === 2) {
            $extra_lang = 'fr';
            return ['ar', $extra_lang];
        }

        return ['ar'];
    }
}
