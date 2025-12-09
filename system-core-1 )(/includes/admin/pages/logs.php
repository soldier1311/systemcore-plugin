<?php
if (!defined('ABSPATH')) exit;
?>

<div class="sc-page">

    <div class="sc-page-header" style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
        <div>
            <h1 class="sc-title">System Logs (Last 3 Days)</h1>
            <div class="sc-subtitle">
                This view displays system-level logs from Fetch, Scraper, AI, Publisher, Scheduler and Feed Loader for the last 3 days.
            </div>
        </div>

        <div style="display:flex; gap:10px; align-items:center;">
            <button type="button" class="sc-btn sc-btn-danger" id="sc-logs-clear">Clear All Logs</button>
        </div>
    </div>

    <div class="sc-card sc-mt-20">
        <div class="sc-card-header">
            <h2 class="sc-card-title">System Logs</h2>
            <div class="sc-card-desc">Showing last 72 hours.</div>
        </div>

        <div class="sc-card-body" style="padding-top:0;">
            <table class="sc-table" id="sc-logs-table">
                <thead>
                    <tr>
                        <th width="6%">ID</th>
                        <th width="10%">Level</th>
                        <th width="12%">Context</th>
                        <th>Message</th>
                        <th width="18%">Created At</th>
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

        $.post(ajaxurl, payload)
            .done(function(res) {
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

        $.post(ajaxurl, payload)
            .always(function() {
                clearBtn.disabled = false;
                clearBtn.textContent = "Clear All Logs";
                loadLogs();
            });
    }

    clearBtn.addEventListener("click", clearLogs);

    loadLogs();
});
</script>
