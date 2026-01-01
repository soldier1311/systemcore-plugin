<?php
/**
 * 24Ounce – Code Guard (Smart Scan + Warning Mode)
 *
 * Detects REAL architectural violations.
 * Shows collapsible admin warnings only.
 */

if (!defined('ABSPATH')) exit;

final class TwentyFourOunce_Code_Guard {

    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'run_checks']);
    }

    public static function run_checks(): void {

        if (!current_user_can('manage_options')) {
            return;
        }

        $base_path = plugin_dir_path(dirname(__FILE__));
        $violations = [];

        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base_path)
        );

        foreach ($rii as $file) {

            if ($file->isDir()) continue;
            if ($file->getExtension() !== 'php') continue;

            $path = $file->getPathname();

            // ===== Global Exclusions =====
            if (self::is_globally_excluded($path)) {
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) continue;

            // ===== Smart SQL Scan =====
            if (self::is_admin_or_ajax($path) && self::contains_real_sql($content)) {
                $violations[] = 'SQL usage outside database.php → ' . $path;
            }

            // ===== Prefix Scan =====
            if (self::is_admin_or_ajax($path) && str_contains($content, '$wpdb->prefix')) {
                $violations[] = 'Direct $wpdb->prefix usage → ' . $path;
            }
        }

        if (!empty($violations)) {
            self::show_warnings($violations);
        }
    }

    /* =====================================================
     * Smart Rules
     * =================================================== */

    private static function is_globally_excluded(string $path): bool {

        $excluded = [
            'includes/database.php',
            'includes/registry.php',
            'includes/dev-guard.php',
            'includes/price-engine.php',
            'includes/price-service.php',
            'includes/chart-engine.php',
            'includes/order-engine.php',
            'includes/wallet-engine.php',
            'includes/vault-ledger-engine.php',
            '24ounce-core.php',
        ];

        foreach ($excluded as $item) {
            if (str_contains($path, $item)) {
                return true;
            }
        }

        return false;
    }

    private static function is_admin_or_ajax(string $path): bool {
        return str_contains($path, '/admin/')
            || str_contains($path, 'ajax');
    }

    private static function contains_real_sql(string $content): bool {

        // Detect actual wpdb execution calls only
        return preg_match(
            '/\$wpdb->(query|get_results|get_row|get_var|prepare)\s*\(/i',
            $content
        ) === 1;
    }

    /* =====================================================
     * UI – Collapsible Warning Box
     * =================================================== */

    private static function show_warnings(array $violations): void {

        add_action('admin_notices', function () use ($violations) {

            $count = count($violations);

            echo '<div class="notice notice-warning">';
            echo '<p><strong>24Ounce Code Guard</strong> — ' . esc_html($count) . ' architecture warnings detected.</p>';

            echo '<details style="margin-top:8px;">';
            echo '<summary style="cursor:pointer;font-weight:bold;">عرض التفاصيل</summary>';
            echo '<ul style="margin-top:8px;">';

            foreach ($violations as $v) {
                echo '<li>' . esc_html($v) . '</li>';
            }

            echo '</ul>';
            echo '</details>';
            echo '</div>';
        });
    }

    private function __construct() {}
    private function __clone() {}
}
