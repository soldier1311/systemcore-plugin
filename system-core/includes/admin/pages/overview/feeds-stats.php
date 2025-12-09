<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$feeds_table = $wpdb->prefix . 'systemcore_feed_sources';

$feed_total = 0;
$feed_rss   = 0;
$feed_xml   = 0;
$feed_json  = 0;

if (systemcore_table_exists($feeds_table)) {
    $has_feed_type = systemcore_column_exists($feeds_table, 'feed_type');

    if ($has_feed_type) {
        $rows = $wpdb->get_results("SELECT feed_type, COUNT(*) AS cnt FROM {$feeds_table} GROUP BY feed_type");
        foreach ($rows as $row) {
            $type = strtolower($row->feed_type);
            $cnt  = (int) $row->cnt;
            $feed_total += $cnt;

            if ($type === 'xml')       $feed_xml += $cnt;
            elseif ($type === 'json')  $feed_json += $cnt;
            else                       $feed_rss += $cnt;
        }
    } else {
        $feed_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$feeds_table}");
        $feed_rss   = $feed_total;
    }
}
?>
<div class="sc-card">
    <div class="sc-kpi-title">Feed Sources</div>
    <div class="sc-kpi-value"><?php echo $feed_total; ?></div>
    <p style="margin:6px 0 0;font-size:12px;">
        RSS: <?php echo $feed_rss; ?> •
        XML: <?php echo $feed_xml; ?> •
        JSON: <?php echo $feed_json; ?>
    </p>
    <p style="margin:6px 0 0;">
        <a href="<?php echo esc_url($feeds_url); ?>">Open Feed Sources</a>
    </p>
</div>
