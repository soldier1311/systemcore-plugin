<?php
/**
 * 24Ounce – Wallet Engine
 * CORE / PRODUCTION READY
 *
 * Responsibilities:
 * - Maintain customer ownership balances
 * - Reflect executed Vault movements
 * - Reserve / release on SELL orders
 * - Immutable wallet ledger
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TwentyFourOunce_Wallet_Engine {

    /** @var wpdb */
    private $db;

    private string $wallet_table;
    private string $wallet_ledger_table;
    private string $orders_table;
    private string $products_table;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        $prefix = $wpdb->prefix . '24ounce_';

        $this->wallet_table        = $prefix . 'wallet_balances';
        $this->wallet_ledger_table = $prefix . 'wallet_ledger';
        $this->orders_table        = $prefix . 'orders';
        $this->products_table      = $prefix . 'products';
    }

    /* =====================================================
       APPLY EXECUTED ORDER (FROM VAULT)
    ===================================================== */

    public function apply_executed_order(int $order_id): bool {

        $this->db->query('START TRANSACTION');

        try {

            $order = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->orders_table}
                     WHERE id = %d
                     FOR UPDATE",
                    $order_id
                ),
                ARRAY_A
            );

            if (!$order || $order['status'] !== 'approved') {
                throw new Exception('Invalid order state');
            }

            $user_id    = (int) $order['user_id'];
            $product_id = (int) $order['product_id'];
            $qty        = (float) $order['quantity'];
            $type       = $order['order_type'];

            $this->ensure_wallet_row($user_id, $product_id);

            if ($type === 'buy') {

                // BUY → credit available
                $this->update_wallet(
                    $user_id,
                    $product_id,
                    +$qty,
                    0
                );

                $this->insert_wallet_ledger(
                    $user_id,
                    $product_id,
                    $order_id,
                    'credit',
                    $qty
                );

            } elseif ($type === 'sell') {

                // SELL → debit reserved
                $this->update_wallet(
                    $user_id,
                    $product_id,
                    0,
                    -$qty
                );

                $this->insert_wallet_ledger(
                    $user_id,
                    $product_id,
                    $order_id,
                    'debit',
                    $qty
                );

            } else {
                throw new Exception('Unknown order type');
            }

            $this->db->query('COMMIT');
            return true;

        } catch (Throwable $e) {

            $this->db->query('ROLLBACK');
            return false;
        }
    }

    /* =====================================================
       RESERVE FOR SELL (ORDER PENDING)
    ===================================================== */

    public function reserve_for_sell(
        int $user_id,
        int $product_id,
        float $qty
    ): bool {

        $this->db->query('START TRANSACTION');

        try {

            $this->ensure_wallet_row($user_id, $product_id);

            $wallet = $this->get_wallet_row($user_id, $product_id, true);

            if ($wallet['available_qty'] < $qty) {
                throw new Exception('Insufficient wallet balance');
            }

            $this->update_wallet(
                $user_id,
                $product_id,
                -$qty,
                +$qty
            );

            $this->insert_wallet_ledger(
                $user_id,
                $product_id,
                null,
                'reserve',
                $qty
            );

            $this->db->query('COMMIT');
            return true;

        } catch (Throwable $e) {

            $this->db->query('ROLLBACK');
            return false;
        }
    }

    /* =====================================================
       RELEASE RESERVED (REJECT / CANCEL)
    ===================================================== */

    public function release_reserved(
        int $user_id,
        int $product_id,
        float $qty
    ): bool {

        $this->db->query('START TRANSACTION');

        try {

            $wallet = $this->get_wallet_row($user_id, $product_id, true);

            if ($wallet['reserved_qty'] < $qty) {
                throw new Exception('Invalid reserved amount');
            }

            $this->update_wallet(
                $user_id,
                $product_id,
                +$qty,
                -$qty
            );

            $this->insert_wallet_ledger(
                $user_id,
                $product_id,
                null,
                'release',
                $qty
            );

            $this->db->query('COMMIT');
            return true;

        } catch (Throwable $e) {

            $this->db->query('ROLLBACK');
            return false;
        }
    }

    /* =====================================================
       READ METHODS
    ===================================================== */

    public function get_wallet(int $user_id): array {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT wb.*, p.name, p.slug
                 FROM {$this->wallet_table} wb
                 JOIN {$this->products_table} p ON p.id = wb.product_id
                 WHERE wb.user_id = %d",
                $user_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /* =====================================================
       INTERNAL HELPERS
    ===================================================== */

    private function ensure_wallet_row(int $user_id, int $product_id): void {

        $exists = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*)
                 FROM {$this->wallet_table}
                 WHERE user_id = %d AND product_id = %d",
                $user_id,
                $product_id
            )
        );

        if (!$exists) {
            $this->db->insert(
                $this->wallet_table,
                [
                    'user_id'        => $user_id,
                    'product_id'     => $product_id,
                    'available_qty'  => 0,
                    'reserved_qty'   => 0,
                    'updated_at'     => current_time('mysql', true),
                ],
                ['%d','%d','%f','%f','%s']
            );
        }
    }

    private function get_wallet_row(
        int $user_id,
        int $product_id,
        bool $lock = false
    ): array {

        $sql = "
            SELECT *
            FROM {$this->wallet_table}
            WHERE user_id = %d AND product_id = %d
        ";

        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        return $this->db->get_row(
            $this->db->prepare($sql, $user_id, $product_id),
            ARRAY_A
        );
    }

    private function update_wallet(
        int $user_id,
        int $product_id,
        float $available_delta,
        float $reserved_delta
    ): void {

        $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->wallet_table}
                 SET available_qty = available_qty + %f,
                     reserved_qty  = reserved_qty  + %f
                 WHERE user_id = %d AND product_id = %d",
                $available_delta,
                $reserved_delta,
                $user_id,
                $product_id
            )
        );
    }

    private function insert_wallet_ledger(
        int $user_id,
        int $product_id,
        ?int $order_id,
        string $type,
        float $qty
    ): void {

        $this->db->insert(
            $this->wallet_ledger_table,
            [
                'user_id'    => $user_id,
                'product_id' => $product_id,
                'order_id'   => $order_id,
                'entry_type' => $type,
                'quantity'   => $qty,
                'created_at'=> current_time('mysql', true),
            ],
            ['%d','%d','%d','%s','%f','%s']
        );
    }
}
