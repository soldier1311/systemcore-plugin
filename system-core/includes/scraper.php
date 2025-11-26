<?php
/**
 * SystemCore Scraper — Modular Loader
 */

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;

if (!defined('ABSPATH')) exit;

// تحميل مكتبة Readability
if (!class_exists(Readability::class)) {
    if (defined('SYSTEMCORE_PATH')) {
        require_once SYSTEMCORE_PATH . 'includes/readability/vendor/autoload.php';
    }
}

// تحميل الوحدات الجديدة
require_once __DIR__ . '/scraper/HttpClient.php';
require_once __DIR__ . '/scraper/ContentExtractor.php';
require_once __DIR__ . '/scraper/ContentCleaner.php';
require_once __DIR__ . '/scraper/MetadataParser.php';
require_once __DIR__ . '/scraper/QualityGate.php';
require_once __DIR__ . '/scraper/Scraper.php';
