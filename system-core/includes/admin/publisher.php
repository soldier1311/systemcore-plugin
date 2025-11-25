<?php if (!defined('ABSPATH')) exit;

$defaults = [
    'status'          => 'draft',
    'posts_per_batch' => 5,
    'category_id'     => 0,
];

$options = wp_parse_args(get_option('systemcore_publisher_settings', []), $defaults);

// حفظ الإعدادات
if (isset($_POST['systemcore_publisher_save']) && check_admin_referer('systemcore_publisher_settings')) {

    $options['status']          = in_array($_POST['status'], ['draft','pending','publish'], true) ? $_POST['status'] : 'draft';
    $options['posts_per_batch'] = max(1, (int) $_POST['posts_per_batch']);
    $options['category_id']     = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

    update_option('systemcore_publisher_settings', $options);

    echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
}

$categories = get_categories(['hide_empty' => false]);
?>

<div class="systemcore-wrap">
    <h1 class="systemcore-title">Publisher Settings</h1>

    <div class="systemcore-card">
        <form method="post">
            <?php wp_nonce_field('systemcore_publisher_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="status">Post Status</label></th>
                    <td>
                        <select name="status" id="status">
                            <option value="draft"   <?php selected($options['status'], 'draft'); ?>>Draft</option>
                            <option value="pending" <?php selected($options['status'], 'pending'); ?>>Pending Review</option>
                            <option value="publish" <?php selected($options['status'], 'publish'); ?>>Published</option>
                        </select>
                        <p class="description">للمرحلة الحالية يفضل أن يبقى على Draft.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="posts_per_batch">Posts per batch</label></th>
                    <td>
                        <input type="number" min="1" name="posts_per_batch" id="posts_per_batch"
                               value="<?php echo esc_attr($options['posts_per_batch']); ?>">
                        <p class="description">كم خبر يتم تحويله من الـ Queue إلى منشورات في كل دفعة (يدويًا أو عبر Cron).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="category_id">Target Category</label></th>
                    <td>
                        <select name="category_id" id="category_id">
                            <option value="0">— لا شيء (بدون تصنيف) —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat->term_id; ?>" <?php selected($options['category_id'], $cat->term_id); ?>>
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            من الأفضل إنشاء تصنيف خاص مثلاً: <strong>SystemCore News</strong> وربطه هنا،  
                            ثم استعماله في صفحة الأرشيف <code>/systemcore-news/</code>.
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="systemcore_publisher_save" class="button button-primary">Save Settings</button>
            </p>
        </form>
    </div>

    <div class="systemcore-card sc-mt-30">
        <h2 class="systemcore-card-title">Manual Batch Publish</h2>
        <p>يمكنك أيضاً إطلاق دفعة نشر الآن مباشرة من هنا:</p>
        <button class="button button-primary sc-btn-publish-now">Run Publish Batch Now</button>
        <div class="sc-notice-area"></div>
    </div>
</div>
