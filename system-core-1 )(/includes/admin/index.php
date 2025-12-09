<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================================
 * ADMIN MENU
 * ==========================================================================
 */
add_action('admin_menu', function () {

    // Main Dashboard page (default: Overview tab)
    add_menu_page(
        'SystemCore',
        'SystemCore',
        'manage_options',
        'systemcore-dashboard',
        function () { systemcore_render_main_page('overview'); },
        'dashicons-hammer',
        3
    );

    // Sidebar submenu pages (all use the same renderer with different tab)
    $pages = [
        'queue'     => 'Queue',
        'feeds'     => 'Feed Sources',
        'publisher' => 'Publisher',
        'ai'        => 'AI Settings',
        'dedupe'    => 'Dedupe & Filters',
        'logs'      => 'Logs',
        'scraper'   => 'Scraper Test',
    ];

    foreach ($pages as $key => $label) {

        $slug = 'systemcore-' . $key;

        add_submenu_page(
            'systemcore-dashboard',
            $label,
            $label,
            'manage_options',
            $slug,
            function () use ($key) {
                systemcore_render_main_page($key);
            }
        );
    }
});

/**
 * ==========================================================================
 * SAFE INCLUDE
 * ==========================================================================
 */
function systemcore_admin_safe_include($file) {

    $root = realpath(SYSTEMCORE_PATH);
    $real = realpath($file);

    if (!$real || strpos($real, $root) !== 0) {
        echo "<div class='notice notice-error'><p>Missing admin page: " . esc_html($file) . "</p></div>";
        return;
    }

    include $real;
}

/**
 * ==========================================================================
 * LOAD PAGE BY TAB KEY
 * ==========================================================================
 */
function systemcore_load_tab_page($tab) {

    $pages_root = SYSTEMCORE_PATH . 'includes/admin/pages/';

    switch ($tab) {

        case 'overview':
            systemcore_admin_safe_include($pages_root . 'overview/system-overview.php');
            break;

        case 'queue':
            systemcore_admin_safe_include($pages_root . 'queue.php');
            break;

        case 'feeds':
            systemcore_admin_safe_include($pages_root . 'feed-sources.php');
            break;

        case 'publisher':
            systemcore_admin_safe_include(SYSTEMCORE_PATH . 'includes/publisher/admin-screen.php');
            break;

        case 'ai':
            systemcore_admin_safe_include($pages_root . 'ai-settings.php');
            break;

        case 'dedupe':
            systemcore_admin_safe_include($pages_root . 'dedupe.php');
            break;

        case 'logs':
            systemcore_admin_safe_include($pages_root . 'logs.php');
            break;

        case 'scraper':
            systemcore_admin_safe_include($pages_root . 'test-scraper.php');
            break;

        default:
            systemcore_admin_safe_include($pages_root . 'overview/system-overview.php');
    }
}

/**
 * ==========================================================================
 * MAIN PAGE WITH TABS
 * ==========================================================================
 */
function systemcore_render_main_page($force_tab = null) {

    $tabs = [
        'overview'  => 'System Overview',
        'queue'     => 'Queue',
        'feeds'     => 'Feed Sources',
        'publisher' => 'Publisher',
        'ai'        => 'AI Settings',
        'dedupe'    => 'Dedupe & Filters',
        'logs'      => 'Logs',
        'scraper'   => 'Scraper Test',
    ];

    // Determine active tab: from sidebar (force_tab) or from ?tab=
    $active = $force_tab ?? (isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview');

    if (!array_key_exists($active, $tabs)) {
        $active = 'overview';
    }
    ?>
    <div class="wrap systemcore-settings">

        <h1>SystemCore Control Panel</h1>

        <!-- Tabs -->
        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="nav-tab <?php echo ($active === $key ? 'nav-tab-active' : ''); ?>"
                   href="<?php echo esc_url( admin_url( 'admin.php?page=systemcore-dashboard&tab=' . $key ) ); ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <!-- Content -->
        <div style="margin-top:20px;">
            <?php systemcore_load_tab_page($active); ?>
        </div>

    </div>
    <?php
}
