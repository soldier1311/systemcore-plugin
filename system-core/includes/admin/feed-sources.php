<?php if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'systemcore_feed_sources';
$sources = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
?>

<div class="systemcore-wrap">
    <h1 class="systemcore-title">Feed Sources</h1>

    <div class="systemcore-card">

        <button class="button button-primary" id="sc-add-source-btn">+ Add New Source</button>

        <table class="systemcore-table sc-mt-20">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Source Name</th>
                    <th>Feed URL</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sources): foreach ($sources as $src): ?>
                    <tr>
                        <td><?= esc_html($src->id) ?></td>
                        <td><?= esc_html($src->source_name) ?></td>
                        <td><?= esc_html($src->feed_url) ?></td>
                        <td>
                            <button class="button sc-edit-source" data-id="<?= $src->id ?>" data-name="<?= esc_attr($src->source_name) ?>" data-url="<?= esc_attr($src->feed_url) ?>">Edit</button>
                            <button class="button sc-delete-source" data-id="<?= $src->id ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4">No feed sources found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- MODAL -->
<div id="sc-modal" class="sc-modal" style="display:none;">
    <div class="sc-modal-content">
        <h2 id="sc-modal-title">Add Feed Source</h2>

        <input type="hidden" id="sc-source-id">

        <label>Source Name:</label>
        <input type="text" id="sc-source-name" class="regular-text" placeholder="Example: TechCrunch">

        <label class="sc-mt-10">Feed URL:</label>
        <input type="text" id="sc-source-url" class="regular-text" placeholder="https://example.com/rss">

        <div class="sc-mt-20">
            <button class="button button-primary" id="sc-save-source">Save</button>
            <button class="button" id="sc-close-modal">Cancel</button>
        </div>
    </div>
</div>


<style>
.sc-modal {
    position: fixed;
    top: 0; left: 0;
    width:100%; height:100%;
    background: rgba(0,0,0,0.4);
    display:flex; justify-content:center; align-items:center;
}
.sc-modal-content {
    background:#fff;
    padding:20px;
    width:450px;
    border-radius:6px;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById("sc-modal");

    // open modal (new)
    document.getElementById("sc-add-source-btn").onclick = () => {
        document.getElementById("sc-modal-title").innerText = "Add Feed Source";
        document.getElementById("sc-source-id").value = "";
        document.getElementById("sc-source-name").value = "";
        document.getElementById("sc-source-url").value = "";
        modal.style.display = "flex";
    };

    // close modal
    document.getElementById("sc-close-modal").onclick = () => {
        modal.style.display = "none";
    };

    // edit source
    document.querySelectorAll(".sc-edit-source").forEach(btn => {
        btn.onclick = () => {
            document.getElementById("sc-modal-title").innerText = "Edit Feed Source";
            document.getElementById("sc-source-id").value = btn.dataset.id;
            document.getElementById("sc-source-name").value = btn.dataset.name;
            document.getElementById("sc-source-url").value = btn.dataset.url;
            modal.style.display = "flex";
        };
    });

    // save (insert or update)
    document.getElementById("sc-save-source").onclick = () => {

        let id = document.getElementById("sc-source-id").value;
        let name = document.getElementById("sc-source-name").value;
        let url = document.getElementById("sc-source-url").value;

        jQuery.post(ajaxurl, {
            action: "systemcore_save_feed_source",
            id: id,
            name: name,
            url: url
        }, function(response){
            location.reload(); // refresh list
        });
    };

    // delete source
    document.querySelectorAll(".sc-delete-source").forEach(btn => {
        btn.onclick = () => {
            if (!confirm("Delete this feed source?")) return;

            jQuery.post(ajaxurl, {
                action: "systemcore_delete_feed_source",
                id: btn.dataset.id
            }, function(){
                location.reload();
            });
        };
    });

});
</script>
