<?php
/**
 * Admin Save Handler – 24Ounce Prices
 * FINAL / LOCK AWARE / SAFE / ENGINE-AGNOSTIC
 */

if (!defined('ABSPATH')) exit;

add_action('admin_post_24ounce_save_prices', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_admin_referer('24ounce_save_all_action');

    /* =========================
       Trading lock check
    ========================= */

    $db = new TwentyFourOunce_Database();

    if ($db->is_trading_locked()) {
        wp_safe_redirect(
            add_query_arg('locked', '1', admin_url('admin.php?page=24ounce-prices'))
        );
        exit;
    }

    /* =========================
       Services
    ========================= */

    require_once TWENTYFOUR_OUNCE_PATH . 'includes/price-admin-service.php';

    if (!class_exists('TwentyFourOunce_Price_Admin_Service')) {
        wp_die('Price admin service missing');
    }

    $service = new TwentyFourOunce_Price_Admin_Service();
    $engine  = new TwentyFourOunce_Price_Engine();

    /* =========================
       Assets (MUST match Admin UI)
    ========================= */

    $assets = [
        'gold_21k',
        'ounce_999_9',
        'ounce_999_5',
        'bar_100g',
        'coin',
        'half_coin',
        'quarter_coin',
        'silver_250g',
        'silver_500g',
        'silver_1kg',
    ];

    foreach ($assets as $asset) {

        if (
            !isset($_POST['buy'][$asset]) ||
            !isset($_POST['sell'][$asset])
        ) {
            continue;
        }

        $buy  = (float) str_replace(',', '.', trim((string) $_POST['buy'][$asset]));
        $sell = (float) str_replace(',', '.', trim((string) $_POST['sell'][$asset]));

        if ($buy < 0 || $sell < 0 || $sell < $buy) {
            continue;
        }

        /* السعر الحالي */
        $current = $engine->get_price($asset);
        if (!$current) {
            continue;
        }

        $cur_buy  = (float) $current['buy_price'];
        $cur_sell = (float) $current['sell_price'];

        /* حساب الفرق */
        $delta_buy  = $buy  - $cur_buy;
        $delta_sell = $sell - $cur_sell;

        /* الاتجاه يجب أن يكون واحد */
        if (
            ($delta_buy > 0 && $delta_sell < 0) ||
            ($delta_buy < 0 && $delta_sell > 0)
        ) {
            continue;
        }

        /* المتوسط الآمن */
        $delta = ($delta_buy + $delta_sell) / 2;

        /* تحديد الاتجاه */
        $type = $delta > 0 ? 'up' : 'down';

        /* حساب عدد الخطوات (0.01) */
        $steps = (int) round(abs($delta) / 0.01);

        /* تنفيذ الحركة */
        if ($steps > 0) {
            $engine->move_price($asset, $type, $steps);
        }
    }

    wp_safe_redirect(
        add_query_arg('updated', '1', admin_url('admin.php?page=24ounce-prices'))
    );
    exit;
});
