<?php

if (!defined('ABSPATH')) exit;

/**
 * Publisher Settings Screen
 * - General publisher options
 * - Multilingual language config
 * - AI toggle
 * - Health widgets: AI Test, Batch Test, WPML status, Recent logs
 */

/* LOAD CURRENT SETTINGS */
$options  = systemcore_get_publisher_settings();
$defaults = systemcore_get_publisher_defaults();

/* ============================================================
   SAVE SETTINGS
============================================================ */

if (isset($_POST['systemcore_publisher_save']) && check_admin_referer('systemcore_publisher_settings')) {

    /* Post Status */
    $status = sanitize_text_field($_POST['status'] ?? 'publish');

    if (!in_array($status, ['publish', 'draft', 'pending'], true)) {
        $status = 'publish';
    }

    $options['status'] = $status;

    /* Batches + Thumb */
    $options['posts_per_batch'] = max(1, (int) ($_POST['posts_per_batch'] ?? 5));
    $options['default_thumb_id'] = (int) ($_POST['default_thumb_id'] ?? 0);

    /* Publisher IDs */
    $raw = sanitize_text_field($_POST['publishers'] ?? '');
    $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', $raw)));
    $options['publishers'] = $ids;

    /* Default Language */
    $options['default_lang'] = sanitize_text_field($_POST['default_lang'] ?? 'ar');

    /* Global Fixed Category */
    $options['category_id'] = isset($_POST['category_id'])
        ? (int) $_POST['category_id']
        : (int) ($options['category_id'] ?? 0);

    /* AI Options */
    $options['enable_ai_rewrite']  = !empty($_POST['enable_ai_rewrite'])  ? 1 : 0;
    $options['enable_ai_category'] = !empty($_POST['enable_ai_category']) ? 1 : 0;

    /* Per Language (ar/fr/de) */
    foreach (['ar', 'fr', 'de'] as $lang) {

        $key = 'lang_' . $lang;

        $options['languages'][$lang]['active'] =
            !empty($_POST[$key . '_active']) ? 1 : 0;

        $options['languages'][$lang]['category_id'] =
            isset($_POST[$key . '_category_id'])
                ? (int) $_POST[$key . '_category_id']
                : (int) ($options['languages'][$lang]['category_id'] ?? 0);

        $options['languages'][$lang]['categories_api'] =
            esc_url_raw($_POST[$key . '_categories_api'] ?? $defaults['languages'][$lang]['categories_api']);

        $options['languages'][$lang]['prompts']['rewrite'] =
            wp_kses_post($_POST[$key . '_prompt_rewrite'] ?? '');

        $options['languages'][$lang]['prompts']['category'] =
            wp_kses_post($_POST[$key . '_prompt_category'] ?? '');
    }

    update_option('systemcore_publisher_settings', $options);

    if (class_exists('SystemCore_Logger')) {
        SystemCore_Logger::info(
            'Publisher settings saved',
            'publisher',
            wp_json_encode($options)
        );
    }

    echo '<div class="notice notice-success"><p>Publisher settings saved.</p></div>';
}

/* ============================================================
   KPI DATA (call helper functions we moved)
============================================================ */

$queue_count  = systemcore_publisher_get_queue_count();
$active_langs = systemcore_publisher_get_active_langs_text($options);
$last_run_info = systemcore_publisher_get_last_run_info();

/* WPML STATUS */
$wpml_active = defined('ICL_SITEPRESS_VERSION');
$wpml_langs  = [];

if ($wpml_active) {
    $langs = apply_filters('wpml_active_languages', null, [
        'skip_missing' => 0,
        'orderby'      => 'id',
    ]);

    if (is_array($langs)) {
        foreach ($langs as $code => $info) {
            $wpml_langs[] =
                strtoupper($code) . ' — ' .
                ($info['translated_name'] ?? $info['native_name'] ?? '');
        }
    }
}
?>

