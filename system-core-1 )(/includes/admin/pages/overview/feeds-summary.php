<?php
if (!defined('ABSPATH')) exit;
?>
<div class="sc-card" style="margin-top:25px;">
    <h2 class="sc-card-title">Feed Sources Summary</h2>
    <p style="margin-top:0;">
        This section shows a high-level summary of your configured feed sources.
        For full editing, open the <a href="<?php echo esc_url($feeds_url); ?>">Feed Sources page</a>.
    </p>

    <table class="sc-table sc-table-compact">
        <tr><th>Total Sources</th><td><?php echo $feed_total; ?></td></tr>
        <tr><th>RSS Sources</th><td><?php echo $feed_rss; ?></td></tr>
        <tr><th>XML Sources</th><td><?php echo $feed_xml; ?></td></tr>
        <tr><th>JSON Sources</th><td><?php echo $feed_json; ?></td></tr>
    </table>
</div>
