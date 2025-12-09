<?php

if (!defined('ABSPATH')) exit;

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;

class SystemCore_ContentExtractor {

    public static function extract($html, $url) {

        $html = (string) $html;
        $url  = (string) $url;

        if ($html === '' || $url === '') {
            return self::fail('Invalid extractor input', $url, 'init');
        }

        $td = self::extract_title_description($html, $url);

        if (self::is_strict_mode()) {

            if (strlen($td['title']) >= 15 && strlen($td['description']) >= 50) {
                return [
                    'ok'       => true,
                    'title'    => $td['title'],
                    'raw_html' => $td['description']
                ];
            }
        }

        $rd = self::readability_extract($html, $url);

        if ($rd['ok']) {
            return [
                'ok'       => true,
                'title'    => $rd['title'] ?: $td['title'],
                'raw_html' => $rd['html']  ?: $td['description']
            ];
        }

        if (!empty($td['description'])) {
            return [
                'ok'       => true,
                'title'    => $td['title'],
                'raw_html' => $td['description']
            ];
        }

        return self::fail('Extractor: no usable content', $url, 'fallback');
    }

    private static function fail($msg, $url, $stage) {

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::error(
                $msg,
                'extractor',
                json_encode([
                    'url'   => $url,
                    'stage' => $stage
                ])
            );
        }

        return [
            'ok'       => false,
            'title'    => '',
            'raw_html' => '',
            'error'    => $msg
        ];
    }

    private static function extract_title_description($html, $url) {

        $title = '';
        $desc  = '';

        if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(html_entity_decode($m[1]));
        }

        if (preg_match(
            '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i',
            $html,
            $m
        )) {
            $desc = trim(html_entity_decode($m[1]));
        }

        if ($desc === '') {
            if (preg_match(
                '/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\']/i',
                $html,
                $m2
            )) {
                $desc = trim(html_entity_decode($m2[1]));
            }
        }

        return [
            'title'       => trim($title),
            'description' => trim($desc)
        ];
    }

    private static function is_strict_mode() {
        $opt = get_option('systemcore_scraper_settings', []);
        return !empty($opt['mode']) && $opt['mode'] === 'strict';
    }

    private static function readability_extract($html, $url) {

        try {

            $config = new Configuration([
                'fixRelativeURLs'     => true,
                'originalURL'         => $url,
                'summonCthulhu'       => false,
                'removeScripts'       => true,
                'cleanConditionally'  => true,
                'charThreshold'       => 300,
            ]);

            $readability = new Readability($config);
            $success     = $readability->parse($html);

            if (!$success) {
                return ['ok' => false];
            }

            $content = $readability->getContent();
            $title   = $readability->getTitle();

            $content = trim((string) $content);
            $title   = trim((string) $title);

            if (strlen(strip_tags($content)) < 150) {
                return ['ok' => false];
            }

            return [
                'ok'   => true,
                'html' => $content,
                'title'=> $title
            ];

        } catch (\Throwable $e) {

            if (class_exists('SystemCore_Logger')) {
                SystemCore_Logger::error(
                    'Readability exception: ' . $e->getMessage(),
                    'extractor',
                    json_encode(['url' => $url])
                );
            }

            return ['ok' => false];
        }
    }
}