<div class="sc-page">

    <h1 class="sc-title">Publisher Settings</h1>

    <!-- TOP KPI GRID -->
    <div class="sc-grid sc-grid-3 sc-mt-20">

        <!-- Queue Size -->
        <div class="sc-card sc-kpi">
            <div class="sc-kpi-title">Queue (pending items)</div>
            <div class="sc-kpi-value"><?php echo (int) $queue_count; ?></div>
            <div class="sc-kpi-sub">Items waiting in systemcore_queue</div>
        </div>

        <!-- Active Languages -->
        <div class="sc-card sc-kpi">
            <div class="sc-kpi-title">Active Languages</div>
            <div class="sc-kpi-value"
                 style="font-size:14px; line-height:1.4;">
                <?php echo esc_html($active_langs); ?>
            </div>
            <div class="sc-kpi-sub">Controlled below in “Language Settings”</div>
        </div>

        <!-- Last Run -->
        <div class="sc-card sc-kpi">
            <div class="sc-kpi-title">Last Publisher Run</div>
            <div class="sc-kpi-value">
                <?php echo $last_run_info
                    ? esc_html(ucfirst($last_run_info['level']))
                    : '—'; ?>
            </div>
            <div class="sc-kpi-sub"
                 style="font-size:12px; max-height:40px; overflow:hidden;">
                <?php echo $last_run_info
                    ? esc_html($last_run_info['time'] . ' — ' . $last_run_info['message'])
                    : 'No publisher logs yet'; ?>
            </div>
        </div>

    </div>

    <!-- TOOLS -->
    <div class="sc-grid sc-grid-3 sc-mt-30">

        <!-- AI Test -->
        <div class="sc-card">
            <h2 class="sc-card-title">AI Health Check</h2>
            <p>Run a quick test against OpenAI using current AI Engine settings.</p>
            <button type="button"
                    class="button button-primary sc-mt-15"
                    id="sc-publisher-test-ai">
                Test AI Connection
            </button>
            <div id="sc-publisher-test-ai-result"
                 class="sc-mt-15"
                 style="font-weight:bold; font-size:13px;"></div>
        </div>

        <!-- Batch Test -->
        <div class="sc-card">
            <h2 class="sc-card-title">Test Publisher Batch</h2>
            <p>Run a single batch using current queue + settings.</p>
            <button type="button"
                    class="button"
                    id="sc-publisher-test-batch">
                Run Test Batch
            </button>
            <div id="sc-publisher-test-batch-result"
                 class="sc-mt-15" style="font-size:13px;"></div>
        </div>

        <!-- WPML Status -->
        <div class="sc-card">
            <h2 class="sc-card-title">WPML Status</h2>

            <table class="sc-table sc-table-compact">
                <tr>
                    <th>WPML Plugin</th>
                    <td><?php echo $wpml_active ? 'Active' : 'Not Active'; ?></td>
                </tr>
                <tr>
                    <th>WPML Languages</th>
                    <td>
                        <?php
                        if ($wpml_active && !empty($wpml_langs)) {
                            echo esc_html(implode(' | ', $wpml_langs));
                        } elseif ($wpml_active) {
                            echo 'Active, but no languages list returned.';
                        } else {
                            echo 'Publisher will still work, but translations linking disabled.';
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <p class="sc-note sc-mt-10">
                Best SEO: Arabic primary, French/German as WPML translations.
            </p>
        </div>

    </div>

    <!-- MAIN SETTINGS FORM -->
    <form method="post" class="sc-mt-30">

        <?php wp_nonce_field('systemcore_publisher_settings'); ?>

        <!-- GENERAL -->
        <div class="sc-card sc-mt-20">
            <h2 class="sc-card-title">General Settings</h2>

            <table class="sc-table">

                <tr>
                    <th>Post Status</th>
                    <td>
                        <select name="status">
                            <option value="publish" <?php selected($options['status'], 'publish'); ?>>Publish</option>
                            <option value="draft"   <?php selected($options['status'], 'draft');   ?>>Draft</option>
                            <option value="pending" <?php selected($options['status'], 'pending'); ?>>Pending</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Posts per Batch</th>
                    <td>
                        <input type="number"
                               min="1"
                               name="posts_per_batch"
                               value="<?php echo esc_attr($options['posts_per_batch']); ?>">
                    </td>
                </tr>

                <tr>
                    <th>Publisher IDs</th>
                    <td>
                        <input type="text"
                               class="sc-input-wide"
                               name="publishers"
                               value="<?php echo esc_attr(implode(', ', $options['publishers'])); ?>">
                        <div class="sc-note">Example: 2,4,5</div>
                    </td>
                </tr>

                <tr>
                    <th>Default Language</th>
                    <td>
                        <select name="default_lang">
                            <option value="ar" <?php selected($options['default_lang'], 'ar'); ?>>Arabic</option>
                            <option value="fr" <?php selected($options['default_lang'], 'fr'); ?>>French</option>
                            <option value="de" <?php selected($options['default_lang'], 'de'); ?>>German</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Global Fixed Category</th>
                    <td>
                        <input type="number"
                               name="category_id"
                               value="<?php echo isset($options['category_id'])
                                    ? esc_attr((int) $options['category_id'])
                                    : 0; ?>">
                        <div class="sc-note">If set > 0: all posts forced into this category.</div>
                    </td>
                </tr>

                <tr>
                    <th>Default Thumbnail ID</th>
                    <td>
                        <input type="number"
                               name="default_thumb_id"
                               value="<?php echo esc_attr($options['default_thumb_id']); ?>">
                    </td>
                </tr>

            </table>
        </div>

        <!-- AI SETTINGS -->
        <div class="sc-card sc-mt-30">

            <h2 class="sc-card-title">AI Settings</h2>

            <table class="sc-table">

                <tr>
                    <th>AI Rewrite</th>
                    <td>
                        <label class="sc-switch">
                            <input type="checkbox"
                                   name="enable_ai_rewrite"
                                   value="1"
                                   <?php checked($options['enable_ai_rewrite'], 1); ?>>
                            <span></span>
                        </label>
                        <div class="sc-note">If off: Publisher won't call AI Engine.</div>
                    </td>
                </tr>

                <tr>
                    <th>AI Category Detection</th>
                    <td>
                        <label class="sc-switch">
                            <input type="checkbox"
                                   name="enable_ai_category"
                                   value="1"
                                   <?php checked($options['enable_ai_category'], 1); ?>>
                            <span></span>
                        </label>
                        <div class="sc-note">If off: only fixed categories used.</div>
                    </td>
                </tr>

            </table>

        </div>

        <!-- LANGUAGE SETTINGS -->
        <div class="sc-card sc-mt-30">

            <h2 class="sc-card-title">Language Settings</h2>

            <div class="sc-grid sc-grid-3 sc-mt-20">

                <?php
                $langs = ['ar' => 'Arabic', 'fr' => 'French', 'de' => 'German'];

                foreach ($langs as $code => $label):
                    $cfg = $options['languages'][$code];
                    $key = 'lang_' . $code;
                ?>

                <div class="sc-subcard">

                    <h3 class="sc-subcard-title">
                        <?php echo esc_html("{$label} ({$code})"); ?>
                    </h3>

                    <table class="sc-table sc-table-compact">

                        <tr>
                            <th>Enable</th>
                            <td>
                                <label class="sc-switch">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($key); ?>_active"
                                           value="1"
                                           <?php checked($cfg['active'],1); ?>>
                                    <span></span>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th>Fixed Category ID</th>
                            <td>
                                <input type="number"
                                       name="<?php echo esc_attr($key); ?>_category_id"
                                       value="<?php echo isset($cfg['category_id'])
                                            ? esc_attr((int) $cfg['category_id'])
                                            : 0; ?>">
                            </td>
                        </tr>

                        <tr>
                            <th>Categories API</th>
                            <td>
                                <input type="text"
                                       class="sc-input-wide"
                                       name="<?php echo esc_attr($key); ?>_categories_api"
                                       value="<?php echo esc_attr($cfg['categories_api']); ?>">
                            </td>
                        </tr>

                        <tr>
                            <th>Rewrite Prompt</th>
                            <td>
                                <textarea name="<?php echo esc_attr($key); ?>_prompt_rewrite"
                                          class="sc-textarea">
<?php echo esc_textarea($cfg['prompts']['rewrite']); ?>
</textarea>
                            </td>
                        </tr>

                        <tr>
                            <th>Category Prompt</th>
                            <td>
                                <textarea name="<?php echo esc_attr($key); ?>_prompt_category"
                                          class="sc-textarea">
<?php echo esc_textarea($cfg['prompts']['category']); ?>
</textarea>
                            </td>
                        </tr>

                    </table>

                </div>

                <?php endforeach; ?>

            </div>

            <div class="sc-note sc-mt-15">
                SEO: Arabic primary → French/German as translations.
            </div>

        </div>

        <button class="button button-primary sc-mt-30"
                name="systemcore_publisher_save">
            Save Settings
        </button>

    </form>

    <!-- RECENT LOGS -->
    <div class="sc-card sc-mt-40">

        <h2 class="sc-card-title">Recent Publisher Logs</h2>

        <table class="sc-table sc-table-compact">

            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th style="width:80px;">Level</th>
                    <th style="width:180px;">Time</th>
                    <th>Message</th>
                </tr>
            </thead>

            <tbody>
            <?php
            global $wpdb;

            $log_table = $wpdb->prefix . 'systemcore_logs';

            $logs = $wpdb->get_results(
                $wpdb->prepare("
                    SELECT *
                    FROM {$log_table}
                    WHERE source IN (%s,%s)
                    ORDER BY id DESC
                    LIMIT %d
                ",
                'publisher',
                'ai',
                15)
            );

            if ($logs):

                foreach ($logs as $log):

                    $class = '';

                    if ($log->level === 'error' || $log->level === 'critical') {
                        $class = 'sc-log-error';
                    } elseif ($log->level === 'warning') {
                        $class = 'sc-log-warning';
                    } elseif ($log->level === 'info') {
                        $class = 'sc-log-info';
                    } else {
                        $class = 'sc-log-debug';
                    }
            ?>

                <tr class="<?php echo esc_attr($class); ?>">
                    <td><?php echo (int) $log->id; ?></td>
                    <td><?php echo esc_html($log->level); ?></td>
                    <td><?php echo esc_html($log->created_at); ?></td>
                    <td style="max-width:600px; word-break:break-all;">
                        <?php echo esc_html($log->message); ?>
                    </td>
                </tr>

            <?php
                endforeach;

            else:
            ?>

                <tr>
                    <td colspan="4">No logs found for publisher / ai.</td>
                </tr>

            <?php endif; ?>
            </tbody>

        </table>

    </div>

</div>


<?php
/* ============================================================
   AJAX: TEST AI + TEST BATCH
============================================================ */

/**
 * AI Connectivity Test
 */
function systemcore_publisher_test_openai_now() {

    $settings = get_option('systemcore_ai_settings', []);
    $key      = isset($settings['api_key']) ? trim((string) $settings['api_key']) : '';
    $model    = isset($settings['model']) ? (string) $settings['model'] : 'gpt-4o-mini';
    $endpoint = !empty($settings['api_endpoint'])
        ? trim((string) $settings['api_endpoint'])
        : 'https://api.openai.com/v1/responses';

    if ($key === '') {
        return ['status' => 'error', 'message' => 'Missing API key in AI Engine settings.'];
    }

    $body = [
        'model'             => $model,
        'input'             => 'SystemCore publisher AI health check',
        'max_output_tokens' => 20,
        'response_format'   => ['type' => 'text'],
    ];

    $res = wp_remote_post($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode($body),
        'timeout' => 15,
    ]);

    if (is_wp_error($res)) {
        return ['status' => 'error', 'message' => $res->get_error_message()];
    }

    $code = wp_remote_retrieve_response_code($res);
    $raw  = wp_remote_retrieve_body($res);

    if ($code >= 200 && $code < 300) {

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::info('Publisher AI test OK', 'ai', $raw);
        }

        return ['status' => 'success', 'message' => 'AI connection OK (Responses API).'];
    }

    if (class_exists('SystemCore_Logger')) {
        SystemCore_Logger::error("Publisher AI test HTTP {$code} body: {$raw}", 'ai');
    }

    return ['status' => 'error', 'message' => "HTTP {$code}: {$raw}"];
}

