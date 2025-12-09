<?php

if (!defined('ABSPATH')) exit;

class SystemCore_Dedupe {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'systemcore_dedupe';
    }

    /** Normalize URL */
    private static function normalize_url($url) {
        $url = trim((string) $url);
        if ($url === '') return '';

        $url = esc_url_raw($url);
        $parts = wp_parse_url($url);

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme']);
        $host   = strtolower($parts['host']);
        $path   = isset($parts['path']) ? $parts['path'] : '';

        if ($path !== '/') {
            $path = rtrim($path, '/');
            if ($path === '') $path = '/';
        }

        $query_args = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query_args);
            $tracking = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','fbclid','gclid'];
            foreach ($tracking as $t) unset($query_args[$t]);
        }

        $normalized = $scheme . '://' . $host;

        if (!empty($parts['port'])) {
            $normalized .= ':' . (int)$parts['port'];
        }

        $normalized .= $path;

        if (!empty($query_args)) {
            $normalized = add_query_arg($query_args, $normalized);
        }

        return $normalized;
    }

    /** Title hash */
    private static function make_title_hash($title) {
        $clean  = wp_strip_all_tags((string)$title);
        $norm   = mb_strtolower(trim(preg_replace('/\s+/', ' ', $clean)));
        if ($norm === '') return ['', ''];
        return [$clean, md5($norm)];
    }

    /** Normalize hash length */
    private static function normalize_hash($hash) {
        $hash = trim((string)$hash);
        if ($hash === '') return '';
        return substr($hash, 0, 64);
    }

    /** Add new dedupe entry */
    public static function add($url, $title, $content_hash = '', $image_hash = '', $source_id = 0, $source_type = '') {
        global $wpdb;

        if (empty($url) && empty($title)) return false;

        $table = self::table();

        $normalized_url = self::normalize_url($url);
        list($clean_title, $title_hash) = self::make_title_hash($title);
        $content_hash = self::normalize_hash($content_hash);
        $image_hash   = self::normalize_hash($image_hash);

        $data = [
            'url'          => $normalized_url,
            'title'        => $clean_title,
            'title_hash'   => $title_hash,
            'content_hash' => $content_hash,
            'image_hash'   => $image_hash,
            'source_id'    => (int)$source_id,
            'source_type'  => sanitize_key($source_type),
            'created_at'   => current_time('mysql'),
        ];

        $formats = ['%s','%s','%s','%s','%s','%d','%s','%s'];

        return (bool)$wpdb->insert($table, $data, $formats);
    }

    /** Check duplicate URL */
    public static function exists_url($url) {
        global $wpdb;
        $table = self::table();
        $normalized_url = self::normalize_url($url);
        if ($normalized_url === '') return false;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE url = %s", $normalized_url);
        return (int)$wpdb->get_var($sql) > 0;
    }

    /** Get full row by URL */
    public static function get_by_url($url) {
        global $wpdb;
        $table = self::table();
        $normalized_url = self::normalize_url($url);
        if ($normalized_url === '') return null;

        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE url = %s LIMIT 1", $normalized_url);
        return $wpdb->get_row($sql);
    }

    /** Check title hash */
    public static function exists_title($title) {
        global $wpdb;
        $table = self::table();
        list($_c, $hash) = self::make_title_hash($title);
        if ($hash === '') return false;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE title_hash = %s", $hash);
        return (int)$wpdb->get_var($sql) > 0;
    }

    /** Check content hash */
    public static function exists_content_hash($content_hash) {
        global $wpdb;
        $table = self::table();
        $hash = self::normalize_hash($content_hash);
        if ($hash === '') return false;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE content_hash = %s", $hash);
        return (int)$wpdb->get_var($sql) > 0;
    }

    /** Check image hash */
    public static function exists_image_hash($image_hash) {
        global $wpdb;
        $table = self::table();
        $hash = self::normalize_hash($image_hash);
        if ($hash === '') return false;

        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE image_hash = %s", $hash);
        return (int)$wpdb->get_var($sql) > 0;
    }

    /** Cleanup old entries */
    public static function cleanup() {
        global $wpdb;
        $table = self::table();
        $sql = $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(%s, INTERVAL 7 DAY)",
            current_time('mysql')
        );
        $wpdb->query($sql);
    }
}
