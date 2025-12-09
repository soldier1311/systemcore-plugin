<?php
if (!defined('ABSPATH')) exit;

/**
 * SystemCore Bootstrap Loader
 */

class SystemCore_Bootstrap {

    public static function init() {

        self::load_core();
        self::load_ui_framework(); // load early
        self::load_ai();
        self::load_publisher();
        self::load_admin();
        self::load_ajax();
    }

    /**
     * ============================================================
     * CORE MODULES
     * ============================================================
     */
    private static function load_core() {

        require_once SYSTEMCORE_PATH . 'includes/database.php';
        require_once SYSTEMCORE_PATH . 'includes/logger.php';

        // Priority Engine
        $prio = SYSTEMCORE_PATH . 'includes/priority-engine.php';
        if (file_exists($prio)) require_once $prio;

        // Queue system
        require_once SYSTEMCORE_PATH . 'includes/queue.php';

        // Scraper
        require_once SYSTEMCORE_PATH . 'includes/scraper.php';

        // Dedupe
        $dedupe = SYSTEMCORE_PATH . 'includes/class-systemcore-dedupe.php';
        if (file_exists($dedupe)) require_once $dedupe;

        /**
         * FEED SYSTEM (helpers → queue-handler → processors → loader)
         */
        require_once SYSTEMCORE_PATH . 'includes/feed/feed-helpers.php';
        require_once SYSTEMCORE_PATH . 'includes/feed/Queue_Handler.php';
        require_once SYSTEMCORE_PATH . 'includes/feed/Processors.php';
        require_once SYSTEMCORE_PATH . 'includes/feed/Feed_Loader.php';

        // Cron Scheduler
        require_once SYSTEMCORE_PATH . 'includes/scheduler.php';
    }

    /**
     * ============================================================
     * UI SYSTEM
     * ============================================================
     */
    private static function load_ui_framework() {

        $loader = SYSTEMCORE_PATH . 'includes/ui/systemcore-ui-loader.php';
        if (file_exists($loader)) require_once $loader;
    }

    /**
     * ============================================================
     * AI MODULES
     * (يدعم الهيكلة الجديدة + القديمة بدون تكرار)
     * ============================================================
     */
    private static function load_ai() {

        // AI Engine (new folder first)
        $new_ai_engine = SYSTEMCORE_PATH . 'includes/ai/ai-engine.php';
        $old_ai_engine = SYSTEMCORE_PATH . 'includes/ai-engine.php';

        if (file_exists($new_ai_engine)) {
            require_once $new_ai_engine;
        } elseif (file_exists($old_ai_engine)) {
            require_once $old_ai_engine;
        }

        // AI Category
        $new_ai_cat = SYSTEMCORE_PATH . 'includes/ai/ai-category.php';
        $old_ai_cat = SYSTEMCORE_PATH . 'includes/ai-category.php';

        if (file_exists($new_ai_cat)) {
            require_once $new_ai_cat;
        } elseif (file_exists($old_ai_cat)) {
            require_once $old_ai_cat;
        }
    }

    /**
     * ============================================================
     * PUBLISHER MODULE
     * ============================================================
     */
    private static function load_publisher() {

        $fn = SYSTEMCORE_PATH . 'includes/publisher/functions.php';
        if (file_exists($fn)) require_once $fn;

        $pub = SYSTEMCORE_PATH . 'includes/publisher/class-systemcore-publisher.php';
        if (file_exists($pub)) require_once $pub;
    }

    /**
     * ============================================================
     * ADMIN PAGES
     * ============================================================
     */
    private static function load_admin() {

        $admin = SYSTEMCORE_PATH . 'includes/admin/index.php';
        if (file_exists($admin)) require_once $admin;
    }

    /**
     * ============================================================
     * AJAX MODULES
     * ============================================================
     */
    private static function load_ajax() {

        $ajax_files = [
            'includes/ajax/ajax-dashboard.php',
            'includes/ajax/ajax-fetch.php',
            'includes/ajax/ajax-queue.php',
            'includes/ajax/ajax-logs.php',
            'includes/ajax/ajax-publisher.php',
            'includes/ajax/ajax-feed-sources.php'
        ];

        foreach ($ajax_files as $file) {
            $path = SYSTEMCORE_PATH . $file;
            if (file_exists($path)) require_once $path;
        }
    }
}
