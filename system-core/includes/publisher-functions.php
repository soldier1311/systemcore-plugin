<?php
if (!defined('ABSPATH')) exit;

/**
 * Default publisher settings
 */
function systemcore_get_publisher_defaults() {
    return [
        'status'          => 'publish',
        'posts_per_batch' => 5,
        'default_thumb_id'=> 0,
        'publishers'      => [],
        'default_lang'    => 'ar',
        'enable_ai_rewrite'  => 1,
        'enable_ai_category' => 1,
        'languages' => [
            'ar' => [
                'active' => 1,
                'categories_api' => home_url('/wp-json/wp/v2/categories?per_page=100&lang=ar'),
                'prompts' => [
                    'rewrite'  => '',
                    'category' => '',
                ],
            ],
            'fr' => [
                'active' => 0,
                'categories_api' => home_url('/wp-json/wp/v2/categories?per_page=100&lang=fr'),
                'prompts' => [
                    'rewrite'  => '',
                    'category' => '',
                ],
            ],
            'de' => [
                'active' => 0,
                'categories_api' => home_url('/wp-json/wp/v2/categories?per_page=100&lang=de'),
                'prompts' => [
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

    return wp_parse_args($saved, systemcore_get_publisher_defaults());
}
