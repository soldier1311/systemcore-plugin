<?php
if (!defined('ABSPATH')) exit;

class SystemCore_Scraper {

    private static function fail($msg = '', $url = '', $stage = '', $extra = []) {

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::error(
                $msg ?: 'Scraper failure',
                'scraper',
                wp_json_encode([
                    'url'   => (string)$url,
                    'stage' => (string)$stage,
                    'extra' => $extra,
                ])
            );
        }

        return [
            'success'      => false,
            'title'        => '',
            'raw_html'     => '',
            'clean_html'   => '',
            'clean_text'   => '',
            'content'      => '',
            'image'        => '',
            'images'       => [],
            'description'  => '',
            'canonical'    => '',
            'keywords'     => '',
            'meta'         => [],
            'links'        => [],
            'hashes'       => [],
            'ai_payload'   => [],
            'error'        => $msg ?: 'Scraper failed',
        ];
    }

    /**
     * Compatibility method (Publisher calls this)
     */
    public static function scrape($url) {
        return self::fetch_full_article($url);
    }

    public static function fetch_full_article($url) {

        $url = trim((string)$url);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return self::fail('Invalid URL', $url, 'validate');
        }

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::info('Scraper started', 'scraper', wp_json_encode(['url' => $url]));
        }

        if (!class_exists('SystemCore_HttpClient')) {
            return self::fail('HttpClient missing', $url, 'http_client');
        }

        $http = SystemCore_HttpClient::fetch($url);

        if (empty($http['ok'])) {
            return self::fail($http['error'] ?? 'HTTP fetch failed', $url, 'http');
        }

        $html = $http['html'] ?? '';
        if ($html === '') {
            return self::fail('Empty HTML', $url, 'html_empty');
        }

        $mode = get_option('systemcore_scraper_mode', 'readability');

        if ($mode === 'strict') {
            return self::strict_mode($html, $url);
        }

        if ($mode === 'simple') {
            return self::simple_mode($html, $url);
        }

        if (
            !class_exists('SystemCore_ContentExtractor') ||
            !class_exists('SystemCore_ContentCleaner')   ||
            !class_exists('SystemCore_MetadataParser')   ||
            !class_exists('SystemCore_QualityGate')
        ) {
            return self::fail('Dependencies missing', $url, 'dependencies');
        }

        $ext = SystemCore_ContentExtractor::extract($html, $url);
        if (empty($ext['ok'])) {
            return self::fail($ext['error'] ?? 'Extractor failed', $url, 'extract');
        }

        $raw_html = $ext['raw_html'] ?? '';
        $title    = $ext['title']    ?? '';

        if ($raw_html === '') {
            return self::fail('Extractor returned empty HTML', $url, 'raw_empty');
        }

        $clean = SystemCore_ContentCleaner::clean($raw_html, $url, $title);
        if (empty($clean['ok'])) {
            return self::fail($clean['error'] ?? 'Cleaner failed', $url, 'clean');
        }

        $clean_html = $clean['clean_html'] ?? '';
        $clean_text = $clean['clean_text'] ?? '';

        if ($clean_text === '') {
            return self::fail('Cleaned text empty', $url, 'clean_text_empty');
        }

        $meta = SystemCore_MetadataParser::parse($html, $clean_html, $title, $url);

        $meta_title       = $meta['title']       ?? $title;
        $meta_description = $meta['description'] ?? '';
        $meta_keywords    = $meta['keywords']    ?? '';
        $meta_canonical   = $meta['canonical']   ?? '';
        $meta_links       = $meta['links']       ?? [];
        $meta_image       = $meta['image']       ?? '';

        $image  = self::resolve_image($html, $clean_html, $meta_image, $url);
        $images = self::extract_all_images($html, $clean_html, $url);

        $q = SystemCore_QualityGate::validate($clean_text, $meta, $url);
        if (!empty($q['reject'])) {
            $reason = $q['reason'] ?? 'Rejected';
            return self::fail($reason, $url, 'quality', ['reason' => $reason]);
        }

        return self::finalize(
            $url,
            $meta_title,
            $raw_html,
            $clean_html,
            $clean_text,
            $image,
            $images,
            $meta_description,
            $meta_keywords,
            $meta_canonical,
            $meta_links,
            $meta
        );
    }

    private static function strict_mode($html, $url) {

        preg_match('/<title>(.*?)<\/title>/si', $html, $m1);
        $title = isset($m1[1]) ? trim(strip_tags($m1[1])) : '';

        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)/i', $html, $m2);
        $desc = isset($m2[1]) ? trim($m2[1]) : '';

        if ($title === '' && $desc === '') {
            return self::fail('Strict: title/description missing', $url, 'strict');
        }

        $img = self::resolve_image($html, '', '', $url);

        return self::finalize(
            $url,
            $title,
            $html,
            '',
            $title . "\n\n" . $desc,
            $img,
            $img ? [$img] : [],
            $desc,
            '',
            '',
            [],
            []
        );
    }

    private static function simple_mode($html, $url) {

        preg_match('/<title>(.*?)<\/title>/si', $html, $m1);
        $title = isset($m1[1]) ? trim(strip_tags($m1[1])) : '';

        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)/i', $html, $m2);
        $desc = isset($m2[1]) ? trim($m2[1]) : '';

        if ($title === '' && $desc === '') {
            return self::fail('Simple: title/description missing', $url, 'simple');
        }

        $img = self::resolve_image($html, '', '', $url);

        return self::finalize(
            $url,
            $title,
            $html,
            '',
            $title . "\n\n" . $desc,
            $img,
            $img ? [$img] : [],
            $desc,
            '',
            '',
            [],
            []
        );
    }

    private static function resolve_image($html, $clean_html, $meta_img, $url) {

        if (!empty($meta_img)) {
            return self::abs_url(html_entity_decode($meta_img), $url);
        }

        if (!empty($clean_html) && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $clean_html, $m1)) {
            return self::abs_url(html_entity_decode($m1[1]), $url);
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m2)) {
            return self::abs_url(html_entity_decode($m2[1]), $url);
        }

        return '';
    }

    private static function extract_all_images($html, $clean_html, $url) {

        $imgs = [];

        if (!empty($clean_html) && preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $clean_html, $matches)) {
            foreach ($matches[1] as $src) {
                $imgs[] = self::abs_url(html_entity_decode($src), $url);
            }
        }

        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches2)) {
            foreach ($matches2[1] as $src) {
                $imgs[] = self::abs_url(html_entity_decode($src), $url);
            }
        }

        return array_values(array_unique(array_filter($imgs)));
    }

    private static function finalize(
        $url,
        $title,
        $raw_html,
        $clean_html,
        $clean_text,
        $image,
        $images,
        $description,
        $keywords,
        $canonical,
        $links,
        $meta
    ) {

        $clean_title = trim(strip_tags((string)$title));

        $content_hash = md5(substr($clean_text, 0, 800));

        $image_hash = '';
        if (!empty($image) && function_exists('wp_remote_get')) {
            $img = wp_remote_get($image, ['timeout' => 10]);
            if (!is_wp_error($img)) {
                $bin = wp_remote_retrieve_body($img);
                if (!empty($bin)) {
                    $image_hash = md5($bin);
                }
            }
        }

        /**
         * IMPORTANT:
         * Do NOT add any Dedupe here.
         * Dedupe must be added only AFTER successful publish in Publisher.
         */

        return [
            'success'      => true,
            'title'        => $clean_title,
            'raw_html'     => $raw_html,
            'clean_html'   => $clean_html,
            'clean_text'   => $clean_text,
            'content'      => $clean_text,
            'image'        => $image,
            'images'       => $images,
            'description'  => (string)$description,
            'canonical'    => (string)$canonical,
            'keywords'     => (string)$keywords,
            'meta'         => is_array($meta) ? $meta : [],
            'links'        => is_array($links) ? $links : [],
            'hashes'       => [
                'content' => $content_hash,
                'image'   => $image_hash,
            ],
            'ai_payload'   => [
                'title'       => $clean_title,
                'description' => $description,
                'content'     => $clean_text,
                'html'        => $clean_html,
                'meta'        => $meta,
                'keywords'    => $keywords,
                'canonical'   => $canonical,
                'images'      => $images,
                'featured'    => $image,
                'source_url'  => $url
            ],
            'error'        => '',
        ];
    }

    protected static function abs_url($src, $base) {

        $src  = (string)$src;
        $base = (string)$base;

        if ($src === '') return '';

        // Absolute or protocol-relative
        if (parse_url($src, PHP_URL_SCHEME) || substr($src, 0, 2) === '//') {
            return $src;
        }

        $b = parse_url($base);
        if (!$b) return $src;

        $scheme = $b['scheme'] ?? 'https';
        $host   = $b['host']   ?? '';
        $port   = isset($b['port']) ? ':' . $b['port'] : '';
        $path   = $b['path']   ?? '/';

        if (substr($src, 0, 1) === '/') {
            return $scheme . '://' . $host . $port . $src;
        }

        $dir = rtrim(preg_replace('#/[^/]*$#', '/', $path), '/') . '/';

        return $scheme . '://' . $host . $port . $dir . ltrim($src, './');
    }
}
