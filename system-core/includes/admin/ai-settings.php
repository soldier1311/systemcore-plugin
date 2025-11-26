<?php
if (!defined('ABSPATH')) exit;

/**
 * فحص اتصال OpenAI API
 */
if (!function_exists('systemcore_test_openai_connection')) {

    function systemcore_test_openai_connection($api_key, $model = 'gpt-4o-mini') {

        if (empty($api_key)) {
            return [
                'status'  => 'error',
                'message' => 'Missing API key'
            ];
        }

        $endpoint = 'https://api.openai.com/v1/chat/completions';

        $body = [
            'model'    => $model,
            'messages' => [
                [ 'role' => 'user', 'content' => 'test' ],
            ],
            'max_tokens' => 5,
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return [
                'status'  => 'error',
                'message' => $response->get_error_message(),
            ];
        }

        $code     = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);

        if ($code >= 200 && $code < 300) {
            return [
                'status'  => 'success',
                'message' => 'API connected successfully',
            ];
        }

        $msg = 'HTTP Error ' . $code;
        $data = json_decode($raw_body, true);
        if (!empty($data['error']['message'])) {
            $msg .= ' – ' . $data['error']['message'];
        }

        return [
            'status'  => 'error',
            'message' => $msg,
        ];
    }
}

/**
 * تحميل الإعدادات من قاعدة البيانات
 */
$settings = get_option('systemcore_ai_settings', [
    'enabled'            => 'yes',
    'api_key'            => '',
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
]);

/**
 * حفظ الإعدادات
 */
if (isset($_POST['systemcore_ai_save'])) {

    check_admin_referer('systemcore_ai_settings');

    $settings = [
        'enabled'            => sanitize_text_field($_POST['enabled'] ?? 'yes'),
        'api_key'            => sanitize_text_field($_POST['api_key'] ?? ''),
        'model'              => sanitize_text_field($_POST['model'] ?? 'gpt-4o-mini'),
        'rewrite_strength'   => intval($_POST['rewrite_strength'] ?? 70),
        'default_language'   => sanitize_text_field($_POST['default_language'] ?? 'auto'),
        'writing_style'      => sanitize_text_field($_POST['writing_style'] ?? 'tech_journalism'),
        'add_intro'          => sanitize_text_field($_POST['add_intro'] ?? 'yes'),
        'add_conclusion'     => sanitize_text_field($_POST['add_conclusion'] ?? 'yes'),
        'use_h2_h3'          => sanitize_text_field($_POST['use_h2_h3'] ?? 'yes'),
        'target_word_count'  => intval($_POST['target_word_count'] ?? 600),
        'meta_length'        => intval($_POST['meta_length'] ?? 160),
        'title_length'       => intval($_POST['title_length'] ?? 60),
        'seo_mode'           => sanitize_text_field($_POST['seo_mode'] ?? 'advanced'),
        'image_generation'   => sanitize_text_field($_POST['image_generation'] ?? 'no'),
        'image_style'        => sanitize_text_field($_POST['image_style'] ?? 'tech-flat'),
        'debug_mode'         => sanitize_text_field($_POST['debug_mode'] ?? 'no'),

        'model_rewrite'   => sanitize_text_field($_POST['model_rewrite'] ?? 'default'),
        'model_title'     => sanitize_text_field($_POST['model_title'] ?? 'default'),
        'model_seo'       => sanitize_text_field($_POST['model_seo'] ?? 'default'),
        'model_keywords'  => sanitize_text_field($_POST['model_keywords'] ?? 'default'),
        'model_language'  => sanitize_text_field($_POST['model_language'] ?? 'default'),
    ];

    update_option('systemcore_ai_settings', $settings);

    echo '<div class="notice notice-success"><p>AI Settings saved successfully.</p></div>';
}

/**
 * اختبار الـ API للأعلى
 */