/* AJAX: Test AI */
add_action('wp_ajax_systemcore_publisher_test_ai', function () {

    check_ajax_referer('systemcore_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Not allowed']);
    }

    $res = systemcore_publisher_test_openai_now();

    if ($res['status'] === 'success') {
        wp_send_json_success(['message' => $res['message']]);
    }

    wp_send_json_error(['message' => $res['message']]);
});

/* AJAX: Test Batch */
add_action('wp_ajax_systemcore_publisher_test_batch', function () {

    check_ajax_referer('systemcore_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Not allowed']);
    }

    if (!class_exists('SystemCore_Publisher')) {
        wp_send_json_error(['message' => 'SystemCore_Publisher class missing.']);
    }

    try {
        $result = SystemCore_Publisher::run_batch();
    } catch (Throwable $e) {

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::error(
                'Test batch exception: ' . $e->getMessage(),
                'publisher'
            );
        }

        wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
    }

    wp_send_json_success(['result' => $result]);
});
?>


<script>
jQuery(function($) {

    var scPublisherNonce   = '<?php echo esc_js(wp_create_nonce('systemcore_admin_nonce')); ?>';
    var scPublisherAjaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

    function scPublisherAjax(action, $btn, $out) {

        if (!$out.length) return;

        $out.text('Running…');

        $.post(scPublisherAjaxUrl, {
            action: action,
            nonce:  scPublisherNonce
        })

        .done(function(resp) {

            if (resp && resp.success) {

                if (resp.data && resp.data.message) {
                    $out.text(resp.data.message);
                }

                else if (resp.data && resp.data.result) {

                    try {
                        $out.text(JSON.stringify(resp.data.result, null, 2));
                    } catch (e) {
                        $out.text('Batch executed (see logs).');
                    }

                } else {
                    $out.text('Done (check logs).');
                }

            } else {
                var msg = (resp && resp.data && resp.data.message)
                        ? resp.data.message
                        : 'Unknown error';
                $out.text('Error: ' + msg);
            }

        })

        .fail(function(err) {
            $out.text('HTTP error: ' + err.status + ' ' + err.statusText);
        });
    }

    $('#sc-publisher-test-ai').on('click', function() {
        scPublisherAjax('systemcore_publisher_test_ai',
                        $(this),
                        $('#sc-publisher-test-ai-result'));
    });

    $('#sc-publisher-test-batch').on('click', function() {
        scPublisherAjax('systemcore_publisher_test_batch',
                        $(this),
                        $('#sc-publisher-test-batch-result'));
    });

});
</script>
