<?php
if (!defined('ABSPATH')) exit;

class SystemCore_Scheduler {

    /**
     * Initialize scheduler components
     */
    public static function init() {
        add_filter('cron_schedules', [__CLASS__, 'add_schedules']);
        add_action('init', [__CLASS__, 'register_cron']);
    }

    /**
     * Custom cron intervals
     */
    public static function add_schedules($schedules) {

        // Every 5 minutes – main runner
        if (!isset($schedules['every_5_minutes'])) {
            $schedules['every_5_minutes'] = [
                'interval' => 300,
                'display'  => __('Every 5 Minutes', 'system-core')
            ];
        }

        // Every 12 hours – dedupe cleanup
        if (!isset($schedules['systemcore_12_hours'])) {
            $schedules['systemcore_12_hours'] = [
                'interval' => 12 * HOUR_IN_SECONDS,
                'display'  => __('Every 12 Hours (Dedupe Cleanup)', 'system-core')
            ];
        }

        return $schedules;
    }

    /**
     * Register cron events safely
     */
    public static function register_cron() {

        // Prevent double-registration
        if (!wp_next_scheduled('systemcore_cron_runner')) {
            wp_schedule_event(time() + 60, 'every_5_minutes', 'systemcore_cron_runner');
        }

        if (!wp_next_scheduled('systemcore_dedupe_cleanup_event')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'systemcore_12_hours', 'systemcore_dedupe_cleanup_event');
        }
    }

    /**
     * Clear cron hooks
     */
    public static function clear_cron() {
        wp_clear_scheduled_hook('systemcore_cron_runner');
        wp_clear_scheduled_hook('systemcore_dedupe_cleanup_event');
    }
}

SystemCore_Scheduler::init();


/* ============================================================
   MAIN CRON RUNNER (Fetch → Queue → Publish)
============================================================ */
add_action('systemcore_cron_runner', function () {

    if (class_exists('SystemCore_Logger')) {
        SystemCore_Logger::info("CRON: systemcore_cron_runner started", 'cron');
    }

    // 1) FETCH SOURCES → QUEUE
    if (class_exists('SystemCore_Feed_Loader')) {

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::info("CRON: Running Feed Loader", 'cron', wp_json_encode(['lang' => 'ar']));
        }

        SystemCore_Feed_Loader::load_feeds('ar');
    }

    // 2) PUBLISH ARABIC POSTS
    if (class_exists('SystemCore_Publisher')) {

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::info("CRON: Running Publisher", 'cron', wp_json_encode(['lang' => 'ar']));
        }

        SystemCore_Publisher::run_batch('ar');
    }

    // 3) LOGS CLEANUP (last 3 days only)
    global $wpdb;
    $table = $wpdb->prefix . 'systemcore_logs';

    // MySQL datetime in UTC — correct!
    $date_limit = gmdate('Y-m-d H:i:s', strtotime('-3 days'));

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $date_limit
        )
    );

    if (class_exists('SystemCore_Logger')) {
        SystemCore_Logger::info("CRON: systemcore_cron_runner completed", 'cron');
    }
});


/* ============================================================
   DEDUPE CLEANUP (Every 12 hours)
============================================================ */
add_action('systemcore_dedupe_cleanup_event', function () {

    if (class_exists('SystemCore_Dedupe')) {
        SystemCore_Dedupe::cleanup();
    }
});
