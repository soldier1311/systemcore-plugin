<?php
/**
 * 24Ounce – Price Engine FINAL
 * Core Market Engine – Tick Based (0,01)
 * UI Agnostic – API Ready – Audit Safe
 */

if (!defined('ABSPATH')) exit;

final class TwentyFourOunce_Price_Engine {

    /** @var wpdb */
    private $db;

    private string $products_table;
    private string $prices_table;
    private string $history_table;

    private const STEP      = 0.01;
    private const PRECISION = 2;

    private const RANGE_DAY   = 'day';
    private const RANGE_WEEK  = 'week';
    private const RANGE_MONTH = 'month';
    private const RANGE_YEAR  = 'year';

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        $prefix = $wpdb->prefix . '24ounce_';
        $this->products_table = $prefix . 'products';
        $this->prices_table   = $prefix . 'prices';
        $this->history_table  = $prefix . 'price_history';
    }

    /* =====================================================
       PRODUCTS (READ ONLY – CONTROLLED VIA ADMIN LATER)
    ===================================================== */

    public function get_products(bool $only_active = true): array {
        $sql = "SELECT * FROM {$this->products_table}";
        if ($only_active) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY sort_order ASC";

        return $this->db->get_results($sql, ARRAY_A) ?: [];
    }

    public function get_product(string $slug): ?array {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->products_table} WHERE slug = %s LIMIT 1",
                sanitize_text_field($slug)
            ),
            ARRAY_A
        ) ?: null;
    }

    /* =====================================================
       CURRENT PRICES
    ===================================================== */

    public function get_all_prices(): array {
        return $this->db->get_results(
            "SELECT p.slug, p.name, p.unit, p.weight,
                    pr.buy_price, pr.sell_price, pr.updated_at
             FROM {$this->products_table} p
             JOIN {$this->prices_table} pr ON pr.product_id = p.id
             WHERE p.active = 1
             ORDER BY p.sort_order ASC",
            ARRAY_A
        ) ?: [];
    }

    public function get_price(string $slug): ?array {
        $product = $this->get_product($slug);
        if (!$product || !$product['active']) return null;

        $price = $this->db->get_row(
            $this->db->prepare(
                "SELECT buy_price, sell_price, updated_at
                 FROM {$this->prices_table}
                 WHERE product_id = %d LIMIT 1",
                $product['id']
            ),
            ARRAY_A
        );

        if (!$price) return null;

        return [
            'product'    => $product,
            'buy_price'  => $price['buy_price'],
            'sell_price' => $price['sell_price'],
            'updated_at' => $price['updated_at'],
        ];
    }

    /* =====================================================
       TICK ENGINE (↑ ↓ ONLY)
    ===================================================== */

    public function move_price(string $slug, string $type, int $steps = 1): bool {

        if ($steps < 1) return false;

        $product = $this->get_product($slug);
        if (!$product || !$product['active']) return false;

        $price = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->prices_table}
                 WHERE product_id = %d LIMIT 1",
                $product['id']
            ),
            ARRAY_A
        );

        if (!$price) return false;

        $buy  = (float) $price['buy_price'];
        $sell = (float) $price['sell_price'];

        $delta = self::STEP * $steps;

        if ($type === 'up') {
            $buy  += $delta;
            $sell += $delta;
        } elseif ($type === 'down') {
            $buy  -= $delta;
            $sell -= $delta;
        } else {
            return false;
        }

        $buy  = $this->normalize($buy);
        $sell = $this->normalize($sell);

        if ($buy < 0 || $sell < 0 || $sell < $buy) {
            return false;
        }

        $this->db->update(
            $this->prices_table,
            [
                'buy_price'  => $buy,
                'sell_price' => $sell,
                'updated_at' => current_time('mysql', true),
            ],
            ['product_id' => $product['id']],
            ['%f','%f','%s'],
            ['%d']
        );

        $this->insert_history($product['id'], $buy, $sell);

        return true;
    }

    /* =====================================================
       CHARTS (UTC – FIXED PRECISION)
    ===================================================== */
    
    public function get_chart(string $slug, string $range): array {
    
        $product = $this->get_product($slug);
        if (!$product) return [];
    
        $from = match ($range) {
            self::RANGE_DAY   => gmdate('Y-m-d H:i:s', strtotime('-24 hours')),
            self::RANGE_WEEK  => gmdate('Y-m-d H:i:s', strtotime('-7 days')),
            self::RANGE_MONTH => gmdate('Y-m-d H:i:s', strtotime('-30 days')),
            self::RANGE_YEAR  => gmdate('Y-m-d H:i:s', strtotime('-365 days')),
            default           => null,
        };
    
        if (!$from) return [];
    
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT buy_price, sell_price, recorded_at
                 FROM {$this->history_table}
                 WHERE product_id = %d
                   AND recorded_at >= %s
                 ORDER BY recorded_at ASC",
                $product['id'],
                $from
            ),
            ARRAY_A
        ) ?: [];
    }
    
    /* =====================================================
       ADMIN HELPERS (READ ONLY)
    ===================================================== */
    
    public function get_last_price_before(string $slug): ?array {
    
        $product = $this->get_product($slug);
        if (!$product) {
            return null;
        }
    
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT buy_price, sell_price
                 FROM {$this->history_table}
                 WHERE product_id = %d
                 ORDER BY recorded_at DESC
                 LIMIT 1 OFFSET 1",
                $product['id']
            ),
            ARRAY_A
        );
    
        if (!$row) {
            return null;
        }
    
        return [
            'old_buy'  => (float) $row['buy_price'],
            'old_sell' => (float) $row['sell_price'],
        ];
    }
    
    /* =====================================================
       INTERNAL HELPERS
    ===================================================== */
    
    
        private function normalize(float $value): float {
            return round($value, self::PRECISION);
        }
    
        private function insert_history(int $product_id, float $buy, float $sell): void {
            $this->db->insert(
                $this->history_table,
                [
                    'product_id'  => $product_id,
                    'buy_price'   => $buy,
                    'sell_price'  => $sell,
                    'recorded_at' => current_time('mysql', true),
                ],
                ['%d','%f','%f','%s']
            );
        }
    }
