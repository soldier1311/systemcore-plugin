<?php
if (!defined('ABSPATH')) exit;

/* ============================================================
   LOAD SETTINGS
============================================================ */

$defaults = [
    'enabled'            => 'yes',
    'api_key'            => '',
    'api_endpoint'       => 'https://api.openai.com/v1/responses',
    'model'              => 'gpt-4o-mini',
    'rewrite_strength'   => 70,
    'default_language'   => 'auto',
    'writing_style'      => 'tech_journalism',
    'add_intro'          => 'yes',
    'add_conclusion'     => 'yes',
    'use_h2_h3'          => 'yes',
    'target_word_count'  => 600,
    'meta_length'        => 160,
    'title_length'       => 60,
    'seo_mode'           => 'advanced',
    'image_generation'   => 'no',
    'image_style'        => 'tech-flat',
    'debug_mode'         => 'no',
    'model_rewrite'      => 'default',
    'model_title'        => 'default',
    'model_seo'          => 'default',
    'model_keywords'     => 'default',
    'model_language'     => 'default',
];

$settings = get_option('systemcore_ai_settings', []);
$settings = array_merge($defaults, (array) $settings);

/* ============================================================
   SAVE SETTINGS
============================================================ */
if (isset($_POST['systemcore_ai_save'])) {
    check_admin_referer('systemcore_ai_settings');

    $yes_no = function($v) {
        $v = sanitize_text_field(wp_unslash($v));
        return ($v === 'yes') ? 'yes' : 'no';
    };
    $as_int = function($v, $min = 0, $max = 999999) {
        $n = (int) $v;
        if ($n < $min) $n = $min;
        if ($n > $max) $n = $max;
        return $n;
    };

    // Text fields
    $settings['api_key']        = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : $settings['api_key'];
    $settings['api_endpoint']   = isset($_POST['api_endpoint']) ? esc_url_raw(wp_unslash($_POST['api_endpoint'])) : $settings['api_endpoint'];
    $settings['model']          = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : $settings['model'];
    $settings['default_language']= isset($_POST['default_language']) ? sanitize_text_field(wp_unslash($_POST['default_language'])) : $settings['default_language'];
    $settings['writing_style']  = isset($_POST['writing_style']) ? sanitize_text_field(wp_unslash($_POST['writing_style'])) : $settings['writing_style'];
    $settings['seo_mode']       = isset($_POST['seo_mode']) ? sanitize_text_field(wp_unslash($_POST['seo_mode'])) : $settings['seo_mode'];
    $settings['image_style']    = isset($_POST['image_style']) ? sanitize_text_field(wp_unslash($_POST['image_style'])) : $settings['image_style'];

    // Yes/No fields
    $settings['enabled']         = isset($_POST['enabled']) ? $yes_no($_POST['enabled']) : $settings['enabled'];
    $settings['add_intro']       = isset($_POST['add_intro']) ? $yes_no($_POST['add_intro']) : $settings['add_intro'];
    $settings['add_conclusion']  = isset($_POST['add_conclusion']) ? $yes_no($_POST['add_conclusion']) : $settings['add_conclusion'];
    $settings['use_h2_h3']       = isset($_POST['use_h2_h3']) ? $yes_no($_POST['use_h2_h3']) : $settings['use_h2_h3'];
    $settings['image_generation']= isset($_POST['image_generation']) ? $yes_no($_POST['image_generation']) : $settings['image_generation'];
    $settings['debug_mode']      = isset($_POST['debug_mode']) ? $yes_no($_POST['debug_mode']) : $settings['debug_mode'];

    // Int fields
    $settings['rewrite_strength']  = isset($_POST['rewrite_strength']) ? $as_int(wp_unslash($_POST['rewrite_strength']), 1, 100) : $settings['rewrite_strength'];
    $settings['target_word_count'] = isset($_POST['target_word_count']) ? $as_int(wp_unslash($_POST['target_word_count']), 100, 5000) : $settings['target_word_count'];
    $settings['meta_length']       = isset($_POST['meta_length']) ? $as_int(wp_unslash($_POST['meta_length']), 50, 500) : $settings['meta_length'];
    $settings['title_length']      = isset($_POST['title_length']) ? $as_int(wp_unslash($_POST['title_length']), 20, 120) : $settings['title_length'];

    // Models per task
    $model_keys = ['model_rewrite','model_title','model_seo','model_keywords','model_language'];
    foreach ($model_keys as $k) {
        if (isset($_POST[$k])) $settings[$k] = sanitize_text_field(wp_unslash($_POST[$k]));
    }

    update_option('systemcore_ai_settings', $settings);

    echo '<div class="notice notice-success"><p>AI Settings saved successfully.</p></div>';
}

