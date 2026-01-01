<?php
/**
 * 24Ounce – Order Engine
 * CORE / PRODUCTION READY
 *
 * Responsibilities:
 * - Create BUY / SELL orders
 * - Freeze price snapshot
 * - Manage order states
 * - Delegate execution to Vault / Ledger Engine
 * - Enforce GLOBAL trading lock
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once TWENTYFOUR_OUNCE_PATH . 'includes/vault-ledger-engine.php';

final class TwentyFourOunce_Order_Engine {

    /** @var wpdb */
    private $db;

    private string $products_table;
    private string $prices_table;
    private string $orders_table;
    private string $order_log_table;
    private string $trading_state_table;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        $prefix = $wpdb->prefix . '24ounce_';

        $this->products_table   = $prefix . 'products';
        $this->prices_table     = $prefix . 'prices';
        $this->orders_table     = $prefix . 'orders';
        $this->order_log_table  = $prefix . 'order_status_log';
        $this->trading_state_table = $prefix . 'trading_state';
    }

    /* =====================================================
       GLOBAL TRADING LOCK
    ===================================================== */

    private function is_trading_locked(): bool {

        $state = $this->db->get_row(
            "SELECT is_locked
             FROM {$this->trading_state_table}
             WHERE id = 1
             LIMIT 1",
            ARRAY_A
        );

        return !empty($state) && (int)$state['is_locked'] === 1;
    }

    /* =====================================================
       CREATE ORDER (CUSTOMER)
    ===================================================== */

    public function create_order(
        int $user_id,
        string $product_slug,
        string $order_type,
        float $quantity
    ): int|false {

        if ($this->is_trading_locked()) {
            return false;
        }

        if ($quantity <= 0) {
            return false;
        }

        if (!in_array($order_type, ['buy','sell'], true)) {
            return false;
        }

        $product = $this->db->get_row(
            $this->db->prepare(
                "SELECT id FROM {$this->products_table}
                 WHERE slug = %s AND active = 1
                 LIMIT 1",
                sanitize_text_field($product_slug)
            ),
            ARRAY_A
        );

        if (!$product) {
            return false;
        }

        $price = $this->db->get_row(
            $this->db->prepare(
                "SELECT buy_price, sell_price
                 FROM {$this->prices_table}
                 WHERE product_id = %d
                 LIMIT 1",
                $product['id']
            ),
            ARRAY_A
        );

        if (!$price) {
            return false;
        }

        $price_snapshot = ($order_type === 'buy')
            ? (float) $price['buy_price']
            : (float) $price['sell_price'];

        $inserted = $this->db->insert(
            $this->orders_table,
            [
                'user_id'        => $user_id,
                'product_id'     => $product['id'],
                'order_type'     => $order_type,
                'quantity'       => $quantity,
                'price_snapshot' => $price_snapshot,
                'status'         => 'pending',
                'created_at'     => current_time('mysql', true),
            ],
            ['%d','%d','%s','%f','%f','%s','%s']
        );

        if ($inserted === false) {
            return false;
        }

        $order_id = (int) $this->db->insert_id;

        $this->log_status_change(
            $order_id,
            'pending',
            'pending',
            $user_id
        );

        return $order_id;
    }

    /* =====================================================
       CUSTOMER ACTIONS
    ===================================================== */

    public function cancel_order(int $order_id, int $user_id): bool {

        if ($this->is_trading_locked()) {
            return false;
        }

        $order = $this->get_order($order_id);

        if (
            !$order ||
            (int)$order['user_id'] !== $user_id ||
            $order['status'] !== 'pending'
        ) {
            return false;
        }

        $changed = $this->change_status($order_id, 'cancelled', $user_id);

        if (!$changed) {
            return false;
        }

        $vault = new TwentyFourOunce_Vault_Ledger_Engine();
        return $vault->release_reservation($order_id);
    }

    /* =====================================================
       ADMIN ACTIONS
    ===================================================== */

    public function approve_order(int $order_id, int $admin_id): bool {

        if ($this->is_trading_locked()) {
            return false;
        }

        $changed = $this->change_status($order_id, 'approved', $admin_id);

        if (!$changed) {
            return false;
        }

        $vault = new TwentyFourOunce_Vault_Ledger_Engine();
        return $vault->execute_order($order_id);
    }

    public function reject_order(int $order_id, int $admin_id): bool {

        if ($this->is_trading_locked()) {
            return false;
        }

        $changed = $this->change_status($order_id, 'rejected', $admin_id);

        if (!$changed) {
            return false;
        }

        $vault = new TwentyFourOunce_Vault_Ledger_Engine();
        return $vault->release_reservation($order_id);
    }

    /* =====================================================
       CORE STATE TRANSITION
    ===================================================== */

    private function change_status(
        int $order_id,
        string $new_status,
        int $actor_id
    ): bool {

        if (!in_array($new_status, ['approved','rejected','cancelled'], true)) {
            return false;
        }

        $order = $this->get_order($order_id);

        if (!$order || $order['status'] !== 'pending') {
            return false;
        }

        $updated = $this->db->update(
            $this->orders_table,
            [
                'status'     => $new_status,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $order_id],
            ['%s','%s'],
            ['%d']
        );

        if ($updated === false) {
            return false;
        }

        $this->log_status_change(
            $order_id,
            $order['status'],
            $new_status,
            $actor_id
        );

        return true;
    }

    /* =====================================================
       READ METHODS
    ===================================================== */

    public function get_order(int $order_id): ?array {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT *
                 FROM {$this->orders_table}
                 WHERE id = %d
                 LIMIT 1",
                $order_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function get_user_orders(int $user_id): array {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT *
                 FROM {$this->orders_table}
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        ) ?: [];
    }

    public function get_pending_orders(): array {
        return $this->db->get_results(
            "SELECT *
             FROM {$this->orders_table}
             WHERE status = 'pending'
             ORDER BY created_at ASC",
            ARRAY_A
        ) ?: [];
    }

    /* =====================================================
       STATUS LOG
    ===================================================== */

    private function log_status_change(
        int $order_id,
        string $old_status,
        string $new_status,
        int $actor_id
    ): void {

        $this->db->insert(
            $this->order_log_table,
            [
                'order_id'   => $order_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'changed_by' => $actor_id,
                'changed_at' => current_time('mysql', true),
            ],
            ['%d','%s','%s','%d','%s']
        );
    }
}
