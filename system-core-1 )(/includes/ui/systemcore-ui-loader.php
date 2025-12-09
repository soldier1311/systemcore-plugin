<?php
if (!defined('ABSPATH')) exit;

class SystemCore_UI_Loader {

    public static function init() {

        add_action('admin_enqueue_scripts', [__CLASS__, 'load_assets']);
        add_action('admin_head', [__CLASS__, 'inject_tokens']);
    }

    public static function load_assets($hook) {

        if (strpos($hook, 'systemcore') === false) return;

        // CSS
        wp_enqueue_style(
            'systemcore-ui',
            SYSTEMCORE_PLUGIN_URL . 'assets/ui/systemcore-ui.css',
            [],
            filemtime(SYSTEMCORE_PLUGIN_PATH . 'assets/ui/systemcore-ui.css')
        );

        // JS
        wp_enqueue_script(
            'systemcore-ui',
            SYSTEMCORE_PLUGIN_URL . 'assets/ui/systemcore-ui.js',
            ['jquery'],
            filemtime(SYSTEMCORE_PLUGIN_PATH . 'assets/ui/systemcore-ui.js'),
            true
        );

        wp_localize_script('systemcore-ui', 'SC_UI_Admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('systemcore_admin_nonce'),
        ]);
    }

    public static function inject_tokens() {

        if (!isset($_GET['page']) || strpos($_GET['page'], 'systemcore') === false) {
            return;
        }
?>
<style>
:root{
    --sc-radius:10px;
    --sc-bg:#f8fafc;
    --sc-card-bg:#fff;
    --sc-border:#e2e8f0;
    --sc-text:#1e293b;
    --sc-text-light:#64748b;
    --sc-primary:#2563eb;
    --sc-primary-dark:#1e40af;
    --sc-success:#16a34a;
    --sc-danger:#dc2626;
    --sc-warning:#d97706;
    --sc-info:#0ea5e9;
    --sc-shadow:0 2px 12px rgba(0,0,0,0.06);
}
</style>
<?php
    }
}

SystemCore_UI_Loader::init();