/* ============================================================
   API TEST FUNCTION (declared once)
============================================================ */
if (!function_exists('systemcore_test_openai_now')) {
    function systemcore_test_openai_now($key, $model, $endpoint) {

        $key = trim((string)$key);
        $endpoint = trim((string)$endpoint);
        $model = trim((string)$model);

        if ($key === '') return ['status'=>'error','message'=>'Missing API Key'];
        if ($endpoint === '') return ['status'=>'error','message'=>'Missing Endpoint'];

        $payload = [
            'model' => ($model !== '' ? $model : 'gpt-4o-mini'),
            'input' => 'test',
            'max_output_tokens' => 10,
        ];

        $res = wp_remote_post(
            $endpoint,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($payload),
                'timeout' => 20,
            ]
        );

        if (is_wp_error($res)) {
            return ['status'=>'error','message'=>$res->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        if ($code >= 200 && $code < 300) {
            return ['status'=>'success','message'=>'Connected'];
        }

        $snippet = mb_substr($body, 0, 300);
        return ['status'=>'error','message'=>"HTTP $code: " . $snippet];
    }
}

/* ============================================================
   Register AJAX handler ONCE (avoid duplicates)
============================================================ */
if (!has_action('wp_ajax_systemcore_test_api_now')) {
    add_action('wp_ajax_systemcore_test_api_now', function () {

        check_ajax_referer('systemcore_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Not allowed']);
        }

        $settings  = get_option('systemcore_ai_settings', []);
        $key       = $settings['api_key'] ?? '';
        $model     = $settings['model'] ?? 'gpt-4o-mini';
        $endpoint  = $settings['api_endpoint'] ?? 'https://api.openai.com/v1/responses';

        $res = systemcore_test_openai_now($key, $model, $endpoint);

        if (($res['status'] ?? '') === 'success') {
            wp_send_json_success(['message' => 'Connected']);
        }

        wp_send_json_error(['message' => $res['message'] ?? 'Not connected']);
    });
}

/* ============================================================
   UI STATUS (do not auto-call API)
============================================================ */
$has_key = trim((string)$settings['api_key']) !== '';
$api_label = $has_key ? 'Not Tested' : 'Missing Key';
$api_class = $has_key ? '' : 'sc-error';

