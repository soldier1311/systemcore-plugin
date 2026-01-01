<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_24ounce_toggle_trading', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json_error();
    }

    check_ajax_referer('24ounce_trading_nonce');

    $db = new TwentyFourOunce_Database();

    $current = $db->get_trading_state();
    $new     = ($current === 1) ? 0 : 1;

    $db->set_trading_state($new);

    wp_send_json_success([
        'is_locked' => $new
    ]);
});
