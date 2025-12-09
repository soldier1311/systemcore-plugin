<?php
if (!defined('ABSPATH')) exit;

class SystemCore_AI_Engine {

    /* ============================================================
       DEFAULT SETTINGS
    ============================================================ */
    private static function defaults() {
        return [
            'enabled'          => 'yes',
            'api_key'          => '',
            'model'            => 'gpt-4.1-mini',
            'temperature'      => 0.4,
            'max_tokens'       => 4500,
            'timeout'          => 40,
            'retries'          => 2,
            'backoff_ms'       => 600,

            'min_h2'           => 3,
            'rewrite_strength' => 1,
            'writing_style'    => 'tech_journalism',
            'seo_mode'         => 'medium',
            'add_intro'        => 'yes',
            'add_conclusion'   => 'yes',
            'use_h2_h3'        => 'yes',
            'image_generation' => 'no',
            'image_style'      => 'clean',
            'debug_mode'       => 'no'
        ];
    }

    private static function settings() {
        $saved = get_option('systemcore_ai_settings', []);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    /* ============================================================
       MAIN ENTRY: PRODUCE ARTICLE (Unified Contract)
    ============================================================ */
    public static function produce_article(array $args) {

        $s = self::settings();
        self::assert_enabled($s);
        self::rate_limit('produce_article');

        $topic    = $args['topic'] ?? '';
        $language = $args['language'] ?? 'ar';
        $keyword  = $args['keyword'] ?? '';
        $source   = $args['content'] ?? '';
        $target   = isset($args['word_target']) ? (int)$args['word_target'] : 1200;

        $prompt = self::build_prompt($topic, $language, $keyword, $source, $target, $s);

        /* =====================================================
           SEND REQUEST (auto fallback)
        ===================================================== */
        $response = self::call_ai_json($prompt, $s);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => $response['message'],
                'raw' => $response['raw']
            ];
        }

        $json = self::force_parse_json($response['text']);

        if (!$json) {
            return [
                'success' => false,
                'message' => 'Invalid JSON',
                'raw' => $response['raw']
            ];
        }

        return [
            'success' => true,
            'data' => $json,
            'raw' => ($s['debug_mode'] === 'yes') ? $response['raw'] : null
        ];
    }

    /* ============================================================
       PROMPT BUILDER
    ============================================================ */
    private static function build_prompt($topic, $lang, $keyword, $original, $target, $s) {

        $intro       = ($s['add_intro'] === 'yes') ? "true" : "false";
        $conclusion  = ($s['add_conclusion'] === 'yes') ? "true" : "false";
        $h2          = ($s['use_h2_h3'] === 'yes') ? "true" : "false";
        $img         = ($s['image_generation'] === 'yes') ? "true" : "false";
        $debug       = ($s['debug_mode'] === 'yes') ? "true" : "false";

        return "
SYSTEM RULES:
- Always respond with VALID JSON ONLY.
- No text outside JSON.
- Respect target language: {$lang}.
- Maintain stable structure.
- No repeated sentences.
- SEO must follow rules provided.

TOPIC: {$topic}
LANGUAGE: {$lang}
KEYWORD: {$keyword}

SETTINGS:
Writing style: {$s['writing_style']}
Rewrite strength: {$s['rewrite_strength']}
SEO mode: {$s['seo_mode']}
Add intro: {$intro}
Add conclusion: {$conclusion}
Use H2/H3: {$h2}
Minimum H2 count: {$s['min_h2']}
Target word count: {$target}
Generate image prompt: {$img}
Image style: {$s['image_style']}
Debug mode: {$debug}

SOURCE CONTENT:
{$original}

RETURN JSON IN THIS EXACT FORMAT:
{
  \"title\": \"...\",
  \"intro\": \"...\",
  \"content\": \"...\",
  \"conclusion\": \"...\",
  \"headings\": [\"...\", \"...\",
  ],
  \"seo\": {
     \"meta_title\": \"...\",
     \"meta_description\": \"...\",
     \"slug\": \"...\"
  },
  \"keyword_stats\": {
     \"keyword\": \"{$keyword}\",
     \"count\": 0
  },
  \"image_prompt\": \"...\",
  \"debug\": \"...\"
}";
    }

    /* ============================================================
       AUTO-FALLBACK AI CALL
    ============================================================ */

    private static function call_ai_json($prompt, $s) {

        $payload_responses = [
            'model' => $s['model'],
            'input' => $prompt,
            'max_output_tokens' => (int)$s['max_tokens']
        ];

        /* Try responses API */
        $r1 = self::call_api($s['base_url'], $payload_responses, $s, 'responses');

        if ($r1['success']) return $r1;

        /* Fallback to chat/completions */
        $payload_chat = [
            'model' => $s['model'],
            'messages' => [
                ['role' => 'system', 'content' => 'Always return JSON only.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => (int)$s['max_tokens']
        ];

        $r2 = self::call_api("https://api.openai.com/v1/chat/completions", $payload_chat, $s, 'chat');

        if ($r2['success']) return $r2;

        return [
            'success' => false,
            'message' => $r2['message'],
            'raw' => [$r1, $r2]
        ];
    }

    private static function call_api($url, $payload, $s, $mode) {

        $args = [
            'headers' => [
                'Authorization' => 'Bearer '.$s['api_key'],
                'Content-Type'  => 'application/json'
            ],
            'timeout' => $s['timeout'],
            'body'    => json_encode($payload)
        ];

        $res = wp_remote_post($url, $args);

        if (is_wp_error($res)) {
            return ['success'=>false, 'message'=>$res->get_error_message(), 'text'=>'', 'raw'=>null];
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $json = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            return [
                'success'=>false,
                'message'=>$json['error']['message'] ?? "http_{$code}",
                'text'=>'',
                'raw'=>$json
            ];
        }

        if ($mode === 'responses') {
            $text = $json['output'][0]['content'][0]['text'] ?? '';
        } else {
            $text = $json['choices'][0]['message']['content'] ?? '';
        }

        return [
            'success'=>!empty($text),
            'message'=>!empty($text) ? 'ok' : 'empty',
            'text'=>$text,
            'raw'=>$json
        ];
    }

    /* ============================================================
       JSON PARSER
    ============================================================ */
    private static function force_parse_json($text) {
        $text = trim($text);
        $clean = preg_replace('/^[^{]+/','',$text);
        $clean = preg_replace('/[^}]+$/','',$clean);
        $json = json_decode($clean, true);
        return is_array($json) ? $json : null;
    }

    /* ============================================================
       UTILITIES
    ============================================================ */
    private static function rate_limit($task) {
        $s = self::settings();
        $key = 'sc_ai_rl_'.$task.'_'.get_current_user_id();
        $win = $s['retries'] ?? 60;
        $max = $s['rate_limit_max'] ?? 8;

        $now = time();
        $list = get_transient($key) ?: [];
        $list = array_filter($list, fn($t)=>($now-$t)<$win);

        if (count($list) >= $max) wp_die("AI limit");

        $list[] = $now;
        set_transient($key,$list,$win);
    }

    private static function assert_enabled($s) {
        if ($s['enabled'] !== 'yes') wp_die('AI disabled');
        if (empty($s['api_key'])) wp_die('Missing API key');
    }
}
