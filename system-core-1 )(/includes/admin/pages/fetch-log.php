<?php
if (!defined('ABSPATH')) exit;
?>

<div class="sc-page">

    <div class="sc-page-header" style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
        <div>
            <h1 class="sc-title">System Logs (Last 3 Days)</h1>
            <div class="sc-subtitle">View recent log entries and clear logs if needed.</div>
        </div>

        <div style="display:flex; gap:10px; align-items:center;">
            <button type="button" class="sc-btn sc-btn-danger" id="sc-logs-clear">Clear All Logs</button>
        </div>
    </div>

    <div class="sc-card sc-mt-20">
        <div class="sc-card-header">
            <h2 class="sc-card-title">Log Entries</h2>
            <div class="sc-card-desc">Showing logs for the last 72 hours.</div>
        </div>

        <div class="sc-card-body" style="padding-top:0;">
            <table class="sc-table" id="sc-logs-table">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th style="width:90px;">Level</th>
                        <th style="width:120px;">Context</th>
                        <th>Message</th>
                        <th style="width:160px;">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5">Loading...</td></tr>
                </tbody>
            </table>

            <div class="sc-muted" id="sc-logs-meta" style="margin-top:10px;"></div>
        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const $ = jQuery;

    const tbody = document.querySelector("#sc-logs-table tbody");
    const meta  = document.getElementById("sc-logs-meta");
    const clearBtn = document.getElementById("sc-logs-clear");

    function escHtml(s) {
        return String(s ?? '')
            .replaceAll("&","&amp;")
            .replaceAll("<","&lt;")
            .replaceAll(">","&gt;")
            .replaceAll('"',"&quot;")
            .replaceAll("'","&#039;");
    }

    function renderRows(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5">No logs found.</td></tr>';
            meta.textContent = '';
            return;
        }

        let html = "";
        rows.forEach(row => {
            html += `
                <tr>
                    <td>${escHtml(row.id)}</td>
                    <td>${escHtml(row.level)}</td>
                    <td>${escHtml(row.context || '')}</td>
                    <td>${escHtml(row.message || '')}</td>
                    <td>${escHtml(row.created_at || '')}</td>
                </tr>`;
        });

        tbody.innerHTML = html;
        meta.textContent = rows.length + " log(s)";
    }

    function loadLogs() {
        tbody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
        meta.textContent = '';

        const payload = { action: "systemcore_load_logs_last3days" };

        // إذا endpoints عندك محمية بالـnonce فعّل هذا:
        // payload.nonce = (window.SystemCoreAdmin && SystemCoreAdmin.nonce) ? SystemCoreAdmin.nonce : ((window.SC_UI_Admin && SC_UI_Admin.nonce) ? SC_UI_Admin.nonce : '');

        $.post(ajaxurl, payload)
            .done(function(res) {
                // يدعم {success,data} أو Array مباشر
                if (res && typeof res === 'object' && 'success' in res) {
                    if (res.success) renderRows(res.data || []);
                    else {
                        renderRows([]);
                        meta.textContent = (typeof res.data === 'string') ? res.data : 'Failed to load logs.';
                    }
                    return;
                }

                renderRows(res);
            })
            .fail(function() {
                tbody.innerHTML = '<tr><td colspan="5">Failed to load logs.</td></tr>';
            });
    }

    function clearLogs() {
        if (!confirm("Clear all logs?")) return;

        clearBtn.disabled = true;
        clearBtn.textContent = "Clearing...";

        const payload = { action: "systemcore_clear_logs" };

        // payload.nonce = ...

        $.post(ajaxurl, payload)
            .done(function(res) {
                loadLogs();
            })
            .always(function() {
                clearBtn.disabled = false;
                clearBtn.textContent = "Clear All Logs";
            });
    }

    clearBtn.addEventListener("click", clearLogs);

    loadLogs();
});
</script>
