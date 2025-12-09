<?php
if (!defined('ABSPATH')) exit;

require_once SYSTEMCORE_PLUGIN_PATH . 'includes/security.php';

add_action('wp_ajax_systemcore_dashboard_stats', 'systemcore_ajax_dashboard_stats');

function systemcore_ajax_dashboard_stats() {
    SystemCore_Security::verify();

    global $wpdb;

    $now      = current_time('mysql');
    $today    = date('Y-m-d 00:00:00', current_time('timestamp'));
    $queue_tb = $wpdb->prefix . 'systemcore_queue';
    $logs_tb  = $wpdb->prefix . 'systemcore_logs';
    $feeds_tb = $wpdb->prefix . 'systemcore_feed_sources';

    $data = [
        'queue' => [
            'total'      => 0,
            'pending'    => 0,
            'processing' => 0,
            'done'       => 0,
            'failed'     => 0,
        ],
        'publishing' => [
            'today_published' => 0,
        ],
        'ai' => [
            'today_calls' => 0,
            'last_error'  => '',
        ],
        'scheduler' => [
            'last_run'   => '',
            'last_error' => '',
        ],
        'feeds' => [],
        'logs'  => [],
    ];

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $queue_tb))) {
        $data['queue']['total']      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$queue_tb}");
        $data['queue']['pending']    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$queue_tb} WHERE status = 'pending'");
        $data['queue']['processing'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$queue_tb} WHERE status = 'processing'");
        $data['queue']['done']       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$queue_tb} WHERE status = 'done'");
        $data['queue']['failed']     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$queue_tb} WHERE status = 'failed'");
    }

    $data['publishing']['today_published'] = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(ID) FROM {$wpdb->posts}
         WHERE post_type = 'post'
           AND post_status = 'publish'
           AND post_date >= %s",
        $today
    ));

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $logs_tb))) {

        $rows = $wpdb->get_results(
            "SELECT id, level, context, message, created_at
             FROM {$logs_tb}
             ORDER BY id DESC
             LIMIT 10",
            ARRAY_A
        );
        $data['logs'] = $rows ?: [];

        $data['ai']['today_calls'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$logs_tb}
             WHERE context = 'ai'
               AND created_at >= %s",
            $today
        ));

        $last_ai_error = $wpdb->get_var(
            "SELECT message FROM {$logs_tb}
             WHERE context = 'ai' AND level = 'error'
             ORDER BY id DESC LIMIT 1"
        );
        $data['ai']['last_error'] = $last_ai_error ? (string) $last_ai_error : '';

        $last_scheduler_log = $wpdb->get_row(
            "SELECT message, created_at
             FROM {$logs_tb}
             WHERE context = 'scheduler'
             ORDER BY id DESC
             LIMIT 1",
            ARRAY_A
        );

        if ($last_scheduler_log) {
            $data['scheduler']['last_run'] = $last_scheduler_log['created_at'];
            if (stripos($last_scheduler_log['message'], 'error') !== false) {
                $data['scheduler']['last_error'] = $last_scheduler_log['message'];
            }
        }
    }

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $feeds_tb))) {
        $feeds = $wpdb->get_results(
            "SELECT id, name, url, success_count, fail_count
             FROM {$feeds_tb}
             ORDER BY success_count DESC
             LIMIT 10",
            ARRAY_A
        );
        $data['feeds'] = $feeds ?: [];
    }

    wp_send_json_success($data);
}
