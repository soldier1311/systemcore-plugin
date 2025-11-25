<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap systemcore-wrap">
    <h1>SystemCore – Logs</h1>

    <p class="description">
        Central log viewer for Fetch, Scraper, AI, Publisher, Scheduler and other modules.
    </p>

    <div class="systemcore-card" style="margin-top:20px;">

        <div class="systemcore-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <strong>Filters</strong>
            </div>

            <div class="sc-notice-area"></div>
        </div>

        <div class="systemcore-card-body">

            <div style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;">

                <div>
                    <label for="sc-logs-level"><strong>Level</strong></label><br>
                    <select id="sc-logs-level">
                        <option value="">All</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="debug">Debug</option>
                    </select>
                </div>

                <div>
                    <label for="sc-logs-source"><strong>Source</strong></label><br>
                    <select id="sc-logs-source">
                        <option value="">All</option>
                        <option value="fetch">Fetch</option>
                        <option value="scraper">Scraper</option>
                        <option value="ai">AI</option>
                        <option value="publisher">Publisher</option>
                        <option value="scheduler">Scheduler</option>
                        <option value="system">System</option>
                    </select>
                </div>

                <div style="align-self:flex-end;">
                    <button type="button" class="button" id="sc-logs-refresh">
                        Refresh
                    </button>
                    <button type="button" class="button button-secondary" id="sc-logs-clear">
                        Clear all logs
                    </button>
                </div>
            </div>

            <table class="widefat fixed striped" id="sc-logs-table">
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="8%">Level</th>
                        <th width="12%">Source</th>
                        <th>Message</th>
                        <th width="20%">Context</th>
                        <th width="16%">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="6">Loading...</td></tr>
                </tbody>
            </table>

            <div id="sc-logs-pagination" style="margin-top:15px;"></div>

        </div>
    </div>
</div>
