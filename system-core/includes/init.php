<?php
if (!defined('ABSPATH')) exit;

// Core modules
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/scheduler.php';
require_once __DIR__ . '/scraper.php';
require_once __DIR__ . '/ai-engine.php';
require_once __DIR__ . '/publisher.php';
require_once __DIR__ . '/queue.php';
require_once __DIR__ . '/class-systemcore-feed-loader.php';

// Publisher settings functions
require_once __DIR__ . '/publisher-functions.php';

// Admin menu + pages
require_once __DIR__ . '/admin/index.php';

// AJAX handlers
require_once __DIR__ . '/ajax/ajax-dashboard.php';
require_once __DIR__ . '/ajax/ajax-fetch.php';
require_once __DIR__ . '/ajax/ajax-queue.php';
require_once __DIR__ . '/ajax/ajax-logs.php';
require_once __DIR__ . '/ajax/ajax-publisher.php';
require_once __DIR__ . '/ajax/ajax-feed-sources.php';
