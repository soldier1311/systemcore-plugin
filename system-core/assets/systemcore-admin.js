jQuery(function ($) {

    /* ============================================================
     *  Core AJAX Handler
     * ============================================================ */
    function scAjax(action, data, onSuccess, onError) {
        data = data || {};
        data.action = action;
        data.nonce = SystemCoreAdmin.nonce;

        $.post(SystemCoreAdmin.ajax_url, data)
            .done(function (resp) {
                if (resp && resp.success) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(resp.data);
                    }
                } else {
                    if (typeof onError === 'function') {
                        onError(resp.data || resp);
                    }
                }
            })
            .fail(function (err) {
                if (typeof onError === 'function') {
                    onError(err);
                }
            });
    }

    /* ============================================================
     *  Notice Helper
     * ============================================================ */
    function scShowNotice($container, type, message) {
        if (!$container.length) return;

        var cls  = 'notice notice-' + type + ' is-dismissible';
        var html = '<div class="' + cls + '" style="margin-top:10px;"><p>' + message + '</p></div>';

        // سلوكك الأصلي كان يستخدم html() وليس append
        $container.html(html);
    }

    /* ============================================================
     *  Dashboard Stats Auto-Refresh
     * ============================================================ */
    function scLoadDashboardStats() {
        if (!$('[data-sc="total_queue"]').length) return;

        scAjax('systemcore_dashboard_stats', {}, function (data) {
            $('[data-sc="total_queue"]').text(data.total_queue);
            $('[data-sc="pending_queue"]').text(data.pending_queue);
            $('[data-sc="processed_queue"]').text(data.processed_queue);
            $('[data-sc="total_logs"]').text(data.total_logs);
            $('[data-sc="last_fetch"]').text(data.last_fetch);
            $('[data-sc="last_publish"]').text(data.last_publish);
        });
    }

    scLoadDashboardStats();

    /* ============================================================
     *  Fetch Now
     * ============================================================ */
    $(document).on('click', '.sc-btn-fetch-now', function (e) {
        e.preventDefault();

        var $wrap = $(this).closest('.systemcore-card').find('.sc-notice-area');

        scShowNotice($wrap, 'info', 'Running fetch...');

        scAjax('systemcore_fetch_now', {}, function (data) {

            scShowNotice($wrap, 'success', data.message || 'Fetch completed.');

            scLoadDashboardStats();

            setTimeout(function () {
                location.reload();
            }, 1000);

        }, function () {
            scShowNotice($wrap, 'error', 'Failed to run fetch.');
        });
    });

    /* ============================================================
     *  Publish Batch
     * ============================================================ */
    $(document).on('click', '.sc-btn-publish-now', function (e) {
        e.preventDefault();

        var $wrap = $(this).closest('.systemcore-card, .systemcore-wrap').find('.sc-notice-area').first();

        scShowNotice($wrap, 'info', 'Publishing batch...');

        scAjax('systemcore_manual_publish', {}, function (data) {

            scShowNotice($wrap, 'success', data.message || 'Publish batch completed.');

            scLoadDashboardStats();

            setTimeout(function () {
                location.reload();
            }, 1000);

        }, function (err) {

            var msg = (err && err.message) ? err.message : 'Publisher not implemented yet.';
            scShowNotice($wrap, 'error', msg);
        });

    });

    /* ============================================================
     *  Queue View
     * ============================================================ */
    function scRenderPagination($container, page, totalPages) {
        $container.empty();
        if (totalPages <= 1) return;

        for (var p = 1; p <= totalPages; p++) {
            var $btn = $('<button type="button" class="button button-secondary"></button>')
                .text(p)
                .data('page', p);

            if (p === page) {
                $btn.addClass('button-primary');
            }

            $container.append($btn);
        }
    }

    function scLoadQueue(page) {
        var $body = $('#sc-queue-table tbody');
        if (!$body.length) return;

        page = page || 1;
        var search = $('#sc-queue-search').val() || '';

        $body.html('<tr><td colspan="6">Loading...</td></tr>');

        scAjax('systemcore_queue_list', { page: page, search: search }, function (data) {

            $body.empty();

            if (!data.items || !data.items.length) {
                $body.html('<tr><td colspan="6">No items found.</td></tr>');
                $('#sc-queue-pagination').empty();
                return;
            }

            $.each(data.items, function (_, item) {
                var status = item.processed == 1 ? 'Processed' : 'Pending';

                var $row = $('<tr></tr>');
                $row.append('<td>' + item.id + '</td>');
                $row.append('<td>' + item.source + '</td>');
                $row.append('<td><a href="' + item.url + '" target="_blank">' + item.title + '</a></td>');
                $row.append('<td>' + (item.published_at || '') + '</td>');
                $row.append('<td>' + status + '</td>');

                var $actions = $('<td></td>');
                $actions.append(
                    $('<button type="button" class="button button-small sc-queue-delete">Delete</button>').data('id', item.id)
                );
                $actions.append(' ');
                $actions.append(
                    $('<button type="button" class="button button-small sc-queue-mark">Mark processed</button>').data('id', item.id)
                );

                $row.append($actions);
                $body.append($row);
            });

            scRenderPagination($('#sc-queue-pagination'), data.page, data.total_pages);
        });
    }

    $('#sc-queue-refresh').on('click', function () {
        scLoadQueue(1);
    });

    $('#sc-queue-search').on('keyup', function (e) {
        if (e.keyCode === 13) {
            scLoadQueue(1);
        }
    });

    $('#sc-queue-pagination').on('click', 'button', function () {
        var page = $(this).data('page');
        scLoadQueue(page);
    });

    $('#sc-queue-table').on('click', '.sc-queue-delete', function () {
        var id = $(this).data('id');
        if (!confirm('Delete this queue item?')) return;

        scAjax('systemcore_queue_delete', { id: id }, function () {
            scLoadQueue(1);
            scLoadDashboardStats();
        });
    });

    $('#sc-queue-table').on('click', '.sc-queue-mark', function () {
        var id = $(this).data('id');

        scAjax('systemcore_queue_mark_processed', { id: id }, function () {
            scLoadQueue(1);
            scLoadDashboardStats();
        });
    });

    scLoadQueue(1);

    /* ============================================================
     *  Logs View
     * ============================================================ */
    function scLoadLogs(page) {
        var $body = $('#sc-logs-table tbody');
        if (!$body.length) return;

        page = page || 1;
        var level  = $('#sc-logs-level').val() || '';
        var source = $('#sc-logs-source').val() || '';

        $body.html('<tr><td colspan="6">Loading...</td></tr>');

        scAjax('systemcore_logs_list', { page: page, level: level, source: source }, function (data) {

            $body.empty();

            if (!data.items || !data.items.length) {
                $body.html('<tr><td colspan="6">No logs found.</td></tr>');
                $('#sc-logs-pagination').empty();
                return;
            }

            $.each(data.items, function (_, item) {
                var $row = $('<tr></tr>');
                $row.append('<td>' + item.id + '</td>');
                $row.append('<td>' + item.level + '</td>');
                $row.append('<td>' + item.source + '</td>');
                $row.append('<td><pre style="white-space:pre-wrap;margin:0;">' + item.message + '</pre></td>');
                $row.append('<td><pre style="white-space:pre-wrap;margin:0;">' + (item.context || '') + '</pre></td>');
                $row.append('<td>' + (item.created_at || '') + '</td>');
                $body.append($row);
            });

            scRenderPagination($('#sc-logs-pagination'), data.page, data.total_pages);
        });
    }

    $('#sc-logs-refresh').on('click', function () {
        scLoadLogs(1);
    });

    $('#sc-logs-level, #sc-logs-source').on('change', function () {
        scLoadLogs(1);
    });

    $('#sc-logs-pagination').on('click', 'button', function () {
        var page = $(this).data('page');
        scLoadLogs(page);
    });

    $('#sc-logs-clear').on('click', function () {
        if (!confirm('Clear all logs?')) return;

        scAjax('systemcore_logs_clear', {}, function () {
            scLoadLogs(1);
        });
    });

    scLoadLogs(1);

    /* ============================================================
     *  AI Settings — Tabs (FINAL CLEAN VERSION)
     * ============================================================ */

    function scActivateTab($wrapper, tab) {
        // أزرار التابات داخل نفس الـ wrapper فقط
        $wrapper.find('.sc-tab-button').removeClass('active');
        $wrapper.find('.sc-tab-button[data-tab="' + tab + '"]').addClass('active');

        // Panels داخل نفس الـ wrapper فقط
        $wrapper.find('.sc-tab-panel').removeClass('active').hide();
        $wrapper
            .find('.sc-tab-panel[data-tab-panel="' + tab + '"]')
            .addClass('active')
            .show();
    }

    // عند الضغط على زر تاب
    $(document).on('click', '.systemcore-tab-nav .sc-tab-button', function (e) {
        e.preventDefault();

        var $btn     = $(this);
        var tab      = $btn.data('tab');
        var $wrapper = $btn.closest('.systemcore-tabs');

        if (!tab || !$wrapper.length) return;

        scActivateTab($wrapper, tab);
    });

    // تفعيل التاب الافتراضي عند تحميل الصفحة
    $('.systemcore-tabs').each(function () {
        var $wrapper    = $(this);
        var $activeBtn  = $wrapper.find('.systemcore-tab-nav .sc-tab-button.active').first();
        var $firstBtn;

        if ($activeBtn.length) {
            scActivateTab($wrapper, $activeBtn.data('tab'));
        } else {
            $firstBtn = $wrapper.find('.systemcore-tab-nav .sc-tab-button').first();
            if ($firstBtn.length) {
                scActivateTab($wrapper, $firstBtn.data('tab'));
            }
        }
    });
    
    /* ============================================================
 *  AI Settings — Accordion (Replaces Tabs)
 * ============================================================ */
jQuery(document).on('click', '.sc-acc-header', function () {

    const $item = jQuery(this).closest('.sc-acc-item');

    // فتح + إغلاق
    $item.toggleClass('open');
});


}); // END jQuery(function($))
