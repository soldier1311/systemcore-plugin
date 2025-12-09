<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

/**
 * Helpers used by overview cards
 */
if (!function_exists('systemcore_table_exists')) {
    function systemcore_table_exists($table_name) {
        global $wpdb;
        if (empty($table_name)) return false;
        $like  = $wpdb->prepare('SHOW TABLES LIKE %s', $table_name);
        $found = $wpdb->get_var($like);
        return ($found === $table_name);
    }
}

if (!function_exists('systemcore_column_exists')) {
    function systemcore_column_exists($table_name, $column_name) {
        global $wpdb;
        if (empty($table_name) || empty($column_name)) return false;

        $db = defined('DB_NAME') ? DB_NAME : (property_exists($wpdb, 'dbname') ? $wpdb->dbname : '');

        if (empty($db)) return false;

        $sql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME=%s",
            $db, $table_name, $column_name
        );

        return ((int) $wpdb->get_var($sql) > 0);
    }
}

/**
 * Safe include helper
 */
if (!function_exists('systemcore_overview_safe_include')) {
    function systemcore_overview_safe_include($file_path) {

        $file_path = (string) $file_path;

        $root = realpath(SYSTEMCORE_PATH);
        $real = realpath($file_path);

        if (!$root || !$real || strpos($real, $root) !== 0 || !file_exists($real)) {
            echo '<div class="notice notice-error"><p><strong>SystemCore:</strong> Missing overview file: <code>' .
                 esc_html(str_replace(SYSTEMCORE_PATH, '', $file_path)) .
                 '</code></p></div>';
            return;
        }

        include $real;
    }
}

/**
 * Status snapshot helper
 * - باقي الأنظمة من جدول systemcore_logs
 * - Queue من جدول systemcore_queue (حقيقي)
 */
if (!function_exists('systemcore_get_system_status_snapshot')) {
    function systemcore_get_system_status_snapshot() {
        global $wpdb;

        $logs_table  = $wpdb->prefix . 'systemcore_logs';
        $queue_table = $wpdb->prefix . 'systemcore_queue';
        $statuses    = [];

        $has_logs  = systemcore_table_exists($logs_table);
        $has_queue = systemcore_table_exists($queue_table);

        // تعريف الأنظمة
        $systems = [
            'feed_loader' => ['label' => 'Feed Loader', 'source' => 'feed'],
            'queue'       => ['label' => 'Queue', 'source' => 'queue'],
            'publisher'   => ['label' => 'Publisher', 'source' => 'publisher'],
            'scheduler'   => ['label' => 'Scheduler', 'source' => 'cron'],
            'dedupe'      => ['label' => 'Dedupe', 'source' => 'dedupe'],
            'scraper'     => ['label' => 'Scraper', 'source' => 'scraper'],
            'ai_engine'   => ['label' => 'AI Engine', 'source' => 'ai'],
        ];

        $max_age_sec = 5 * 60;

        foreach ($systems as $key => $config) {

            $source    = $config['source'];
            $label     = $config['label'];
            $created_at = null;

            /**
             * 1) Queue: نأخذ آخر نشاط من جدول الـ Queue نفسه
             */
            if ($key === 'queue') {

                if (!$has_queue) {
                    $statuses[$key] = [
                        'label'      => $label,
                        'state'      => 'stale',
                        'last_label' => 'No activity yet',
                    ];
                    continue;
                }

                // آخر تحديث على أي صف في الـ Queue
                $created_at = $wpdb->get_var("SELECT MAX(updated_at) FROM {$queue_table}");

                if (empty($created_at)) {
                    $statuses[$key] = [
                        'label'      => $label,
                        'state'      => 'stale',
                        'last_label' => 'No activity yet',
                    ];
                    continue;
                }

            } else {
                /**
                 * 2) باقي الأنظمة: من جدول اللوجز كما هو
                 */
                if (!$has_logs) {
                    $statuses[$key] = [
                        'label'      => $label,
                        'state'      => 'stale',
                        'last_label' => 'No activity yet',
                    ];
                    continue;
                }

                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT message, created_at
                         FROM {$logs_table}
                         WHERE source = %s
                         ORDER BY id DESC
                         LIMIT 1",
                        $source
                    )
                );

                if (!$row || empty($row->created_at)) {
                    $statuses[$key] = [
                        'label'      => $label,
                        'state'      => 'stale',
                        'last_label' => 'No activity yet',
                    ];
                    continue;
                }

                $created_at = $row->created_at;
            }

            // حساب العمر اعتماداً على وقت ووردبريس
            $ts     = strtotime($created_at);
            $ts_now = strtotime(current_time('mysql'));

            if (!$ts || !$ts_now) {
                $statuses[$key] = [
                    'label'      => $label,
                    'state'      => 'stale',
                    'last_label' => esc_html($created_at),
                ];
                continue;
            }

            $age = $ts_now - $ts;
            if ($age < 0) {
                $age = 0;
            }

            $state = ($age <= $max_age_sec) ? 'ok' : 'stale';

            if ($age < 60) {
                $age_text = sprintf(__('Last run: %ds ago', 'systemcore'), $age);
            } elseif ($age < 3600) {
                $age_text = sprintf(__('Last run: %d min ago', 'systemcore'), floor($age / 60));
            } else {
                $age_text = sprintf(__('Last run: %d h ago', 'systemcore'), floor($age / 3600));
            }

            $statuses[$key] = [
                'label'      => $label,
                'state'      => $state,
                'last_label' => $age_text,
            ];
        }

        return $statuses;
    }
}

