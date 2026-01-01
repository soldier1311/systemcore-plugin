<?php
/**
 * Admin Page – Prices Management (FINAL)
 */

if (!defined('ABSPATH')) exit;

function twentyfouounce_render_prices_page() {

    if (!current_user_can('manage_options')) return;

    if (!class_exists('TwentyFourOunce_Price_Engine')) {
        echo '<div class="notice notice-error"><p>Price engine not loaded.</p></div>';
        return;
    }

    $engine = new TwentyFourOunce_Price_Engine();

    /* =========================
       Assets
    ========================= */

    $gold_assets = [
        'gold_21k'     => ['label' => 'غرام 21'],
        'ounce_999_9'  => ['label' => 'أونصة 999.9'],
        'ounce_999_5'  => ['label' => 'أونصة 999.5'],
        'bar_100g'     => ['label' => 'سبيكة 100 غرام'],
        'coin'         => ['label' => 'ليرة'],
        'half_coin'    => ['label' => 'نصف ليرة'],
        'quarter_coin' => ['label' => 'ربع ليرة'],
    ];

    $silver_assets = [
        'silver_250g' => ['label' => 'فضة ربع كيلو'],
        'silver_500g' => ['label' => 'فضة نصف كيلو'],
        'silver_1kg'  => ['label' => 'فضة كيلو'],
    ];

    /* =========================
       Existing prices
    ========================= */

    $existing = [];
    foreach ($engine->get_all_prices() as $row) {
        $existing[$row['slug']] = $row;
    }

    /* =========================
       Table renderer
    ========================= */

    $render_table = function(array $assets) use ($existing) {
        ?>
        <table class="twentyfourounce-prices-table">
            <thead>
                <tr>
                    <th>الأصل</th>
                    <th>شراء حالي</th>
                    <th>شراء جديد</th>
                    <th>بيع حالي</th>
                    <th>بيع جديد</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($assets as $slug => $config):
                $buy  = $existing[$slug]['buy_price']  ?? null;
                $sell = $existing[$slug]['sell_price'] ?? null;
            ?>
                <tr>
                    <td class="asset-label"><strong><?php echo esc_html($config['label']); ?></strong></td>
                    <td><?php echo $buy !== null ? number_format((float)$buy, 2) : '—'; ?></td>
                    <td>
                        <input type="number"
                               class="price-input"
                               name="buy[<?php echo esc_attr($slug); ?>]"
                               step="0.01" min="0" readonly>
                    </td>
                    <td><?php echo $sell !== null ? number_format((float)$sell, 2) : '—'; ?></td>
                    <td>
                        <input type="number"
                               class="price-input"
                               name="sell[<?php echo esc_attr($slug); ?>]"
                               step="0.01" min="0" readonly>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    };
    ?>

    <div class="wrap twentyfourounce-ui">

        <!-- Action Buttons -->
        <div class="admin-actions">
            <button type="button"
                    id="toggle-edit-btn"
                    class="twentyfourounce-btn btn-edit">
                فتح التعديل
            </button>

            <button type="submit"
                    id="save-btn"
                    form="prices-form"
                    class="twentyfourounce-btn btn-save"
                    disabled>
                حفظ الأسعار
            </button>
        </div>

        <form id="prices-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="24ounce_save_prices">
            <?php wp_nonce_field('24ounce_save_all_action'); ?>

            <div class="twentyfourounce-panels">

                <div class="metal-panel">
                    <h2>أسعار الذهب</h2>
                    <?php $render_table($gold_assets); ?>
                </div>

                <div class="metal-panel">
                    <h2>أسعار الفضة</h2>
                    <?php $render_table($silver_assets); ?>
                </div>

            </div>
        </form>

    </div>

    <script>
        jQuery(function ($) {

            let editOpen = false;

            $('#toggle-edit-btn').on('click', function () {

                editOpen = !editOpen;

                $('.price-input').prop('readonly', !editOpen);
                $('#save-btn').prop('disabled', !editOpen);

                $(this)
                    .text(editOpen ? 'إقفال التعديل' : 'فتح التعديل')
                    .toggleClass('btn-edit btn-save');
            });

            $('#prices-form').on('submit', function () {

                $('.price-input').prop('readonly', true);
                $('#save-btn').prop('disabled', true);

                $('#toggle-edit-btn')
                    .text('فتح التعديل')
                    .removeClass('btn-save')
                    .addClass('btn-edit');
            });

        });
    </script>

<?php
}
