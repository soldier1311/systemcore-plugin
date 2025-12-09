<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$logs_table = $wpdb->prefix . 'systemcore_logs';

$logs_total      = 0;
$logs_info       = 0;
$logs_warning    = 0;
$logs_error      = 0;
$logs_debug      = 0;
$last_log_time   = '—';
$last_error_time = '—';

if (systemcore_table_exists($logs_table)) {
    $logs_total   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table}");
    $logs_info    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table} WHERE level='info'");
    $logs_warning = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table} WHERE level='warning'");
    $logs_error   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table} WHERE level='error'");
    $logs_debug   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table} WHERE level='debug'");

    $last_log_time = $wpdb->get_var("SELECT created_at FROM {$logs_table} ORDER BY id DESC LIMIT 1") ?: '—';
    $last_error_time = $wpdb->get_var("
        SELECT created_at FROM {$logs_table}
        WHERE level='error'
        ORDER BY id DESC LIMIT 1
    ") ?: '—';
}
?>
<div class="sc-card">
    <h2 class="sc-card-title">Logs Overview</h2>
    <table class="sc-table sc-table-compact sc-overview-table">
        <tr><th>Total Logs</th><td><?php echo $logs_total; ?></td></tr>
        <tr><th>Info</th><td><?php echo $logs_info; ?></td></tr>
        <tr><th>Warnings</th><td><?php echo $logs_warning; ?></td></tr>
        <tr><th>Errors</th><td><?php echo $logs_error; ?></td></tr>
        <tr><th>Debug</th><td><?php echo $logs_debug; ?></td></tr>
        <tr><th>Last Log Entry</th><td><?php echo $last_log_time; ?></td></tr>
        <tr><th>Last Error</th><td><?php echo $last_error_time; ?></td></tr>
    </table>
    <p style="margin-top:10px;font-size:12px;">
        <a href="<?php echo esc_url($logs_url); ?>">Open Full Logs Page</a>
    </p>
</div>
