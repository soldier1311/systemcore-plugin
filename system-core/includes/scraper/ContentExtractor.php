<?php

if (!defined('ABSPATH')) exit;

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;

class SystemCore_ContentExtractor {

    public static function extract($html, $url) {

        if (empty($html)) {
            return [
                'ok'        => false,
                'error'     => 'Empty HTML response',
                'raw_html'  => '',
                'title'     => '',
            ];
        }

        $raw_article = '';
        $title       = '';

        /* ---------------------------------------------------------
           1) READABILITY (Primary Extractor)
        --------------------------------------------------------- */
        if (class_exists(Readability::class)) {
            try {
                $config = new Configuration([
                    'fixRelativeURLs'   => true,
                    'normalizeEntities' => true,
                    'originalURL'       => $url,
                    'relativeURL'       => $url,
                    'wordThreshold'     => 60,
                    'maxTopCandidates'  => 12,
                ]);

                $readability = new Readability($config);

                if ($readability->parse($html)) {
                    $raw_article = $readability->getContent();
                    $title       = $readability->getTitle();
                }

            } catch (\Throwable $e) {
                if (class_exists('SystemCore_Logger')) {
                    SystemCore_Logger::warning("Readability failed: ".$e->getMessage(), 'scraper');
                }
            }
        }

        /* If Readability fails or gives too short result → fallback */
        if (empty($raw_article) || strlen(strip_tags($raw_article)) < 200) {
            $raw_article = self::fallback_extract($html);
        }

        if (empty($raw_article)) {
            return [
                'ok'        => false,
                'error'     => 'Unable to extract article',
                'raw_html'  => '',
                'title'     => $title,
            ];
        }

        /* ---------------------------------------------------------
           Extract full clean title
        --------------------------------------------------------- */
        if (empty($title)) {
            $title = self::extract_title($html);
        }

        return [
            'ok'        => true,
            'error'     => '',
            'raw_html'  => $raw_article,
            'title'     => $title,
        ];
    }


    /* =====================================================================
       FALLBACK Extractor (Advanced selectors)
    ===================================================================== */
    protected static function fallback_extract($html) {

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($dom);

        $selectors = [
            '//article',
            '//main',
            '//*[contains(@class,"article")]',
            '//*[contains(@class,"post-content")]',
            '//*[contains(@class,"entry-content")]',
            '//*[contains(@class,"story-body")]',
            '//*[contains(@class,"single-content")]',
            '//*[contains(@id,"content")]',
            '//*[contains(@id,"main")]',
            '//*[@role="main"]',
        ];

        foreach ($selectors as $selector) {
            $nodes = @$xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                return $dom->saveHTML($nodes->item(0));
            }
        }

        /* AMP pages */
        if (preg_match('/<amp-article[^>]*>(.*?)<\/amp-article>/is', $html, $match)) {
            return $match[1];
        }

        /* fallback = body */
        $body = $dom->getElementsByTagName('body');
        if ($body->length > 0) {
            return $dom->saveHTML($body->item(0));
        }

        return '';
    }


    /* =====================================================================
       Extract best possible title
    ===================================================================== */
    protected static function extract_title($html) {

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        // 1) Try <h1>
        $h1 = $dom->getElementsByTagName('h1');
        if ($h1->length > 0) {
            return trim($h1->item(0)->textContent);
        }

        // 2) Try <meta property="og:title">
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('property')) === 'og:title') {
                return trim($meta->getAttribute('content'));
            }
        }

        // 3) <title>
        $titleTags = $dom->getElementsByTagName('title');
        if ($titleTags->length > 0) {
            return trim($titleTags->item(0)->textContent);
        }

        return '';
    }
}
