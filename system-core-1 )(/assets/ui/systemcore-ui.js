jQuery(function ($) {

    /* ============================================================
       SYSTEMCORE UI FRAMEWORK v1 — JS ENGINE (FINAL PRO v1.3)
    ============================================================ */

    window.SC_UI = {

        /* ============================================================
           MODAL SYSTEM
        ============================================================ */
        openModal(id) {
            $(id).fadeIn(120).css("display", "flex");
        },

        closeModal(id) {
            $(id).fadeOut(120);
        },

        bindModalSystem() {
            // زر الإغلاق
            $(document).on("click", ".sc-close-modal", function () {
                $(this).closest(".sc-modal").fadeOut(120);
            });

            // الضغط خارج المودال
            $(document).on("click", ".sc-modal", function (e) {
                if ($(e.target).hasClass("sc-modal")) {
                    $(this).fadeOut(120);
                }
            });
        },

        /* ============================================================
           TOAST NOTIFICATIONS
        ============================================================ */
        notify(msg, type = "success") {
            let box = $(`
                <div class="sc-toast sc-toast-${type}">
                    ${msg}
                </div>
            `);

            $("body").append(box);

            box.animate({ opacity: 1, top: "25px" }, 200);

            setTimeout(() => {
                box.animate({ opacity: 0, top: "0" }, 200, () => box.remove());
            }, 2400);
        },

        /* ============================================================
           CONFIRM DIALOG — Promise
        ============================================================ */
        confirm(message = "Are you sure?") {
            return new Promise(resolve => {

                // إنشاء مودال إذا غير موجود
                if (!$("#sc-confirm").length) {
                    $("body").append(`
                        <div id="sc-confirm" class="sc-modal">
                            <div class="sc-modal-content" style="max-width:360px;">
                                <div id="sc-confirm-message" style="margin-bottom:15px;font-size:14px;font-weight:600;"></div>
                                <div style="display:flex;gap:8px;justify-content:flex-end;">
                                    <button id="sc-confirm-no" class="sc-btn sc-btn-outline">Cancel</button>
                                    <button id="sc-confirm-yes" class="sc-btn sc-btn-primary">Confirm</button>
                                </div>
                            </div>
                        </div>
                    `);
                }

                $("#sc-confirm-message").text(message);
                $("#sc-confirm").fadeIn(120).css("display","flex");

                $("#sc-confirm-yes").off("click").on("click", function () {
                    $("#sc-confirm").fadeOut(120);
                    resolve(true);
                });

                $("#sc-confirm-no").off("click").on("click", function () {
                    $("#sc-confirm").fadeOut(120);
                    resolve(false);
                });
            });
        },

        /* ============================================================
           AJAX HELPER
        ============================================================ */
        ajax(action, data = {}, cb = null, err = null) {

            data.action = action;
            data.nonce = SC_UI_Admin.nonce || "";

            $.post(ajaxurl, data)
                .done(resp => {
                    if (resp && resp.success) {
                        if (typeof cb === "function") cb(resp.data || resp);
                    } else {
                        SC_UI.notify("Error: request failed", "error");
                        if (typeof err === "function") err(resp);
                    }
                })
                .fail(() => {
                    SC_UI.notify("AJAX connection error", "error");
                    if (typeof err === "function") err("ajax_error");
                });
        },

        /* ============================================================
           BUTTON LOADING STATE
        ============================================================ */
        buttonLoading(btn, state = true) {
            if (state) {
                $(btn).data("orig", $(btn).html());
                $(btn).html("Processing…").prop("disabled", true);
            } else {
                $(btn).html($(btn).data("orig")).prop("disabled", false);
            }
        },

        /* ============================================================
           PAGE LOADING OVERLAY
        ============================================================ */
        showOverlay() {
            if (!$("#sc-loading-overlay").length) {
                $("body").append(`
                    <div id="sc-loading-overlay">
                        <div class="sc-loader"></div>
                    </div>
                `);
            }
            $("#sc-loading-overlay").fadeIn(120);
        },

        hideOverlay() {
            $("#sc-loading-overlay").fadeOut(120);
        },

        /* ============================================================
           FORM VALIDATION
        ============================================================ */
        validate(fields = []) {
            let ok = true;

            fields.forEach(selector => {
                let el = $(selector);
                if (el.val().trim() === "") {
                    el.css("border-color", "#dc2626");
                    ok = false;
                } else {
                    el.css("border-color", "#e2e8f0");
                }
            });

            return ok;
        },

        /* ============================================================
           AUTO REFRESH HELPERS
        ============================================================ */
        reloadPage(ms = 350) {
            setTimeout(() => location.reload(), ms);
        },

        replaceHTML(target, html) {
            $(target).html(html);
        },

        /* ============================================================
           ROW SELECT (Notion)
        ============================================================ */
        bindRowSelect() {
            $(document).on("click", ".sc-table-notion tbody tr", function () {
                $(".sc-table-notion tbody tr").removeClass("sc-row-active");
                $(this).addClass("sc-row-active");
            });
        },

        /* ============================================================
           ROW HOVER
        ============================================================ */
        bindTableHover() {
            $(document).on("mouseenter", ".sc-table-notion tbody tr", function () {
                $(this).addClass("sc-row-hover");
            });

            $(document).on("mouseleave", ".sc-table-notion tbody tr", function () {
                $(this).removeClass("sc-row-hover");
            });
        },

        /* ============================================================
           ACTION BUTTONS (edit/delete/toggle)
        ============================================================ */
        bindActions() {
            $(document).on("click", ".sc-action-edit", function () {
                $(document).trigger("sc:edit", { id: $(this).data("id") });
            });

            $(document).on("click", ".sc-action-delete", function () {
                $(document).trigger("sc:delete", { id: $(this).data("id") });
            });

            $(document).on("click", ".sc-action-toggle", function () {
                $(document).trigger("sc:toggle", { id: $(this).data("id") });
            });
        },

        /* ============================================================
           HEADER BUILDER
        ============================================================ */
        renderHeader(target, title, desc = "", buttons = []) {
            let header = $('<div class="sc-header"></div>');

            let left = $(`
                <div class="sc-header-left">
                    <h1 class="sc-header-title">${title}</h1>
                    ${desc ? `<div class="sc-header-desc">${desc}</div>` : ""}
                </div>
            `);

            let right = $('<div class="sc-header-right"></div>');

            buttons.forEach(btn => {
                let b = $(`
                    <button class="sc-btn-header ${
                        btn.type === "primary"
                            ? "sc-btn-header-primary"
                            : "sc-btn-header-outline"
                    }">${btn.label}</button>
                `);

                if (typeof btn.onClick === "function") b.on("click", btn.onClick);

                right.append(b);
            });

            header.append(left).append(right);

            $(target).prepend(header);
        },

        /* ============================================================
           NOTION TABLE RENDER FUNCTION
        ============================================================ */
        renderTable(target, columns, rows) {

            let wrap = $('<div class="sc-table-wrap"></div>');
            let table = $('<table class="sc-table-notion sc-table"></table>');

            let thead = $("<thead><tr></tr></thead>");
            let trHead = thead.find("tr");

            columns.forEach(col => {
                trHead.append(`<th style="width:${col.width || "auto"}">${col.label}</th>`);
            });

            let tbody = $("<tbody></tbody>");

            rows.forEach(row => {
                let tr = $("<tr></tr>");
                if (row.class) tr.addClass(row.class);

                columns.forEach(col => {
                    let value = row[col.key] !== undefined ? row[col.key] : "";
                    tr.append(`<td>${value}</td>`);
                });

                tbody.append(tr);
            });

            table.append(thead).append(tbody);
            wrap.append(table);

            $(target).html(wrap);
        },

        /* ============================================================
           INIT
        ============================================================ */
        init() {
            this.bindModalSystem();
            this.bindTableHover();
            this.bindRowSelect();
            this.bindActions();
        }
    };

    /* ------------------------------------------------------------
       RUN
    ------------------------------------------------------------ */
    SC_UI.init();

    /* ============================================================
       GLOBAL INLINE STYLES (Toast + Overlay)
    ============================================================ */
    if (!$("#sc-toast-style").length) {
        $("head").append(`
            <style id="sc-toast-style">

                .sc-toast {
                    position: fixed;
                    left: 50%;
                    transform: translateX(-50%);
                    top: 0;
                    opacity: 0;
                    z-index: 999999;
                    padding: 10px 16px;
                    border-radius: 8px;
                    font-size: 13px;
                    font-weight: 600;
                    box-shadow: 0 4px 14px rgba(0,0,0,0.15);
                    color: #fff;
                    transition: all 0.25s ease;
                }

                .sc-toast-success { background:#16a34a; }
                .sc-toast-error   { background:#dc2626; }
                .sc-toast-warning { background:#d97706; }

                #sc-loading-overlay {
                    display:none;
                    position: fixed;
                    z-index: 99999;
                    inset:0;
                    background: rgba(0,0,0,0.35);
                    backdrop-filter: blur(3px);
                }

                #sc-loading-overlay .sc-loader {
                    width: 40px;
                    height: 40px;
                    border: 4px solid #fff;
                    border-top: 4px solid transparent;
                    border-radius: 50%;
                    position:absolute;
                    top:50%; left:50%;
                    transform:translate(-50%, -50%);
                    animation: sc-spin 0.9s linear infinite;
                }

                @keyframes sc-spin {
                    from { transform:translate(-50%, -50%) rotate(0); }
                    to   { transform:translate(-50%, -50%) rotate(360deg); }
                }
            </style>
        `);
    }

});
