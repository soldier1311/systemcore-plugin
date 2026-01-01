<?php
if (!defined('ABSPATH')) exit;

class TwentyFourOunce_Registry {

    /* ========= Tables ========= */
    public static function tables() {
        global $wpdb;
        return [
            'prices'        => $wpdb->prefix . '24ounce_prices',
            'trading_state' => $wpdb->prefix . '24ounce_trading_state',
            'ledger'        => $wpdb->prefix . '24ounce_ledger',
        ];
    }

    /* ========= Paths ========= */
    public static function paths() {
        return [
            'includes' => plugin_dir_path(__FILE__),
            'assets'   => plugin_dir_url(dirname(__FILE__)) . 'assets/',
        ];
    }

    /* ========= Services ========= */
    public static function services() {
        return [
            'price_service' => 'TwentyFourOunce_Price_Service',
            'price_engine'  => 'TwentyFourOunce_Price_Engine',
        ];
    }
}
