<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table  = $wpdb->prefix . 'systemcore_feed_sources';
$sources = $wpdb->get_results("SELECT * FROM {$table} ORDER BY is_main_source DESC, priority_level ASC, id ASC");
$nonce  = wp_create_nonce('systemcore_admin_nonce');

$priority_labels = [
    0 => 'Main Source',
    1 => 'High',
    2 => 'Medium',
    3 => 'Low',
    4 => 'Very Low',
];
?>
<div class="sc-page">

    <!-- Header ثابت بدون JS -->
    <div class="sc-header">
        <div class="sc-header-left">
            <h1 class="sc-header-title">Feed Sources</h1>
            <div class="sc-header-desc">
                Manage, edit and organize all feed sources used by SystemCore.
            </div>
        </div>
        <div class="sc-header-right">
            <button type="button" id="sc-add-source" class="sc-btn-header sc-btn-header-primary">
                + Add New Source
            </button>
        </div>
    </div>

    <!-- وصف بسيط -->
    <div class="sc-card sc-mt-20">
        <p style="margin:0;">
            Priority and main-source flags are used by the Priority Engine and Language Load Control.
        </p>
    </div>

    <!-- Notion Table -->
    <div class="sc-card sc-mt-20">
        <div class="sc-table-wrap">
            <table class="sc-table sc-table-notion sc-table-compact" id="sc-feed-table">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Source Name</th>
                        <th>Feed URL</th>
                        <th style="width:80px;">Type</th>
                        <th style="width:120px;">Priority</th>
                        <th style="width:80px;">Main</th>
                        <th style="width:90px;">Status</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($sources)) : ?>
                    <?php foreach ($sources as $src) :
                        $type = !empty($src->feed_type) ? strtolower($src->feed_type) : 'rss';
                        if (!in_array($type, ['rss','xml','json'], true)) {
                            $type = 'rss';
                        }

                        $priority = (int) $src->priority_level;
                        $prio_label = $priority_labels[$priority] ?? 'Unknown';
                    ?>
                    <tr
                        data-id="<?php echo (int) $src->id; ?>"
                        data-name="<?php echo esc_attr($src->source_name); ?>"
                        data-url="<?php echo esc_attr($src->feed_url); ?>"
                        data-type="<?php echo esc_attr($type); ?>"
                        data-priority="<?php echo esc_attr($priority); ?>"
                        data-main="<?php echo (int) $src->is_main_source; ?>"
                        data-active="<?php echo (int) $src->active; ?>"
                    >
                        <td><?php echo (int) $src->id; ?></td>
                        <td><?php echo esc_html($src->source_name); ?></td>
                        <td>
                            <a href="<?php echo esc_url($src->feed_url); ?>" target="_blank">
                                <?php echo esc_html($src->feed_url); ?>
                            </a>
                        </td>
                        <td>
                            <span class="sc-table-badge sc-table-badge-blue">
                                <?php echo strtoupper(esc_html($type)); ?>
                            </span>
                        </td>
                        <td>
                            <span class="sc-table-badge sc-table-badge-gray">
                                <?php echo esc_html($prio_label); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($src->is_main_source): ?>
                                <span class="sc-table-badge sc-table-badge-blue">MAIN</span>
                            <?php else: ?>
                                <span class="sc-table-badge sc-table-badge-gray">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($src->active): ?>
                                <span class="sc-table-badge sc-table-badge-green">Active</span>
                            <?php else: ?>
                                <span class="sc-table-badge sc-table-badge-red">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="sc-actions">
                                <button type="button" class="sc-btn-xxs sc-feed-edit">Edit</button>
                                <button type="button" class="sc-btn-xxs sc-feed-toggle">
                                    <?php echo $src->active ? 'Disable' : 'Enable'; ?>
                                </button>
                                <button type="button" class="sc-btn-xxs sc-feed-delete">Delete</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">No feed sources found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal -->
