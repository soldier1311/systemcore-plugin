<?php
if (!defined('ABSPATH')) exit;

/**
 * SystemCore Queue Manager — Unified Queue Layer
 * 
 * هذا الملف يجعل نظام Queue مستقلاً ومنظماً بدون تغيير سلوك Publisher الحالي.
 * وهو يعتمد على نفس الجداول التي أنشأتها Database class سابقاً.
 */

class SystemCore_Queue {

    /**
     * Add item to queue
     */
    public static function add($feed_id, $url, $lang = 'ar') {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_queue';

        return $wpdb->insert($table, [
            'feed_id'   => (int) $feed_id,
            'url'       => esc_url_raw($url),
            'lang'      => sanitize_text_field($lang),
            'status'    => 'pending',
            'attempts'  => 0,
            'created_at'=> current_time('mysql'),
        ]);
    }

    /**
     * Get next pending job
     */
    public static function next_job($lang = 'ar') {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_queue';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status='pending' AND lang=%s
                 ORDER BY id ASC
                 LIMIT 1",
                $lang
            )
        );
    }

    /**
     * Mark job as processing
     */
    public static function mark_processing($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_queue';

        return $wpdb->update($table, [
            'status' => 'processing',
            'started_at' => current_time('mysql'),
        ], [
            'id' => (int) $id
        ]);
    }

    /**
     * Mark job as completed
     */
    public static function mark_done($id, $post_id = 0) {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_queue';

        return $wpdb->update($table, [
            'status' => 'done',
            'post_id' => (int) $post_id,
            'finished_at' => current_time('mysql'),
        ], [
            'id' => (int) $id
        ]);
    }

    /**
     * Mark job as failed
     */
    public static function mark_failed($id, $error_message = '') {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_queue';

        return $wpdb->update($table, [
            'status' => 'failed',
            'error_message' => sanitize_textarea_field($error_message),
            'finished_at' => current_time('mysql'),
        ], [
            'id' => (int) $id
        ]);
    }

    /**
     * Retry logic
     */
    public static function increment_attempts($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_queue';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET attempts = attempts + 1
                 WHERE id = %d",
                $id
            )
        );
    }
}
