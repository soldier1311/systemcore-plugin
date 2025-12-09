<?php
if (!defined('ABSPATH')) exit;

/**
 * SystemCore Priority Engine — Final Version (3-level system)
 * Priority Levels:
 * 0 = Main Source  (all languages)
 * 1 = High Source  (all languages)
 * 2 = Arabic Only  (reject any other language)
 */

class SystemCore_Priority_Engine {

    /**
     * Main calculation entry
     */
    public static function calculate_priority($feed, $item, $lang = 'ar') {

        // Early reject by language
        if (self::reject_by_language($feed, $lang)) {
            return 'REJECT_LANGUAGE';
        }

        $feed_priority   = self::score_feed_priority($feed);
        $item_priority   = self::score_item($item);
        $freshness_score = self::score_freshness($item);
        $lang_bias       = self::score_language_bias($feed, $lang);

        return (int) round(
            $feed_priority +
            $item_priority +
            $freshness_score +
            $lang_bias
        );
    }


    /* ============================================================
     * LANGUAGE CONTROL
     * ============================================================ */

    /**
     * Reject immediately by language rules
     * Priority 0 → all languages
     * Priority 1 → all languages
     * Priority 2 → Arabic only
     */
    private static function reject_by_language($feed, $lang) {

        $level = isset($feed->priority_level) ? (int)$feed->priority_level : 2;

        // Main source + High → allow all languages
        if ($level === 0 || $level === 1) {
            return false;
        }

        // Priority 2 → only Arabic allowed
        if ($level === 2 && $lang !== 'ar') {
            return true;
        }

        return false;
    }


    /* ============================================================
     * FEED PRIORITY
     * ============================================================ */

    private static function score_feed_priority($feed) {

        $level = isset($feed->priority_level) ? (int)$feed->priority_level : 2;

        switch ($level) {
            case 0: return 100; // strongest source
            case 1: return 60;  // high importance
            case 2: return 20;  // arabic-only
        }

        return 10;
    }


    /* ============================================================
     * LANGUAGE BIAS
     * ============================================================ */

    private static function score_language_bias($feed, $lang) {

        $level = isset($feed->priority_level) ? (int)$feed->priority_level : 2;

        // 0 + 1 → all languages allowed, bias gently
        if ($level === 0 || $level === 1) {
            if ($lang === 'ar') return 8;
            return 5;
        }

        // 2 → arabic only (others rejected earlier)
        if ($level === 2 && $lang === 'ar') {
            return 8;
        }

        return 0;
    }


    /* ============================================================
     * ITEM SCORING
     * ============================================================ */
    private static function score_item($item) {

        if (!isset($item['title'])) return -5;

        $title = trim(strtolower($item['title']));
        $len   = strlen($title);
        $score = 0;

        // keyword boosts
        if (strpos($title, 'breaking') !== false) $score += 10;
        if (strpos($title, 'leak')     !== false) $score += 6;
        if (strpos($title, 'review')   !== false) $score += 4;

        // title length
        if ($len < 10)  $score -= 8;
        if ($len > 140) $score -= 3;

        return $score;
    }


    /* ============================================================
     * FRESHNESS
     * ============================================================ */
    private static function score_freshness($item) {

        if (empty($item['pubDate'])) return 0;

        $ts = strtotime($item['pubDate']);
        if (!$ts) return 0;

        $age = (time() - $ts) / 3600; // hours

        if ($age <= 1)  return 20;
        if ($age <= 6)  return 12;
        if ($age <= 24) return 6;
        if ($age <= 72) return 0;
        if ($age <= 168) return -8;

        // older than 7 days = dead content
        return -100;
    }


    /* ============================================================
     * REJECTION RULE
     * ============================================================ */
    public static function should_reject($score) {
        if ($score === 'REJECT_LANGUAGE') return true;
        if (!is_numeric($score)) return true;
        return ($score <= -50);
    }


    /* ============================================================
     * QUEUE LIMIT CONTROL
     * ============================================================ */
    public static function enforce_queue_limits() {
        global $wpdb;

        $table = $wpdb->prefix . 'systemcore_queue';

        // remove rejected or invalid
        $wpdb->query("DELETE FROM {$table} WHERE priority <= -50");

        // limit max queue
        $max = 300;
        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($count > $max) {
            $wpdb->query("
                DELETE FROM {$table}
                ORDER BY priority ASC, id DESC
                LIMIT " . ($count - $max)
            );
        }
    }
}
