<?php

if (!defined('ABSPATH')) exit;

class SystemCore_QualityGate {

    public static function validate($clean_text, $meta, $url) {

        // حاليًا لا نضيف أي شروط إضافية حتى لا يتغير السلوك
        // يمكن لاحقًا إضافة:
        // - منع المقالات القصيرة
        // - منع المحتوى الترويجي
        // - منع التكرار
        // إلخ...

        return [
            'reject' => false,
            'reason' => '',
        ];
    }
}
