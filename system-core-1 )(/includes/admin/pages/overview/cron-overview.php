<?php
if (!defined('ABSPATH')) exit;

$cron_disabled   = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
$cron_next       = wp_next_scheduled('systemcore_cron_runner');
$cron_next_human = $cron_next ? date_i18n('Y-m-d H:i:s', $cron_next) : '—';

global $wpdb;
$logs_table = $wpdb->prefix . 'systemcore_logs';

$last_cron_run = '—';
if (systemcore_table_exists($logs_table)) {
    $last_cron_run = $wpdb->get_var("
        SELECT created_at
        FROM {$logs_table}
        WHERE message LIKE 'CRON: systemcore_cron_runner completed%'
        ORDER BY id DESC
        LIMIT 1
    ") ?: '—';
}

$cron_status = $cron_disabled ? 'Disabled (DISABLE_WP_CRON)' : ($cron_next ? 'Scheduled' : 'Not Scheduled');
?>
<div class="sc-card">
    <h2 class="sc-card-title">Scheduler & Cron</h2>
    <table class="sc-table sc-table-compact sc-overview-table">
        <tr><th>WP Cron Status</th><td><?php echo $cron_status; ?></td></tr>
        <tr><th>DISABLE_WP_CRON</th><td><?php echo $cron_disabled ? 'true' : 'false'; ?></td></tr>
        <tr><th>Next Run</th><td><?php echo $cron_next_human; ?></td></tr>
        <tr><th>Last Cron Completed</th><td><?php echo $last_cron_run; ?></td></tr>
        <tr><th>Interval</th><td>Every 5 minutes</td></tr>
    </table>
</div>
