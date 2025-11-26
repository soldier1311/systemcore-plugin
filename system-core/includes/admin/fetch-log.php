<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_logs = $wpdb->prefix . 'systemcore_logs';

// إحصائيات سريعة للبطاقات العلوية
$total_logs  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_logs}");
$total_error = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_logs} WHERE level = %s", 'error'));
$total_warn  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_logs} WHERE level = %s", 'warning'));
$total_info  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_logs} WHERE level = %s", 'info'));
$last_log_at = $wpdb->get_var("SELECT created_at FROM {$table_logs} ORDER BY id DESC LIMIT 1");

// قائمة المصادر المميزة من جدول اللوج
$log_sources = $wpdb->get_col("SELECT DISTINCT source FROM {$table_logs} ORDER BY source ASC");
?>
<div class="systemcore-wrap">
    <h1 class="systemcore-title">SystemCore Logs</h1>

    <!-- بطاقات الإحصائيات -->
    <div class="systemcore-grid systemcore-grid-4 sc-mt-15">
        <div class="systemcore-card">
            <div class="sc-card-label">Total Logs</div>
            <div class="sc-card-value"><?php echo (int) $total_logs; ?></div>
            <div class="sc-card-sub">All levels</div>
        </div>

        <div class="systemcore-card sc-card-warning">
            <div class="sc-card-label">Errors</div>
            <div class="sc-card-value"><?php echo (int) $total_error; ?></div>
            <div class="sc-card-sub">level = error</div>
        </div>

        <div class="systemcore-card">
            <div class="sc-card-label">Warnings</div>
            <div class="sc-card-value"><?php echo (int) $total_warn; ?></div>
            <div class="sc-card-sub">level = warning</div>
        </div>

        <div class="systemcore-card sc-card-success">
            <div class="sc-card-label">Infos</div>
            <div class="sc-card-value"><?php echo (int) $total_info; ?></div>
            <div class="sc-card-sub">
                Last log: <?php echo $last_log_at ? esc_html($last_log_at) : '—'; ?>
            </div>
        </div>
    </div>

    <!-- أدوات التحكم والفلاتر -->
    <div class="systemcore-card sc-mt-30">
        <h2 class="systemcore-card-title">Filters</h2>

        <div class="systemcore-toolbar sc-mt-15">
            <label for="sc-logs-level"><strong>Level:</strong></label>
            <select id="sc-logs-level">
                <option value="">All</option>
                <option value="error">Error</option>
                <option value="warning">Warning</option>
                <option value="info">Info</option>
            </select>

            <label for="sc-logs-source"><strong>Source:</strong></label>
            <select id="sc-logs-source">
                <option value="">All</option>
                <?php if (!empty($log_sources)) : ?>
                    <?php foreach ($log_sources as $src) : ?>
                        <?php if (!$src) continue; ?>
                        <option value="<?php echo esc_attr($src); ?>">
                            <?php echo esc_html($src); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <button type="button" class="button" id="sc-logs-refresh">Refresh</button>

            <button type="button" class="button button-secondary" id="sc-logs-clear">
                Clear All Logs
            </button>
        </div>
    </div>

    <!-- جدول اللوج -->
    <div class="systemcore-card sc-mt-15">
        <h2 class="systemcore-card-title">Log Entries</h2>

        <table class="systemcore-table systemcore-table-compact" id="sc-logs-table">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th style="width:90px;">Level</th>
                    <th style="width:130px;">Source</th>
                    <th>Message</th>
                    <th style="width:220px;">Context</th>
                    <th style="width:160px;">Created At</th>
                </tr>
            </thead>
            <tbody>
                <!-- سيتم تحميل المحتوى عبر AJAX من systemcore-admin.js / scLoadLogs -->
                <tr>
                    <td colspan="6">Loading...</td>
                </tr>
            </tbody>
        </table>

        <div id="sc-logs-pagination" class="systemcore-pagination"></div>
    </div>
</div>
