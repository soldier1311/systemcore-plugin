<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$today = current_time('Y-m-d');
$table_posts = $wpdb->posts;
$table_meta  = $wpdb->postmeta;

$today_published = (int) $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(DISTINCT p.ID)
    FROM {$table_posts} AS p
    INNER JOIN {$table_meta} AS m
         ON m.post_id = p.ID AND m.meta_key='_systemcore_source_url'
    WHERE p.post_type='post'
      AND p.post_status='publish'
      AND p.post_date >= %s
", $today . ' 00:00:00'));

$all_published = (int) $wpdb->get_var("
    SELECT COUNT(DISTINCT p.ID)
    FROM {$table_posts} AS p
    INNER JOIN {$table_meta} AS m
         ON m.post_id = p.ID AND m.meta_key='_systemcore_source_url'
    WHERE p.post_type='post'
      AND p.post_status='publish'
");
?>
<div class="sc-card">
    <div class="sc-kpi-title">Posts Published Today</div>
    <div class="sc-kpi-value sc-green"><?php echo $today_published; ?></div>
    <p style="margin:6px 0 0;font-size:12px;">
        Total published via SystemCore: <?php echo $all_published; ?>
    </p>
</div>
