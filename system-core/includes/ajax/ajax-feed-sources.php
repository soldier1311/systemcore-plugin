<?php

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_systemcore_save_feed_source', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'systemcore_feed_sources';

    $id   = isset($_POST['id'])   ? intval($_POST['id']) : 0;
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $url  = isset($_POST['url'])  ? esc_url_raw($_POST['url']) : '';

    if (empty($name) || empty($url)) {
        wp_send_json_error("Missing fields");
    }

    if ($id > 0) {
        // Update existing row
        $wpdb->update(
            $table,
            [ 'source_name' => $name, 'feed_url' => $url ],
            [ 'id' => $id ]
        );
    } else {
        // Insert new row
        $wpdb->insert(
            $table,
            [ 'source_name' => $name, 'feed_url' => $url ]
        );
    }

    wp_send_json_success("Saved");
});

add_action('wp_ajax_systemcore_delete_feed_source', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'systemcore_feed_sources';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $wpdb->delete($table, [ 'id' => $id ]);
    }

    wp_send_json_success("Deleted");
});
