<?php
if (!defined('ABSPATH')) exit;

/**
 * ----------------------------------------------------
 * Admin Menu
 * ----------------------------------------------------
 */
add_action('admin_menu', function () {

    add_menu_page(
        'SystemCore Dashboard',
        'SystemCore',
        'manage_options',
        'systemcore-dashboard',
        'systemcore_render_dashboard',
        'dashicons-hammer',
        3
    );

    add_submenu_page(
        'systemcore-dashboard',
        'AI Settings',
        'AI Settings',
        'manage_options',
        'systemcore-ai-settings',
        'systemcore_render_ai_settings'
    );

    add_submenu_page(
        'systemcore-dashboard',
        'Publisher',
        'Publisher',
        'manage_options',
        'systemcore-publisher-settings',
        'systemcore_render_publisher_settings_page'
    );

    add_submenu_page(
        'systemcore-dashboard',
        'Queue',
        'Queue',
        'manage_options',
        'systemcore-queue',
        'systemcore_render_queue'
    );

    add_submenu_page(
        'systemcore-dashboard',
        'Feed Sources',
        'Feed Sources',
        'manage_options',
        'systemcore-feed-sources',
        'systemcore_render_feed_sources'
    );

    add_submenu_page(
        'systemcore-dashboard',
        'Logs',
        'Logs',
        'manage_options',
        'systemcore-logs',
        'systemcore_render_logs'
    );

    add_submenu_page(
        'systemcore-dashboard',
        'Scraper Test',
        'Scraper Test',
        'manage_options',
        'systemcore-scraper-test',
        'systemcore_render_scraper_test'
    );
});

/**
 * ----------------------------------------------------
 * Rendering Functions
 * ----------------------------------------------------
 */

function systemcore_render_dashboard() {
    include __DIR__ . '/dashboard.php';
}

function systemcore_render_ai_settings() {
    include __DIR__ . '/ai-settings.php';
}

function systemcore_render_publisher_settings_page() {
    include __DIR__ . '/publisher-settings.php';
}

function systemcore_render_queue() {
    include __DIR__ . '/queue.php';
}

function systemcore_render_feed_sources() {
    include __DIR__ . '/feed-sources.php';
}

function systemcore_render_logs() {
    include __DIR__ . '/fetch-log.php';
}

function systemcore_render_scraper_test() {
    include __DIR__ . '/test-scraper.php';
}


/**
 * ----------------------------------------------------
 * Admin Assets Loader (CSS + JS)
 * ----------------------------------------------------
 */
add_action('admin_enqueue_scripts', function($hook) {

    // load only for SystemCore pages
    if (strpos($hook, 'systemcore') === false) {
        return;
    }

    wp_enqueue_style(
        'systemcore-admin',
        plugin_dir_url(dirname(__DIR__)) . 'assets/systemcore-admin.css',
        [],
        '1.0'
    );

    wp_enqueue_script(
        'systemcore-admin',
        plugin_dir_url(dirname(__DIR__)) . 'assets/systemcore-admin.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script(
        'systemcore-admin',
        'SystemCoreAdmin',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('systemcore_admin_nonce'),
        ]
    );
});
