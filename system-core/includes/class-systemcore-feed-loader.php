<?php

if (!defined('ABSPATH')) exit;

class SystemCore_Feed_Loader {

    /**
     * تحميل الخلاصات وإضافة الروابط إلى الـ Queue
     */
    public static function load_feeds($lang = 'ar') {

        SystemCore_Logger::info('SystemCore_Feed_Loader::load_feeds() started');

        // تحميل دوال الـ RSS إذا لم تكن محمّلة
        if (!function_exists('fetch_feed')) {
            require_once ABSPATH . WPINC . '/feed.php';
        }

        global $wpdb;

        $queue_table = $wpdb->prefix . 'systemcore_queue';
        $feeds_table = $wpdb->prefix . 'systemcore_feed_sources';

        /*
        |--------------------------------------------------------------------------
        | 1) تنظيف Queue من المقالات الأقدم من 6 ساعات
        |--------------------------------------------------------------------------
        */
        $deleted = $wpdb->query(
            "DELETE FROM {$queue_table} WHERE created_at < (NOW() - INTERVAL 6 HOUR)"
        );
        SystemCore_Logger::info('Old queue cleanup done. Deleted rows: ' . (int) $deleted);

        /*
        |--------------------------------------------------------------------------
        | 2) جلب مصادر RSS من قاعدة البيانات (مع الـ ID)
        |--------------------------------------------------------------------------
        */
        $feeds = $wpdb->get_results("SELECT id, source_name, feed_url FROM {$feeds_table}");

        if (empty($feeds)) {
            SystemCore_Logger::warning("No feed sources found in database.", "fetch");
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) معالجة كل مصدر RSS
        |--------------------------------------------------------------------------
        */
        foreach ($feeds as $feed) {

            $feed_id     = (int) $feed->id;
            $feed_name   = trim($feed->source_name);
            $feed_url    = esc_url_raw($feed->feed_url);

            if (empty($feed_url)) {
                SystemCore_Logger::warning("Empty feed_url for feed ID {$feed_id}", "fetch");
                continue;
            }

            SystemCore_Logger::info("Fetching RSS feed [{$feed_id}] {$feed_name}: {$feed_url}");

            $rss = fetch_feed($feed_url);

            if (is_wp_error($rss)) {
                SystemCore_Logger::error(
                    "RSS ERROR for {$feed_url}: " . $rss->get_error_message(),
                    "fetch"
                );
                continue;
            }

            $maxitems = $rss->get_item_quantity(20);
            SystemCore_Logger::info("Items found: {$maxitems} in {$feed_url}");

            if ($maxitems <= 0) {
                SystemCore_Logger::warning("NO ITEMS returned for {$feed_url}", "fetch");
                continue;
            }

            $items = $rss->get_items(0, $maxitems);

            /*
            |--------------------------------------------------------------------------
            | 4) المرور على كل مقالة داخل RSS
            |--------------------------------------------------------------------------
            */
            foreach ($items as $item) {

                $title = trim(wp_strip_all_tags($item->get_title()));
                $link  = esc_url_raw($item->get_permalink());

                if (empty($link)) {
                    SystemCore_Logger::warning("Skipped item with empty link in {$feed_url}");
                    continue;
                }

                // وقت النشر من الـ RSS أو الوقت الحالي كاحتياط
                $pub = $item->get_date('Y-m-d H:i:s');
                if (empty($pub)) {
                    $pub = current_time('mysql');
                }

                $published_time = strtotime($pub);

                // تخطي المقالات الأقدم من 6 ساعات (يمكنك تعديل المدة لاحقاً)
                if ($published_time !== false && $published_time < strtotime('-6 hours')) {
                    SystemCore_Logger::info("Skipped OLD article (older than 6h): {$title}");
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | التحقق من التكرار في Queue بناءً على URL
                |--------------------------------------------------------------------------
                */
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$queue_table} WHERE url = %s LIMIT 1",
                        $link
                    )
                );

                if ($exists) {
                    SystemCore_Logger::info("Skipped duplicate URL in queue: {$link}");
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | 5) إدخال المقال في Queue عبر SystemCore_Queue مع feed_id الصحيح
                |--------------------------------------------------------------------------
                */
                $added = false;

                if (class_exists('SystemCore_Queue')) {
                    $added = SystemCore_Queue::add($feed_id, $link, $lang);
                } else {
                    // Fallback آمن جداً
                    $added = $wpdb->insert(
                        $queue_table,
                        [
                            'feed_id'    => $feed_id,
                            'url'        => $link,
                            'lang'       => $lang,
                            'status'     => 'pending',
                            'attempts'   => 0,
                            'created_at' => current_time('mysql'),
                        ]
                    );
                }

                if ($added) {
                    SystemCore_Logger::info("Queued article from [{$feed_name}]: {$title} ({$link})");
                } else {
                    SystemCore_Logger::error("Failed to queue article: {$title} ({$link})");
                }
            }
        }

        SystemCore_Logger::info("SystemCore_Feed_Loader::load_feeds() END");
    }
}
