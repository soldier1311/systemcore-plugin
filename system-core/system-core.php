<?php
/**
 * Plugin Name: SystemCore
 * Description: Internal system module.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

define('SYSTEMCORE_PATH', plugin_dir_path(__FILE__));
define('SYSTEMCORE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SYSTEMCORE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load init.php (bootstrap loader)
require_once SYSTEMCORE_PLUGIN_PATH . 'includes/init.php';

/**
 * IMPORTANT:
 * Do NOT load CSS/JS here.
 * UI Loader handles all UI assets.
 */
