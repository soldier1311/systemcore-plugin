<?php
if (!defined('ABSPATH')) exit;

/**
 * SystemCore AI Engine — Final Stable Version (2025)
 * - Compatible with OpenAI Responses API
 * - Central AI helper used by Publisher and other modules
 */

class SystemCore_AI_Engine {

    /* ============================================================
        SETTINGS
    ============================================================ */

    private static function get_settings() {
        return get_option('systemcore_ai_settings', []);
    }

    private static function get_api_key() {
        $s = self::get_settings();
        return (!empty($s['api_key'])) ? trim($s['api_key']) : null;
    }

    private static function get_model($task) {
        $s  = self::get_settings();
        $tk = "model_{$task}";

        if (!empty($s[$tk]) && $s[$tk] !== 'default') {
            return $s[$tk];
        }

        return $s['model'] ?? 'gpt-4o-mini';
    }

    /**
     * Public generic ask() for other modules (Publisher etc.)
     */
    public static function ask($prompt, $task = 'generic') {
        $model    = self::get_model($task);
        $response = self::call_openai($prompt, $model);

        return is_string($response) ? trim($response) : '';
    }

    /* ============================================================
        OPENAI CALL — Responses API
    ============================================================ */

    private static function call_openai($prompt, $model = null) {

        $api_key = self::get_api_key();

        if (!$api_key) {
            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::error("AI Engine: Missing API Key.");
            }
            return '';
        }

        if (!$model) {
            $model = self::get_model('rewrite');
        }

        $endpoint = "https://api.openai.com/v1/responses";

        $body = [
            "model" => $model,
            "input" => $prompt,
        ];

        $args = [
            'body'    => json_encode($body),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'timeout' => 45,
        ];

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::error("AI HTTP Error: " . $response->get_error_message());
            }
            return '';
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($json['error'])) {
            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::error("AI API Error: " . $json['error']['message']);
            }
            return '';
        }

        return $json['output_text'] ?? '';
    }

    /* ============================================================
        REWRITE TITLE
    ============================================================ */

    public static function rewrite_title($title) {
        if (!$title) return '';

        $prompt = "
أعد صياغة العنوان التالي بأسلوب تقني صحفي جذاب ومحسّن لمحركات البحث:
{$title}
        ";

        return trim(self::ask($prompt, 'title'));
    }

    /* ============================================================
        REWRITE CONTENT
    ============================================================ */

    public static function rewrite_content($content, $lang = 'ar') {
        if (!$content) return '';

        $s = self::get_settings();

        $strength       = $s['rewrite_strength']   ?? 70;
        $style          = $s['writing_style']      ?? 'tech_journalism';
        $add_intro      = $s['add_intro']          ?? 'yes';
        $add_conclusion = $s['add_conclusion']     ?? 'yes';
        $use_h2_h3      = $s['use_h2_h3']          ?? 'yes';
        $word_count     = $s['target_word_count']  ?? 600;

        $prompt = "
أعد كتابة هذا المحتوى بأسلوب صحفي تقني عالي الجودة لمدونة المستقبل التقني.
إرشادات:
- قوة إعادة الكتابة: {$strength}%
- النمط: {$style}
- عناوين H2/H3: {$use_h2_h3}
- مقدمة: {$add_intro}
- خاتمة: {$add_conclusion}
- عدد الكلمات: حوالي {$word_count} كلمة
- اللغة: {$lang}

المحتوى:
{$content}
        ";

        return trim(self::ask($prompt, 'rewrite'));
    }

    /* ============================================================
        META DESCRIPTION
    ============================================================ */

    public static function generate_meta_description($content) {
        if (!$content) return '';

        $s   = self::get_settings();
        $max = $s['meta_length'] ?? 160;

        $prompt = "
اكتب Meta Description احترافية وجذابة لا تتجاوز {$max} حرفاً للنص التالي:
{$content}
        ";

        return trim(self::ask($prompt, 'seo'));
    }

    /* ============================================================
        KEYWORDS EXTRACTION
    ============================================================ */

    public static function extract_keywords($content) {
        if (!$content) return '';

        $prompt = "
استخرج كلمات مفتاحية مناسبة للتدوين التقني وضعها مفصولة بفواصل:
{$content}
        ";

        return trim(self::ask($prompt, 'keywords'));
    }

    /* ============================================================
        DETECT LANGUAGE
    ============================================================ */

    public static function detect_language($text) {
        if (!$text) return 'ar';

        $prompt = "
تعرّف لغة النص وأعد فقط الكود:
ar / en / fr / de

النص:
{$text}
        ";

        $code = trim(self::ask($prompt, 'language'));

        return $code ?: 'ar';
    }

    /* ============================================================
        PIPELINE (Scraper → AI → Publisher)
    ============================================================ */

    public static function process_article($scraped) {

        if (!$scraped || empty($scraped['content'])) {
            return [
                'lang'      => 'ar',
                'title'     => '',
                'content'   => '',
                'meta'      => '',
                'keywords'  => '',
            ];
        }

        $s = self::get_settings();

        $default_lang = isset($s['default_language']) ? $s['default_language'] : 'ar';

        $lang = ($default_lang === 'auto')
            ? self::detect_language($scraped['content'])
            : $default_lang;

        $new_title   = self::rewrite_title($scraped['title']);
        $new_content = self::rewrite_content($scraped['content'], $lang);

        if (empty($new_content)) {
            $new_content = $scraped['content'];
        }
        if (empty($new_title)) {
            $new_title = $scraped['title'];
        }

        $meta     = self::generate_meta_description($new_content) ?: '';
        $keywords = self::extract_keywords($new_content) ?: '';

        if (!empty($s['debug_mode']) && $s['debug_mode'] === 'yes') {
            return [
                'debug'     => true,
                'original'  => $scraped,
                'lang'      => $lang,
                'title'     => $new_title,
                'content'   => $new_content,
                'meta'      => $meta,
                'keywords'  => $keywords,
            ];
        }

        return [
            'lang'      => $lang,
            'title'     => $new_title,
            'content'   => $new_content,
            'meta'      => $meta,
            'keywords'  => $keywords,
        ];
    }
}
