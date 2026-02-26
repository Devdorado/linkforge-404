<?php
/**
 * Redirects management view — add, edit, delete, bulk operations.
 *
 * PRD §5.5 FR-502: Bulk actions & redirect management.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap linkforge-wrap">
    <h1>
        <span class="dashicons dashicons-randomize"></span>
        <?php esc_html_e( 'LinkForge 404 — Redirects', 'linkforge-404' ); ?>
    </h1>

    <!-- Add New Redirect -->
    <div class="linkforge-section linkforge-add-redirect">
        <h2><?php esc_html_e( 'Add New Redirect', 'linkforge-404' ); ?></h2>

        <form id="linkforge-add-form" class="linkforge-inline-form">
            <div class="linkforge-form-row">
                <label for="lf-url-from"><?php esc_html_e( 'Source URL', 'linkforge-404' ); ?></label>
                <input type="text" id="lf-url-from" class="regular-text" placeholder="/old-page" required />
            </div>

            <div class="linkforge-form-row">
                <label for="lf-url-to"><?php esc_html_e( 'Target URL', 'linkforge-404' ); ?></label>
                <input type="text" id="lf-url-to" class="regular-text" placeholder="/new-page or https://..." required />
            </div>

            <div class="linkforge-form-row">
                <label for="lf-match-type"><?php esc_html_e( 'Match Type', 'linkforge-404' ); ?></label>
                <select id="lf-match-type">
                    <option value="exact"><?php esc_html_e( 'Exact', 'linkforge-404' ); ?></option>
                    <option value="regex"><?php esc_html_e( 'Regex', 'linkforge-404' ); ?></option>
                </select>
            </div>

            <div class="linkforge-form-row">
                <label for="lf-status-code"><?php esc_html_e( 'Status Code', 'linkforge-404' ); ?></label>
                <select id="lf-status-code">
                    <option value="301"><?php esc_html_e( '301 Moved Permanently', 'linkforge-404' ); ?></option>
                    <option value="302"><?php esc_html_e( '302 Found (Temporary)', 'linkforge-404' ); ?></option>
                    <option value="307"><?php esc_html_e( '307 Temporary Redirect', 'linkforge-404' ); ?></option>
                    <option value="410"><?php esc_html_e( '410 Gone', 'linkforge-404' ); ?></option>
                </select>
            </div>

            <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Redirect', 'linkforge-404' ); ?></button>
        </form>
    </div>

    <!-- Redirects List -->
    <div class="linkforge-section">
        <h2><?php esc_html_e( 'Existing Redirects', 'linkforge-404' ); ?></h2>

        <div class="linkforge-bulk-bar">
            <select id="linkforge-redirects-bulk-action">
                <option value=""><?php esc_html_e( 'Bulk Actions', 'linkforge-404' ); ?></option>
                <option value="activate"><?php esc_html_e( 'Activate', 'linkforge-404' ); ?></option>
                <option value="deactivate"><?php esc_html_e( 'Deactivate', 'linkforge-404' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Delete', 'linkforge-404' ); ?></option>
            </select>
            <button type="button" class="button" id="linkforge-redirects-bulk-apply"><?php esc_html_e( 'Apply', 'linkforge-404' ); ?></button>

            <div class="linkforge-export-bar" style="float: right;">
                <button type="button" class="button" id="linkforge-export-apache">
                    <?php esc_html_e( 'Export .htaccess', 'linkforge-404' ); ?>
                </button>
                <button type="button" class="button" id="linkforge-export-nginx">
                    <?php esc_html_e( 'Export Nginx', 'linkforge-404' ); ?>
                </button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped" id="linkforge-redirects-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="linkforge-redirects-select-all" />
                    </td>
                    <th class="manage-column column-url-from"><?php esc_html_e( 'Source URL', 'linkforge-404' ); ?></th>
                    <th class="manage-column column-url-to"><?php esc_html_e( 'Target URL', 'linkforge-404' ); ?></th>
                    <th class="manage-column column-type"><?php esc_html_e( 'Type', 'linkforge-404' ); ?></th>
                    <th class="manage-column column-status"><?php esc_html_e( 'Status', 'linkforge-404' ); ?></th>
                    <th class="manage-column column-hits"><?php esc_html_e( 'Hits', 'linkforge-404' ); ?></th>
                    <th class="manage-column column-active"><?php esc_html_e( 'Active', 'linkforge-404' ); ?></th>
                    <th class="manage-column column-actions"><?php esc_html_e( 'Actions', 'linkforge-404' ); ?></th>
                </tr>
            </thead>
            <tbody id="linkforge-redirects-body">
                <tr>
                    <td colspan="8" class="linkforge-loading">
                        <?php esc_html_e( 'Loading…', 'linkforge-404' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="linkforge-pagination" id="linkforge-redirects-pagination"></div>
    </div>

    <!-- Export Modal -->
    <div id="linkforge-export-modal" class="linkforge-modal" style="display:none;">
        <div class="linkforge-modal-content">
            <h3 id="linkforge-export-title"><?php esc_html_e( 'Export Rules', 'linkforge-404' ); ?></h3>
            <textarea id="linkforge-export-output" class="large-text code" rows="15" readonly></textarea>
            <p>
                <button type="button" class="button" id="linkforge-export-copy"><?php esc_html_e( 'Copy to Clipboard', 'linkforge-404' ); ?></button>
                <button type="button" class="button" id="linkforge-export-close"><?php esc_html_e( 'Close', 'linkforge-404' ); ?></button>
            </p>
        </div>
    </div>
</div>
