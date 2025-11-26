<?php

if (!defined('ABSPATH')) exit;

class SystemCore_HttpClient {

    protected static function ua_list() {
        return [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/122 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/121 Safari/537.36',
            'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:123.0) Gecko/20100101 Firefox/123.0',
            'Mozilla/5.0 (Linux; Android 14; Pixel 7 Pro) AppleWebKit/537.36 Chrome/121 Mobile Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1 Safari/604.1',
        ];
    }

    protected static function random_ua() {
        $list = self::ua_list();
        return $list[array_rand($list)];
    }

    public static function fetch($url) {

        $base = [
            'timeout'     => 25,
            'redirection' => 10,
            'decompress'  => true,
            'headers'     => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9,ar;q=0.8,de;q=0.8,fr;q=0.8',
                'Connection'      => 'keep-alive',
            ],
        ];

        // المحاولة الأولى
        $args1 = $base;
        $args1['headers']['User-Agent'] = self::random_ua();
        $args1['headers']['Referer']    = $url;

        $res = wp_remote_get($url, $args1);

        if (!is_wp_error($res)) {
            $code = wp_remote_retrieve_response_code($res);
            if ($code >= 200 && $code < 400) {
                $html = wp_remote_retrieve_body($res);

                if (!mb_detect_encoding($html, 'UTF-8', true)) {
                    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
                }

                return [
                    'ok'     => true,
                    'html'   => $html,
                    'error'  => '',
                    'code'   => $code,
                    'raw'    => $res,
                ];
            }
        }

        // المحاولة الثانية
        if (class_exists('SystemCore_Logger')) {
            SystemCore_Logger::warning("Scraper HTTP retry for URL: $url", 'scraper');
        }

        $args2 = $base;
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
                    'ok'     => true,
                    'html'   => $html2,
                    'error'  => '',
                    'code'   => $code2,
                    'raw'    => $res2,
                ];
            }
        }

        $err_msg = is_wp_error($res2 ?? null)
            ? $res2->get_error_message()
            : 'HTTP error';

        return [
            'ok'     => false,
            'html'   => '',
            'error'  => $err_msg,
            'code'   => 0,
            'raw'    => isset($res2) ? $res2 : null,
        ];
    }
}
