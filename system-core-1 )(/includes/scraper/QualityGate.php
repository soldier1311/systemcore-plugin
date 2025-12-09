<?php

if (!defined('ABSPATH')) exit;

class SystemCore_QualityGate {

    /**
     * Master quality validator — optimized stability version.
     */
    public static function validate($text, $meta, $url = '') {

        $text = trim((string)$text);
        if ($text === '') {
            return [
                'reject' => true,
                'reason' => 'empty_text'
            ];
        }

        // Minimum raw text length
        if (strlen($text) < 120) {
            return [
                'reject' => true,
                'reason' => 'text_too_short'
            ];
        }

        // Word count — relaxed to avoid false negatives
        $word_count = str_word_count(strip_tags($text));
        if ($word_count < 30) {
            return [
                'reject' => true,
                'reason' => 'too_few_words'
            ];
        }

        // Reject "utility" / non-article pages
        $bad_titles = [
            'contact', 'privacy', 'terms', 'about', 'login', 'register',
            'cookies', '404', 'not found', 'category', 'tag', 'archive',
            'search', 'results', 'profile', 'account'
        ];

        $title = strtolower($meta['title'] ?? '');

        foreach ($bad_titles as $k) {
            if (strpos($title, $k) !== false) {
                return [
                    'reject' => true,
                    'reason' => 'invalid_page_type'
                ];
            }
        }

        // Description check — NO REJECTION, only warning
        $desc = trim((string)($meta['description'] ?? ''));
        if ($desc !== '' && strlen($desc) < 25) {
            // NOT reject — scraper will continue
            self::warn('Weak description detected', $url);
        }

        // Sentence quality check — more tolerant
        $sentences = preg_split('/[.!?]+/', $text);
        $valid_sentences = array_filter($sentences, function ($s) {
            return strlen(trim($s)) > 40; // previously 50
        });

        if (count($valid_sentences) < 1) {
            return [
                'reject' => true,
                'reason' => 'no_valid_sentences'
            ];
        }

        // Repetition detection — more tolerant
        $unique_ratio = self::unique_word_ratio($text);
        if ($unique_ratio < 0.28) { // lowered threshold from 0.35
            return [
                'reject' => true,
                'reason' => 'content_too_repetitive'
            ];
        }

        return [
            'reject' => false,
            'reason' => ''
        ];
    }

    /**
     * Unique word ratio calculation.
     */
    private static function unique_word_ratio($text) {

        $words = preg_split('/\s+/', strtolower(strip_tags($text)));
        $words = array_filter($words, fn($w) => strlen($w) > 3);

        if (count($words) === 0) {
            return 0;
        }

        $unique = array_unique($words);

        return count($unique) / count($words);
    }

    /**
     * Logger warning helper (non-fatal)
     */
    private static function warn($msg, $url = '') {
        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::warning($msg, 'quality', json_encode(['url' => $url]));
        }
    }
}

