<?php
if (!defined('ABSPATH')) exit;

class SystemCore_Feed_Processors {

    /* ============================================================
     * RSS FEED
     * ============================================================ */
    public static function process_rss_feed($feed, $lang, $queue_table) {

        if (!function_exists('fetch_feed')) {
            require_once ABSPATH . WPINC . '/feed.php';
        }

        $feed_id   = (int) $feed->id;
        $feed_name = trim((string) $feed->source_name);
        $feed_url  = esc_url_raw((string) $feed->feed_url);

        $rss = fetch_feed($feed_url);

        if (is_wp_error($rss)) {
            SystemCore_Feed_Helpers::insert_fetch_log(
                $feed_id,
                $feed_name,
                $feed_url,
                'rss',
                'error',
                $rss->get_error_message()
            );
            return;
        }

        $maxitems = (int) $rss->get_item_quantity(10);

        SystemCore_Feed_Helpers::insert_fetch_log(
            $feed_id,
            $feed_name,
            $feed_url,
            'rss',
            'success',
            'Fetched items: ' . $maxitems
        );

        if ($maxitems <= 0) {
            return;
        }

        $items = $rss->get_items(0, $maxitems);

        foreach ($items as $item) {

            $title = trim(wp_strip_all_tags((string) $item->get_title()));
            $link  = esc_url_raw((string) $item->get_permalink());
            if (empty($link)) {
                continue;
            }

            $priority = 0;
            if (class_exists('SystemCore_Priority_Engine')) {

                $item_data = [
                    'title'   => $title,
                    'pubDate' => $item->get_date('Y-m-d H:i:s'),
                ];

                $score = SystemCore_Priority_Engine::calculate_priority($feed, $item_data, $lang);

                if (SystemCore_Priority_Engine::should_reject($score)) {
                    continue;
                }

                if ($score > 0) {
                    $priority = (int) $score;
                }
            }

            SystemCore_Feed_Queue_Handler::push_to_queue(
                $feed_id,
                $feed_name,
                $link,
                $title,
                $lang,
                $queue_table,
                $priority
            );
        }
    }

    /* ============================================================
     * XML FEED
     * ============================================================ */
    public static function process_xml_feed($feed, $lang, $queue_table) {

        $feed_id   = (int) $feed->id;
        $feed_name = trim((string) $feed->source_name);
        $feed_url  = esc_url_raw((string) $feed->feed_url);

        $body = SystemCore_Feed_Helpers::fetch_remote_body($feed_url);
        if ($body === false) {
            SystemCore_Feed_Helpers::insert_fetch_log(
                $feed_id,
                $feed_name,
                $feed_url,
                'xml',
                'error',
                'Failed to fetch XML body'
            );
            return;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);

        if (!$xml) {
            SystemCore_Feed_Helpers::insert_fetch_log(
                $feed_id,
                $feed_name,
                $feed_url,
                'xml',
                'error',
                'Invalid XML'
            );
            return;
        }

        SystemCore_Feed_Helpers::insert_fetch_log(
            $feed_id,
            $feed_name,
            $feed_url,
            'xml',
            'success',
            'XML parsed'
        );

        $items = [];
        if (!empty($xml->channel->item)) {
            $items = $xml->channel->item;
        } elseif (!empty($xml->entry)) {
            $items = $xml->entry;
        } elseif (!empty($xml->item)) {
            $items = $xml->item;
        }

        if (empty($items)) {
            return;
        }

        $count = 0;
        foreach ($items as $item) {

            $title = !empty($item->title) ? trim((string) $item->title) : '';
            $link  = '';

            if (!empty($item->link)) {
                if (isset($item->link['href'])) {
                    $link = (string) $item->link['href'];
                } else {
                    $link = (string) $item->link;
                }
            }

            $link = esc_url_raw(trim($link));
            if (empty($link)) {
                continue;
            }

            $priority = 0;
            if (class_exists('SystemCore_Priority_Engine')) {

                $item_data = [
                    'title'   => $title,
                    'pubDate' => !empty($item->pubDate) ? (string) $item->pubDate : '',
                ];

                $score = SystemCore_Priority_Engine::calculate_priority($feed, $item_data, $lang);

                if (SystemCore_Priority_Engine::should_reject($score)) {
                    continue;
                }

                if ($score > 0) {
                    $priority = (int) $score;
                }
            }

            SystemCore_Feed_Queue_Handler::push_to_queue(
                $feed_id,
                $feed_name,
                $link,
                $title,
                $lang,
                $queue_table,
                $priority
            );

            $count++;
            if ($count >= 10) {
                break;
            }
        }
    }

    /* ============================================================
     * JSON FEED
     * ============================================================ */
    public static function process_json_feed($feed, $lang, $queue_table) {

        $feed_id   = (int) $feed->id;
        $feed_name = trim((string) $feed->source_name);
        $feed_url  = esc_url_raw((string) $feed->feed_url);

        $body = SystemCore_Feed_Helpers::fetch_remote_body($feed_url);
        if ($body === false) {
            SystemCore_Feed_Helpers::insert_fetch_log(
                $feed_id,
                $feed_name,
                $feed_url,
                'json',
                'error',
                'Failed to fetch JSON body'
            );
            return;
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            SystemCore_Feed_Helpers::insert_fetch_log(
                $feed_id,
                $feed_name,
                $feed_url,
                'json',
                'error',
                'Invalid JSON'
            );
            return;
        }

        SystemCore_Feed_Helpers::insert_fetch_log(
            $feed_id,
            $feed_name,
            $feed_url,
            'json',
            'success',
            'JSON parsed'
        );

        $items = [];
        if (!empty($feed->json_path)) {
            $items = SystemCore_Feed_Helpers::get_by_path(
                $json,
                trim((string) $feed->json_path)
            );
        } else {
            if (!empty($json['items']) && is_array($json['items'])) {
                $items = $json['items'];
            } elseif (!empty($json['data']) && is_array($json['data'])) {
                $items = $json['data'];
            } else {
                $items = $json;
            }
        }

        if (!is_array($items)) {
            return;
        }

        $count = 0;
        foreach ($items as $item) {

            if (!is_array($item)) {
                continue;
            }

            $title = '';
            $link  = '';

            $title_key = !empty($feed->json_title_key)
                ? (string) $feed->json_title_key
                : 'title';

            $link_key  = !empty($feed->json_link_key)
                ? (string) $feed->json_link_key
                : 'link';

            if (isset($item[$title_key])) {
                $title = trim((string) $item[$title_key]);
            }
            if (isset($item[$link_key])) {
                $link  = trim((string) $item[$link_key]);
            }

            $link = esc_url_raw($link);
            if (empty($link)) {
                continue;
            }

            $priority = 0;
            if (class_exists('SystemCore_Priority_Engine')) {

                $item_data = [
                    'title'   => $title,
                    'pubDate' => !empty($item['pubDate']) ? (string) $item['pubDate'] : '',
                ];

                $score = SystemCore_Priority_Engine::calculate_priority($feed, $item_data, $lang);

                if (SystemCore_Priority_Engine::should_reject($score)) {
                    continue;
                }

                if ($score > 0) {
                    $priority = (int) $score;
                }
            }

            SystemCore_Feed_Queue_Handler::push_to_queue(
                $feed_id,
                $feed_name,
                $link,
                $title,
                $lang,
                $queue_table,
                $priority
            );

            $count++;
            if ($count >= 10) {
                break;
            }
        }
    }
}
