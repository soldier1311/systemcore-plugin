/**
 * 24Ounce Admin UI
 * Prices Auto Refresh + Trading Lock Toggle
 * FINAL / SAFE
 */

document.addEventListener('DOMContentLoaded', function () {

    if (typeof twentyfourounceAjax === 'undefined') {
        return;
    }

    /* =====================================================
       HELPERS (مضافة)
    ===================================================== */

    function setInputsLocked(locked) {
        document.querySelectorAll(
            'input[name^="buy["], input[name^="sell["]'
        ).forEach(el => {
            el.readOnly = locked;
        });
    }

    /* =====================================================
       PRICES AUTO REFRESH (READ ONLY)
    ===================================================== */

    const table = document.querySelector('.twentyfourounce-prices-table');
    if (table) {

        const tbody = table.querySelector('tbody');

        const refreshPrices = () => {

            fetch(twentyfourounceAjax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    action: '24ounce_get_prices',
                    _ajax_nonce: twentyfourounceAjax.nonce
                })
            })
            .then(response => response.json())
            .then(response => {

                if (!response || !response.success || !Array.isArray(response.data)) {
                    return;
                }

                response.data.forEach(row => {

                    const tr = tbody.querySelector(`tr[data-asset="${row.slug}"]`);
                    if (!tr) return;

                    const buyOld  = tr.querySelector('.price-buy-old');
                    const sellOld = tr.querySelector('.price-sell-old');

                    if (buyOld) {
                        buyOld.textContent = parseFloat(row.buy_price).toFixed(2);
                    }

                    if (sellOld) {
                        sellOld.textContent = parseFloat(row.sell_price).toFixed(2);
                    }

                });

            })
            .catch(() => {
                // silent fail – no UI break
            });
        };

        // refresh every 60 seconds
        setInterval(refreshPrices, 60000);
    }

    /* =====================================================
       TRADING LOCK TOGGLE
    ===================================================== */

    const toggleBtn  = document.getElementById('toggle-trading-btn');
    const statusBox = document.querySelector('.trading-status');
    const noticeBox = document.querySelector('.twentyfourounce-notices');

    if (!toggleBtn) {
        return;
    }

    toggleBtn.addEventListener('click', function () {

        toggleBtn.disabled = true;

        fetch(twentyfourounceAjax.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                action: '24ounce_toggle_trading',
                _ajax_nonce: twentyfourounceAjax.nonce
            })
        })
        .then(res => res.json())
        .then(res => {

            toggleBtn.disabled = false;

            if (!res || !res.success) {
                showNotice('فشل تغيير حالة التداول', 'error');
                return;
            }

            const locked = res.data.is_locked === 1;

            if (locked) {
                statusBox.textContent = 'التداول موقوف';
                statusBox.classList.remove('open');
                statusBox.classList.add('locked');
                toggleBtn.textContent = 'فتح التداول';
            } else {
                statusBox.textContent = 'التداول مفتوح';
                statusBox.classList.remove('locked');
                statusBox.classList.add('open');
                toggleBtn.textContent = 'إقفال التداول';
            }

            // ⭐ الإضافة المهمة
            setInputsLocked(locked);

            showNotice('تم تحديث حالة التداول بنجاح', 'success');
        })
        .catch(() => {
            toggleBtn.disabled = false;
            showNotice('خطأ في الاتصال بالخادم', 'error');
        });
    });

    /* =====================================================
       NOTICES
    ===================================================== */

    function showNotice(text, type) {

        if (!noticeBox) return;

        noticeBox.innerHTML =
            `<div class="twentyfourounce-notice ${type}">${text}</div>`;

        setTimeout(() => {
            noticeBox.innerHTML = '';
        }, 3000);
    }

});
