<?php
if (!defined('ABSPATH')) exit;

class SystemCore_Feed_Queue_Handler {

    /**
     * SINGLE push_to_queue()
     */
    public static function push_to_queue(
        $feed_id,
        $feed_name,
        $link,
        $title,
        $lang,
        $queue_table,
        $priority = 0
    ) {
        global $wpdb;

        $link  = esc_url_raw(trim((string) $link));
        $title = trim((string) $title);

        if (empty($link)) {
            return;
        }

        // Skip if already published (dedupe table)
        if (class_exists('SystemCore_Dedupe')
            && method_exists('SystemCore_Dedupe', 'exists_url')) {

            if (SystemCore_Dedupe::exists_url($link)) {
                return;
            }
        }

        // Avoid duplicates inside queue itself (any status)
        $exists_queue = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$queue_table} WHERE url = %s LIMIT 1",
                $link
            )
        );
        if ($exists_queue) {
            return;
        }

        // Add to queue
        $added = false;

        if (class_exists('SystemCore_Queue')
            && method_exists('SystemCore_Queue', 'add')) {

            $added = (bool) SystemCore_Queue::add(
                (int) $feed_id,
                $link,
                (string) $lang,
                (int) $priority,
                (string) $title
            );

        } else {
            $added = (bool) $wpdb->insert(
                $queue_table,
                [
                    'feed_id'    => (int) $feed_id,
                    'url'        => $link,
                    'lang'       => (string) $lang,
                    'status'     => 'pending',
                    'priority'   => (int) $priority,
                    'attempts'   => 0,
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        if ($added && class_exists('SystemCore_Logger')) {
            SystemCore_Logger::info(
                'Feed Loader: queued',
                'feed',
                wp_json_encode([
                    'feed_id'    => (int) $feed_id,
                    'feed_name'  => (string) $feed_name,
                    'lang'       => (string) $lang,
                    'priority'   => (int) $priority,
                    'url'        => (string) $link,
                    'title'      => (string) $title,
                ])
            );
        }
    }
}
