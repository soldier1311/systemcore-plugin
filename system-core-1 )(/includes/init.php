<?php
if (!defined('ABSPATH')) exit;

/**
 * SystemCore INIT
 * Loads bootstrap and initializes plugin.
 */

// Optional debug (comment it if it floods error log)
// error_log("SystemCore INIT LOADED");

require_once SYSTEMCORE_PATH . 'includes/bootstrap.php';

if (class_exists('SystemCore_Bootstrap')) {
    SystemCore_Bootstrap::init();
} else {
    error_log("SystemCore ERROR: Bootstrap class not found.");
}
