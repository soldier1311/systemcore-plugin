<?php
if (!defined('ABSPATH')) exit;

/**
 * Load Queue with feed_name + search
 */
add_action('wp_ajax_systemcore_load_queue_new', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json([]);
    }

    global $wpdb;

    $queue_table = $wpdb->prefix . 'systemcore_queue';
    $feed_table  = $wpdb->prefix . 'systemcore_feed_sources';

    $search = isset($_POST['search']) ? trim(sanitize_text_field($_POST['search'])) : '';

    // Base SQL
    $base_sql = "
        SELECT 
            q.id,
            q.feed_id,
            q.url,
            q.lang,
            q.status,
            q.created_at,
            fs.source_name AS feed_name
        FROM $queue_table q
        LEFT JOIN $feed_table fs ON fs.id = q.feed_id
    ";

    // With search
    if ($search !== '') {

        $like = '%' . $wpdb->esc_like($search) . '%';

        $sql = $wpdb->prepare("
            $base_sql
            WHERE q.url LIKE %s
               OR fs.source_name LIKE %s
            ORDER BY q.id DESC
            LIMIT 200
        ", $like, $like);

    } else {
        // No search
        $sql = "
            $base_sql
            ORDER BY q.id DESC
            LIMIT 200
        ";
    }

    $rows = $wpdb->get_results($sql, ARRAY_A);

    if (!$rows) {
        wp_send_json([]);
    }

    // Convert status to readable labels (optional تحسين فقط)
    foreach ($rows as &$row) {
        switch ($row['status']) {
            case 'pending':    $row['status_label'] = 'Pending'; break;
            case 'processing': $row['status_label'] = 'Processing'; break;
            case 'done':       $row['status_label'] = 'Completed'; break;
            case 'failed':     $row['status_label'] = 'Failed'; break;
            default:           $row['status_label'] = $row['status']; break;
        }
    }

    wp_send_json($rows);
});


/**
 * Delete queue item
 */
add_action('wp_ajax_systemcore_delete_queue_item', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['success' => false]);
    }

    global $wpdb;

    $queue_table = $wpdb->prefix . 'systemcore_queue';

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($id <= 0) {
        wp_send_json_error(['success' => false]);
    }

    $wpdb->delete($queue_table, ['id' => $id], ['%d']);

    wp_send_json_success(['success' => true]);
});
