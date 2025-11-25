<?php

if (!defined('ABSPATH')) exit;

class SystemCore_Scraper {

    protected static function fail($msg = '') {
        return [
            'success'            => false,
            'title'              => '',
            'content'            => '',
            'content_html_clean' => '',
            'image'              => '',
            'description'        => '',
            'og_title'           => '',
            'og_description'     => '',
            'canonical'          => '',
            'keywords'           => '',
            'links'              => [],
            'error'              => $msg,
        ];
    }

    public static function fetch_full_article($url) {

        $url = trim((string) $url);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return self::fail('Invalid URL');
        }

        /* ---------------------------------------------------------
           1) HTTP Fetch
        --------------------------------------------------------- */
        $http = SystemCore_HttpClient::fetch($url);

        if (empty($http['ok'])) {
            return self::fail($http['error'] ?? 'HTTP fetch failed');
        }

        $html = $http['html'];

        /* ---------------------------------------------------------
           2) Extract content (ContentExtractor)
        --------------------------------------------------------- */
        $ext = SystemCore_ContentExtractor::extract($html, $url);

        if (empty($ext['ok'])) {
            return self::fail($ext['error'] ?? 'Extract failed');
        }

        $raw_html = $ext['raw_html'] ?? '';
        $title    = $ext['title']    ?? '';

        /* ---------------------------------------------------------
           3) Clean HTML + text (ContentCleaner)
        --------------------------------------------------------- */
        $clean = SystemCore_ContentCleaner::clean($raw_html, $url, $title);

        if (empty($clean['ok'])) {
            return self::fail($clean['error'] ?? 'Clean failed');
        }

        $clean_html = $clean['clean_html'];
        $clean_text = $clean['clean_text'];

        /* ---------------------------------------------------------
           4) Parse metadata
        --------------------------------------------------------- */
        $meta = SystemCore_MetadataParser::parse($html, $clean_html, $title, $url);

        $meta_title       = $meta['title']         ?? $title;
        $meta_description = $meta['description']   ?? '';
        $meta_og_title    = $meta['og_title']      ?? '';
        $meta_og_desc     = $meta['og_description']?? '';
        $meta_canonical   = $meta['canonical']     ?? '';
        $meta_keywords    = $meta['keywords']      ?? '';
        $meta_links       = $meta['links']         ?? [];
        $meta_image       = $meta['image']         ?? '';

        /* ---------------------------------------------------------
           5) Quality Gate
        --------------------------------------------------------- */
        $q = SystemCore_QualityGate::validate($clean_text, $meta, $url);

        if (!empty($q['reject'])) {
            return self::fail($q['reason'] ?? 'Rejected by quality gate');
        }

        /* ---------------------------------------------------------
           6) Image Extraction
        --------------------------------------------------------- */
        $image = $meta_image;

        if (empty($image) &&
            preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/', $html, $m)) {
            $image = $m[1];
        }

        if (empty($image) &&
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $clean_html, $m)) {
            $image = $m[1];
        }

        if (empty($image) &&
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $m)) {
            $image = $m[1];
        }

        if (!empty($image)) {
            $image = html_entity_decode($image);
            $image = self::abs_url($image, $url);
        }

        /* ---------------------------------------------------------
           7) Success
        --------------------------------------------------------- */
        return [
            'success'            => true,
            'title'              => $meta_title,
            'content'            => $clean_text,
            'content_html_clean' => $clean_html,
            'image'              => $image,
            'description'        => $meta_description,
            'og_title'           => $meta_og_title,
            'og_description'     => $meta_og_desc,
            'canonical'          => $meta_canonical,
            'keywords'           => $meta_keywords,
            'links'              => $meta_links,
            'error'              => '',
        ];
    }

    /* ---------------------------------------------------------
       Make absolute URL for images
    --------------------------------------------------------- */
    protected static function abs_url($src, $base) {

        if (empty($src)) return '';

        if (parse_url($src, PHP_URL_SCHEME) || str_starts_with($src, '//')) {
            return $src;
        }

        $b = parse_url($base);
        if (!$b || empty($b['scheme']) || empty($b['host'])) {
            return $src;
        }

        $scheme = $b['scheme'];
        $host   = $b['host'];
        $port   = isset($b['port']) ? ':' . $b['port'] : '';
        $path   = $b['path'] ?? '/';

        if (str_starts_with($src, '/')) {
            return $scheme . '://' . $host . $port . $src;
        }

        $dir = rtrim(preg_replace('#/[^/]*$#', '/', $path), '/') . '/';
        return $scheme . '://' . $host . $port . $dir . ltrim($src, './');
    }
}
