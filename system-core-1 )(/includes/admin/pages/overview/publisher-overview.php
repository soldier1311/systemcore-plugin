<?php
if (!defined('ABSPATH')) exit;

$pub_options = function_exists('systemcore_get_publisher_settings')
    ? systemcore_get_publisher_settings()
    : get_option('systemcore_publisher_settings', []);

$pub_status      = $pub_options['status']          ?? 'publish';
$pub_per_batch   = $pub_options['posts_per_batch'] ?? 5;
$pub_default_lang= $pub_options['default_lang']    ?? 'ar';
$pub_authors     = $pub_options['publishers']      ?? [];
$pub_ai_rewrite  = !empty($pub_options['enable_ai_rewrite']);
$pub_ai_category = !empty($pub_options['enable_ai_category']);
$pub_fixed_cat   = (int) ($pub_options['category_id'] ?? ($pub_options['fixed_category_id'] ?? 0));

$pub_langs       = $pub_options['languages'] ?? [];
$pub_langs_total = count($pub_langs);
$pub_langs_active = 0;
foreach ($pub_langs as $cfg) {
    if (!empty($cfg['active'])) $pub_langs_active++;
}
?>
<div class="sc-card">
    <h2 class="sc-card-title">Publisher Overview</h2>
    <table class="sc-table sc-table-compact sc-overview-table">
        <tr><th>Post Status</th><td><?php echo $pub_status; ?></td></tr>
        <tr><th>Posts per Batch</th><td><?php echo $pub_per_batch; ?></td></tr>
        <tr><th>Default Language</th><td><?php echo $pub_default_lang; ?></td></tr>
        <tr><th>Authors</th><td><?php echo !empty($pub_authors) ? implode(', ', $pub_authors) : '—'; ?></td></tr>
        <tr><th>AI Rewrite</th><td><?php echo $pub_ai_rewrite ? 'Enabled' : 'Disabled'; ?></td></tr>
        <tr><th>AI Category</th><td><?php echo $pub_ai_category ? 'Enabled' : 'Disabled'; ?></td></tr>
        <tr><th>Fixed Category</th><td><?php echo $pub_fixed_cat; ?></td></tr>
        <tr><th>Languages</th><td>Active: <?php echo $pub_langs_active; ?> / Total: <?php echo $pub_langs_total; ?></td></tr>
    </table>
    <p style="margin-top:10px;font-size:12px;">
        <a href="<?php echo esc_url($pub_url); ?>">Open Publisher Settings</a>
    </p>
</div>
