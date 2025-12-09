<?php
if (!defined('ABSPATH')) exit;

require_once SYSTEMCORE_PLUGIN_PATH . 'includes/security.php';

global $wpdb;
$table = $wpdb->prefix . 'systemcore_feed_sources';

/* ============================================================
 * GET SINGLE SOURCE
 * ============================================================ */
add_action('wp_ajax_systemcore_get_feed_source', function () use ($wpdb, $table) {
    SystemCore_Security::verify();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id < 1) {
        wp_send_json_error(['message' => 'Invalid ID']);
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $id
    ));

    if (!$row) {
        wp_send_json_error(['message' => 'Source not found']);
    }

    wp_send_json_success($row);
});


/* ============================================================
 * SAVE / UPDATE SOURCE
 * ============================================================ */
add_action('wp_ajax_systemcore_save_feed_source', function () use ($wpdb, $table) {
    SystemCore_Security::verify();

    $id   = (int) ($_POST['id'] ?? 0);
    $name = sanitize_text_field($_POST['source_name'] ?? '');
    $url  = esc_url_raw($_POST['feed_url'] ?? '');

    $feed_type   = sanitize_text_field($_POST['feed_type'] ?? 'rss');
    $priority    = (int) ($_POST['priority_level'] ?? 1);
    $is_main     = (int) ($_POST['is_main_source'] ?? 0);
    $active      = (int) ($_POST['active'] ?? 1);

    $xml_root    = sanitize_text_field($_POST['xml_root_path'] ?? '');
    $xml_mapping = wp_unslash($_POST['xml_mapping'] ?? '');

    $json_root    = sanitize_text_field($_POST['json_root_path'] ?? '');
    $json_mapping = wp_unslash($_POST['json_mapping'] ?? '');

    if (!$name || !$url) {
        wp_send_json_error(['message' => 'Missing fields']);
    }

    /* Enforce ONLY ONE main source */
    if ($is_main === 1) {
        $wpdb->query("UPDATE {$table} SET is_main_source = 0");
    }

    $data = [
        'source_name'   => $name,
        'feed_url'      => $url,
        'feed_type'     => $feed_type,
        'priority_level'=> $priority,
        'is_main_source'=> $is_main,
        'active'        => $active,
        'xml_root_path' => $xml_root,
        'xml_mapping'   => $xml_mapping,
        'json_root_path'=> $json_root,
        'json_mapping'  => $json_mapping,
        'last_checked'  => current_time('mysql'),
    ];

    if ($id > 0) {
        $wpdb->update($table, $data, ['id' => $id]);
        wp_send_json_success(['message' => 'Source updated']);
    }

    $wpdb->insert($table, $data);

    wp_send_json_success([
        'message' => 'Source added',
        'id'      => $wpdb->insert_id
    ]);
});


/* ============================================================
 * DELETE SOURCE
 * ============================================================ */
add_action('wp_ajax_systemcore_delete_feed_source', function () use ($wpdb, $table) {
    SystemCore_Security::verify();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id < 1) {
        wp_send_json_error(['message' => 'Invalid ID']);
    }

    $wpdb->delete($table, ['id' => $id]);

    wp_send_json_success(['message' => 'Source deleted']);
});
