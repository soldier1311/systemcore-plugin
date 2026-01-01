<?php
/**
 * 24Ounce Prices AJAX Endpoint
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX actions
 */
add_action('wp_ajax_24ounce_get_prices', 'twentyfourounce_ajax_get_prices');
add_action('wp_ajax_nopriv_24ounce_get_prices', 'twentyfourounce_ajax_get_prices');

/**
 * AJAX callback
 */
function twentyfourounce_ajax_get_prices(): void {

    // Security: nonce check (optional for public, but recommended)
    if (
        isset($_REQUEST['nonce']) &&
        !wp_verify_nonce($_REQUEST['nonce'], '24ounce_nonce')
    ) {
        wp_send_json_error([
            'message' => 'Invalid security token'
        ], 403);
    }

    // Service availability
    if (!class_exists('TwentyFourOunce_Price_Service')) {
        wp_send_json_error([
            'message' => 'Price service not available'
        ], 500);
    }

    try {

        $service = new TwentyFourOunce_Price_Service();
        $prices  = $service->get_all();

        if (empty($prices)) {
            wp_send_json_success([
                'data' => [],
                'note' => 'No prices found'
            ]);
        }

        wp_send_json_success([
            'data' => $prices
        ]);

    } catch (Throwable $e) {

        wp_send_json_error([
            'message' => 'Failed to load prices'
        ], 500);
    }
}
