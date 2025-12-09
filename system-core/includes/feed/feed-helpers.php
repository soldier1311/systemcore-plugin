<?php
if (!defined('ABSPATH')) exit;

class SystemCore_Feed_Helpers {

    /**
     * Fetch remote body for XML/JSON feeds
     */
    public static function fetch_remote_body($url) {

        $resp = wp_remote_get($url, ['timeout' => 20]);
        if (is_wp_error($resp)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return false;
        }

        $body = wp_remote_retrieve_body($resp);
        if (empty($body)) {
            return false;
        }

        return $body;
    }

    /**
     * Insert fetch log into systemcore_fetch_log (if table exists)
     */
    public static function insert_fetch_log($feed_id, $feed_name, $feed_url, $feed_type, $status, $message) {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_fetch_log';
        if (empty($table)) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'feed_id'    => (int) $feed_id,
                'feed_name'  => (string) $feed_name,
                'feed_url'   => (string) $feed_url,
                'feed_type'  => (string) $feed_type,
                'status'     => (string) $status,
                'message'    => (string) $message,
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Safe JSON path resolver (for JSON feeds)
     */
    public static function get_by_path($data, $path) {
        if (!is_array($data)) {
            return null;
        }

        $path = trim((string) $path);
        if ($path === '') {
            return $data;
        }

        $parts = array_filter(array_map('trim', explode('.', $path)));
        if (empty($parts)) {
            return $data;
        }

        $current = $data;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }
}
