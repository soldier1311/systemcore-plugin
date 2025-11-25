<?php

if (!defined('ABSPATH')) exit;

class SystemCore_MetadataParser {

    public static function parse($html, $clean_html, $fallback_title, $url) {

        $dom3 = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom3->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $img = $desc = $og_title = $og_desc = $canonical = $keywords = '';

        foreach ($dom3->getElementsByTagName('meta') as $m) {

            $name = strtolower($m->getAttribute('name'));
            $prop = strtolower($m->getAttribute('property'));
            $c    = $m->getAttribute('content');

            if ($prop === 'og:image' && !$img)           $img       = $c;
            if ($name === 'description' && !$desc)       $desc      = $c;
            if ($prop === 'og:title' && !$og_title)      $og_title  = $c;
            if ($prop === 'og:description' && !$og_desc) $og_desc   = $c;
            if ($name === 'keywords' && !$keywords)      $keywords  = $c;
        }

        foreach ($dom3->getElementsByTagName('link') as $l) {
            if (strtolower($l->getAttribute('rel')) === 'canonical') {
                $canonical = $l->getAttribute('href');
            }
        }

        $title = $fallback_title;

        if (!$title) {
            $t = $dom3->getElementsByTagName('title');
            if ($t->length > 0) {
                $title = $t->item(0)->textContent;
            } elseif ($og_title) {
                $title = $og_title;
            }
        }

        // استخراج الروابط الخارجية من المحتوى النظيف
        $links = [];
        $dom2 = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom2->loadHTML('<?xml encoding="utf-8" ?>' . $clean_html);
        libxml_clear_errors();

        $anchors = $dom2->getElementsByTagName('a');
        $host    = parse_url($url, PHP_URL_HOST);

        foreach ($anchors as $a) {
            $href = $a->getAttribute('href');
            if (!$href) continue;
            if (!filter_var($href, FILTER_VALIDATE_URL)) continue;

            $link_host = parse_url($href, PHP_URL_HOST);
            if ($link_host && $link_host !== $host) {
                $links[] = $href;
            }
        }
        $links = array_values(array_unique($links));

        return [
            'title'         => $title,
            'image'         => $img,
            'description'   => $desc,
            'og_title'      => $og_title,
            'og_description'=> $og_desc,
            'canonical'     => $canonical,
            'keywords'      => $keywords,
            'links'         => $links,
        ];
    }
}
