<?php
if (!defined('ABSPATH')) exit;

/**
 * SystemCore — Dedupe & Filters Admin Screen (Fixed)
 */

if (!current_user_can('manage_options')) {
    wp_die('Not allowed');
}

global $wpdb;

$table = $wpdb->prefix . 'systemcore_dedupe';

// Base URL (keeps page slug)
$current_page_slug = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'systemcore-dashboard';

function systemcore_dedupe_build_url($current_page_slug, $overrides = []) {
    $keep = [
        'page'        => $current_page_slug,
        'tab'         => 'dedupe',
        's'           => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        'source_type' => isset($_GET['source_type']) ? sanitize_key($_GET['source_type']) : '',
        'age'         => isset($_GET['age']) ? sanitize_text_field(wp_unslash($_GET['age'])) : '',
        'only_dupes'  => (isset($_GET['only_dupes']) && $_GET['only_dupes'] === '1') ? '1' : '',
        'orderby'     => isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'created_at',
        'order'       => (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'asc' : 'desc',
        'paged'       => isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1,
    ];

    foreach ($keep as $k => $v) {
        if ($v === '' || $v === null) unset($keep[$k]);
    }

    $args = array_merge($keep, $overrides);

    return add_query_arg($args, admin_url('admin.php'));
}

// Table exists?
$table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table);

// Handle bulk actions
if ($table_exists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['systemcore_dedupe_action'])) {

    check_admin_referer('systemcore_dedupe_bulk', 'systemcore_dedupe_nonce');

    $action = sanitize_text_field(wp_unslash($_POST['systemcore_dedupe_action']));
    $ids    = isset($_POST['ids']) ? (array) $_POST['ids'] : [];

    switch ($action) {

        case 'delete_selected':
            $ids = array_values(array_filter(array_map('absint', $ids)));
            if (!empty($ids)) {
                // Safe IN() handling
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $sql = "DELETE FROM {$table} WHERE id IN ($placeholders)";
                $prepared = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $ids));
                $wpdb->query($prepared);
            }
            break;

        case 'delete_older_7':
            $wpdb->query("DELETE FROM {$table} WHERE created_at < (NOW() - INTERVAL 7 DAY)");
            break;

        case 'clear_all':
            $wpdb->query("TRUNCATE TABLE {$table}");
            break;
    }

    wp_safe_redirect(systemcore_dedupe_build_url($current_page_slug, ['paged' => 1]));
    exit;
}

// Filters and pagination
$per_page = 50;
$paged    = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$offset   = ($paged - 1) * $per_page;

$search      = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$source_type = isset($_GET['source_type']) ? sanitize_key($_GET['source_type']) : '';
$age_filter  = isset($_GET['age']) ? sanitize_text_field(wp_unslash($_GET['age'])) : '';
$only_dupes  = isset($_GET['only_dupes']) && $_GET['only_dupes'] === '1';

$order_by = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'created_at';
$order    = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';

$allowed_orderby = ['id', 'created_at', 'url', 'source_type', 'source_id', 'title'];
if (!in_array($order_by, $allowed_orderby, true)) {
    $order_by = 'created_at';
}

// Defaults
$total_items = $total_24h = $total_7d = 0;
$total_feed = $total_scraper = $total_publisher = 0;
$url_dup_groups = $title_dup_groups = $content_dup_groups = $image_dup_groups = 0;
$total_filtered = 0;
$total_pages = 1;
$rows = [];

if ($table_exists) {

    // Build WHERE
    $where_sql = 'WHERE 1=1';

    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where_sql .= $wpdb->prepare(" AND (url LIKE %s OR title LIKE %s)", $like, $like);
    }

    if ($source_type !== '') {
        $where_sql .= $wpdb->prepare(" AND source_type = %s", $source_type);
    }

    if ($age_filter === '24h') {
        $where_sql .= " AND created_at >= (NOW() - INTERVAL 1 DAY)";
    } elseif ($age_filter === '3d') {
        $where_sql .= " AND created_at >= (NOW() - INTERVAL 3 DAY)";
    } elseif ($age_filter === '7d') {
        $where_sql .= " AND created_at >= (NOW() - INTERVAL 7 DAY)";
    }

    // NOTE: this is "has hashes", not "is duplicate". Keeping your behavior but clearer semantics.
    if ($only_dupes) {
        $where_sql .= " AND (title_hash <> '' OR content_hash <> '' OR image_hash <> '')";
    }

    // Global stats
    $total_items     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $total_24h       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE created_at >= (NOW() - INTERVAL 1 DAY)");
    $total_7d        = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE created_at >= (NOW() - INTERVAL 7 DAY)");
    $total_feed      = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE source_type = %s", 'feed'));
    $total_scraper   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE source_type = %s", 'scraper'));
    $total_publisher = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE source_type = %s", 'publisher'));

    $url_dup_groups = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM (
            SELECT url
            FROM {$table}
            WHERE url <> ''
            GROUP BY url
            HAVING COUNT(*) > 1
        ) AS t
    ");

    $title_dup_groups = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM (
            SELECT title_hash
            FROM {$table}
            WHERE title_hash <> ''
            GROUP BY title_hash
            HAVING COUNT(*) > 1
        ) AS t
    ");

    $content_dup_groups = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM (
            SELECT content_hash
            FROM {$table}
            WHERE content_hash <> ''
            GROUP BY content_hash
            HAVING COUNT(*) > 1
        ) AS t
    ");

    $image_dup_groups = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM (
            SELECT image_hash
            FROM {$table}
            WHERE image_hash <> ''
            GROUP BY image_hash
            HAVING COUNT(*) > 1
        ) AS t
    ");

    // Filtered rows
    $total_filtered = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where_sql}");
    $total_pages    = max(1, (int) ceil($total_filtered / $per_page));

    $query_sql = $wpdb->prepare(
        "SELECT * FROM {$table} {$where_sql} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
        $per_page,
        $offset
    );

    $rows = $wpdb->get_results($query_sql);
}

