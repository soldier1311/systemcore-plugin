<?php
if (!defined('ABSPATH')) exit;

class SystemCore_Publisher_V2 {

    /* ============================================================
       SETTINGS
    ============================================================ */
    private static function settings() {
        $defaults = [
            'strict_mode'      => 'yes',
            'publish_status'   => 'publish',
            'default_language' => 'ar',
            'languages'        => ['ar','fr','de'],
            'generate_images'  => 'no',
            'image_size'       => 'large',
            'debug_mode'       => 'no'
        ];

        $saved = get_option('systemcore_publisher_settings', []);
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    /* ============================================================
       AUTO CATEGORY MAPPING
    ============================================================ */
    private static function auto_category(string $lang, string $topic, string $keyword) {

        $map = [
            'ar' => [
                'ذكاء'      => 'تقنية الذكاء الاصطناعي',
                'اصطناعي'   => 'تقنية الذكاء الاصطناعي',
                'هواتف'     => 'أخبار الهواتف',
                'سامسونج'   => 'سامسونج',
                'شاومي'     => 'شاومي',
                'آيفون'     => 'أبل',
                'تسريب'     => 'تسريبات تقنية',
            ],
            'fr' => [
                'AI'         => 'Intelligence Artificielle',
                'Samsung'    => 'Samsung',
                'iPhone'     => 'Apple',
                'Xiaomi'     => 'Xiaomi',
                'smartphone' => 'Actualités Smartphones',
            ],
            'de' => [
                'KI'         => 'Künstliche Intelligenz',
                'Samsung'    => 'Samsung Deutschland',
                'Xiaomi'     => 'Xiaomi Deutschland',
                'iPhone'     => 'Apple Deutschland',
                'Smartphone' => 'Smartphone Nachrichten',
            ],
        ];

        $set = $map[$lang] ?? [];

        foreach ($set as $needle => $term) {
            if (stripos($topic, $needle) !== false || stripos($keyword, $needle) !== false) {
                return $term;
            }
        }

        return ($lang === 'ar') ? 'تقنية' : (($lang === 'fr') ? 'Technologie' : 'Technologie');
    }

    /* ============================================================
       MAIN ENTRY: PUBLISH MULTILINGUAL ARTICLE
    ============================================================ */
    public static function publish(array $args) {

        $s = self::settings();

        /* الأمن الأساسي */
        if (!is_admin() || !current_user_can('manage_options')) {
            return ['success'=>false, 'message'=>'Unauthorized access'];
        }

        /* -------------------------------------
           Determine primary language
        ------------------------------------- */
        $primary_lang = $args['primary_language'] ?? $s['default_language'];
        $langs = $args['languages'] ?? $s['languages'];

        if (!in_array($primary_lang, $langs)) {
            array_unshift($langs, $primary_lang);
            $langs = array_unique($langs);
        }

        $topic   = $args['topic'] ?? '';
        $keyword = $args['keyword'] ?? '';
        $source  = $args['content'] ?? '';

        $results = [];
        $trid = null;
        $primary_post_id = null;

        /* ============================================================
           LOOP: Generate + Publish each language
        ============================================================ */
        foreach ($langs as $lang) {

            /*
             ============================================================
               AI GENERATION + RETRY LOGIC
             ============================================================
            */
            $ai = SystemCore_AI_Engine::produce_article([
                'topic'       => $topic,
                'keyword'     => $keyword,
                'language'    => $lang,
                'content'     => $source,
                'word_target' => $args['word_target'] ?? 1200
            ]);

            if (!$ai['success']) {
                // إعادة المحاولة مرة واحدة فقط
                $ai_retry = SystemCore_AI_Engine::produce_article([
                    'topic'       => $topic,
                    'keyword'     => $keyword,
                    'language'    => $lang,
                    'content'     => $source,
                    'word_target' => $args['word_target'] ?? 1200,
                    'retry'       => true
                ]);

                if (!empty($ai_retry['success'])) {
                    $ai = $ai_retry;
                }
            }

            /* عند الفشل النهائي */
            if (!$ai['success']) {
                if ($s['strict_mode'] === 'yes') {
                    return [
                        'success'=>false,
                        'failed_language'=>$lang,
                        'message'=>'AI generation failed',
                        'ai_error'=>$ai
                    ];
                }
                $results[$lang] = ['success'=>false,'message'=>'AI failed'];
                continue;
            }

            $data = $ai['data'];

            /*
            ============================================================
                SMART SEO ENHANCER
            ============================================================
            */
            if (empty($data['seo']['meta_description']) || strlen($data['seo']['meta_description']) < 40) {

                if (!empty($data['intro'])) {
                    $data['seo']['meta_description'] = wp_trim_words(strip_tags($data['intro']), 30, '');
                } elseif (!empty($data['content'])) {
                    $data['seo']['meta_description'] = wp_trim_words(strip_tags($data['content']), 30, '');
                } else {
                    $data['seo']['meta_description'] = 'مقال تقني مترجم متعدد اللغات بواسطة SystemCore.';
                }
            }

            /* ======================================================
               BUILD FULL ARTICLE HTML
            ====================================================== */
            $html = self::build_article_html($data, $lang);

            /* ======================================================
               INSERT POST
            ====================================================== */
            $post_id = wp_insert_post([
                'post_title'   => wp_strip_all_tags($data['title']),
                'post_content' => $html,
                'post_status'  => $s['publish_status'],
                'post_type'    => 'post'
            ]);

            if (is_wp_error($post_id)) {
                if ($s['strict_mode'] === 'yes') {
                    return [
                        'success'=>false,
                        'message'=>'WordPress insert failed',
                        'error'=>$post_id->get_error_message()
                    ];
                }
                $results[$lang] = ['success'=>false,'message'=>'insert failed'];
                continue;
            }

            /* ======================================================
               AUTO CATEGORY ASSIGNMENT
            ====================================================== */
            $cat_name = self::auto_category($lang, $topic, $keyword);
            if ($cat_name) {
                $term = term_exists($cat_name, 'category');
                if (!$term) $term = wp_insert_term($cat_name, 'category');
                if (!is_wp_error($term)) {
                    wp_set_post_terms($post_id, [$term['term_id']], 'category');
                }
            }

            /* ======================================================
               WPML LINKING
            ====================================================== */
            do_action('wpml_set_element_language_details', [
                'element_id' => $post_id,
                'element_type' => 'post_post',
                'trid'         => $trid,
                'language_code'=> $lang,
                'source_language_code'=> null
            ]);

            if ($lang === $primary_lang) {
                $primary_post_id = $post_id;
                $trid = apply_filters('wpml_element_trid', null, $post_id, 'post_post');
            }

            /* ======================================================
               SEO META via Yoast filters (unchanged)
            ====================================================== */
            add_filter('wpseo_title', function() use ($data) {
                return $data['seo']['meta_title'] ?? '';
            });

            add_filter('wpseo_metadesc', function() use ($data) {
                return $data['seo']['meta_description'] ?? '';
            });

            /* ======================================================
               IMAGE GENERATION
            ====================================================== */
            if ($s['generate_images'] === 'yes' && !empty($data['image_prompt'])) {
                self::attach_featured_image($post_id, $data['image_prompt'], $lang, $s);
            }

            /* ======================================================
               LOG
            ====================================================== */
            $results[$lang] = [
                'success' => true,
                'post_id' => $post_id,
                'language' => $lang
            ];
        }

        return [
            'success' => true,
            'primary_post_id' => $primary_post_id,
            'trid' => $trid,
            'results' => $results
        ];
    }

    /* ============================================================
       ARTICLE HTML BUILDER
    ============================================================ */
    private static function build_article_html($d, $lang) {

        $html  = "<h1>{$d['title']}</h1>\n";

        if (!empty($d['intro'])) {
            $html .= "<p>{$d['intro']}</p>\n";
        }

        if (!empty($d['content'])) {
            $html .= wpautop($d['content']);
        }

        if (!empty($d['conclusion'])) {
            $html .= "<h2>".($lang === 'ar' ? "الخلاصة" : "Conclusion")."</h2>\n";
            $html .= "<p>{$d['conclusion']}</p>\n";
        }

        return $html;
    }

    /* ============================================================
       IMAGE GENERATION + ATTACHMENT
    ============================================================ */
    private static function attach_featured_image($post_id, $prompt, $lang, $s) {

        $ai = SystemCore_AI_Engine::produce_image([
            'prompt' => $prompt,
            'language' => $lang,
            'style' => $s['image_style']
        ]);

        if (!$ai['success']) return false;

        $img_url = $ai['url'];

        $tmp = download_url($img_url);
        if (is_wp_error($tmp)) return false;

        $file = [
            'name'     => 'systemcore-' . time() . '.jpg',
            'tmp_name' => $tmp
        ];

        $id = media_handle_sideload($file, $post_id);

        @unlink($tmp);

        if (!is_wp_error($id)) {
            set_post_thumbnail($post_id, $id);
        }
    }
}
