<?php
if (!defined('ABSPATH')) exit;

class SystemCore_Feed_Loader {

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
                SystemCore_Logger::error('Feed Loader: missing tables', 'feed', wp_json_encode([
                    'queue_table' => $queue_table,
                    'feeds_table' => $feeds_table,
                ]));
            }
            return;
        }

        // Cleanup queue (pending older than 12 hours)
        $wpdb->query(
            "DELETE FROM {$queue_table}
             WHERE status='pending'
             AND created_at < (NOW() - INTERVAL 12 HOUR)"
        );

        // Load active feed sources  **FIXED HERE**
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

            $feed_url = !empty($feed->feed_url) ? trim((string)$feed->feed_url) : '';
            if (empty($feed_url)) {
                continue;
            }

            $feed_type = !empty($feed->feed_type)
                ? strtolower(trim((string)$feed->feed_type))
                : 'rss';

            switch ($feed_type) {
                case 'xml':
                    self::process_xml_feed($feed, $lang, $queue_table);
                    break;

                case 'json':
                    self::process_json_feed($feed, $lang, $queue_table);
                    break;

                case 'rss':
                default:
                    self::process_rss_feed($feed, $lang, $queue_table);
                    break;
            }
        }

        // Enforce queue limits
        if (class_exists('SystemCore_Priority_Engine') && method_exists('SystemCore_Priority_Engine', 'enforce_queue_limits')) {
            SystemCore_Priority_Engine::enforce_queue_limits();
        }

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::info('Feed Loader: completed', 'feed', wp_json_encode(['lang' => $lang]));
        }
    }

    protected static function get_allowed_languages($feed) {

        $level = isset($feed->priority_level) ? (int)$feed->priority_level : 4;

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

    /* ============================================================
     * RSS FEED
     * ============================================================ */
    protected static function process_rss_feed($feed, $lang, $queue_table) {

        if (!function_exists('fetch_feed')) {
            require_once ABSPATH . WPINC . '/feed.php';
        }

        $feed_id   = (int)$feed->id;
        $feed_name = trim((string)$feed->source_name);
        $feed_url  = esc_url_raw((string)$feed->feed_url);

        $rss = fetch_feed($feed_url);

        if (is_wp_error($rss)) {
            self::insert_fetch_log($feed_id, $feed_name, $feed_url, 'rss', 'error', $rss->get_error_message());
            return;
        }

        $maxitems = (int)$rss->get_item_quantity(10);

        self::insert_fetch_log($feed_id, $feed_name, $feed_url, 'rss', 'success', 'Fetched items: ' . $maxitems);

        if ($maxitems <= 0) return;

        $items = $rss->get_items(0, $maxitems);

        foreach ($items as $item) {

            $title = trim(wp_strip_all_tags((string)$item->get_title()));
            $link  = esc_url_raw((string)$item->get_permalink());
            if (empty($link)) continue;

            $priority = 0;
            if (class_exists('SystemCore_Priority_Engine')) {
                $item_data = [
                    'title'   => $title,
                    'pubDate' => $item->get_date('Y-m-d H:i:s'),
                ];

                $score = SystemCore_Priority_Engine::calculate_priority($feed, $item_data, $lang);

                if (SystemCore_Priority_Engine::should_reject($score)) {
                    continue;
                }

                if ($score > 0) $priority = (int)$score;
            }

            self::push_to_queue($feed_id, $feed_name, $link, $title, $lang, $queue_table, $priority);
        }
    }

    /* ============================================================
     * XML FEED
     * ============================================================ */
    protected static function process_xml_feed($feed, $lang, $queue_table) {

        $feed_id   = (int)$feed->id;
        $feed_name = trim((string)$feed->source_name);
        $feed_url  = esc_url_raw((string)$feed->feed_url);

        $body = self::fetch_remote_body($feed_url);
        if ($body === false) {
            self::insert_fetch_log($feed_id, $feed_name, $feed_url, 'xml', 'error', 'Failed to fetch XML body');
            return;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);

        if (!$xml) {
            self::insert_fetch_log($feed_id, $feed_name, $feed_url, 'xml', 'error', 'Invalid XML');
            return;
        }

        self::insert_fetch_log($feed_id, $feed_name, $feed_url, 'xml', 'success', 'XML parsed');

        $items = [];
        if (!empty($xml->channel->item)) {
            $items = $xml->channel->item;
        } elseif (!empty($xml->entry)) {
            $items = $xml->entry;
        } elseif (!empty($xml->item)) {
            $items = $xml->item;
        }

        if (empty($items)) return;

        $count = 0;
        foreach ($items as $item) {

            $title = !empty($item->title) ? trim((string)$item->title) : '';
            $link  = '';

            if (!empty($item->link)) {
                if (isset($item->link['href'])) {
                    $link = (string)$item->link['href'];
                } else {
                    $link = (string)$item->link;
                }
            }

            $link = esc_url_raw(trim($link));
            if (empty($link)) continue;

            $priority = 0;
            if (class_exists('SystemCore_Priority_Engine')) {
                $item_data = [
                    'title'   => $title,
                    'pubDate' => !empty($item->pubDate) ? (string)$item->pubDate : '',
                ];

                $score = SystemCore_Priority_Engine::calculate_priority($feed, $item_data, $lang);

                if (SystemCore_Priority_Engine::should_reject($score)) {
                    continue;
                }

                if ($score > 0) $priority = (int)$score;
            }

            self::push_to_queue($feed_id, $feed_name, $link, $title, $lang, $queue_table, $priority);

            $count++;
            if ($count >= 10) break;
        }
    }

    /* ============================================================
     * JSON FEED
     * ============================================================ */
    protected static function process_json_feed($feed, $lang, $queue_table) {

        $feed_id   = (int)$feed->id;
        $feed_name = trim((string)$feed->source_name);
        $feed_url  = esc_url_raw((string)$feed->feed_url);

        $body = self::fetch_remote_body($feed_url);
        if ($body === false) {
            self::insert_fetch_log($feed_id, $feed_name, $feed_url, 'json', 'error', 'Failed to fetch JSON body');
            return;
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            self::insert_fetch_log($feed_id, $feed_name, $feed_url, 'json', 'error', 'Invalid JSON');
            return;
        }

        self::insert_fetch_log($feed_id, $feed_name, $feed_url, 'json', 'success', 'JSON parsed');

        $items = [];
        if (!empty($feed->json_path)) {
            $items = self::get_by_path($json, trim((string)$feed->json_path));
        } else {
            if (!empty($json['items']) && is_array($json['items'])) {
                $items = $json['items'];
            } elseif (!empty($json['data']) && is_array($json['data'])) {
                $items = $json['data'];
            } else {
                $items = $json;
            }
        }

        if (!is_array($items)) return;

        $count = 0;
        foreach ($items as $item) {

            if (!is_array($item)) continue;

            $title = '';
            $link  = '';

            $title_key = !empty($feed->json_title_key) ? (string)$feed->json_title_key : 'title';
            $link_key  = !empty($feed->json_link_key)  ? (string)$feed->json_link_key  : 'link';

            if (isset($item[$title_key])) $title = trim((string)$item[$title_key]);
            if (isset($item[$link_key]))  $link  = trim((string)$item[$link_key]);

            $link = esc_url_raw($link);
            if (empty($link)) continue;

            $priority = 0;
            if (class_exists('SystemCore_Priority_Engine')) {
                $item_data = [
                    'title'   => $title,
                    'pubDate' => !empty($item['pubDate']) ? (string)$item['pubDate'] : '',
                ];

                $score = SystemCore_Priority_Engine::calculate_priority($feed, $item_data, $lang);

                if (SystemCore_Priority_Engine::should_reject($score)) {
                    continue;
                }

                if ($score > 0) $priority = (int)$score;
            }

            self::push_to_queue($feed_id, $feed_name, $link, $title, $lang, $queue_table, $priority);

            $count++;
            if ($count >= 10) break;
        }
    }

    /**
     * SINGLE push_to_queue()
     */
    protected static function push_to_queue(
        $feed_id,
        $feed_name,
        $link,
        $title,
        $lang,
        $queue_table,
        $priority = 0
    ) {
        global $wpdb;

        $link  = esc_url_raw(trim((string)$link));
        $title = trim((string)$title);

        if (empty($link)) return;

        // Skip if already published (dedupe table)
        if (class_exists('SystemCore_Dedupe') && method_exists('SystemCore_Dedupe', 'exists_url')) {
            if (SystemCore_Dedupe::exists_url($link)) {
                return;
            }
        }

        // Avoid duplicates inside queue itself (any status)
        $exists_queue = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$queue_table} WHERE url = %s LIMIT 1",
                $link
            )
        );
        if ($exists_queue) return;

        // Add to queue
        $added = false;

        if (class_exists('SystemCore_Queue') && method_exists('SystemCore_Queue', 'add')) {
            $added = (bool) SystemCore_Queue::add((int)$feed_id, $link, (string)$lang, (int)$priority);
        } else {
            $added = (bool) $wpdb->insert(
                $queue_table,
                [
                    'feed_id'    => (int)$feed_id,
                    'url'        => $link,
                    'lang'       => (string)$lang,
                    'status'     => 'pending',
                    'priority'   => (int)$priority,
                    'attempts'   => 0,
                    'created_at' => current_time('mysql')
                ]
            );
        }

        if ($added && class_exists('SystemCore_Logger')) {
            SystemCore_Logger::info(
                'Feed Loader: queued',
                'feed',
                wp_json_encode([
                    'feed_id'    => (int)$feed_id,
                    'feed_name'  => (string)$feed_name,
                    'lang'       => (string)$lang,
                    'priority'   => (int)$priority,
                    'url'        => (string)$link,
                    'title'      => (string)$title,
                ])
            );
        }
    }

    /* ============================================================
     * HELPERS
     * ============================================================ */
    protected static function fetch_remote_body($url) {

        $resp = wp_remote_get($url, ['timeout' => 20]);
        if (is_wp_error($resp)) return false;

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return false;

        $body = wp_remote_retrieve_body($resp);
        if (empty($body)) return false;

        return $body;
    }

    protected static function insert_fetch_log($feed_id, $feed_name, $feed_url, $feed_type, $status, $message) {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_fetch_log';
        if (empty($table)) return;

        $wpdb->insert(
            $table,
            [
                'feed_id'    => (int)$feed_id,
                'feed_name'  => (string)$feed_name,
                'feed_url'   => (string)$feed_url,
                'feed_type'  => (string)$feed_type,
                'status'     => (string)$status,
                'message'    => (string)$message,
                'created_at' => current_time('mysql'),
            ]
        );
    }

    protected static function get_by_path($data, $path) {
        if (!is_array($data)) return null;

        $path = trim((string)$path);
        if ($path === '') return $data;

        $parts = array_filter(array_map('trim', explode('.', $path)));
        if (empty($parts)) return $data;

        $current = $data;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }
}
