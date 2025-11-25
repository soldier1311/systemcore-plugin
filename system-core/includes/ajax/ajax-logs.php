<?php
if (!defined('ABSPATH')) exit;

/**
 * Logs list with filters + pagination
 */
add_action('wp_ajax_systemcore_logs_list', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Not allowed']);
    }

    check_ajax_referer('systemcore_admin_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'systemcore_logs';

    $page     = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
    $per_page = 20;
    $offset   = ($page - 1) * $per_page;

    $level  = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '';
    $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';

    $where  = '1=1';
    $params = [];

    if ($level !== '') {
        $where   .= ' AND level = %s';
        $params[] = $level;
    }

    if ($source !== '') {
        $where   .= ' AND source = %s';
        $params[] = $source;
    }

    // Count
    if ($params) {
        $sql_count = $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where", ...$params);
    } else {
        $sql_count = "SELECT COUNT(*) FROM $table WHERE $where";
    }
    $total = (int) $wpdb->get_var($sql_count);

    // Items
    if ($params) {
        $sql_items = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page, $offset])
        );
    } else {
        $sql_items = $wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
    }

    $items       = $wpdb->get_results($sql_items, ARRAY_A);
    $total_pages = max(1, (int) ceil($total / $per_page));

    wp_send_json_success([
        'items'       => $items,
        'page'        => $page,
        'total_pages' => $total_pages,
    ]);
});

/**
 * Clear all logs
 */
add_action('wp_ajax_systemcore_logs_clear', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Not allowed']);
    }

    check_ajax_referer('systemcore_admin_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'systemcore_logs';

    $wpdb->query("TRUNCATE TABLE $table");

    wp_send_json_success(['message' => 'Logs cleared.']);
});
