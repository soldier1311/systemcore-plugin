<?php
if (!defined('ABSPATH')) exit;

$ai_settings = get_option('systemcore_ai_settings', []);

$ai_enabled   = isset($ai_settings['enabled']) ? $ai_settings['enabled'] === 'yes' : true;
$ai_has_key   = !empty(trim($ai_settings['api_key'] ?? ''));
$ai_connected = $ai_enabled && $ai_has_key;

$ai_model     = $ai_settings['model']             ?? 'gpt-4o-mini';
$ai_lang      = $ai_settings['default_language']  ?? 'auto';
$ai_style     = $ai_settings['writing_style']     ?? 'tech_journalism';
$ai_rewrite   = $ai_settings['rewrite_strength']  ?? 70;
$ai_target_wc = $ai_settings['target_word_count'] ?? 600;
$ai_title_len = $ai_settings['title_length']      ?? 60;
$ai_meta_len  = $ai_settings['meta_length']       ?? 160;
$ai_seo_mode  = $ai_settings['seo_mode']          ?? 'advanced';
?>
<div class="sc-card">
    <h2 class="sc-card-title">AI Engine Overview</h2>
    <table class="sc-table sc-table-compact sc-overview-table">
        <tr><th>AI Engine Status</th><td><?php echo $ai_enabled ? 'Enabled' : 'Disabled'; ?></td></tr>
        <tr><th>API Connection</th><td><?php echo $ai_connected ? 'Connected' : 'Not Connected'; ?></td></tr>
        <tr><th>Default Model</th><td><?php echo esc_html($ai_model); ?></td></tr>
        <tr><th>Default Language</th><td><?php echo esc_html($ai_lang); ?></td></tr>
        <tr><th>Writing Style</th><td><?php echo esc_html($ai_style); ?></td></tr>
        <tr><th>Rewrite Strength</th><td><?php echo $ai_rewrite; ?>%</td></tr>
        <tr><th>Target Word Count</th><td><?php echo $ai_target_wc; ?></td></tr>
        <tr><th>Title Length</th><td><?php echo $ai_title_len; ?></td></tr>
        <tr><th>Meta Length</th><td><?php echo $ai_meta_len; ?></td></tr>
        <tr><th>SEO Mode</th><td><?php echo esc_html($ai_seo_mode); ?></td></tr>
    </table>
    <p style="margin-top:10px;font-size:12px;">
        <a href="<?php echo esc_url($ai_url); ?>">Open AI Settings</a>
    </p>
</div>
