<?php
if (!defined('ABSPATH')) exit;

/**
 * ================================================================
 *  Publisher Functions — Settings + Helpers
 * ================================================================
 */


/**
 * Default publisher settings (multilingual)
 */
function systemcore_get_publisher_defaults() {

    return [

        'status'          => 'publish',
        'posts_per_batch' => 5,
        'default_thumb_id'=> 0,
        'publishers'      => [],
        'default_lang'    => 'ar',
        'category_id'     => 0,

        'enable_ai_rewrite'  => 1,
        'enable_ai_category' => 1,

        'languages' => [

            'ar' => [
                'active'        => 1,
                'category_id'   => 0,
                'categories_api'=> home_url('/wp-json/wp/v2/categories?per_page=100&lang=ar'),
                'prompts'       => [
                    'rewrite'  => '',
                    'category' => '',
                ],
            ],

            'fr' => [
                'active'        => 0,
                'category_id'   => 0,
                'categories_api'=> home_url('/wp-json/wp/v2/categories?per_page=100&lang=fr'),
                'prompts'       => [
                    'rewrite'  => '',
                    'category' => '',
                ],
            ],

            'de' => [
                'active'        => 0,
                'category_id'   => 0,
                'categories_api'=> home_url('/wp-json/wp/v2/categories?per_page=100&lang=de'),
                'prompts'       => [
                    'rewrite'  => '',
                    'category' => '',
                ],
            ],
        ],
    ];
}


/**
 * Load saved settings with defaults
 */
function systemcore_get_publisher_settings() {

    $saved = get_option('systemcore_publisher_settings', []);

    if (!is_array($saved)) {
        $saved = [];
    }

    $defaults = systemcore_get_publisher_defaults();

    return array_replace_recursive($defaults, $saved);
}


/**
 * ================================================================
 *  QUEUE COUNT FOR ADMIN SCREEN
 * ================================================================
 */
function systemcore_publisher_get_queue_count() {
    global $wpdb;
    $table = $wpdb->prefix . 'systemcore_queue';

    if (! $wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
        return 0;
    }

    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");

    return max(0, $count);
}


/**
 * ================================================================
 *  Active languages text (used in publisher admin)
 * ================================================================
 */
function systemcore_publisher_get_active_langs_text($options) {

    if (empty($options['languages']) || !is_array($options['languages'])) {
        return '—';
    }

    $labels = [
        'ar' => 'Arabic',
        'fr' => 'French',
        'de' => 'German',
    ];

    $out = [];

    foreach ($options['languages'] as $key => $lang) {
        if (!empty($lang['active'])) {
            $out[] = isset($labels[$key]) ? $labels[$key] : strtoupper($key);
        }
    }

    return empty($out) ? '—' : implode(', ', $out);
}


/**
 * ================================================================
 *  Validate and Save Settings
 * ================================================================
 */
function systemcore_publisher_save_settings($data) {

    $defaults = systemcore_get_publisher_defaults();
    $settings = systemcore_get_publisher_settings();

    // Basic fields
    $settings['status']          = in_array($data['status'], ['draft','pending','publish'], true) ? $data['status'] : 'draft';
    $settings['posts_per_batch'] = max(1, (int) $data['posts_per_batch']);
    $settings['default_thumb_id']= (int) ($data['default_thumb_id'] ?? 0);
    $settings['default_lang']    = sanitize_text_field($data['default_lang']);
    $settings['category_id']     = (int) ($data['category_id'] ?? 0);

    $settings['enable_ai_rewrite']  = empty($data['enable_ai_rewrite']) ? 0 : 1;
    $settings['enable_ai_category'] = empty($data['enable_ai_category']) ? 0 : 1;

    // Multilingual fields
    foreach ($defaults['languages'] as $key => $lang) {

        if (!isset($settings['languages'][$key])) {
            $settings['languages'][$key] = $lang;
        }

        $settings['languages'][$key]['active']        = isset($data["lang_{$key}_active"]) ? 1 : 0;
        $settings['languages'][$key]['category_id']   = (int) ($data["lang_{$key}_category_id"] ?? 0);
        $settings['languages'][$key]['prompts']['rewrite']  = sanitize_textarea_field($data["lang_{$key}_rewrite"] ?? '');
        $settings['languages'][$key]['prompts']['category'] = sanitize_textarea_field($data["lang_{$key}_category"] ?? '');
    }

    update_option('systemcore_publisher_settings', $settings);

    return true;
}



/**
 * ================================================================
 *  Last Publisher Run Info (Fix for admin-screen.php)
 * ================================================================
 */

/**
 * Get last run info
 */
function systemcore_publisher_get_last_run_info() {

    $log = get_option('systemcore_publisher_last_run', []);

    if (!is_array($log)) {
        return [
            'time'      => '—',
            'total'     => 0,
            'published' => 0,
            'failed'    => 0,
            'skipped'   => 0,
        ];
    }

    return wp_parse_args($log, [
        'time'      => '—',
        'total'     => 0,
        'published' => 0,
        'failed'    => 0,
        'skipped'   => 0,
    ]);
}


/**
 * Save last run info
 */
function systemcore_publisher_set_last_run($data) {

    if (!is_array($data)) {
        return false;
    }

    $data['time'] = current_time('mysql');

    update_option('systemcore_publisher_last_run', $data);

    return true;
}
