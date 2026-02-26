<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package LinkForge404
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}linkforge_redirects" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}linkforge_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Delete all plugin options.
$options = [
    'linkforge_enable_logging',
    'linkforge_log_retention_days',
    'linkforge_ignored_extensions',
    'linkforge_rate_limit_max',
    'linkforge_rate_limit_window',
    'linkforge_fuzzy_threshold',
    'linkforge_anonymize_ip',
    'linkforge_ai_enabled',
    'linkforge_ai_api_key',
    'linkforge_ai_confidence',
    'linkforge_ai_daily_budget',
    'linkforge_db_version',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Clear scheduled cron events.
$hooks = [
    'linkforge_flush_buffer',
    'linkforge_garbage_collect',
    'linkforge_resolve_chains',
    'linkforge_update_sitemaps',
    'linkforge_ai_batch',
];

foreach ( $hooks as $hook ) {
    wp_clear_scheduled_hook( $hook );
}

// Clean up log buffer directory.
$log_dir = WP_CONTENT_DIR . '/linkforge/logs';
if ( is_dir( $log_dir ) ) {
    array_map( 'unlink', glob( "{$log_dir}/*" ) );
    rmdir( $log_dir );
    rmdir( WP_CONTENT_DIR . '/linkforge' );
}
