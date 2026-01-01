<?php
/**
 * 24Ounce – Chart Engine
 * Read-only / Trading-ready / API-compatible
 */

if (!defined('ABSPATH')) exit;

final class TwentyFourOunce_Chart_Engine {

    private wpdb $db;
    private string $history_table;

    /**
     * Supported ranges
     */
    private const RANGES = [
        'day'   => '-1 day',
        'week'  => '-7 day',
        'month' => '-1 month',
        'year'  => '-1 year',
    ];

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->history_table = $wpdb->prefix . '24ounce_price_history';
    }

    /**
     * Get chart data
     *
     * @param string $asset
     * @param string $range day|week|month|year
     * @return array
     */
    public function get_chart(string $asset, string $range): array {

        $asset = sanitize_text_field($asset);
        $range = sanitize_text_field($range);

        if (!isset(self::RANGES[$range])) {
            return [];
        }

        $from = gmdate(
            'Y-m-d H:i:s',
            strtotime(self::RANGES[$range])
        );

        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT 
                    buy_price,
                    sell_price,
                    recorded_at
                 FROM {$this->history_table}
                 WHERE asset = %s
                   AND recorded_at >= %s
                 ORDER BY recorded_at ASC",
                $asset,
                $from
            ),
            ARRAY_A
        );

        if (!$rows) {
            return [];
        }

        return array_map(function ($row) {
            return [
                'time' => $row['recorded_at'],
                'buy'  => (float) $row['buy_price'],
                'sell' => (float) $row['sell_price'],
            ];
        }, $rows);
    }

    /**
     * Shortcuts
     */
    public function daily(string $asset): array {
        return $this->get_chart($asset, 'day');
    }

    public function weekly(string $asset): array {
        return $this->get_chart($asset, 'week');
    }

    public function monthly(string $asset): array {
        return $this->get_chart($asset, 'month');
    }

    public function yearly(string $asset): array {
        return $this->get_chart($asset, 'year');
    }
}
