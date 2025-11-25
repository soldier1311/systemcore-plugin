<?php
if (!defined('ABSPATH')) exit;

/**
 * SystemCore Publisher – Final Version (2025)
 * يعتمد على:
 * - SystemCore_Scraper
 * - SystemCore_ContentExtractor
 * - SystemCore_ContentCleaner
 * - SystemCore_AI_Engine
 *
 * قواعد أساسية:
 * 1) إذا فشل Scraper → لا نشر
 * 2) إذا فشل AI أو لم يُرجع محتوى كافٍ → لا نشر
 * 3) الصورة دائماً من Scraper (ليست من AI)
 * 4) اللغة المستهدفة للمحتوى النهائي: العربية دائماً حالياً
 */
class SystemCore_Publisher {

    /**
     * تشغيل دفعة نشر
     * $lang = رمز لغة التصنيفات (ar / fr / de) – للمستقبل
     */
    public static function run_batch($lang = null) {
        global $wpdb;

        // =========================
        // 0) تحميل إعدادات الناشر
        // =========================
        if (function_exists('systemcore_get_publisher_settings')) {
            $settings = systemcore_get_publisher_settings();
        } else {
            $settings = self::get_local_defaults();
        }

        $status        = isset($settings['status']) ? $settings['status'] : 'publish';
        $limit         = isset($settings['posts_per_batch']) ? max(1, (int) $settings['posts_per_batch']) : 5;
        $default_thumb = isset($settings['default_thumb_id']) ? (int) $settings['default_thumb_id'] : 0;
        $authors       = (!empty($settings['publishers']) && is_array($settings['publishers']))
                            ? $settings['publishers']
                            : [1];

        $fixed_category_id = isset($settings['category_id']) ? (int) $settings['category_id'] : 0;

        $enable_ai_rewrite   = !empty($settings['enable_ai_rewrite']);    // من إعدادات الناشر
        $enable_ai_category  = !empty($settings['enable_ai_category']);

        // لغة الناشر (لجلب التصنيفات فقط)
        if (!$lang) {
            $lang = isset($settings['default_lang']) ? $settings['default_lang'] : 'ar';
        }

        // =========================
        // 0.1) التحقق من إعدادات AI
        // =========================
        if (!class_exists('SystemCore_AI_Engine')) {
            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::error('AI Engine class not found. Publishing aborted.', 'publisher');
            }
            return [
                'success'   => false,
                'message'   => 'AI Engine not available – publishing stopped.',
                'published' => []
            ];
        }

        $ai_settings = get_option('systemcore_ai_settings', []);
        $ai_enabled  = isset($ai_settings['enabled']) ? ($ai_settings['enabled'] === 'yes') : false;
        $ai_key      = !empty($ai_settings['api_key']) ? trim($ai_settings['api_key']) : '';

