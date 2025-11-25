<?php
if (!defined('ABSPATH')) exit;

// Load saved settings & defaults
$options  = systemcore_get_publisher_settings();
$defaults = systemcore_get_publisher_defaults();

// Save settings
if (isset($_POST['systemcore_publisher_save']) && check_admin_referer('systemcore_publisher_settings')) {

    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'publish';
    if (!in_array($status, ['publish', 'draft', 'pending'], true)) {
        $status = 'publish';
    }

    $options['status']          = $status;
    $options['posts_per_batch'] = max(1, (int) ($_POST['posts_per_batch'] ?? 5));
    $options['default_thumb_id']= (int) ($_POST['default_thumb_id'] ?? 0);

    // Publishers list
    $raw_publishers = isset($_POST['publishers']) ? sanitize_text_field($_POST['publishers']) : '';
    $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', $raw_publishers)));
    $options['publishers'] = $ids;

    $options['default_lang']      = $_POST['default_lang'] ?? 'ar';
    $options['enable_ai_rewrite'] = !empty($_POST['enable_ai_rewrite']) ? 1 : 0;
    $options['enable_ai_category']= !empty($_POST['enable_ai_category']) ? 1 : 0;

    // Languages
    foreach (['ar','fr','de'] as $lang) {

        $lang_key = 'lang_' . $lang;

        $options['languages'][$lang]['active']         = !empty($_POST[$lang_key . '_active']) ? 1 : 0;

        $options['languages'][$lang]['categories_api'] =
            !empty($_POST[$lang_key . '_categories_api'])
                ? esc_url_raw($_POST[$lang_key . '_categories_api'])
                : $defaults['languages'][$lang]['categories_api'];

        $options['languages'][$lang]['prompts']['rewrite'] =
            !empty($_POST[$lang_key . '_prompt_rewrite'])
                ? wp_kses_post($_POST[$lang_key . '_prompt_rewrite'])
                : '';

        $options['languages'][$lang]['prompts']['category'] =
            !empty($_POST[$lang_key . '_prompt_category'])
                ? wp_kses_post($_POST[$lang_key . '_prompt_category'])
                : '';
    }

    update_option('systemcore_publisher_settings', $options);

    echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
}
?>

<div class="wrap systemcore-settings">

    <h1>SystemCore Publisher</h1>

    <form method="post">
        <?php wp_nonce_field('systemcore_publisher_settings'); ?>

        <!-- GENERAL SETTINGS -->
        <h2>General Settings</h2>
        <table class="form-table">

            <tr>
                <th>Status</th>
                <td>
                    <select name="status">
                        <option value="publish" <?php selected($options['status'], 'publish'); ?>>Publish</option>
                        <option value="draft"   <?php selected($options['status'], 'draft'); ?>>Draft</option>
                        <option value="pending" <?php selected($options['status'], 'pending'); ?>>Pending</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th>Posts per Batch</th>
                <td>
                    <input type="number" name="posts_per_batch" min="1"
                           value="<?php echo esc_attr($options['posts_per_batch']); ?>">
                </td>
            </tr>

            <tr>
                <th>Publishers (IDs)</th>
                <td>
                    <input type="text" name="publishers"
                           value="<?php echo esc_attr(implode(', ', $options['publishers'])); ?>"
                           class="regular-text">
                    <p class="description">Example: 2,4,5</p>
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
                <th>Default Thumbnail ID</th>
                <td><input type="number" name="default_thumb_id"
                           value="<?php echo esc_attr($options['default_thumb_id']); ?>"></td>
            </tr>

        </table>


        <!-- AI SETTINGS -->
        <h2>AI Settings</h2>
        <table class="form-table">

            <tr>
                <th>AI Rewrite</th>
                <td><input type="checkbox" name="enable_ai_rewrite" value="1" <?php checked($options['enable_ai_rewrite'], 1); ?>></td>
            </tr>

            <tr>
                <th>AI Category Detection</th>
                <td><input type="checkbox" name="enable_ai_category" value="1" <?php checked($options['enable_ai_category'], 1); ?>></td>
            </tr>

        </table>


        <!-- LANGUAGE CONFIG BOXES -->
        <h2>Languages Configuration</h2>

        <style>
            .systemcore-lang-box {
                background: #fff;
                border: 1px solid #ccc;
                padding: 18px;
                margin-bottom: 22px;
            }
            .systemcore-lang-box textarea {
                width: 100%;
                min-height: 110px;
                font-family: monospace;
            }
        </style>

        <?php
        $langs = ['ar' => 'Arabic', 'fr' => 'French', 'de' => 'German'];

        foreach ($langs as $code => $label):
            $cfg = $options['languages'][$code];
            $key = 'lang_' . $code;
        ?>
            <div class="systemcore-lang-box">
                <h3><?php echo $label . " ({$code})"; ?></h3>

                <p>
                    <label>
                        <input type="checkbox" name="<?php echo $key; ?>_active"
                               value="1" <?php checked($cfg['active'], 1); ?>>
                        Enable this language
                    </label>
                </p>

                <p>
                    <label>Categories API URL</label><br>
                    <input type="text" class="large-text"
                           name="<?php echo $key; ?>_categories_api"
                           value="<?php echo esc_attr($cfg['categories_api']); ?>">
                </p>

                <p><strong>Rewrite Prompt</strong></p>
                <textarea name="<?php echo $key; ?>_prompt_rewrite"><?php echo esc_textarea($cfg['prompts']['rewrite']); ?></textarea>

                <p><strong>Category Selection Prompt</strong></p>
                <textarea name="<?php echo $key; ?>_prompt_category"><?php echo esc_textarea($cfg['prompts']['category']); ?></textarea>
            </div>

        <?php endforeach; ?>

        <!-- SAVE BUTTON -->
        <?php submit_button('Save Settings', 'primary', 'systemcore_publisher_save'); ?>

    </form>

</div>
