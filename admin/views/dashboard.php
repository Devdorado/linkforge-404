<?php
/**
 * Dashboard view — 404 log overview with stats and top errors.
 *
 * PRD §5.5 FR-501: 404 log grouped by URL, sorted by hits (desc).
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
        <?php esc_html_e( 'LinkForge 404 — Dashboard', 'linkforge-404' ); ?>
    </h1>

    <!-- Stats Cards -->
    <div class="linkforge-stats" id="linkforge-stats">
        <div class="linkforge-stat-card">
            <div class="linkforge-stat-number" id="stat-active-redirects">—</div>
            <div class="linkforge-stat-label"><?php esc_html_e( 'Active Redirects', 'linkforge-404' ); ?></div>
        </div>
        <div class="linkforge-stat-card">
            <div class="linkforge-stat-number" id="stat-total-hits">—</div>
            <div class="linkforge-stat-label"><?php esc_html_e( 'Total Redirect Hits', 'linkforge-404' ); ?></div>
        </div>
        <div class="linkforge-stat-card linkforge-stat-warning">
            <div class="linkforge-stat-number" id="stat-unresolved">—</div>
            <div class="linkforge-stat-label"><?php esc_html_e( 'Unresolved 404s', 'linkforge-404' ); ?></div>
        </div>
        <div class="linkforge-stat-card">
            <div class="linkforge-stat-number" id="stat-total-404-hits">—</div>
            <div class="linkforge-stat-label"><?php esc_html_e( 'Total 404 Hits', 'linkforge-404' ); ?></div>
        </div>
    </div>

    <!-- Top 404s Table -->
    <div class="linkforge-section">
        <h2><?php esc_html_e( 'Top Unresolved 404 Errors', 'linkforge-404' ); ?></h2>

        <form id="linkforge-logs-form">
            <?php wp_nonce_field( 'linkforge_bulk_action', '_linkforge_nonce' ); ?>

            <div class="linkforge-bulk-bar">
                <select id="linkforge-bulk-action">
                    <option value=""><?php esc_html_e( 'Bulk Actions', 'linkforge-404' ); ?></option>
                    <option value="redirect-301"><?php esc_html_e( 'Create 301 Redirect → Homepage', 'linkforge-404' ); ?></option>
                    <option value="redirect-410"><?php esc_html_e( 'Mark as 410 Gone', 'linkforge-404' ); ?></option>
                    <option value="redirect-custom"><?php esc_html_e( 'Create 301 Redirect → Custom URL', 'linkforge-404' ); ?></option>
                </select>
                <input type="url" id="linkforge-custom-url" class="regular-text" placeholder="<?php esc_attr_e( 'Target URL (for custom redirect)', 'linkforge-404' ); ?>" style="display:none;" />
                <button type="button" class="button" id="linkforge-bulk-apply"><?php esc_html_e( 'Apply', 'linkforge-404' ); ?></button>
            </div>

            <table class="wp-list-table widefat fixed striped" id="linkforge-logs-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="linkforge-select-all" />
                        </td>
                        <th class="manage-column column-url"><?php esc_html_e( 'URL', 'linkforge-404' ); ?></th>
                        <th class="manage-column column-hits"><?php esc_html_e( 'Hits', 'linkforge-404' ); ?></th>
                        <th class="manage-column column-referrer"><?php esc_html_e( 'Referrer', 'linkforge-404' ); ?></th>
                        <th class="manage-column column-last-seen"><?php esc_html_e( 'Last Seen', 'linkforge-404' ); ?></th>
                        <th class="manage-column column-actions"><?php esc_html_e( 'Actions', 'linkforge-404' ); ?></th>
                    </tr>
                </thead>
                <tbody id="linkforge-logs-body">
                    <tr>
                        <td colspan="6" class="linkforge-loading">
                            <?php esc_html_e( 'Loading…', 'linkforge-404' ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="linkforge-pagination" id="linkforge-logs-pagination"></div>
        </form>
    </div>

    <!-- System Status -->
    <div class="linkforge-section">
        <h2><?php esc_html_e( 'System Status', 'linkforge-404' ); ?></h2>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e( 'Object Cache', 'linkforge-404' ); ?></strong></td>
                    <td>
                        <?php if ( wp_using_ext_object_cache() ) : ?>
                            <span class="linkforge-badge linkforge-badge-success"><?php esc_html_e( 'Active (Redis/Memcached)', 'linkforge-404' ); ?></span>
                        <?php else : ?>
                            <span class="linkforge-badge linkforge-badge-warning"><?php esc_html_e( 'Not Available — Using File Fallback', 'linkforge-404' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Log Buffer', 'linkforge-404' ); ?></strong></td>
                    <td>
                        <?php
                        $next_flush = wp_next_scheduled( 'linkforge_flush_log_buffer' );
                        if ( $next_flush ) {
                            printf(
                                /* translators: %s: human-readable time difference */
                                esc_html__( 'Next flush in %s', 'linkforge-404' ),
                                esc_html( human_time_diff( time(), $next_flush ) )
                            );
                        } else {
                            esc_html_e( 'Not scheduled', 'linkforge-404' );
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'IP Anonymization', 'linkforge-404' ); ?></strong></td>
                    <td>
                        <?php if ( get_option( 'linkforge_ip_anonymize', true ) ) : ?>
                            <span class="linkforge-badge linkforge-badge-success"><?php esc_html_e( 'Enabled (GDPR-compliant)', 'linkforge-404' ); ?></span>
                        <?php else : ?>
                            <span class="linkforge-badge linkforge-badge-error"><?php esc_html_e( 'Disabled — Not recommended!', 'linkforge-404' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Plugin Version', 'linkforge-404' ); ?></strong></td>
                    <td><?php echo esc_html( LINKFORGE_VERSION ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
