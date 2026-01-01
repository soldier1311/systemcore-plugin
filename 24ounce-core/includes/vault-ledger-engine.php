<?php
/**
 * 24Ounce – Vault / Ledger Engine
 * CORE / PRODUCTION READY
 *
 * Responsibilities:
 * - Reserve quantities after order approval
 * - Execute final movements
 * - Maintain immutable ledger
 * - Atomic & auditable operations
 */

if (!defined('ABSPATH')) {
    exit;
}

/* =====================================================
   Load Wallet Engine (Safe Dependency)
===================================================== */
require_once TWENTYFOUR_OUNCE_PATH . 'includes/wallet-engine.php';

final class TwentyFourOunce_Vault_Ledger_Engine {

    /** @var wpdb */
    private $db;

    private string $products_table;
    private string $orders_table;
    private string $vault_table;
    private string $ledger_table;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        $prefix = $wpdb->prefix . '24ounce_';

        $this->products_table = $prefix . 'products';
        $this->orders_table   = $prefix . 'orders';
        $this->vault_table    = $prefix . 'vault_balances';
        $this->ledger_table   = $prefix . 'ledger_entries';
    }

    /* =====================================================
       EXECUTE APPROVED ORDER (ONE TIME ONLY)
    ===================================================== */

    public function execute_order(int $order_id): bool {

        $this->db->query('START TRANSACTION');

        try {

            // Lock order row
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

            $product_id = (int) $order['product_id'];
            $qty        = (float) $order['quantity'];
            $type       = $order['order_type'];

            // Lock vault row
            $vault = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->vault_table}
                     WHERE product_id = %d
                     FOR UPDATE",
                    $product_id
                ),
                ARRAY_A
            );

            if (!$vault) {
                throw new Exception('Vault record missing');
            }

            if ($type === 'buy') {

                // BUY: reserve from vault (out)
                if ($vault['available_qty'] < $qty) {
                    throw new Exception('Insufficient vault quantity');
                }

                $this->update_vault(
                    $product_id,
                    -$qty,
                    +$qty
                );

                $this->insert_ledger(
                    $order_id,
                    $product_id,
                    'reserve',
                    $qty,
                    'out'
                );

            } elseif ($type === 'sell') {

                // SELL: add to vault (in)
                $this->update_vault(
                    $product_id,
                    +$qty,
                    0
                );

                $this->insert_ledger(
                    $order_id,
                    $product_id,
                    'execute',
                    $qty,
                    'in'
                );

            } else {
                throw new Exception('Unknown order type');
            }

            /* =====================================================
               Commit Vault + Ledger
            ===================================================== */
            $this->db->query('COMMIT');

            /* =====================================================
               Reflect execution in Wallet
            ===================================================== */
            $wallet = new TwentyFourOunce_Wallet_Engine();

            if (!$wallet->apply_executed_order($order_id)) {
                return false;
            }

            return true;

        } catch (Throwable $e) {

            $this->db->query('ROLLBACK');
            return false;
        }
    }

    /* =====================================================
       RELEASE RESERVED QUANTITY (REJECT / CANCEL)
    ===================================================== */

    public function release_reservation(int $order_id): bool {

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

            if (!$order || !in_array($order['status'], ['rejected','cancelled'], true)) {
                throw new Exception('Invalid order state');
            }

            if ($order['order_type'] !== 'buy') {
                throw new Exception('Nothing to release');
            }

            $product_id = (int) $order['product_id'];
            $qty        = (float) $order['quantity'];

            $vault = $this->db->get_row(
                $this->db->prepare(
                    "SELECT * FROM {$this->vault_table}
                     WHERE product_id = %d
                     FOR UPDATE",
                    $product_id
                ),
                ARRAY_A
            );

            if (!$vault || $vault['reserved_qty'] < $qty) {
                throw new Exception('Invalid vault state');
            }

            $this->update_vault(
                $product_id,
                +$qty,
                -$qty
            );

            $this->insert_ledger(
                $order_id,
                $product_id,
                'release',
                $qty,
                'in'
            );

            $this->db->query('COMMIT');
            return true;

        } catch (Throwable $e) {

            $this->db->query('ROLLBACK');
            return false;
        }
    }

    /* =====================================================
       INTERNAL HELPERS
    ===================================================== */

    private function update_vault(
        int $product_id,
        float $available_delta,
        float $reserved_delta
    ): void {

        $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->vault_table}
                 SET available_qty = available_qty + %f,
                     reserved_qty  = reserved_qty  + %f
                 WHERE product_id = %d",
                $available_delta,
                $reserved_delta,
                $product_id
            )
        );
    }

    private function insert_ledger(
        int $order_id,
        int $product_id,
        string $type,
        float $qty,
        string $direction
    ): void {

        $this->db->insert(
            $this->ledger_table,
            [
                'order_id'   => $order_id,
                'product_id' => $product_id,
                'entry_type' => $type,
                'quantity'   => $qty,
                'direction'  => $direction,
                'created_at' => current_time('mysql', true),
            ],
            ['%d','%d','%s','%f','%s','%s']
        );
    }
}
