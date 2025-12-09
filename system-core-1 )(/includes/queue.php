<?php
if (!defined('ABSPATH')) exit;

/**
 * SystemCore Queue Manager — Priority Integrated + Logging
 */

class SystemCore_Queue {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'systemcore_queue';
    }

    public static function add($feed_id, $url, $lang = 'ar', $priority = 0) {
        global $wpdb;

        $insert = $wpdb->insert(self::table(), [
            'feed_id'       => (int) $feed_id,
            'url'           => esc_url_raw($url),
            'lang'          => sanitize_text_field($lang),
            'priority'      => (int) $priority,
            'status'        => 'pending',
            'attempts'      => 0,
            'post_id'       => 0,
            'error_message' => '',
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
            'started_at'    => null,
            'finished_at'   => null,
        ]);

        if ($insert) {
            SystemCore_Logger::log('info', "Queue added new job for URL: {$url}", 'queue');
        }

        return $insert;
    }

    public static function next_job($lang = 'ar') {
        global $wpdb;

        SystemCore_Logger::log('debug', "Queue requesting next job for lang {$lang}", 'queue');

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . "
                 WHERE status = 'pending' AND lang = %s
                 ORDER BY priority DESC, id ASC
                 LIMIT 1",
                $lang
            )
        );
    }

    public static function mark_processing($id) {
        global $wpdb;

        $res = $wpdb->update(
            self::table(),
            [
                'status'     => 'processing',
                'updated_at' => current_time('mysql'),
                'started_at' => current_time('mysql'),
            ],
            ['id' => (int) $id]
        );

        if ($res !== false) {
            SystemCore_Logger::log('info', "Queue started job #{$id}", 'queue');
        }

        return $res;
    }

    public static function mark_done($id, $post_id = 0) {
        global $wpdb;

        $res = $wpdb->update(
            self::table(),
            [
                'status'      => 'done',
                'post_id'     => (int) $post_id,
                'finished_at' => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ],
            ['id' => (int) $id]
        );

        if ($res !== false) {
            SystemCore_Logger::log('info', "Queue finished job #{$id}", 'queue');
        }

        return $res;
    }

    public static function mark_failed($id, $error_message = '') {
        global $wpdb;

        $res = $wpdb->update(
            self::table(),
            [
                'status'        => 'failed',
                'error_message' => sanitize_textarea_field($error_message),
                'finished_at'   => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ],
            ['id' => (int) $id]
        );

        if ($res !== false) {
            SystemCore_Logger::log('error', "Queue failed job #{$id}: {$error_message}", 'queue');
        }

        return $res;
    }
}
