<?php
if (!defined('ABSPATH')) exit;

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_url'])) {

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'systemcore_scraper_test')) {
        $result = ['success' => false, 'error' => 'Security check failed (nonce).'];
    } else {
        $url = esc_url_raw(wp_unslash($_POST['test_url']));
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $result = ['success' => false, 'error' => 'Invalid URL.'];
        } elseif (!class_exists('SystemCore_Scraper') || !method_exists('SystemCore_Scraper', 'fetch_full_article')) {
            $result = ['success' => false, 'error' => 'Scraper engine not loaded.'];
        } else {
            $result = SystemCore_Scraper::fetch_full_article($url);
            if (!is_array($result)) {
                $result = ['success' => false, 'error' => 'Unexpected scraper response.'];
            }
        }
    }
}

// helpers
$val = function($key, $default = '') use ($result) {
    return (is_array($result) && array_key_exists($key, $result)) ? $result[$key] : $default;
};

$ok = (is_array($result) && !empty($result['success']));
?>
<div class="wrap systemcore-ui">
    <div class="sc-page-header">
        <div>
            <h1 class="sc-title">SystemCore Scraper Test</h1>
            <p class="sc-subtitle">Test extraction (title, meta, image, content) using the SystemCore scraper engine.</p>
        </div>
    </div>

    <div class="sc-grid sc-grid-2" style="margin-top:14px;align-items:start;">

        <!-- LEFT: FORM -->
        <div class="sc-card">
            <div class="sc-card-header">
                <h2 class="sc-card-title">Input</h2>
                <div class="sc-card-desc">Paste an article URL, then run extraction.</div>
            </div>

            <div class="sc-card-body">
                <form method="post" class="sc-form">
                    <?php wp_nonce_field('systemcore_scraper_test'); ?>

                    <div class="sc-field">
                        <label class="sc-label" for="sc_test_url">Article URL</label>
                        <input
                            id="sc_test_url"
                            class="sc-input"
                            type="url"
                            name="test_url"
                            placeholder="https://example.com/article"
                            value="<?php echo isset($_POST['test_url']) ? esc_attr(wp_unslash($_POST['test_url'])) : ''; ?>"
                            required
                        >
                        <div class="sc-help">Supports most news sites. If content is blocked, try another URL.</div>
                    </div>

                    <div class="sc-actions">
                        <button type="submit" class="sc-btn sc-btn-primary">Test Scraper</button>
                    </div>
                </form>

                <?php if ($result !== null && !$ok): ?>
                    <div class="sc-alert sc-alert-danger" style="margin-top:14px;">
                        <div class="sc-alert-title">Error</div>
                        <div class="sc-alert-text"><?php echo esc_html($val('error', 'Unknown error.')); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($result !== null && $ok): ?>
                    <div class="sc-alert sc-alert-success" style="margin-top:14px;">
                        <div class="sc-alert-title">Success</div>
                        <div class="sc-alert-text">Extraction completed successfully.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: SUMMARY -->
        <div class="sc-card">
            <div class="sc-card-header">
                <h2 class="sc-card-title">Summary</h2>
                <div class="sc-card-desc">Key metadata detected by the scraper.</div>
            </div>

            <div class="sc-card-body">
                <?php if ($result === null): ?>
                    <div class="sc-empty">
                        <div class="sc-empty-title">No results yet</div>
                        <div class="sc-empty-text">Run the test to see extracted metadata and content.</div>
                    </div>
                <?php else: ?>

                    <?php if ($ok): ?>
                        <div class="sc-kv">
                            <div class="sc-kv-row">
                                <div class="sc-kv-key">Title</div>
                                <div class="sc-kv-val"><span class="sc-inline-code"><?php echo esc_html($val('title', '—')); ?></span></div>
                            </div>

                            <?php if (!empty($val('description'))): ?>
                                <div class="sc-kv-row">
                                    <div class="sc-kv-key">Meta Description</div>
                                    <div class="sc-kv-val"><span class="sc-inline-code"><?php echo esc_html($val('description')); ?></span></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($val('og_title'))): ?>
                                <div class="sc-kv-row">
                                    <div class="sc-kv-key">OG Title</div>
                                    <div class="sc-kv-val"><span class="sc-inline-code"><?php echo esc_html($val('og_title')); ?></span></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($val('og_description'))): ?>
                                <div class="sc-kv-row">
                                    <div class="sc-kv-key">OG Description</div>
                                    <div class="sc-kv-val"><span class="sc-inline-code"><?php echo esc_html($val('og_description')); ?></span></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($val('keywords'))): ?>
                                <div class="sc-kv-row">
                                    <div class="sc-kv-key">Keywords</div>
                                    <div class="sc-kv-val"><span class="sc-inline-code"><?php echo esc_html($val('keywords')); ?></span></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($val('canonical'))): ?>
                                <div class="sc-kv-row">
                                    <div class="sc-kv-key">Canonical</div>
                                    <div class="sc-kv-val">
                                        <a class="sc-link" href="<?php echo esc_url($val('canonical')); ?>" target="_blank" rel="noopener">
                                            <?php echo esc_html($val('canonical')); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($val('image'))): ?>
                                <div class="sc-kv-row" style="align-items:start;">
                                    <div class="sc-kv-key">Featured Image</div>
                                    <div class="sc-kv-val">
                                        <div class="sc-media">
                                            <img
                                                src="<?php echo esc_url($val('image')); ?>"
                                                alt="Featured image"
                                                class="sc-media-img"
                                                style="width:160px;height:160px;object-fit:cover;border-radius:12px;"
                                            >
                                            <div class="sc-media-meta">
                                                <div class="sc-muted">Square preview</div>
                                                <a class="sc-link" href="<?php echo esc_url($val('image')); ?>" target="_blank" rel="noopener">
                                                    Open image
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="sc-empty">
                            <div class="sc-empty-title">No metadata</div>
                            <div class="sc-empty-text">Fix the error on the left and try again.</div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- CONTENT -->
    <div class="sc-card" style="margin-top:14px;">
        <div class="sc-card-header">
            <h2 class="sc-card-title">Extracted Content</h2>
            <div class="sc-card-desc">Raw extracted HTML/text (as returned by the scraper).</div>
        </div>

        <div class="sc-card-body">
            <?php if ($result === null): ?>
                <div class="sc-empty">
                    <div class="sc-empty-title">No content yet</div>
                    <div class="sc-empty-text">Run the test to see extracted content here.</div>
                </div>
            <?php elseif ($ok): ?>
                <textarea class="sc-textarea" style="height:360px;" readonly><?php echo esc_textarea($val('content', '')); ?></textarea>
            <?php else: ?>
                <textarea class="sc-textarea" style="height:180px;" readonly><?php echo esc_textarea($val('error', '')); ?></textarea>
            <?php endif; ?>
        </div>
    </div>

    <!-- LINKS -->
    <div class="sc-card" style="margin-top:14px;">
        <div class="sc-card-header">
            <h2 class="sc-card-title">External Links</h2>
            <div class="sc-card-desc">Links found during extraction.</div>
        </div>

        <div class="sc-card-body">
            <?php
            $links = $val('links', []);
            if (!is_array($links)) $links = [];
            ?>
            <?php if ($result === null): ?>
                <div class="sc-empty">
                    <div class="sc-empty-title">No links yet</div>
                    <div class="sc-empty-text">Run the test to list extracted links.</div>
                </div>
            <?php elseif (!$ok): ?>
                <div class="sc-empty">
                    <div class="sc-empty-title">No links available</div>
                    <div class="sc-empty-text">Extraction did not complete successfully.</div>
                </div>
            <?php elseif (empty($links)): ?>
                <div class="sc-empty">
                    <div class="sc-empty-title">No external links</div>
                    <div class="sc-empty-text">The scraper did not return any link list.</div>
                </div>
            <?php else: ?>
                <div class="sc-list">
                    <?php foreach ($links as $link): ?>
                        <div class="sc-list-row">
                            <a class="sc-link" href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($link); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
