<?php

if (!defined('ABSPATH')) exit;

/**
 * ============================================================
 * Publisher Helpers
 * ============================================================
 */

/**
 * Get pending queue count
 */
function systemcore_publisher_get_queue_count() {
    global $wpdb;

    $table = $wpdb->prefix . 'systemcore_queue';

    if (empty($table)) return 0;

    $count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"
    );

    return max(0, $count);
}

/**
 * Get active languages list text
 */
function systemcore_publisher_get_active_langs_text($options) {

    if (empty($options['languages']) || !is_array($options['languages'])) {
        return '—';
    }

    $labels = [
        'ar' => 'Arabic',
        'fr' => 'French',
        'de' => 'German',
    ];

    $out = [];

    foreach ($options['languages'] as $code => $cfg) {

        if (!empty($cfg['active'])) {
            $label = isset($labels[$code]) ? $labels[$code] : strtoupper($code);
            $out[] = $label . " ({$code})";
        }
    }

    if (empty($out)) {
        return 'None (uses default only)';
    }

    return implode(', ', $out);
}

/**
 * Get information about the last publisher run
 */
function systemcore_publisher_get_last_run_info() {
    global $wpdb;

    $table = $wpdb->prefix . 'systemcore_logs';

    if (empty($table)) return null;

    $row = $wpdb->get_row("
        SELECT *
        FROM {$table}
        WHERE source = 'publisher'
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$row) return null;

    return [
        'level'   => $row->level,
        'message' => $row->message,
        'time'    => $row->created_at,
    ];
}
