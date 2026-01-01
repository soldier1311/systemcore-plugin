<?php
/**
 * 24Ounce – Chart AJAX Endpoint
 * Read-only / Secure / Trading-ready
 */

if (!defined('ABSPATH')) exit;

/**
 * AJAX: Get chart data
 *
 * Params:
 * - asset  (string)  required
 * - range  (string)  optional: day | week | month | year
 */
add_action('wp_ajax_24ounce_get_chart', 'twentyfourounce_ajax_get_chart');
add_action('wp_ajax_nopriv_24ounce_get_chart', 'twentyfourounce_ajax_get_chart');

function twentyfourounce_ajax_get_chart() {

    // Basic sanity
    if (!class_exists('TwentyFourOunce_Chart_Engine')) {
        wp_send_json_error([
            'message' => 'Chart engine not available'
        ], 500);
    }

    // Inputs
    $asset = isset($_REQUEST['asset'])
        ? sanitize_text_field($_REQUEST['asset'])
        : '';

    $range = isset($_REQUEST['range'])
        ? sanitize_text_field($_REQUEST['range'])
        : TwentyFourOunce_Chart_Engine::RANGE_DAY;

    if ($asset === '') {
        wp_send_json_error([
            'message' => 'Missing asset parameter'
        ], 400);
    }

    // Engine
    $engine = new TwentyFourOunce_Chart_Engine();

    $data = $engine->get_chart($asset, $range);

    // Empty but valid
    if (empty($data)) {
        wp_send_json_success([
            'asset' => $asset,
            'range' => $range,
            'data'  => [],
        ]);
    }

    // Success
    wp_send_json_success([
        'asset' => $asset,
        'range' => $range,
        'data'  => $data,
    ]);
}
