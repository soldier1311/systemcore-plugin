<?php
/**
 * Plugin Name: 24Ounce Core
 * Description: Core engine for 24Ounce trading platform.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/

define('TWENTYFOUR_OUNCE_PATH', plugin_dir_path(__FILE__));
define('TWENTYFOUR_OUNCE_URL', plugin_dir_url(__FILE__));
define('TWENTYFOUR_OUNCE_VERSION', '1.0.0');

/*
|--------------------------------------------------------------------------
| Registry + Code Guard (MUST LOAD FIRST)
|--------------------------------------------------------------------------
*/

require_once TWENTYFOUR_OUNCE_PATH . 'includes/registry.php';
require_once TWENTYFOUR_OUNCE_PATH . 'includes/dev-guard.php';

TwentyFourOunce_Code_Guard::init();

/*
|--------------------------------------------------------------------------
| Core Loader
|--------------------------------------------------------------------------
*/

$includes = [

    // Database
    'includes/database.php',

    // Price Engine
    'includes/price-engine.php',
    'includes/price-service.php',
    'includes/price-ajax.php',

    // Order Engine
    'includes/order-engine.php',

    // Vault / Ledger Engine
    'includes/vault-ledger-engine.php',

    // Wallet Engine
    'includes/wallet-engine.php',

    // Charts
    'includes/chart-engine.php',
    'includes/chart-ajax.php',
];

foreach ($includes as $file) {
    $path = TWENTYFOUR_OUNCE_PATH . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

/*
|--------------------------------------------------------------------------
| Admin Loader
|--------------------------------------------------------------------------
*/

if (is_admin()) {

    $admin_files = [
        'admin/menu.php',
        'admin/page-prices.php',
        'admin/save-handler.php',

        // Trading Lock AJAX
        'admin/ajax-trading.php',
    ];

    foreach ($admin_files as $file) {
        $path = TWENTYFOUR_OUNCE_PATH . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Assets
|--------------------------------------------------------------------------
*/

// Admin assets (only plugin admin page)
add_action('admin_enqueue_scripts', function ($hook) {

    if ($hook !== 'toplevel_page_24ounce-prices') {
        return;
    }

    wp_enqueue_style(
        '24ounce-admin-ui',
        TWENTYFOUR_OUNCE_URL . 'assets/css/24ounce-ui.css',
        [],
        TWENTYFOUR_OUNCE_VERSION
    );

    wp_enqueue_script(
        '24ounce-admin-trading',
        TWENTYFOUR_OUNCE_URL . 'assets/js/admin-trading.js',
        ['jquery'],
        TWENTYFOUR_OUNCE_VERSION,
        true
    );

    wp_localize_script('24ounce-admin-trading', 'TwentyFourOunce', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('24ounce_trading_nonce'),
    ]);
});

// Frontend assets
add_action('wp_enqueue_scripts', function () {

    wp_enqueue_style(
        '24ounce-ui',
        TWENTYFOUR_OUNCE_URL . 'assets/css/24ounce-ui.css',
        [],
        TWENTYFOUR_OUNCE_VERSION
    );

    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js',
        [],
        null,
        true
    );

    wp_enqueue_script(
        '24ounce-ui',
        TWENTYFOUR_OUNCE_URL . 'assets/js/24ounce-ui.js',
        ['chartjs'],
        TWENTYFOUR_OUNCE_VERSION,
        true
    );

    wp_localize_script('24ounce-ui', 'twentyfourounceAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('24ounce_nonce'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Gold Chart Page (Create Once)
|--------------------------------------------------------------------------
*/

function twentyfourounce_create_gold_chart_page(): void {

    $slug = 'gold-chart';

    if (get_page_by_path($slug)) {
        return;
    }

    wp_insert_post([
        'post_title'   => 'الرسم البياني لأسعار الذهب',
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '',
    ]);
}

/*
|--------------------------------------------------------------------------
| Plugin Activation
|--------------------------------------------------------------------------
*/

register_activation_hook(__FILE__, function () {

    require_once TWENTYFOUR_OUNCE_PATH . 'includes/database.php';
    (new TwentyFourOunce_Database())->install();

    twentyfourounce_create_gold_chart_page();

    flush_rewrite_rules();
});

/*
|--------------------------------------------------------------------------
| Template Override
|--------------------------------------------------------------------------
*/

add_filter('template_include', function ($template) {

    if (is_page('gold-chart')) {

        $custom = TWENTYFOUR_OUNCE_PATH . 'templates/gold-chart.php';

        if (file_exists($custom)) {
            return $custom;
        }
    }

    return $template;
});

/*
|--------------------------------------------------------------------------
| Safety Net
|--------------------------------------------------------------------------
*/

add_action('init', function () {

    if (!get_page_by_path('gold-chart')) {
        twentyfourounce_create_gold_chart_page();
    }
});