$api_status = null;
if (!empty($settings['api_key']) && $settings['enabled'] === 'yes') {
    $test_model = !empty($settings['model']) ? $settings['model'] : 'gpt-4o-mini';
    $api_status = systemcore_test_openai_connection($settings['api_key'], $test_model);
}

// معلومات البطاقات
$api_connected  = $api_status && $api_status['status'] === 'success';
$per_task_count = 0;
foreach (['model_rewrite','model_title','model_seo','model_keywords','model_language'] as $key) {
    if (!empty($settings[$key]) && $settings[$key] !== 'default') {
        $per_task_count++;
    }
}
?>

<div class="systemcore-wrap systemcore-ai-page">

    <!-- رأس الصفحة -->
    <div class="systemcore-grid systemcore-grid-2 systemcore-ai-summary sc-mt-15">

        <div class="systemcore-card">
            <h1 class="systemcore-title">SystemCore – AI Engine</h1>
            <p>
                إعدادات الذكاء الاصطناعي لنظام SystemCore. من هنا يمكنك تفعيل المحرك، ضبط الموديل الافتراضي،
                التحكم في أسلوب الكتابة، وضبط إعدادات السيو والصور.
            </p>

            <p class="sc-mt-15">
                <strong>Current Status:</strong>
                <?php if ($settings['enabled'] === 'yes') : ?>
                    AI Engine: <span style="color:#46b450;font-weight:600;">Enabled</span>
                <?php else: ?>
                    AI Engine: <span style="color:#d63638;font-weight:600;">Disabled</span>
                <?php endif; ?>
                &nbsp;|&nbsp;
                API:
                <?php if ($api_connected): ?>
                    <span style="color:#46b450;font-weight:600;">Connected</span>
                <?php else: ?>
                    <span style="color:#d63638;font-weight:600;">Not Connected</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="systemcore-card">
            <h2 class="systemcore-card-title">Quick Overview</h2>
            <table class="systemcore-table systemcore-table-compact">
                <tbody>
                    <tr>
                        <th>Default Model</th>
                        <td><?php echo esc_html($settings['model'] ?: 'gpt-4o-mini'); ?></td>
                    </tr>
                    <tr>
                        <th>Default Language</th>
                        <td><?php echo esc_html($settings['default_language']); ?></td>
                    </tr>
                    <tr>
                        <th>Writing Style</th>
                        <td><?php echo esc_html($settings['writing_style']); ?></td>
                    </tr>
                    <tr>
                        <th>Target Word Count</th>
                        <td><?php echo (int) $settings['target_word_count']; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>


    <!-- بطاقات الملخص -->
    <div class="systemcore-grid systemcore-grid-4 sc-mt-20 systemcore-ai-summary">

        <div class="systemcore-card">
            <div class="sc-card-label">AI Engine</div>
            <div class="sc-card-value">
                <?php echo ($settings['enabled'] === 'yes') ? 'Enabled' : 'Disabled'; ?>
            </div>
            <div class="sc-card-sub">Controls all AI features</div>
        </div>

        <div class="systemcore-card">
            <div class="sc-card-label">API Connection</div>
            <div class="sc-card-value">
                <?php echo $api_connected ? 'Connected' : 'Not Connected'; ?>
            </div>
            <div class="sc-card-sub">OpenAI chat completions</div>
        </div>

        <div class="systemcore-card">
            <div class="sc-card-label">Per-task Models</div>
            <div class="sc-card-value"><?php echo (int)$per_task_count; ?></div>
            <div class="sc-card-sub">Custom overrides enabled</div>
        </div>

        <div class="systemcore-card">
            <div class="sc-card-label">SEO Mode</div>
            <div class="sc-card-value"><?php echo esc_html(ucfirst($settings['seo_mode'])); ?></div>
            <div class="sc-card-sub">Meta / titles optimization</div>
        </div>

    </div>
    <!-- فورم الإعدادات -->
    <form method="post" class="systemcore-ai-form sc-mt-30">
        <?php wp_nonce_field('systemcore_ai_settings'); ?>

        <!-- الكارت الرئيسي (Accordion Style) -->
        <div class="systemcore-card systemcore-ai-settings-card">

            <div class="sc-accordion">


                <!-- =========================
                     1) قسم GENERAL
                ========================== -->
                <div class="sc-acc-item open">
                    <div class="sc-acc-header">General Settings</div>
                    <div class="sc-acc-content">

                        <div class="systemcore-grid systemcore-grid-2">

                            <!-- GENERAL CARD -->
                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">General</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>AI Engine Enabled</th>
                                            <td>
                                                <select name="enabled">
                                                    <option value="yes" <?php selected($settings['enabled'], 'yes'); ?>>Yes</option>
                                                    <option value="no"  <?php selected($settings['enabled'], 'no');  ?>>No</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>OpenAI API Key</th>
                                            <td>
                                                <input type="text"
                                                       name="api_key"
                                                       class="sc-input-wide"
                                                       value="<?php echo esc_attr($settings['api_key']); ?>">
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Default Model</th>
                                            <td>
                                                <select name="model">
                                                    <option value="gpt-4o-mini" <?php selected($settings['model'], 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                                                    <option value="gpt-4o"       <?php selected($settings['model'], 'gpt-4o');       ?>>GPT-4o</option>
                                                    <option value="gpt-4-turbo"  <?php selected($settings['model'], 'gpt-4-turbo');  ?>>GPT-4 Turbo</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Rewrite Strength</th>
                                            <td>
                                                <input type="number"
                                                       name="rewrite_strength"
                                                       min="1" max="100"
                                                       value="<?php echo esc_attr($settings['rewrite_strength']); ?>">
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>
                            </div>


                            <!-- LANGUAGE & STYLE CARD -->
                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">Language & Style</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>Default Language</th>
                                            <td>
                                                <select name="default_language">
                                                    <option value="auto" <?php selected($settings['default_language'], 'auto'); ?>>Auto detect</option>
                                                    <option value="ar"   <?php selected($settings['default_language'], 'ar');   ?>>Arabic</option>
                                                    <option value="fr"   <?php selected($settings['default_language'], 'fr');   ?>>French</option>
                                                    <option value="de"   <?php selected($settings['default_language'], 'de');   ?>>German</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Writing Style</th>
                                            <td>
                                                <select name="writing_style">
                                                    <option value="tech_journalism" <?php selected($settings['writing_style'], 'tech_journalism'); ?>>Tech Journalism</option>
                                                    <option value="neutral"          <?php selected($settings['writing_style'], 'neutral');          ?>>Neutral</option>
                                                    <option value="creative"         <?php selected($settings['writing_style'], 'creative');         ?>>Creative</option>
                                                    <option value="simple"           <?php selected($settings['writing_style'], 'simple');           ?>>Simple & Easy</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Target Word Count</th>
                                            <td>
                                                <input type="number"
                                                       name="target_word_count"
                                                       value="<?php echo esc_attr($settings['target_word_count']); ?>">
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>

                            </div>

                        </div><!-- systemcore-grid -->

                    </div><!-- sc-acc-content -->
                </div><!-- sc-acc-item GENERAL -->
                <!-- =========================
                     2) MODELS
                ========================== -->
                <div class="sc-acc-item">
                    <div class="sc-acc-header">AI Models per Task</div>
                    <div class="sc-acc-content">

                        <div class="systemcore-grid systemcore-grid-2">

                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">Models Configuration</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>Model for Rewriting</th>
                                            <td>
                                                <select name="model_rewrite">
                                                    <option value="default"      <?php selected($settings['model_rewrite'], 'default');      ?>>Use Default</option>
                                                    <option value="gpt-4o-mini"  <?php selected($settings['model_rewrite'], 'gpt-4o-mini');  ?>>GPT-4o Mini</option>
                                                    <option value="gpt-4o"       <?php selected($settings['model_rewrite'], 'gpt-4o');       ?>>GPT-4o</option>
                                                    <option value="gpt-4-turbo"  <?php selected($settings['model_rewrite'], 'gpt-4-turbo');  ?>>GPT-4 Turbo</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Model for Titles</th>
                                            <td>
                                                <select name="model_title">
                                                    <option value="default"      <?php selected($settings['model_title'], 'default');      ?>>Use Default</option>
                                                    <option value="gpt-4o-mini"  <?php selected($settings['model_title'], 'gpt-4o-mini');  ?>>GPT-4o Mini</option>
                                                    <option value="gpt-4o"       <?php selected($settings['model_title'], 'gpt-4o');       ?>>GPT-4o</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Model for SEO</th>
                                            <td>
                                                <select name="model_seo">
                                                    <option value="default"      <?php selected($settings['model_seo'], 'default');      ?>>Use Default</option>
                                                    <option value="gpt-4o-mini"  <?php selected($settings['model_seo'], 'gpt-4o-mini');  ?>>GPT-4o Mini</option>
                                                    <option value="gpt-4-turbo"  <?php selected($settings['model_seo'], 'gpt-4-turbo');  ?>>GPT-4 Turbo</option>
                                                </select>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>
                            </div>


                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">Models (Keywords & Language)</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>Model for Keywords</th>
                                            <td>
                                                <select name="model_keywords">
                                                    <option value="default"      <?php selected($settings['model_keywords'], 'default');      ?>>Use Default</option>
                                                    <option value="gpt-4o-mini"  <?php selected($settings['model_keywords'], 'gpt-4o-mini');  ?>>GPT-4o Mini</option>
                                                    <option value="gpt-4o"       <?php selected($settings['model_keywords'], 'gpt-4o');       ?>>GPT-4o</option>
                                                    <option value="gpt-4-turbo"  <?php selected($settings['model_keywords'], 'gpt-4-turbo');  ?>>GPT-4 Turbo</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Model for Language Detection</th>
                                            <td>
                                                <select name="model_language">
                                                    <option value="default"      <?php selected($settings['model_language'], 'default');      ?>>Use Default</option>
                                                    <option value="gpt-4o-mini"  <?php selected($settings['model_language'], 'gpt-4o-mini');  ?>>GPT-4o Mini</option>
                                                    <option value="gpt-4o"       <?php selected($settings['model_language'], 'gpt-4o');       ?>>GPT-4o</option>
                                                </select>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>

                            </div>

                        </div><!-- grid -->

                    </div><!-- acc-content -->
                </div><!-- acc-item MODELS -->



                <!-- =========================
                     3) CONTENT STYLE
                ========================== -->
                <div class="sc-acc-item">
                    <div class="sc-acc-header">Content Style</div>
                    <div class="sc-acc-content">

                        <div class="systemcore-grid systemcore-grid-2">

                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">Content Structure</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>Add Intro</th>
                                            <td>
                                                <select name="add_intro">
                                                    <option value="yes" <?php selected($settings['add_intro'], 'yes'); ?>>Yes</option>
                                                    <option value="no"  <?php selected($settings['add_intro'], 'no');  ?>>No</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Add Conclusion</th>
                                            <td>
                                                <select name="add_conclusion">
                                                    <option value="yes" <?php selected($settings['add_conclusion'], 'yes'); ?>>Yes</option>
                                                    <option value="no"  <?php selected($settings['add_conclusion'], 'no');  ?>>No</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Use H2/H3</th>
                                            <td>
                                                <select name="use_h2_h3">
                                                    <option value="yes" <?php selected($settings['use_h2_h3'], 'yes'); ?>>Yes</option>
                                                    <option value="no"  <?php selected($settings['use_h2_h3'], 'no');  ?>>No</option>
                                                </select>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>
                            </div>


                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">Target Length</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>Target Word Count</th>
                                            <td>
                                                <input type="number"
                                                       name="target_word_count"
                                                       value="<?php echo esc_attr($settings['target_word_count']); ?>">
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>

                            </div>

                        </div><!-- grid -->

                    </div><!-- acc-content -->
                </div><!-- acc-item CONTENT -->



                <!-- =========================
                     4) SEO
                ========================== -->
                <div class="sc-acc-item">
                    <div class="sc-acc-header">SEO Settings</div>
                    <div class="sc-acc-content">

                        <div class="systemcore-grid systemcore-grid-2">

                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">SEO Mode</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>SEO Mode</th>
                                            <td>
                                                <select name="seo_mode">
                                                    <option value="basic"    <?php selected($settings['seo_mode'], 'basic');    ?>>Basic</option>
                                                    <option value="advanced" <?php selected($settings['seo_mode'], 'advanced'); ?>>Advanced</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Title Length</th>
                                            <td>
                                                <input type="number"
                                                       name="title_length"
                                                       value="<?php echo esc_attr($settings['title_length']); ?>">
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>
                            </div>


                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">Meta Settings</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>Meta Description Length</th>
                                            <td>
                                                <input type="number"
                                                       name="meta_length"
                                                       value="<?php echo esc_attr($settings['meta_length']); ?>">
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>

                            </div>

                        </div><!-- grid -->

                    </div><!-- acc-content -->
                </div><!-- acc-item SEO -->



                <!-- =========================
                     5) IMAGES
                ========================== -->
                <div class="sc-acc-item">
                    <div class="sc-acc-header">Image Settings</div>
                    <div class="sc-acc-content">

                        <div class="systemcore-grid systemcore-grid-2">

                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">AI Image Generation</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>Generate Image</th>
                                            <td>
                                                <select name="image_generation">
                                                    <option value="no"  <?php selected($settings['image_generation'], 'no');  ?>>No</option>
                                                    <option value="yes" <?php selected($settings['image_generation'], 'yes'); ?>>Yes</option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>Image Style</th>
                                            <td>
                                                <select name="image_style">
                                                    <option value="tech-flat"       <?php selected($settings['image_style'], 'tech-flat');       ?>>Tech Flat</option>
                                                    <option value="3d-illustration" <?php selected($settings['image_style'], '3d-illustration'); ?>>3D Illustration</option>
                                                    <option value="minimal"         <?php selected($settings['image_style'], 'minimal');         ?>>Minimal</option>
                                                </select>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>

                            </div>


                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">Notes</h2>
                                <p>AI Image generation will increase API usage significantly.</p>
                            </div>

                        </div><!-- grid -->

                    </div><!-- acc-content -->
                </div><!-- acc-item IMAGES -->



                <!-- =========================
                     6) DEBUG
                ========================== -->
                <div class="sc-acc-item">
                    <div class="sc-acc-header">Debug Mode</div>
                    <div class="sc-acc-content">

                        <div class="systemcore-grid systemcore-grid-2">

                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">Debug Options</h2>

                                <table class="systemcore-table systemcore-table-compact">
                                    <tbody>

                                        <tr>
                                            <th>Debug Mode</th>
                                            <td>
                                                <select name="debug_mode">
                                                    <option value="no"  <?php selected($settings['debug_mode'], 'no');  ?>>No</option>
                                                    <option value="yes" <?php selected($settings['debug_mode'], 'yes'); ?>>Yes (Show Raw Data)</option>
                                                </select>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>
                            </div>


                            <div class="systemcore-card">
                                <h2 class="systemcore-card-title">Debug Notes</h2>
                                <p>
                                    When enabled, SystemCore will show raw API requests and responses to help debugging.
                                </p>
                            </div>

                        </div><!-- grid -->

                    </div><!-- acc-content -->
                </div><!-- acc-item DEBUG -->



            </div><!-- sc-accordion -->

        </div><!-- systemcore-ai-settings-card -->


        <!-- زر الحفظ -->
        <p class="sc-mt-20">
            <button type="submit" name="systemcore_ai_save" class="button button-primary">
                Save Settings
            </button>
        </p>

    </form>

</div><!-- systemcore-wrap -->