$base = SYSTEMCORE_PATH . 'includes/admin/pages/overview/';

$system_status = systemcore_get_system_status_snapshot();

$base_url      = admin_url('admin.php?page=systemcore-dashboard');
$feeds_url     = $base_url . '&tab=feeds';
$queue_url     = $base_url . '&tab=queue';
$logs_url      = $base_url . '&tab=logs';
$publisher_url = $base_url . '&tab=publisher';
$ai_url        = $base_url . '&tab=ai';

?>
<div class="wrap systemcore-overview-wrap">
    <h1>SystemCore — System Overview</h1>

    <style>
        .systemcore-overview-wrap .sc-status-bar {
            margin: 15px 0 20px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #f5f7fb;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: stretch;
        }
        .systemcore-overview-wrap .sc-status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        .systemcore-overview-wrap .sc-status-label {
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #111827;
        }
        .systemcore-overview-wrap .sc-status-meta {
            font-size: 11px;
            color: #6b7280;
        }
        .systemcore-overview-wrap .sc-status-dot {
            width: 14px;
            height: 14px;
            border-radius: 999px;
            border: 2px solid #111827;
            box-sizing: border-box;
        }
        .systemcore-overview-wrap .sc-status-dot.ok {
            background: #facc15;
            border-color: #a16207;
        }
        .systemcore-overview-wrap .sc-status-dot.stale {
            background: #ef4444;
            border-color: #991b1b;
        }

        .systemcore-overview-wrap .sc-grid.sc-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }
    </style>

    <div class="sc-status-bar">
        <?php foreach ($system_status as $status): ?>
            <div class="sc-status-item">
                <span class="sc-status-dot <?php echo esc_attr($status['state']); ?>"></span>
                <div>
                    <div class="sc-status-label"><?php echo esc_html($status['label']); ?></div>
                    <div class="sc-status-meta"><?php echo esc_html($status['last_label']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="sc-grid sc-grid-3" style="margin-top:10px;">
        <?php
        systemcore_overview_safe_include($base . 'feeds-stats.php');
        systemcore_overview_safe_include($base . 'queue-stats.php');
        systemcore_overview_safe_include($base . 'logs-stats.php');
        systemcore_overview_safe_include($base . 'posts-stats.php');
        systemcore_overview_safe_include($base . 'publisher-overview.php');
        ?>
    </div>

    <?php
    systemcore_overview_safe_include($base . 'feeds-summary.php');
    systemcore_overview_safe_include($base . 'cron-overview.php');
    systemcore_overview_safe_include($base . 'ai-overview.php');
    ?>
</div>
