<?php
if (!defined('ABSPATH')) exit;

final class TwentyFourOunce_Price_Admin_Service {

    private $db;
    private string $products;
    private string $prices;
    private string $history;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        $prefix = $wpdb->prefix . '24ounce_';
        $this->products = $prefix . 'products';
        $this->prices   = $prefix . 'prices';
        $this->history  = $prefix . 'price_history';
    }

    public function set_price(string $slug, float $buy, float $sell): bool {

        if ($buy < 0 || $sell < 0 || $sell < $buy) {
            return false;
        }

        $product = $this->db->get_row(
            $this->db->prepare(
                "SELECT id FROM {$this->products}
                 WHERE slug = %s AND active = 1
                 LIMIT 1",
                $slug
            ),
            ARRAY_A
        );

        if (!$product) {
            return false;
        }

        $this->db->replace(
            $this->prices,
            [
                'product_id' => $product['id'],
                'buy_price'  => round($buy, 2),
                'sell_price' => round($sell, 2),
                'updated_at' => current_time('mysql', true),
            ],
            ['%d','%f','%f','%s']
        );

        $this->db->insert(
            $this->history,
            [
                'product_id'  => $product['id'],
                'buy_price'   => round($buy, 2),
                'sell_price'  => round($sell, 2),
                'recorded_at' => current_time('mysql', true),
            ],
            ['%d','%f','%f','%s']
        );

        return true;
    }
}
