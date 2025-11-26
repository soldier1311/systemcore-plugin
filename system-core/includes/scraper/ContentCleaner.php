<?php

if (!defined('ABSPATH')) exit;

class SystemCore_ContentCleaner {

    /**
     * Clean extracted HTML → clean HTML + clean TEXT
     */
    public static function clean($raw_html, $url = '', $article_title = '') {

        if (empty($raw_html)) {
            return [
                'ok'         => false,
                'error'      => 'Empty raw HTML',
                'clean_html' => '',
                'clean_text' => '',
            ];
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $raw_html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        /* ============================================================
           1) Remove bad tags
        ============================================================ */
        $remove_tags = [
            'script','style','iframe','svg','noscript',
            'footer','form','header','aside','nav',
            'button','input','select','option','video','audio'
        ];

        foreach ($remove_tags as $tag) {
            while (($nodes = $dom->getElementsByTagName($tag)) && $nodes->length > 0) {
                $node = $nodes->item(0);
                if ($node && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        /* ============================================================
           2) Remove ads, widgets, social, tracking
        ============================================================ */
        self::remove_by_class($dom, [
            'ad','ads','advert','advertisement',
            'sponsored','promo','promoted',
            'share','shares','social','social-share',
            'cookie','banner','newsletter','subscribe',
            'related','recommended','trending','widget'
        ]);

        /* ============================================================
           3) Remove inline style
        ============================================================ */
        self::remove_inline_styles($dom);

        /* ============================================================
           4) Fix & clean <a> links
        ============================================================ */
        self::clean_links($dom, $url);

        /* ============================================================
           5) Clean and normalize headings
        ============================================================ */
        self::clean_headings($dom, $article_title);

        /* ============================================================
           FINAL OUTPUT
        ============================================================ */

        $clean_html  = $dom->saveHTML();
        $clean_text  = trim(
            preg_replace('/\s+/', ' ', wp_strip_all_tags($clean_html))
        );

        if (strlen($clean_text) < 50) {
            return [
                'ok'         => false,
                'error'      => 'Too short after cleaning',
                'clean_html' => '',
                'clean_text' => '',
            ];
        }

        return [
            'ok'         => true,
            'error'      => '',
            'clean_html' => $clean_html,
            'clean_text' => $clean_text,
        ];
    }


    /* ============================================================
       HELPERS
    ============================================================ */

    /**
     * Remove nodes by class names
     */
    protected static function remove_by_class($dom, $class_list) {

        $xpath = new DOMXPath($dom);

        foreach ($class_list as $class) {

            $nodes = $xpath->query(
                "//*[contains(translate(@class,
                    'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    'abcdefghijklmnopqrstuvwxyz'),
                    '$class')]"
            );

            if (!$nodes) continue;

            foreach ($nodes as $n) {
                if ($n->parentNode) {
                    $n->parentNode->removeChild($n);
                }
            }
        }
    }

    /**
     * Remove inline style attribute
     */
    protected static function remove_inline_styles($dom) {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@style]');

        foreach ($nodes as $node) {
            $node->removeAttribute('style');
        }
    }

    /**
     * Clean links, remove tracking, convert google redirect
     */
    protected static function clean_links($dom, $base_url) {

        $links = $dom->getElementsByTagName('a');

        foreach ($links as $a) {

            $href = $a->getAttribute('href');

            if (!$href || $href === '#') {
                $a->removeAttribute('href');
                continue;
            }

            // Convert google redirect
            if (strpos($href, 'https://www.google.com/url?') === 0) {
                $parts = wp_parse_url($href);
                if (!empty($parts['query'])) {
                    parse_str($parts['query'], $qs);
                    if (!empty($qs['q'])) {
                        $href = $qs['q'];
                        $a->setAttribute('href', $href);
                    }
                }
            }

            $a->setAttribute('rel', 'nofollow noopener noreferrer');
        }
    }

    /**
     * normalize headings, remove duplicates, fix title
     */
    protected static function clean_headings($dom, $article_title) {

        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');

        if (!$nodes) return;

        foreach ($nodes as $h) {

            $tag = strtolower($h->nodeName);
            $text = trim($h->textContent);

            if ($text === '') {
                $h->parentNode->removeChild($h);
                continue;
            }

            // Convert h5/h6 → h3
            if ($tag === 'h5' || $tag === 'h6') {
                $new = $dom->createElement('h3', $text);
                $h->parentNode->replaceChild($new, $h);
                continue;
            }

            // Remove duplicate H1
            if ($tag === 'h1' && $article_title !== '' && strtolower($text) !== strtolower($article_title)) {
                $new = $dom->createElement('h2', $text);
                $h->parentNode->replaceChild($new, $h);
                continue;
            }
        }

        // Ensure at least one H1
        if ($article_title !== '') {
            $h1 = $xpath->query('//h1');
            if (!$h1 || $h1->length === 0) {
                $body = $dom->getElementsByTagName('body')->item(0);
                if ($body) {
                    $h1node = $dom->createElement('h1', $article_title);
                    $body->insertBefore($h1node, $body->firstChild);
                }
            }
        }
    }
}
