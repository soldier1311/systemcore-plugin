<?php
if (!defined('ABSPATH')) exit;

class SystemCore_AI_Category {

    public static function select($title, $content, array $categories, $lang = 'ar') {

        if (empty($categories)) {
            return 0;
        }

        $list = '';
        foreach ($categories as $cat) {
            if (!empty($cat['id']) && !empty($cat['name'])) {
                $list .= $cat['id'] . ' - ' . $cat['name'] . "\n";
            }
        }

        $prompt = "
Choose the single most suitable category ID for this article.
Return only the category ID with no extra text.

Categories:
{$list}

Title:
{$title}

Content:
{$content}
        ";

        $response = SystemCore_AI_Engine::ask($prompt, 'category', ['language' => $lang]);

        if (
            !is_array($response) ||
            empty($response['success']) ||
            empty($response['text'])
        ) {
            return 0;
        }

        $text = trim($response['text']);

        if (preg_match('/\b(\d+)\b/', $text, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    public static function select_safe($title, $content, array $categories, $lang = 'ar') {

        $id = self::select($title, $content, $categories, $lang);

        if (!$id && !empty($categories)) {
            return (int) $categories[0]['id'];
        }

        return $id;
    }
}
