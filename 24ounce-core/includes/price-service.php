<?php
/**
 * 24Ounce Price Service
 * Read-only service layer with caching
 */

if (!defined('ABSPATH')) exit;

final class TwentyFourOunce_Price_Service {

    /** @var wpdb */
    private $db;

    private $prices_table;

    /**
     * Cache settings
     */
    private const CACHE_KEY_ALL = '24ounce_prices_all';
    private const CACHE_TTL     = 60; // seconds

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->prices_table = $wpdb->prefix . '24ounce_prices';
    }

    /**
     * Get all prices (cached)
     */
    public function get_all(): array {
        $cached = get_transient(self::CACHE_KEY_ALL);
        if ($cached !== false) {
            return (array) $cached;
        }

        $sql = "SELECT asset, buy_price, sell_price, updated_at
                FROM {$this->prices_table}
                ORDER BY asset ASC";

        $rows = (array) $this->db->get_results($sql, ARRAY_A);

        set_transient(self::CACHE_KEY_ALL, $rows, self::CACHE_TTL);

        return $rows;
    }

    /**
     * Get price by asset (from cached all)
     */
    public function get_by_asset(string $asset): ?array {
        $asset = sanitize_text_field($asset);

        foreach ($this->get_all() as $row) {
            if ((string) $row['asset'] === (string) $asset) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Helper: get buy price only
     */
    public function get_buy(string $asset): ?float {
        $row = $this->get_by_asset($asset);
        return $row ? (float) $row['buy_price'] : null;
    }

    /**
     * Helper: get sell price only
     */
    public function get_sell(string $asset): ?float {
        $row = $this->get_by_asset($asset);
        return $row ? (float) $row['sell_price'] : null;
    }

    /**
     * Clear cache (call after price updates)
     */
    public function clear_cache(): void {
        delete_transient(self::CACHE_KEY_ALL);
    }
}
