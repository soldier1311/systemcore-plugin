<?php
/**
 * Admin Menu – 24Ounce
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page(
        '24Ounce Prices',
        '24Ounce',
        'manage_options',
        '24ounce-prices',
        'twentyfouounce_render_prices_page',
        'dashicons-chart-line',
        56
    );
});
