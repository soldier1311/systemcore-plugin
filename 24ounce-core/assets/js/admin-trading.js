jQuery(function ($) {

    const btn = $('#toggle-trading-btn');
    if (!btn.length) return;

    btn.on('click', function () {

        btn.prop('disabled', true);

        $.post(TwentyFourOunce.ajax_url, {
            action: '24ounce_toggle_trading',
            _ajax_nonce: TwentyFourOunce.nonce
        })
        .done(function () {
            location.reload();
        })
        .fail(function () {
            alert('خطأ أثناء تغيير حالة التداول');
            btn.prop('disabled', false);
        });

    });

});
