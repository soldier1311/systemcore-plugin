<?php

if (!defined('ABSPATH')) exit;

class SystemCore_HttpClient {

    /**
     * User-Agent pool.
     */
    protected static function ua_list() {
        return [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/123 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36',
            'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 Chrome/124 Mobile Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1 Safari/604.1',
        ];
    }

    protected static function random_ua() {
        $list = self::ua_list();
        return $list[array_rand($list)];
    }

    /**
     * Fetch remote HTML.
     */
    public static function fetch($url) {

        $base_args = [
            'timeout'     => 25,
            'redirection' => 10,
            'decompress'  => true,
            'sslverify'   => true,
            'headers'     => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9,de;q=0.8,fr;q=0.8,ar;q=0.8',
                'Cache-Control'   => 'no-cache',
                'Connection'      => 'keep-alive',
            ],
        ];

        /* -------------------------------------------------------------
         * ATTEMPT 1 — direct request
         * ------------------------------------------------------------- */
        $args1 = $base_args;
        $args1['headers']['User-Agent'] = self::random_ua();
        $args1['headers']['Referer']    = $url;

        $res1 = wp_remote_get($url, $args1);

        if (!is_wp_error($res1)) {

            $code = wp_remote_retrieve_response_code($res1);

            if ($code >= 200 && $code < 400) {
                $html = wp_remote_retrieve_body($res1);

                // Fix encoding if needed
                if (!mb_detect_encoding($html, 'UTF-8', true)) {
                    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
                }

                return [
                    'ok'    => true,
                    'html'  => $html,
                    'error' => '',
                    'code'  => $code,
                    'raw'   => $res1,
                ];
            }
        }

        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::warning("HTTP retry triggered: $url", 'scraper');
        }

        /* -------------------------------------------------------------
         * ATTEMPT 2 — stronger fallback
         * ------------------------------------------------------------- */
        $args2 = $base_args;
        $args2['timeout']   = 35;
        $args2['sslverify'] = false;
        $args2['headers']['User-Agent'] = self::random_ua();
        $args2['headers']['Referer']    = 'https://www.google.com/';

        $res2 = wp_remote_get($url, $args2);

        if (!is_wp_error($res2)) {

            $code2 = wp_remote_retrieve_response_code($res2);

            if ($code2 >= 200 && $code2 < 400) {
                $html2 = wp_remote_retrieve_body($res2);

                if (!mb_detect_encoding($html2, 'UTF-8', true)) {
                    $html2 = mb_convert_encoding($html2, 'HTML-ENTITIES', 'UTF-8');
                }

                return [
                    'ok'    => true,
                    'html'  => $html2,
                    'error' => '',
                    'code'  => $code2,
                    'raw'   => $res2,
                ];
            }
        }

        /* -------------------------------------------------------------
         * FAIL
         * ------------------------------------------------------------- */
        $err = '';

        if (is_wp_error($res2)) {
            $err = $res2->get_error_message();
        } elseif (is_wp_error($res1)) {
            $err = $res1->get_error_message();
        } else {
            $err = 'HTTP error';
        }

        return [
            'ok'    => false,
            'html'  => '',
            'error' => $err,
            'code'  => 0,
            'raw'   => isset($res2) ? $res2 : $res1,
        ];
    }
}