<div id="sc-modal" class="sc-modal">
    <div class="sc-modal-content">
        <h2 id="sc-modal-title">Add New Source</h2>

        <form id="sc-source-form">
            <input type="hidden" name="id" id="sc-source-id" value="0">

            <div class="sc-field">
                <label for="sc-source-name">Source Name</label>
                <input type="text" id="sc-source-name" name="source_name">
            </div>

            <div class="sc-field">
                <label for="sc-source-url">Feed URL</label>
                <input type="text" id="sc-source-url" name="feed_url">
            </div>

            <div class="sc-field">
                <label for="sc-source-type">Feed Type</label>
                <select id="sc-source-type" name="feed_type">
                    <option value="rss">RSS</option>
                    <option value="xml">XML</option>
                    <option value="json">JSON</option>
                </select>
            </div>

            <div class="sc-field">
                <label for="sc-priority-level">Priority Level</label>
                <select id="sc-priority-level" name="priority_level">
                    <option value="0">0 – Main Source (highest)</option>
                    <option value="1">1 – High</option>
                    <option value="2">2 – Medium</option>
                    <option value="3">3 – Low</option>
                    <option value="4">4 – Very Low</option>
                </select>
            </div>

            <div class="sc-field-inline">
                <label>
                    <input type="checkbox" id="sc-is-main-source" name="is_main_source" value="1">
                    Mark as Main Source
                </label>
            </div>

            <div class="sc-field-inline">
                <label>
                    <input type="checkbox" id="sc-source-active" name="active" value="1" checked>
                    Active
                </label>
            </div>

            <div style="display:flex;gap:10px;margin-top:18px;">
                <button type="submit" class="sc-btn sc-btn-primary">Save</button>
                <button type="button" class="sc-btn sc-btn-outline sc-modal-close">Cancel</button>
            </div>

            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="action" value="systemcore_save_feed_source">
        </form>
    </div>
</div>

<script>
jQuery(function($){

    const ajaxUrl = (window.SystemCoreAdmin && SystemCoreAdmin.ajax_url) || (window.ajaxurl || '');

    function openModal(title) {
        $('#sc-modal-title').text(title || 'Add New Source');
        $('#sc-modal').fadeIn(120).css('display','flex');
    }

    function closeModal() {
        $('#sc-modal').fadeOut(120);
    }

    function resetForm() {
        $('#sc-source-id').val('0');
        $('#sc-source-name').val('');
        $('#sc-source-url').val('');
        $('#sc-source-type').val('rss');
        $('#sc-priority-level').val('2');
        $('#sc-is-main-source').prop('checked', false);
        $('#sc-source-active').prop('checked', true);
    }

    // Add new
    $('#sc-add-source').on('click', function(){
        resetForm();
        openModal('Add New Source');
    });

    // Close modal
    $(document).on('click', '.sc-modal-close', function(e){
        e.preventDefault();
        closeModal();
    });

    $(document).on('click', '#sc-modal', function(e){
        if ($(e.target).is('#sc-modal')) {
            closeModal();
        }
    });

    // Edit
    $('#sc-feed-table').on('click', '.sc-feed-edit', function(){
        const row = $(this).closest('tr');

        $('#sc-source-id').val(row.data('id'));
        $('#sc-source-name').val(row.data('name'));
        $('#sc-source-url').val(row.data('url'));
        $('#sc-source-type').val(row.data('type'));
        $('#sc-priority-level').val(row.data('priority'));
        $('#sc-is-main-source').prop('checked', row.data('main') == 1);
        $('#sc-source-active').prop('checked', row.data('active') == 1);

        openModal('Edit Source');
    });

    // Delete
    $('#sc-feed-table').on('click', '.sc-feed-delete', function(){
        const row = $(this).closest('tr');
        const id  = row.data('id');

        if (!confirm('Delete this feed source?')) return;

        $.post(ajaxUrl, {
            action: 'systemcore_delete_feed_source',
            id: id,
            nonce: '<?php echo esc_js($nonce); ?>'
        }, function(resp){
            if (resp && resp.success) {
                row.remove();
            } else {
                alert('Failed to delete feed source.');
            }
        });
    });

    // Toggle active
    $('#sc-feed-table').on('click', '.sc-feed-toggle', function(){
        const row = $(this).closest('tr');
        const id  = row.data('id');
        const btn = $(this);

        $.post(ajaxUrl, {
            action: 'systemcore_toggle_feed_source',
            id: id,
            nonce: '<?php echo esc_js($nonce); ?>'
        }, function(resp){
            if (resp && resp.success) {
                location.reload();
            } else {
                alert('Failed to toggle feed source.');
            }
        });
    });

    // Save form
    $('#sc-source-form').on('submit', function(e){
        e.preventDefault();

        if (!$('#sc-source-name').val().trim() || !$('#sc-source-url').val().trim()) {
            alert('Please fill all required fields.');
            return;
        }

        const data = $(this).serialize();

        $.post(ajaxUrl, data, function(resp){
            if (resp && resp.success) {
                location.reload();
            } else {
                alert('Failed to save feed source.');
            }
        }).fail(function(){
            alert('AJAX error while saving feed source.');
        });
    });

});
</script>
