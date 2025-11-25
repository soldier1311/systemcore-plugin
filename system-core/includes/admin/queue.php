<?php if (!defined('ABSPATH')) exit;

global $wpdb;
$feed_table = $wpdb->prefix . 'systemcore_feed_sources';
?>

<div class="systemcore-wrap">
    <h1 class="systemcore-title">SystemCore Queue</h1>

    <div class="systemcore-toolbar">
        <input type="search" class="regular-text" id="sc-queue-search" placeholder="Search URL...">
        <button class="button" id="sc-queue-refresh">Refresh</button>
    </div>

    <div id="sc-queue-table-wrapper" class="systemcore-card sc-mt-15">
        <table class="systemcore-table" id="sc-queue-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Feed Source</th>
                    <th>URL</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th width="130">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="6">Loading queue...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<style>
#sc-queue-table tbody tr:nth-child(even) { background:#f7f7f7; }
#sc-queue-table tbody tr:nth-child(odd) { background:#ffffff; }
#sc-queue-table tbody tr:hover { background:#eef3f8; transition:0.15s; }
.sc-btn-view, .sc-btn-delete {
    padding:3px 10px !important;
    font-size:12px !important;
    border-radius:4px !important;
}
.sc-btn-view {
    border:1px solid #0073aa !important;
    color:#0073aa !important;
    background:#f1faff !important;
}
.sc-btn-delete {
    border:1px solid #aa0000 !important;
    color:#aa0000 !important;
    background:#fff5f5 !important;
}
.systemcore-table td, .systemcore-table th {
    padding:12px 10px !important;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {

    // Convert timestamp into "time ago"
    function timeAgo(dateString) {
        if (!dateString) return "";

        const now = new Date();
        const past = new Date(dateString);
        const diff = Math.floor((now - past) / 1000); // seconds

        if (diff < 60) return diff + "s ago";
        if (diff < 3600) return Math.floor(diff / 60) + "m ago";
        if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
        if (diff < 2592000) return Math.floor(diff / 86400) + "d ago";
        if (diff < 31104000) return Math.floor(diff / 2592000) + "mo ago";

        return Math.floor(diff / 31104000) + "y ago";
    }

    function renderRows(response) {
        let tbody = document.querySelector("#sc-queue-table tbody");

        if (!response || !Array.isArray(response) || response.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">No results found.</td></tr>';
            return;
        }

        let html = "";

        response.forEach(function(row) {

            const id        = row.id || '';
            const url       = row.url || '';
            const created   = row.created_at ? timeAgo(row.created_at) : '';
            const status    = row.status || '';
            const feedName  = row.feed_name || 'Unknown';

            html += `
                <tr>
                    <td>${id}</td>
                    <td>${feedName}</td>
                    <td><a href="${url}" target="_blank">${url}</a></td>
                    <td>${created}</td>
                    <td>${status}</td>
                    <td>
                        <button class="button button-small sc-btn-view" data-link="${url}">View</button>
                        <button class="button button-small sc-btn-delete" data-id="${id}">Delete</button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    function loadQueue() {
        const search = document.getElementById('sc-queue-search').value;

        jQuery.post(ajaxurl, {
            action: "systemcore_load_queue_new",
            search: search
        }, function(response) {
            renderRows(response);
        });
    }

    function deleteQueueItem(id) {
        if (!confirm('Delete this queue item?')) return;

        jQuery.post(ajaxurl, {
            action: "systemcore_delete_queue_item",
            id: id
        }, function(response) {
            if (response && response.success) {
                loadQueue();
            } else {
                alert('Failed to delete item.');
            }
        });
    }

    document.getElementById("sc-queue-refresh").addEventListener("click", loadQueue);
    document.getElementById("sc-queue-search").addEventListener("keyup", loadQueue);

    jQuery(document).on('click', '.sc-btn-delete', function () {
        deleteQueueItem(jQuery(this).data('id'));
    });

    jQuery(document).on('click', '.sc-btn-view', function () {
        const link = jQuery(this).data('link');
        if (link) window.open(link, '_blank');
    });

    loadQueue();
});
</script>