?>
<div class="sc-page">

    <h1 class="sc-title">AI Engine — Settings</h1>

    <div class="sc-grid sc-grid-4 sc-mt-30">

        <div class="sc-card sc-kpi">
            <div class="sc-kpi-title">AI Engine</div>
            <div class="sc-kpi-value">
                <?php echo $settings['enabled']==='yes' ? 'Enabled' : 'Disabled'; ?>
            </div>
        </div>

        <div class="sc-card sc-kpi">
            <div class="sc-kpi-title">API Connection</div>
            <div id="sc-api-status" class="sc-kpi-value <?php echo esc_attr($api_class); ?>">
                <?php echo esc_html($api_label); ?>
            </div>
        </div>

        <div class="sc-card sc-kpi">
            <div class="sc-kpi-title">Default Model</div>
            <div class="sc-kpi-value">
                <?php echo esc_html($settings['model']); ?>
            </div>
        </div>

        <div class="sc-card sc-kpi" style="text-align:center;">
            <div class="sc-kpi-title">Test API Now</div>

            <button type="button" id="sc-test-api-btn" class="button button-primary" style="margin-top:10px;">
                Run Test
            </button>

            <div id="sc-test-result" style="margin-top:12px;font-weight:bold;font-size:14px;"></div>
        </div>

    </div>

    <form method="post" class="sc-mt-40">
        <?php wp_nonce_field('systemcore_ai_settings'); ?>

        <div class="sc-card sc-mt-30" id="general">
            <h2 class="sc-card-title">General Settings</h2>

            <table class="sc-table sc-table-compact">
                <tr>
                    <th>Enable AI Engine</th>
                    <td>
                        <select name="enabled">
                            <option value="yes" <?php selected($settings['enabled'],'yes'); ?>>Yes</option>
                            <option value="no"  <?php selected($settings['enabled'],'no');  ?>>No</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>API Key</th>
                    <td>
                        <input type="text" name="api_key" class="sc-input-wide" value="<?php echo esc_attr($settings['api_key']); ?>">
                    </td>
                </tr>

                <tr>
                    <th>API Endpoint</th>
                    <td>
                        <input type="text" name="api_endpoint" class="sc-input-wide" value="<?php echo esc_attr($settings['api_endpoint']); ?>">
                        <p class="description">Default: https://api.openai.com/v1/responses</p>
                    </td>
                </tr>

                <tr>
                    <th>Default Model</th>
                    <td>
                        <select name="model">
                            <option value="gpt-4o-mini" <?php selected($settings['model'],'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                            <option value="gpt-4o"      <?php selected($settings['model'],'gpt-4o'); ?>>GPT-4o</option>
                            <option value="gpt-4-turbo" <?php selected($settings['model'],'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Rewrite Strength</th>
                    <td>
                        <input type="number" min="1" max="100" name="rewrite_strength" value="<?php echo esc_attr($settings['rewrite_strength']); ?>">
                    </td>
                </tr>
            </table>
        </div>

        <div class="sc-card sc-mt-30">
            <h2 class="sc-card-title">Language & Writing Style</h2>

            <table class="sc-table sc-table-compact">
                <tr>
                    <th>Default Language</th>
                    <td>
                        <select name="default_language">
                            <option value="auto" <?php selected($settings['default_language'],'auto'); ?>>Auto</option>
                            <option value="ar"   <?php selected($settings['default_language'],'ar'); ?>>Arabic</option>
                            <option value="fr"   <?php selected($settings['default_language'],'fr'); ?>>French</option>
                            <option value="de"   <?php selected($settings['default_language'],'de'); ?>>German</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Writing Style</th>
                    <td>
                        <select name="writing_style">
                            <option value="tech_journalism" <?php selected($settings['writing_style'],'tech_journalism'); ?>>Tech Journalism</option>
                            <option value="neutral"         <?php selected($settings['writing_style'],'neutral'); ?>>Neutral</option>
                            <option value="creative"        <?php selected($settings['writing_style'],'creative'); ?>>Creative</option>
                            <option value="simple"          <?php selected($settings['writing_style'],'simple'); ?>>Simple</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Target Word Count</th>
                    <td><input type="number" name="target_word_count" value="<?php echo esc_attr($settings['target_word_count']); ?>"></td>
                </tr>
            </table>
        </div>

        <div class="sc-card sc-mt-30">
            <h2 class="sc-card-title">Content Structure</h2>

            <table class="sc-table sc-table-compact">
                <tr>
                    <th>Add Intro</th>
                    <td>
                        <select name="add_intro">
                            <option value="yes" <?php selected($settings['add_intro'],'yes'); ?>>Yes</option>
                            <option value="no"  <?php selected($settings['add_intro'],'no');  ?>>No</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Add Conclusion</th>
                    <td>
                        <select name="add_conclusion">
                            <option value="yes" <?php selected($settings['add_conclusion'],'yes'); ?>>Yes</option>
                            <option value="no"  <?php selected($settings['add_conclusion'],'no');  ?>>No</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Use H2/H3</th>
                    <td>
                        <select name="use_h2_h3">
                            <option value="yes" <?php selected($settings['use_h2_h3'],'yes'); ?>>Yes</option>
                            <option value="no"  <?php selected($settings['use_h2_h3'],'no');  ?>>No</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="sc-card sc-mt-30">
            <h2 class="sc-card-title">SEO Settings</h2>

            <table class="sc-table sc-table-compact">
                <tr>
                    <th>SEO Mode</th>
                    <td>
                        <select name="seo_mode">
                            <option value="basic"    <?php selected($settings['seo_mode'],'basic'); ?>>Basic</option>
                            <option value="advanced" <?php selected($settings['seo_mode'],'advanced'); ?>>Advanced</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Title Length</th>
                    <td><input type="number" name="title_length" value="<?php echo esc_attr($settings['title_length']); ?>"></td>
                </tr>

                <tr>
                    <th>Meta Description Length</th>
                    <td><input type="number" name="meta_length" value="<?php echo esc_attr($settings['meta_length']); ?>"></td>
                </tr>
            </table>
        </div>

        <div class="sc-card sc-mt-30">
            <h2 class="sc-card-title">AI Image Generation</h2>

            <table class="sc-table sc-table-compact">
                <tr>
                    <th>Generate Image</th>
                    <td>
                        <select name="image_generation">
                            <option value="no"  <?php selected($settings['image_generation'],'no'); ?>>No</option>
                            <option value="yes" <?php selected($settings['image_generation'],'yes'); ?>>Yes</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>Image Style</th>
                    <td>
                        <select name="image_style">
                            <option value="tech-flat"       <?php selected($settings['image_style'],'tech-flat'); ?>>Tech Flat</option>
                            <option value="3d-illustration" <?php selected($settings['image_style'],'3d-illustration'); ?>>3D Illustration</option>
                            <option value="minimal"         <?php selected($settings['image_style'],'minimal'); ?>>Minimal</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="sc-card sc-mt-30" id="models">
            <h2 class="sc-card-title">Models per Task</h2>

            <table class="sc-table sc-table-compact">
            <?php
            $select = function($name, $value) {
                ?>
                <select name="<?php echo esc_attr($name); ?>">
                    <option value="default"     <?php selected($value,'default'); ?>>Use Default</option>
                    <option value="gpt-4o-mini" <?php selected($value,'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                    <option value="gpt-4o"      <?php selected($value,'gpt-4o'); ?>>GPT-4o</option>
                    <option value="gpt-4-turbo" <?php selected($value,'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                </select>
                <?php
            };
            ?>
                <tr><th>Rewriting</th><td><?php $select('model_rewrite',$settings['model_rewrite']); ?></td></tr>
                <tr><th>Title Generation</th><td><?php $select('model_title',$settings['model_title']); ?></td></tr>
                <tr><th>SEO</th><td><?php $select('model_seo',$settings['model_seo']); ?></td></tr>
                <tr><th>Keywords</th><td><?php $select('model_keywords',$settings['model_keywords']); ?></td></tr>
                <tr><th>Language</th><td><?php $select('model_language',$settings['model_language']); ?></td></tr>
            </table>
        </div>

        <div class="sc-card sc-mt-30">
            <h2 class="sc-card-title">Debug</h2>

            <table class="sc-table sc-table-compact">
                <tr>
                    <th>Enable Debug Mode</th>
                    <td>
                        <select name="debug_mode">
                            <option value="no"  <?php selected($settings['debug_mode'],'no'); ?>>No</option>
                            <option value="yes" <?php selected($settings['debug_mode'],'yes'); ?>>Yes (Raw output)</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <button type="submit" name="systemcore_ai_save" class="button button-primary sc-mt-30">Save Settings</button>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const $ = jQuery;

    const btn = document.getElementById("sc-test-api-btn");
    const statusEl = document.getElementById("sc-api-status");
    const resultEl = document.getElementById("sc-test-result");

    if (!btn) return;

    function getNonce() {
        if (window.SystemCoreAdmin && SystemCoreAdmin.nonce) return SystemCoreAdmin.nonce;
        if (window.SC_UI_Admin && SC_UI_Admin.nonce) return SC_UI_Admin.nonce;
        return '';
    }

    btn.addEventListener("click", function () {
        const nonce = getNonce();

        btn.disabled = true;
        btn.textContent = "Testing...";
        resultEl.textContent = "";

        $.post(ajaxurl, {
            action: "systemcore_test_api_now",
            nonce: nonce
        }).done(function (res) {
            // WP style: {success,data:{message}}
            if (res && typeof res === 'object' && 'success' in res) {
                const msg = (res.data && res.data.message) ? res.data.message : (res.success ? "Connected" : "Not Connected");
                resultEl.textContent = msg;

                if (res.success) {
                    statusEl.textContent = "Connected";
                    statusEl.classList.remove("sc-error");
                    statusEl.classList.add("sc-ok");
                } else {
                    statusEl.textContent = "Not Connected";
                    statusEl.classList.remove("sc-ok");
                    statusEl.classList.add("sc-error");
                }
                return;
            }

            resultEl.textContent = "Unexpected response.";
        }).fail(function () {
            resultEl.textContent = "Request failed.";
            statusEl.textContent = "Not Connected";
            statusEl.classList.remove("sc-ok");
            statusEl.classList.add("sc-error");
        }).always(function () {
            btn.disabled = false;
            btn.textContent = "Run Test";
        });
    });
});
</script>