$base_url = systemcore_dedupe_build_url($current_page_slug, ['paged' => 1]);
?>
<div class="wrap systemcore-wrap">
    <h1>SystemCore — Dedupe & Filters</h1>

    <?php if (!$table_exists): ?>
        <div class="notice notice-error">
            <p>Missing table: <code><?php echo esc_html($table); ?></code></p>
        </div>
    <?php endif; ?>

    <style>
        .systemcore-dedupe-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:20px;margin-bottom:24px}
        .systemcore-dedupe-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 18px;box-shadow:0 1px 1px rgba(0,0,0,.03)}
        .systemcore-dedupe-card h3{margin:0 0 8px;font-size:14px;font-weight:600}
        .systemcore-dedupe-card .value{font-size:24px;font-weight:600;margin-bottom:4px}
        .systemcore-dedupe-card .sub{font-size:12px;color:#555d66}
        .systemcore-dedupe-filters{margin:0 0 16px;padding:12px 16px;background:#fff;border:1px solid #dcdcde;border-radius:8px}
        .systemcore-dedupe-filters .row{display:flex;flex-wrap:wrap;gap:8px 12px;align-items:center}
        .systemcore-dedupe-filters input[type="text"]{min-width:220px}
        .systemcore-dedupe-url{max-width:320px;word-break:break-all;font-size:12px}
        .systemcore-dedupe-title{font-weight:500}
        .systemcore-dedupe-bulk{margin-top:12px;display:flex;justify-content:space-between;align-items:center;gap:12px}
        .systemcore-dedupe-pagination{margin-top:12px}
        .systemcore-dedupe-pagination .page-numbers{display:inline-block;margin-right:4px;padding:4px 8px;border:1px solid #dcdcde;border-radius:4px;text-decoration:none}
        .systemcore-dedupe-pagination .current{background:#2271b1;color:#fff;border-color:#2271b1}
        @media (max-width:782px){.systemcore-dedupe-bulk{flex-direction:column;align-items:flex-start}}
    </style>

    <div class="systemcore-dedupe-cards">
        <div class="systemcore-dedupe-card">
            <h3>Total Records</h3>
            <div class="value"><?php echo esc_html(number_format_i18n($total_items)); ?></div>
            <div class="sub">
                Last 24h: <?php echo esc_html(number_format_i18n($total_24h)); ?> &nbsp;•&nbsp;
                Last 7d: <?php echo esc_html(number_format_i18n($total_7d)); ?>
            </div>
        </div>

        <div class="systemcore-dedupe-card">
            <h3>Sources Overview</h3>
            <div class="value"><?php echo esc_html(number_format_i18n($total_feed + $total_scraper + $total_publisher)); ?></div>
            <div class="sub">
                Feed: <?php echo esc_html(number_format_i18n($total_feed)); ?> &nbsp;•&nbsp;
                Scraper: <?php echo esc_html(number_format_i18n($total_scraper)); ?> &nbsp;•&nbsp;
                Publisher: <?php echo esc_html(number_format_i18n($total_publisher)); ?>
            </div>
        </div>

        <div class="systemcore-dedupe-card">
            <h3>URL / Title Duplicates</h3>
            <div class="value"><?php echo esc_html(number_format_i18n($url_dup_groups + $title_dup_groups)); ?></div>
            <div class="sub">
                URL groups: <?php echo esc_html(number_format_i18n($url_dup_groups)); ?> &nbsp;•&nbsp;
                Title groups: <?php echo esc_html(number_format_i18n($title_dup_groups)); ?>
            </div>
        </div>

        <div class="systemcore-dedupe-card">
            <h3>Content / Image Duplicates</h3>
            <div class="value"><?php echo esc_html(number_format_i18n($content_dup_groups + $image_dup_groups)); ?></div>
            <div class="sub">
                Content groups: <?php echo esc_html(number_format_i18n($content_dup_groups)); ?> &nbsp;•&nbsp;
                Image groups: <?php echo esc_html(number_format_i18n($image_dup_groups)); ?>
            </div>
        </div>
    </div>

    <form method="get" class="systemcore-dedupe-filters">
        <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>" />
        <input type="hidden" name="tab" value="dedupe" />

        <div class="row">
            <div>
                <label for="systemcore_dedupe_s">Search</label>
                <input type="text" id="systemcore_dedupe_s" name="s" value="<?php echo esc_attr($search); ?>" class="regular-text" placeholder="URL or title" />
            </div>

            <div>
                <label for="systemcore_dedupe_source_type">Source</label>
                <select id="systemcore_dedupe_source_type" name="source_type">
                    <option value="">All</option>
                    <option value="feed" <?php selected($source_type, 'feed'); ?>>Feed</option>
                    <option value="scraper" <?php selected($source_type, 'scraper'); ?>>Scraper</option>
                    <option value="publisher" <?php selected($source_type, 'publisher'); ?>>Publisher</option>
                </select>
            </div>

            <div>
                <label for="systemcore_dedupe_age">Age</label>
                <select id="systemcore_dedupe_age" name="age">
                    <option value="">Any time</option>
                    <option value="24h" <?php selected($age_filter, '24h'); ?>>Last 24 hours</option>
                    <option value="3d"  <?php selected($age_filter, '3d'); ?>>Last 3 days</option>
                    <option value="7d"  <?php selected($age_filter, '7d'); ?>>Last 7 days</option>
                </select>
            </div>

            <div>
                <label>
                    <input type="checkbox" name="only_dupes" value="1" <?php checked($only_dupes, true); ?> />
                    Only rows with hashes
                </label>
            </div>

            <div>
                <button class="button button-primary" type="submit">Filter</button>
                <a href="<?php echo esc_url($base_url); ?>" class="button">Reset</a>
            </div>
        </div>
    </form>

    <form method="post" class="systemcore-dedupe-table-wrap">
        <?php wp_nonce_field('systemcore_dedupe_bulk', 'systemcore_dedupe_nonce'); ?>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="systemcore-dedupe-select-all" />
                    </td>
                    <th scope="col">ID</th>
                    <th scope="col">URL</th>
                    <th scope="col">Title</th>
                    <th scope="col">Source</th>
                    <th scope="col">Hashes</th>
                    <th scope="col">Created</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$table_exists): ?>
                <tr><td colspan="7">Table missing.</td></tr>
            <?php elseif (empty($rows)) : ?>
                <tr><td colspan="7">No dedupe records found for current filters.</td></tr>
            <?php else : ?>
                <?php foreach ($rows as $row) : ?>
                    <?php
                        $created_ts = strtotime($row->created_at);
                        $created_h  = $created_ts ? human_time_diff($created_ts, current_time('timestamp')) . ' ago' : '';
                    ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="ids[]" value="<?php echo esc_attr((int) $row->id); ?>" />
                        </th>
                        <td><?php echo esc_html((int) $row->id); ?></td>
                        <td class="systemcore-dedupe-url"><?php echo esc_html((string)$row->url); ?></td>
                        <td class="systemcore-dedupe-title"><?php echo esc_html((string)$row->title); ?></td>
                        <td>
                            <?php echo esc_html($row->source_type ?: '-'); ?>
                            <?php if (!empty($row->source_id)) : ?>
                                <br /><span style="font-size:11px;color:#555;">ID: <?php echo esc_html((int) $row->source_id); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-size:11px;">
                                title: <?php echo ($row->title_hash   !== '') ? 'yes' : 'no'; ?><br/>
                                content: <?php echo ($row->content_hash !== '') ? 'yes' : 'no'; ?><br/>
                                image: <?php echo ($row->image_hash   !== '') ? 'yes' : 'no'; ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html((string)$row->created_at); ?><br/>
                            <span style="font-size:11px;color:#555;"><?php echo esc_html($created_h); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="systemcore-dedupe-bulk">
            <div>
                <select name="systemcore_dedupe_action">
                    <option value="">Bulk actions</option>
                    <option value="delete_selected">Delete selected</option>
                    <option value="delete_older_7">Delete entries older than 7 days</option>
                    <option value="clear_all">Clear all records</option>
                </select>
                <button type="submit" class="button action">Apply</button>
            </div>

            <div class="systemcore-dedupe-pagination">
                <?php
                if ($total_pages > 1) {
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $link = systemcore_dedupe_build_url($current_page_slug, ['paged' => $i]);
                        $classes = 'page-numbers' . (($i === $paged) ? ' current' : '');
                        printf('<a href="%s" class="%s">%d</a> ', esc_url($link), esc_attr($classes), (int)$i);
                    }
                }
                ?>
            </div>
        </div>
    </form>

    <script>
        (function() {
            const selectAll = document.getElementById('systemcore-dedupe-select-all');
            if (!selectAll) return;

            selectAll.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.systemcore-dedupe-table-wrap tbody input[type="checkbox"][name="ids[]"]');
                checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            });
        })();
    </script>
</div>