        // إذا كان الـ AI معطل أو بدون مفتاح → لا نشر إطلاقاً
        if (!$ai_enabled || empty($ai_key) || !$enable_ai_rewrite) {
            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::warning('AI disabled or missing key or AI rewrite disabled. Publishing aborted.', 'publisher');
            }
            return [
                'success'   => false,
                'message'   => 'AI disabled or missing key – publishing stopped.',
                'published' => []
            ];
        }

        // =========================
        // 1) تحميل التصنيفات
        // =========================
        $categories = self::load_categories($lang);

        // =========================
        // 2) جلب العناصر من الـ Queue
        // =========================
        $table = $wpdb->prefix . 'systemcore_queue';

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id ASC LIMIT %d", $limit)
        );

        if (empty($rows)) {
            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::info('Queue is empty.', 'publisher');
            }
            return [
                'success'   => true,
                'message'   => 'Queue is empty',
                'published' => []
            ];
        }

        $published = [];

        // =========================
        // 3) معالجة كل عنصر في الـ Queue
        // =========================
        foreach ($rows as $item) {

            $queue_id = (int) $item->id;
            $url      = trim((string) $item->url);

            try {

                // -----------------------------
                // 3.1 – Scraper
                // -----------------------------
                $scraped = SystemCore_Scraper::fetch_full_article($url);

                // Quality Gate على مستوى Scraper
                if (
                    empty($scraped) ||
                    empty($scraped['success']) ||
                    empty($scraped['title']) ||
                    empty($scraped['content']) ||
                    strlen(trim((string) $scraped['content'])) < 200
                ) {
                    if (class_exists('SystemCore_Logger')) {
                        SystemCore_Logger::warning("SCRAPER BLOCKED (weak/invalid content) URL: {$url}", 'publisher');
                    }
                    self::delete_queue($queue_id);
                    continue;
                }

                $raw_title   = (string) $scraped['title'];
                $raw_content = (string) $scraped['content'];
                $raw_image   = !empty($scraped['image']) ? (string) $scraped['image'] : '';

                // -----------------------------
                // 3.2 – AI Rewrite (إجباري وبالعربية)
                // -----------------------------
                $target_lang = 'ar'; // حالياً نجبره على العربية دائماً

                $final_title   = SystemCore_AI_Engine::rewrite_title($raw_title);
                $final_content = SystemCore_AI_Engine::rewrite_content($raw_content, $target_lang);
                $final_excerpt = SystemCore_AI_Engine::generate_meta_description($final_content);

                // إذا فشل AI → لا نشر
                if (
                    empty($final_title) ||
                    empty($final_content) ||
                    strlen(trim($final_content)) < 200
                ) {
                    if (class_exists('SystemCore_Logger')) {
                        SystemCore_Logger::warning("AI BLOCKED (weak AI output) URL: {$url}", 'publisher');
                    }
                    self::delete_queue($queue_id);
                    continue;
                }

                // -----------------------------
                // 3.3 – اختيار التصنيف
                // -----------------------------
                $category_id = 0;

                // إذا كان هناك تصنيف ثابت من الإعدادات → استخدمه
                if ($fixed_category_id > 0) {
                    $category_id = $fixed_category_id;

                } elseif ($enable_ai_category && !empty($categories)) {
                    // استعمال AI لاختيار التصنيف
                    $category_id = self::ai_select_category($final_title, $final_content, $categories);
                }

                // في حال لم نصل لتصنيف بالـ AI → استخدم أول تصنيف متاح
                if (!$category_id && !empty($categories)) {
                    $category_id = (int) $categories[0]['id'];
                }

                // -----------------------------
                // 3.4 – اختيار الكاتب عشوائياً
                // -----------------------------
                $author_id = (int) $authors[array_rand($authors)];

                // -----------------------------
                // 3.5 – الصورة البارزة (من Scraper فقط)
                // -----------------------------
                $thumb_id = 0;

                if (!empty($raw_image)) {
                    $thumb_id = self::download_image($raw_image, $final_title);
                }

                if (!$thumb_id && $default_thumb > 0) {
                    $thumb_id = $default_thumb;
                }

                // -----------------------------
                // 3.6 – إنشاء المنشور
                // -----------------------------
                $post_data = [
                    'post_title'   => $final_title,
                    'post_content' => $final_content,
                    'post_excerpt' => $final_excerpt,
                    'post_status'  => $status,
                    'post_author'  => $author_id,
                    'post_type'    => 'post',
                ];

                $post_id = wp_insert_post($post_data, true);

                if (is_wp_error($post_id) || !$post_id) {
                    if (class_exists('SystemCore_Logger')) {
                        SystemCore_Logger::error("Failed to insert post for URL: {$url}", 'publisher');
                    }
                    self::delete_queue($queue_id);
                    continue;
                }

                // -----------------------------
                // 3.7 – ربط التصنيف
                // -----------------------------
                if ($category_id > 0) {
                    wp_set_post_categories($post_id, [$category_id], false);
                }

                // -----------------------------
                // 3.8 – الصورة البارزة
                // -----------------------------
                if ($thumb_id > 0) {
                    set_post_thumbnail($post_id, $thumb_id);
                }

                // -----------------------------
                // 3.9 – ميتا: رابط المصدر
                // -----------------------------
                update_post_meta($post_id, '_systemcore_source_url', esc_url_raw($url));

                // -----------------------------
                // 3.10 – إزالة من الـ Queue
                // -----------------------------
                self::delete_queue($queue_id);

                // -----------------------------
                // 3.11 – سجل النجاح
                // -----------------------------
                $published[] = [
                    'queue_id' => $queue_id,
                    'post_id'  => $post_id,
                    'title'    => $final_title,
                    'cat'      => $category_id,
                ];

                if (class_exists('SystemCore_Logger')) {
                    SystemCore_Logger::info("Published post #{$post_id} from queue #{$queue_id}", 'publisher');
                }

            } catch (\Throwable $e) {

                if (class_exists('SystemCore_Logger')) {
                    SystemCore_Logger::error("Publisher exception for queue #{$queue_id}: " . $e->getMessage(), 'publisher');
                }

                self::delete_queue($queue_id);
                continue;
            }
        }

        return [
            'success'   => true,
            'message'   => 'Batch finished',
            'published' => $published,
        ];
    }

    /**
     * قيم افتراضية في حال لم تكن هناك إعدادات
     */
    private static function get_local_defaults() {
        return [
            'status'          => 'publish',
            'posts_per_batch' => 5,
            'default_thumb_id'=> 0,
            'publishers'      => [1],
            'default_lang'    => 'ar',
            'enable_ai_rewrite'  => 1,
            'enable_ai_category' => 1,
        ];
    }

    /**
     * حذف عنصر من الـ Queue
     */
    private static function delete_queue($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'systemcore_queue';
        $wpdb->delete($table, ['id' => (int) $id], ['%d']);
    }

    /**
     * تحميل التصنيفات حسب اللغة (WP REST API + WPML)
     */
    private static function load_categories($lang = 'ar') {

        $cache_key = 'systemcore_categories_' . $lang;
        $cached    = get_option($cache_key);

        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $url = home_url('/wp-json/wp/v2/categories');
        $url = add_query_arg([
            'per_page' => 100,
            'lang'     => $lang,
        ], $url);

        $response = wp_remote_get($url, ['timeout' => 20]);

        if (is_wp_error($response)) {
            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::error('Failed to fetch categories for lang ' . $lang, 'publisher');
            }
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return [];
        }

        $cats = [];
        foreach ($body as $c) {
            if (!empty($c['id']) && !empty($c['name'])) {
                $cats[] = [
                    'id'   => (int) $c['id'],
                    'name' => (string) $c['name'],
                ];
            }
        }

        update_option($cache_key, $cats);

        return $cats;
    }

    /**
     * اختيار التصنيف عن طريق AI
     */
    private static function ai_select_category($title, $content, array $categories) {

        if (empty($categories)) {
            return 0;
        }

        $list = '';
        foreach ($categories as $c) {
            $list .= $c['id'] . ' - ' . $c['name'] . "\n";
        }

        $prompt = "
أنت خبير في تصنيف الأخبار التقنية.
مهمتك اختيار التصنيف الأنسب للمقال من القائمة التالية.
أعد فقط رقم الـ ID بدون أي كلام إضافي.

قائمة التصنيفات:
{$list}

العنوان:
{$title}

المحتوى:
{$content}
        ";

        $res = SystemCore_AI_Engine::ask($prompt, 'category');

        if (preg_match('/\d+/', (string) $res, $m)) {
            return (int) $m[0];
        }

        return 0;
    }

    /**
     * تنزيل صورة خارجية وجعلها مرفق ووردبريس
     */
    private static function download_image($url, $title = '') {

        if (empty($url)) {
            return 0;
        }

        $attachment_id = 0;

        add_filter('media_sideload_image', function($html, $id) use (&$attachment_id) {
            $attachment_id = $id;
            return $html;
        }, 10, 2);

        $result = media_sideload_image($url, 0, $title);

        if (is_wp_error($result)) {
            return 0;
        }

        return (int) $attachment_id;
    }
}
