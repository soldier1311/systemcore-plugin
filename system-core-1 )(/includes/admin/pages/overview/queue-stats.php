<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$queue_table = $wpdb->prefix . 'systemcore_queue';

$queue_total   = 0;
$queue_pending = 0;
$queue_done    = 0;

if (systemcore_table_exists($queue_table)) {
    $queue_total   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table}");
    $queue_pending = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$queue_table} WHERE status=%s", 'pending'));
    $queue_done    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$queue_table} WHERE status=%s", 'done'));
}
?>
<div class="sc-card">
    <div class="sc-kpi-title">Queue (Pending / Total)</div>
    <div class="sc-kpi-value">
        <span class="sc-orange"><?php echo $queue_pending; ?></span>
        <span style="font-size:16px;color:#888;"> / <?php echo $queue_total; ?></span>
    </div>
    <p style="margin:6px 0 0;font-size:12px;">
        Processed (done): <span class="sc-green"><?php echo $queue_done; ?></span>
    </p>
    <p style="margin:6px 0 0;">
        <a href="<?php echo esc_url($queue_url); ?>">Open Queue</a>
    </p>
</div>
