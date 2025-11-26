<?php

if (!defined('ABSPATH')) exit;

class SystemCore_Scheduler {

    /**
     * تهيئة الكرون
     */
    public static function init() {

        // إضافة interval مخصص: كل 5 دقائق
        add_filter('cron_schedules', function($schedules) {
            $schedules['every_5_minutes'] = [
                'interval' => 300,
                'display'  => 'Every 5 Minutes'
            ];
            return $schedules;
        });

        // تسجيل الحدث المجدول
        self::register_cron();
    }

    /**
     * تسجيل الحدث إذا لم يكن موجوداً
     */
    public static function register_cron() {
        if (!wp_next_scheduled('systemcore_cron_runner')) {
            wp_schedule_event(time(), 'every_5_minutes', 'systemcore_cron_runner');
        }
    }
}

// تشغيل عند تحميل الإضافة
add_action('plugins_loaded', ['SystemCore_Scheduler', 'init']);


// تشغيل عند التفعيل
register_activation_hook(dirname(__FILE__, 2) . '/system-core.php', function () {

    add_filter('cron_schedules', function($schedules) {
        $schedules['every_5_minutes'] = [
            'interval' => 300,
            'display'  => 'Every 5 Minutes'
        ];
        return $schedules;
    });

    if (!wp_next_scheduled('systemcore_cron_runner')) {
        wp_schedule_event(time(), 'every_5_minutes', 'systemcore_cron_runner');
    }
});


// إزالة الحدث عند التعطيل
register_deactivation_hook(dirname(__FILE__, 2) . '/system-core.php', function () {
    wp_clear_scheduled_hook('systemcore_cron_runner');
});


/**
 * الحدث الذي يتحكم بكل المنظومة
 * (Feed Loader + Queue + Publisher)
 */
add_action('systemcore_cron_runner', function () {

    SystemCore_Logger::info("CRON: systemcore_cron_runner started");

    // 1) جلب الخلاصات وإضافة روابط جديدة للـ Queue
    if (class_exists('SystemCore_Feed_Loader')) {
        SystemCore_Logger::info("CRON: Running Feed Loader");
        SystemCore_Feed_Loader::load_feeds('ar');
    }

    // 2) تشغيل الـ Publisher لمعالجة المهام داخل Queue
    if (class_exists('SystemCore_Publisher')) {
        SystemCore_Logger::info("CRON: Running Publisher");
        SystemCore_Publisher::run_batch('ar');
    }

    SystemCore_Logger::info("CRON: systemcore_cron_runner completed");
});
