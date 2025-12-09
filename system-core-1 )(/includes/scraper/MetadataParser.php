<?php

if (!defined('ABSPATH')) exit;

class SystemCore_MetadataParser {

    /**
     * Parse metadata from original HTML and cleaned HTML.
     */
    public static function parse($html, $clean_html, $fallback_title = '', $url = '') {

        $meta_title       = self::extract_meta($html, 'title');
        $og_title         = self::extract_property($html, 'og:title');

        $meta_description = self::extract_meta($html, 'description');
        $og_description   = self::extract_property($html, 'og:description');

        // Preferred OG image
        $og_image         = self::extract_property($html, 'og:image');
        if ($og_image === '') {
            $og_image = self::extract_property($html, 'twitter:image');
        }

        // Canonical
        $canonical = self::extract_property($html, 'og:url');
        if (!$canonical) {
            $canonical = self::extract_link_rel($html, 'canonical');
        }

        $keywords = self::extract_meta($html, 'keywords');
        $links    = self::extract_links($clean_html);

        // Title priority
        $title_final = $og_title ?: $meta_title ?: $fallback_title;
        $desc_final  = $og_description ?: $meta_description ?: '';

        return [
            'title'          => trim($title_final),
            'description'    => trim($desc_final),
            'image'          => $og_image,
            'og_title'       => $og_title,
            'og_description' => $og_description,
            'canonical'      => $canonical,
            'keywords'       => $keywords,
            'links'          => $links,
        ];
    }

    /**
     * Extract <meta name=""> values.
     */
    private static function extract_meta($html, $name) {

        if (empty($html)) return '';

        $pattern = '/<meta[^>]+name=["\']'
                 . preg_quote($name, '/')
                 . '["\'][^>]*content=["\']([^"\']+)["\']/i';

        return preg_match($pattern, $html, $m) ? trim($m[1]) : '';
    }

    /**
     * Extract <meta property=""> values (OpenGraph/Twitter).
     */
    private static function extract_property($html, $property) {

        if (empty($html)) return '';

        $pattern = '/<meta[^>]+property=["\']'
                 . preg_quote($property, '/')
                 . '["\'][^>]*content=["\']([^"\']+)["\']/i';

        if (preg_match($pattern, $html, $m)) {
            return trim($m[1]);
        }

        // Also try: <meta name="og:image">
        $pattern2 = '/<meta[^>]+name=["\']'
                  . preg_quote($property, '/')
                  . '["\'][^>]*content=["\']([^"\']+)["\']/i';

        return preg_match($pattern2, $html, $m2) ? trim($m2[1]) : '';
    }

    /**
     * Extract <link rel=""> values.
     */
    private static function extract_link_rel($html, $rel) {

        if (empty($html)) return '';

        $pattern = '/<link[^>]+rel=["\']'
                 . preg_quote($rel, '/')
                 . '["\'][^>]*href=["\']([^"\']+)["\']/i';

        return preg_match($pattern, $html, $m) ? trim($m[1]) : '';
    }

    /**
     * Extract links from cleaned HTML.
     */
    private static function extract_links($html) {

        if (empty($html)) return [];

        $links = [];

        if (preg_match_all('#<a[^>]+href=["\']([^"\']+)["\'][^>]*>#i', $html, $m)) {
            foreach ($m[1] as $href) {
                $href = trim($href);
                if ($href !== '') {
                    $links[] = $href;
                }
            }
        }

        return $links;
    }
}
