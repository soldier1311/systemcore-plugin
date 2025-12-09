<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$table_queue = $wpdb->prefix . 'systemcore_queue';
$table_feeds = $wpdb->prefix . 'systemcore_feed_sources';

// Local nonce for this page (fallback if SystemCoreAdmin.nonce not available)
$sc_queue_nonce = wp_create_nonce('systemcore_queue_admin');

/* ============================================
   CHECK TABLES
============================================ */
$queue_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_queue)) === $table_queue);
$feeds_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_feeds)) === $table_feeds);

/* ============================================
   KPIs
============================================ */
$total = $pending = $done = $feeds = 0;

if ($queue_exists) {
    $total   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_queue}");
    $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_queue} WHERE status = 'pending'");
    $done    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_queue} WHERE status = 'done'");
}

if ($feeds_exists) {
    $feeds = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_feeds}");
}
?>

<div class="sc-page">

    <div class="sc-page-header" style="display:flex; align-items:flex-start; justify-content:space-between;">
        <div>
            <h1 class="sc-title">SystemCore Queue</h1>
            <div class="sc-subtitle">Search, review, and manage queue items.</div>
        </div>

        <button class="sc-btn sc-btn-primary" id="sc-run-fetch" type="button">Fetch Now</button>
    </div>

    <?php if (!$queue_exists || !$feeds_exists): ?>
        <div class="sc-card sc-mt-20">
            <div class="sc-alert sc-alert-danger">
                <div class="sc-alert-title">Database tables missing</div>
                <div class="sc-alert-text">
                    <?php if (!$queue_exists): ?>
                        <div>Missing: <code><?php echo esc_html($table_queue); ?></code></div>
                    <?php endif; ?>
                    <?php if (!$feeds_exists): ?>
                        <div>Missing: <code><?php echo esc_html($table_feeds); ?></code></div>
                    <?php endif; ?>
                    <div style="margin-top:8px;">Run database installation.</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="sc-grid sc-grid-4 sc-mt-20">

        <div class="sc-card">
            <div class="sc-kpi-title">Total Items</div>
            <div class="sc-kpi-value"><?php echo (int) $total; ?></div>
        </div>

        <div class="sc-card">
            <div class="sc-kpi-title">Pending</div>
            <div class="sc-kpi-value sc-orange"><?php echo (int) $pending; ?></div>
        </div>

        <div class="sc-card">
            <div class="sc-kpi-title">Processed</div>
            <div class="sc-kpi-value sc-green"><?php echo (int) $done; ?></div>
        </div>

        <div class="sc-card">
            <div class="sc-kpi-title">Feed Sources</div>
            <div class="sc-kpi-value"><?php echo (int) $feeds; ?></div>
        </div>

    </div>

    <div class="sc-card sc-mt-25">
        <div class="sc-card-header">
            <h2 class="sc-card-title">Search & Actions</h2>
            <div class="sc-card-desc">Filter queue by title.</div>
        </div>

        <div class="sc-card-body">
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="search" id="sc-queue-search" placeholder="Search title…" class="sc-input" style="flex:1;">
                <button class="sc-btn" id="sc-queue-refresh" type="button">Refresh</button>
            </div>
        </div>
    </div>

    <div class="sc-card sc-mt-25">

        <div class="sc-card-header">
            <h2 class="sc-card-title">Queue List</h2>
            <div class="sc-card-desc">Latest items pulled from feeds.</div>
        </div>

        <div class="sc-card-body" style="padding-top:0;">
            <table class="sc-table" id="sc-queue-table">
                <thead>
                    <tr>
                        <th width="60">ID</th>
                        <th width="180">Feed</th>
                        <th>Title</th>
                        <th width="140">Created</th>
                        <th width="100">Status</th>
                        <th width="160">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="6">Loading queue…</td></tr>
                </tbody>
            </table>

            <div class="sc-muted" id="sc-queue-meta" style="margin-top:10px;"></div>
        </div>

    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const $ = jQuery;

    const searchEl  = document.getElementById('sc-queue-search');
    const refreshEl = document.getElementById('sc-queue-refresh');
    const fetchEl   = document.getElementById('sc-run-fetch');
    const tbody     = document.querySelector("#sc-queue-table tbody");
    const meta      = document.getElementById('sc-queue-meta');

    // Fallback nonce & ajax URL (compatible with SystemCoreAdmin + WP ajaxurl)
    const SC_NONCE = "<?php echo esc_js($sc_queue_nonce); ?>";
    const ajaxUrl  =
        (typeof SystemCoreAdmin !== "undefined" && SystemCoreAdmin.ajax_url) ? SystemCoreAdmin.ajax_url :
        (typeof ajaxurl !== "undefined" ? ajaxurl : "");

    function getNonce() {
        if (typeof SystemCoreAdmin !== "undefined" && SystemCoreAdmin.nonce) {
            return SystemCoreAdmin.nonce;
        }
        return SC_NONCE;
    }

    let typingTimer = null;
    let lastReq = 0;

    function escHtml(s){
        return String(s ?? "")
            .replaceAll("&","&amp;")
            .replaceAll("<","&lt;")
            .replaceAll(">","&gt;")
            .replaceAll('"',"&quot;")
            .replaceAll("'","&#039;");
    }

    function timeAgo(dateString){
        if (!dateString) return "";

        let past = null;

        // Try native parse first
        const parsedDirect = Date.parse(dateString);
        if (!isNaN(parsedDirect)) {
            past = new Date(parsedDirect);
        } else {
            // Fallback: normalize common "Y-m-d H:i:s"
            const normalized = dateString.replace(" ", "T");
            const parsedNorm = Date.parse(normalized);
            if (!isNaN(parsedNorm)) {
                past = new Date(parsedNorm);
            }
        }

        if (!past) {
            // As a last resort, return raw date string to avoid lying
            return dateString;
        }

        const now  = new Date();
        const diff = Math.floor((now - past) / 1000);

        if (diff < 60)      return diff + "s ago";
        if (diff < 3600)    return Math.floor(diff / 60) + "m ago";
        if (diff < 86400)   return Math.floor(diff / 3600) + "h ago";
        if (diff < 2592000) return Math.floor(diff / 86400) + "d ago";
        return Math.floor(diff / 31104000) + "y ago";
    }

    function renderRows(rows, metaInfo){

        if (!Array.isArray(rows) || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">No results found.</td></tr>';
            meta.textContent = metaInfo && metaInfo.text ? metaInfo.text : "";
            return;
        }

        let html = "";

        rows.forEach(row => {

            const id       = escHtml(row.id);
            const feedName = escHtml(row.feed_name || "Unknown");
            const title    = escHtml(row.title || "(no title)");
            const url      = escHtml(row.url || "");
            const created  = row.created_at ? timeAgo(row.created_at) : "";
            const status   = escHtml(row.status || "");

            html += `
                <tr>
                    <td>${id}</td>
                    <td>${feedName}</td>
                    <td><a href="${url}" target="_blank" rel="noopener">${title}</a></td>
                    <td>${created}</td>
                    <td>${status}</td>
                    <td style="display:flex; gap:6px;">
                        <button class="sc-btn sc-btn-ghost sc-btn-view" data-link="${url}">View</button>
                        <button class="sc-btn sc-btn-danger sc-btn-delete" data-id="${id}">Delete</button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        if (metaInfo && metaInfo.text) {
            meta.textContent = metaInfo.text;
        } else {
            meta.textContent = rows.length + " item(s)";
        }
    }

    function handleLoadResponse(res) {

        // Backward compatibility: plain array from old handler
        if (Array.isArray(res)) {
            renderRows(res, null);
            return;
        }

        // Extended format: { rows: [...], meta: {...} }
        if (res && Array.isArray(res.rows)) {
            const metaInfo = res.meta || {};
            renderRows(res.rows, {
                text: metaInfo.text || (res.rows.length + " item(s)")
            });
            return;
        }

        // Fallback: unknown format
        tbody.innerHTML = '<tr><td colspan="6">Failed to parse response.</td></tr>';
        meta.textContent = "";
    }

    function loadQueue(){

        if (!ajaxUrl) {
            tbody.innerHTML = '<tr><td colspan="6">AJAX URL not defined.</td></tr>';
            meta.textContent = "";
            return;
        }

        const reqId  = ++lastReq;
        const search = (searchEl.value || "").trim();

        tbody.innerHTML = '<tr><td colspan="6">Loading queue…</td></tr>';
        meta.textContent = "";

        $.post(ajaxUrl, {
            action: "systemcore_load_queue_new",
            search: search,
            nonce:  getNonce()
        })
        .done(function(res){
            if (reqId !== lastReq) return;
            handleLoadResponse(res);
        })
        .fail(function(){
            if (reqId !== lastReq) return;
            tbody.innerHTML = '<tr><td colspan="6">Failed to load queue.</td></tr>';
            meta.textContent = "";
        });
    }

    function deleteItem(id){

        if (!ajaxUrl) {
            alert("AJAX URL not defined.");
            return;
        }

        if (!id) return;
        if (!confirm("Delete this queue item?")) return;

        $.post(ajaxUrl, {
            action: "systemcore_delete_queue_item",
            id:     id,
            nonce:  getNonce()
        })
        .always(loadQueue);
    }

    function runFetchNow(){

        if (!ajaxUrl) {
            alert("AJAX URL not defined.");
            return;
        }

        fetchEl.disabled = true;
        fetchEl.textContent = "Fetching…";
        meta.textContent = "Fetching feeds...";

        $.post(ajaxUrl, {
            action: "systemcore_run_fetch_now",
            nonce:  getNonce()
        })
        .done(function(){
            meta.textContent = "Fetch finished.";
            loadQueue();
        })
        .fail(function(){
            meta.textContent = "Fetch failed.";
        })
        .always(function(){
            fetchEl.disabled = false;
            fetchEl.textContent = "Fetch Now";
        });
    }

    // Events
    refreshEl.addEventListener("click", loadQueue);

    searchEl.addEventListener("keyup", function(){
        clearTimeout(typingTimer);
        typingTimer = setTimeout(loadQueue, 250);
    });

    fetchEl.addEventListener("click", runFetchNow);

    $(document).on("click", ".sc-btn-view", function(){
        const link = $(this).data("link");
        if (link) window.open(link, "_blank");
    });

    $(document).on("click", ".sc-btn-delete", function(){
        deleteItem($(this).data("id"));
    });

    // Initial load
    loadQueue();
});
</script>
