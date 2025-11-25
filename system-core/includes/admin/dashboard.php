<?php if (!defined('ABSPATH')) exit;

global $wpdb;

$table_queue = $wpdb->prefix . 'systemcore_queue';
$table_logs  = $wpdb->prefix . 'systemcore_logs';

/* ============================================================
   FIXED ATTRIBUTES (processed not status)
============================================================ */
$total_queue = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_queue");
$pending     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_queue WHERE processed = 0");
$processed   = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_queue WHERE processed = 1");
$total_logs  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_logs");

/* ============================================================
   OPTIONS
============================================================ */
$last_fetch   = get_option('systemcore_last_fetch', '—');
$last_publish = get_option('systemcore_last_publish', '—');

/* ============================================================
   AI STATUS
============================================================ */
$ai_settings = get_option('systemcore_ai_settings', []);
$ai_key      = $ai_settings['api_key'] ?? '';
$ai_status   = (!empty($ai_key)) ? 'Connected' : 'Not Connected';

/* ============================================================
   LATEST LOGS (last 3)
============================================================ */
$latest_logs = $wpdb->get_results("
    SELECT message, level, created_at
    FROM $table_logs
    ORDER BY id DESC
    LIMIT 3
");

/* ============================================================
   CRON STATUS
============================================================ */
$cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
$cron_status   = $cron_disabled ? 'Disabled' : 'Enabled';

?>

<div class="systemcore-wrap">
    <h1 class="systemcore-title">SystemCore Dashboard</h1>

    <!-- STAT CARDS -->
    <div class="systemcore-grid systemcore-grid-4">

        <div class="systemcore-card" id="sc-card-total-queue">
            <div class="sc-card-label">Total in Queue</div>
            <div class="sc-card-value"><?php echo $total_queue; ?></div>
        </div>

        <div class="systemcore-card sc-card-warning">
            <div class="sc-card-label">Pending</div>
            <div class="sc-card-value"><?php echo $pending; ?></div>
        </div>

        <div class="systemcore-card sc-card-success">
            <div class="sc-card-label">Processed</div>
            <div class="sc-card-value"><?php echo $processed; ?></div>
        </div>

        <div class="systemcore-card">
            <div class="sc-card-label">Log Entries</div>
            <div class="sc-card-value"><?php echo $total_logs; ?></div>
        </div>

    </div>

    <!-- SECOND ROW -->
    <div class="systemcore-grid systemcore-grid-3 sc-mt-30">

        <!-- Activity -->
        <div class="systemcore-card">
            <h2 class="systemcore-card-title">Activity</h2>
            <table class="systemcore-table systemcore-table-compact">
                <tbody>
                    <tr>
                        <th>Last Fetch</th>
                        <td><?php echo esc_html($last_fetch); ?></td>
                    </tr>
                    <tr>
                        <th>Last Publish</th>
                        <td><?php echo esc_html($last_publish); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- AI Status -->
        <div class="systemcore-card">
            <h2 class="systemcore-card-title">AI Status</h2>
            <table class="systemcore-table systemcore-table-compact">
                <tbody>
                    <tr>
                        <th>API Status</th>
                        <td><?php echo $ai_status === 'Connected' ? 'Connected ✓' : 'Not Connected ✗'; ?></td>
                    </tr>
                    <tr>
                        <th>Model</th>
                        <td><?php echo esc_html($ai_settings['model'] ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <th>API Key</th>
                        <td><?php echo !empty($ai_key) ? 'Active' : 'Empty'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="systemcore-card">
            <h2 class="systemcore-card-title">Quick Actions</h2>

            <div class="systemcore-actions">
                <button class="button button-primary sc-btn-fetch-now">Fetch Now</button>
                <button class="button sc-btn-publish-now">Publish Batch</button>
                <a href="<?php echo admin_url('admin.php?page=systemcore-queue'); ?>" class="button">Open Queue</a>
                <a href="<?php echo admin_url('admin.php?page=systemcore-logs'); ?>" class="button">View Logs</a>
            </div>

            <div class="sc-notice-area"></div>
        </div>

    </div>

    <!-- THIRD ROW: LOGS + CRON -->
    <div class="systemcore-grid systemcore-grid-2 sc-mt-30">

        <!-- Logs -->
        <div class="systemcore-card">
            <h2 class="systemcore-card-title">Latest Logs</h2>
            <table class="systemcore-table systemcore-table-compact">
                <tbody>
                    <?php if ($latest_logs): ?>
                        <?php foreach ($latest_logs as $log): ?>
                            <tr>
                                <th><?php echo esc_html($log->level); ?></th>
                                <td><?php echo esc_html($log->message); ?><br>
                                    <small><?php echo esc_html($log->created_at); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2">No logs yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Cron Status -->
        <div class="systemcore-card">
            <h2 class="systemcore-card-title">Cron Status</h2>
            <table class="systemcore-table systemcore-table-compact">
                <tbody>
                    <tr>
                        <th>WP Cron</th>
                        <td><?php echo $cron_status; ?></td>
                    </tr>
                    <tr>
                        <th>Cron Disabled?</th>
                        <td><?php echo $cron_disabled ? 'Yes' : 'No'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
jQuery(document).ready(function($) {

    function sc_notice(msg, type = 'info') {
        $(".sc-notice-area").html(
            `<div class="notice notice-${type}" style="padding:10px;margin-top:10px;">
                <strong>${msg}</strong>
            </div>`
        );
    }

    // FETCH NOW
    $(".sc-btn-fetch-now").on("click", function() {
        sc_notice("Running fetch...", "info");

        $.post(ajaxurl, { action: "systemcore_fetch_now" }, function(res) {
            sc_notice(res, "success");
            setTimeout(() => location.reload(), 1000);
        });
    });

    // PUBLISH NOW
    $(".sc-btn-publish-now").on("click", function() {
        sc_notice("Publishing batch...", "info");

        $.post(ajaxurl, { action: "systemcore_publish_batch" }, function(res) {
            sc_notice(res, "success");
            setTimeout(() => location.reload(), 1000);
        });
    });

});
</script>
