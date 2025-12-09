<?php

if (!defined('ABSPATH')) exit;

class SystemCore_ContentCleaner {

    /**
     * Clean extracted article HTML and text.
     */
    public static function clean($html, $url = '', $title = '') {

        if (empty($html)) {
            return [
                'ok'         => false,
                'error'      => 'empty_html',
                'clean_html' => '',
                'clean_text' => ''
            ];
        }

        // Remove scripts, styles, noscript
        $html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);
        $html = preg_replace('#<noscript[^>]*>.*?</noscript>#is', '', $html);

        // Remove tracking pixels
        $html = preg_replace('#<img[^>]+width=["\']?1["\']?[^>]*>#i', '', $html);
        $html = preg_replace('#<img[^>]+height=["\']?1["\']?[^>]*>#i', '', $html);

        // Remove comments
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);

        // Keep only safe iframes (YT, X/Twitter)
        $html = preg_replace_callback(
            '#<iframe[^>]+src=["\']([^"\']+)["\'][^>]*></iframe>#i',
            function($m) {
                $src = $m[1];
                if (strpos($src, 'youtube.com') !== false ||
                    strpos($src, 'youtu.be') !== false ||
                    strpos($src, 'twitter.com') !== false ||
                    strpos($src, 'x.com') !== false) {
                    return $m[0];
                }
                return '';
            },
            $html
        );

        // Clean tags but keep SEO structure
        $allowed_tags = '<p><br><b><strong><i><em><ul><ol><li><blockquote>' .
                        '<img><h2><h3><h4><figure><figcaption><a>';

        $clean_html = strip_tags($html, $allowed_tags);

        // Normalize images (remove garbage attributes)
        $clean_html = preg_replace(
            '#<img[^>]+src=["\']([^"\']+)["\'][^>]*>#i',
            '<img src="$1" alt="">',
            $clean_html
        );

        // Remove empty tags
        $clean_html = preg_replace('#<([a-z]+)([^>]*)>\s*</\1>#i', '', $clean_html);

        // Normalize <br>
        $clean_html = preg_replace('#(<br\s*/?>\s*){2,}#i', '<br>', $clean_html);

        // Remove duplicate spaces
        $clean_html = preg_replace('/\s+/', ' ', $clean_html);
        $clean_html = trim($clean_html);

        // Extract clean text
        $clean_text = wp_strip_all_tags($clean_html);
        $clean_text = preg_replace('/\s+/', ' ', $clean_text);
        $clean_text = trim($clean_text);

        // Reject too-short content
        if (strlen($clean_text) < 50) {
            return [
                'ok'         => false,
                'error'      => 'content_too_short',
                'clean_html' => '',
                'clean_text' => ''
            ];
        }

        return [
            'ok'         => true,
            'clean_html' => $clean_html,
            'clean_text' => $clean_text
        ];
    }
}
