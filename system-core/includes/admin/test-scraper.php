<?php
if (!defined('ABSPATH')) exit;

$result = null;

if (isset($_POST['test_url'])) {

    $url = esc_url_raw($_POST['test_url']);

    // Test scraper with the given URL
    $result = SystemCore_Scraper::fetch_full_article($url);
}
?>

<div class="wrap">
    <h1>SystemCore Scraper Test</h1>

    <form method="post" style="margin-top:20px;">
        <input type="text" name="test_url" placeholder="Paste article URL here" style="width:60%;padding:8px;">
        <button class="button button-primary">Test Scraper</button>
    </form>

    <?php if ($result !== null): ?>
        <hr><br>

        <?php if ($result['success']): ?>

            <h2>Extracted Title:</h2>
            <pre style="background:#f7f7f7;padding:10px;"><?php echo esc_html($result['title']); ?></pre>

            <!-- Meta Description -->
            <?php if (!empty($result['description'])): ?>
                <h2>Meta Description:</h2>
                <pre style="background:#f7f7f7;padding:10px;"><?php echo esc_html($result['description']); ?></pre>
            <?php endif; ?>

            <!-- OG Title -->
            <?php if (!empty($result['og_title'])): ?>
                <h2>OG Title:</h2>
                <pre style="background:#f7f7f7;padding:10px;"><?php echo esc_html($result['og_title']); ?></pre>
            <?php endif; ?>

            <!-- OG Description -->
            <?php if (!empty($result['og_description'])): ?>
                <h2>OG Description:</h2>
                <pre style="background:#f7f7f7;padding:10px;"><?php echo esc_html($result['og_description']); ?></pre>
            <?php endif; ?>

            <!-- Keywords -->
            <?php if (!empty($result['keywords'])): ?>
                <h2>Keywords:</h2>
                <pre style="background:#f7f7f7;padding:10px;"><?php echo esc_html($result['keywords']); ?></pre>
            <?php endif; ?>

            <!-- Canonical URL -->
            <?php if (!empty($result['canonical'])): ?>
                <h2>Canonical URL:</h2>
                <pre style="background:#f7f7f7;padding:10px;"><?php echo esc_url($result['canonical']); ?></pre>
            <?php endif; ?>

            <!-- Featured Image (NEW BLOCK) -->
            <?php if (!empty($result['image'])): ?>
                <h2>Featured Image:</h2>
                <div style="padding:10px;margin-bottom:20px;">
                    <img src="<?php echo esc_url($result['image']); ?>" style="max-width:300px;border:1px solid #ddd;">
                </div>
            <?php endif; ?>
            <!-- END IMAGE BLOCK -->

            <h2>Extracted Content:</h2>
            <textarea style="width:100%;height:350px;"><?php 
                echo esc_textarea($result['content']); 
            ?></textarea>

            <?php if (!empty($result['links'])) : ?>
                <h2>External Links:</h2>
                <div style="background:#fff;padding:15px;border:1px solid #ddd;margin-bottom:20px;">
                    <?php foreach ($result['links'] as $link) : ?>
                        <div><?php echo esc_url($link); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <h2>Error:</h2>
            <pre style="background:#ffefef;padding:10px;border:1px solid #cc0000;">
                <?php echo esc_html($result['error']); ?>
            </pre>

        <?php endif; ?>

    <?php endif; ?>
</div>
