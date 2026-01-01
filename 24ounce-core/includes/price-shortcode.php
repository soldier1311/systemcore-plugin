<?php
/**
 * 24Ounce Price Shortcode
 */

if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [24ounce_prices]
 */
add_shortcode('24ounce_prices', function ($atts) {

    if (!class_exists('TwentyFourOunce_Price_Service')) {
        return '';
    }

    $service = new TwentyFourOunce_Price_Service();
    $prices  = $service->get_all();

    if (empty($prices)) {
        return '<p>No prices available.</p>';
    }

    ob_start();
    ?>
    <div class="twentyfourounce-ui">
        <table class="twentyfourounce-prices-table">
            <thead>
                <tr>
                    <th>Asset</th>
                    <th>Buy</th>
                    <th>Sell</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prices as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['asset']); ?></td>
                        <td><?php echo esc_html($row['buy_price']); ?></td>
                        <td><?php echo esc_html($row['sell_price']); ?></td>
                        <td><?php echo esc_html($row['updated_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php

    return ob_get_clean();
});
