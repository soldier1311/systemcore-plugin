<?php
/**
 * 24Ounce – Database Schema
 * FINAL / WORDPRESS SAFE
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TwentyFourOunce_Database {

    /** @var wpdb */
    private $db;

    private string $products_table;
    private string $prices_table;
    private string $history_table;
    private string $orders_table;
    private string $order_status_log_table;
    private string $vault_balances_table;
    private string $ledger_entries_table;
    private string $wallet_balances_table;
    private string $wallet_ledger_table;
    private string $trading_state_table;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;

        $prefix = $wpdb->prefix . '24ounce_';

        $this->products_table         = $prefix . 'products';
        $this->prices_table           = $prefix . 'prices';
        $this->history_table          = $prefix . 'price_history';
        $this->orders_table           = $prefix . 'orders';
        $this->order_status_log_table = $prefix . 'order_status_log';
        $this->vault_balances_table   = $prefix . 'vault_balances';
        $this->ledger_entries_table   = $prefix . 'ledger_entries';
        $this->wallet_balances_table  = $prefix . 'wallet_balances';
        $this->wallet_ledger_table    = $prefix . 'wallet_ledger';
        $this->trading_state_table    = $prefix . 'trading_state';
    }

    /* =====================================================
     * Install
     * =================================================== */

    public function install(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $this->db->get_charset_collate();

        $sql_products = "
        CREATE TABLE {$this->products_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(64) NOT NULL,
            name VARCHAR(191) NOT NULL,
            unit ENUM('gram','ounce','piece') NOT NULL,
            weight DECIMAL(10,4) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slug (slug),
            KEY idx_active_sort (active, sort_order)
        ) {$charset_collate};
        ";

        $sql_prices = "
        CREATE TABLE {$this->prices_table} (
            product_id BIGINT UNSIGNED NOT NULL,
            buy_price DECIMAL(12,2) NOT NULL,
            sell_price DECIMAL(12,2) NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (product_id),
            KEY idx_product (product_id)
        ) {$charset_collate};
        ";

        $sql_history = "
        CREATE TABLE {$this->history_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT UNSIGNED NOT NULL,
            buy_price DECIMAL(12,2) NOT NULL,
            sell_price DECIMAL(12,2) NOT NULL,
            recorded_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_product_time (product_id, recorded_at)
        ) {$charset_collate};
        ";

        $sql_orders = "
        CREATE TABLE {$this->orders_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            order_type ENUM('buy','sell') NOT NULL,
            quantity DECIMAL(12,4) NOT NULL,
            price_snapshot DECIMAL(12,2) NOT NULL,
            status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_product (product_id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) {$charset_collate};
        ";

        $sql_order_log = "
        CREATE TABLE {$this->order_status_log_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            old_status ENUM('pending','approved','rejected','cancelled') NOT NULL,
            new_status ENUM('pending','approved','rejected','cancelled') NOT NULL,
            changed_by BIGINT UNSIGNED NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_order (order_id),
            KEY idx_changed_at (changed_at)
        ) {$charset_collate};
        ";

        $sql_vault = "
        CREATE TABLE {$this->vault_balances_table} (
            product_id BIGINT UNSIGNED NOT NULL,
            available_qty DECIMAL(14,4) NOT NULL DEFAULT 0,
            reserved_qty DECIMAL(14,4) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (product_id),
            KEY idx_product (product_id)
        ) {$charset_collate};
        ";

        $sql_ledger = "
        CREATE TABLE {$this->ledger_entries_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            entry_type ENUM('reserve','release','execute') NOT NULL,
            quantity DECIMAL(14,4) NOT NULL,
            direction ENUM('in','out') NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_order (order_id),
            KEY idx_product (product_id),
            KEY idx_created (created_at)
        ) {$charset_collate};
        ";

        $sql_wallet = "
        CREATE TABLE {$this->wallet_balances_table} (
            user_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            available_qty DECIMAL(14,4) NOT NULL DEFAULT 0,
            reserved_qty DECIMAL(14,4) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, product_id),
            KEY idx_user (user_id),
            KEY idx_product (product_id)
        ) {$charset_collate};
        ";

        $sql_wallet_ledger = "
        CREATE TABLE {$this->wallet_ledger_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            entry_type ENUM('credit','debit','reserve','release') NOT NULL,
            quantity DECIMAL(14,4) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_product (product_id),
            KEY idx_order (order_id),
            KEY idx_created (created_at)
        ) {$charset_collate};
        ";

        $sql_trading_state = "
        CREATE TABLE {$this->trading_state_table} (
            id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            locked_by BIGINT UNSIGNED NULL,
            locked_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};
        ";

        dbDelta($sql_products);
        dbDelta($sql_prices);
        dbDelta($sql_history);
        dbDelta($sql_orders);
        dbDelta($sql_order_log);
        dbDelta($sql_vault);
        dbDelta($sql_ledger);
        dbDelta($sql_wallet);
        dbDelta($sql_wallet_ledger);
        dbDelta($sql_trading_state);
    }

    /* =====================================================
     * Trading State Helpers (ADDED)
     * =================================================== */

    public function is_trading_locked(): bool {

        $state = $this->db->get_var(
            "SELECT is_locked FROM {$this->trading_state_table} WHERE id = 1"
        );

        return ((int) $state === 1);
    }

    public function get_trading_state(): int {

        return (int) $this->db->get_var(
            "SELECT is_locked FROM {$this->trading_state_table} WHERE id = 1"
        );
    }

    public function set_trading_state(int $lock): bool {

        return (bool) $this->db->update(
            $this->trading_state_table,
            ['is_locked' => $lock],
            ['id' => 1],
            ['%d'],
            ['%d']
        );
    }
}
