<?php
/**
 * Plugin activation handler.
 *
 * Creates custom database tables with indexed structures
 * as specified in PRD §7.1.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Activator {

    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        self::check_requirements();
        self::create_tables();
        self::set_default_options();
        self::schedule_events();

        update_option( 'linkforge_version', LINKFORGE_VERSION );
        flush_rewrite_rules();
    }

    /**
     * Verify minimum environment requirements.
     *
     * @throws \RuntimeException If requirements are not met.
     */
    private static function check_requirements(): void {
        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            deactivate_plugins( LINKFORGE_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'LinkForge 404 requires PHP 8.1 or higher.', 'linkforge-404' ),
                'Plugin Activation Error',
                [ 'back_link' => true ]
            );
        }

        global $wp_version;
        if ( version_compare( $wp_version, '6.4', '<' ) ) {
            deactivate_plugins( LINKFORGE_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'LinkForge 404 requires WordPress 6.4 or higher.', 'linkforge-404' ),
                'Plugin Activation Error',
                [ 'back_link' => true ]
            );
        }
    }

    /**
     * Create custom tables per PRD §7.1 schema.
     *
     * Uses dbDelta() for safe, idempotent table creation.
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $redirects_table = $wpdb->prefix . 'linkforge_redirects';
        $logs_table      = $wpdb->prefix . 'linkforge_logs';

        $sql_redirects = "CREATE TABLE {$redirects_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url_from VARCHAR(2048) NOT NULL,
            url_to VARCHAR(2048) NOT NULL DEFAULT '',
            match_type ENUM('exact','regex','fuzzy','ai') NOT NULL DEFAULT 'exact',
            status_code SMALLINT NOT NULL DEFAULT 301,
            group_id INT UNSIGNED NOT NULL DEFAULT 0,
            hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_url_from (url_from(191)),
            INDEX idx_match_type (match_type),
            INDEX idx_group_id (group_id),
            INDEX idx_is_active (is_active)
        ) {$charset_collate};";

        $sql_logs = "CREATE TABLE {$logs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url VARCHAR(2048) NOT NULL,
            referrer VARCHAR(2048) NOT NULL DEFAULT '',
            user_agent VARCHAR(512) NOT NULL DEFAULT '',
            ip_hash VARCHAR(64) NOT NULL DEFAULT '',
            hit_count INT UNSIGNED NOT NULL DEFAULT 1,
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            INDEX idx_url (url(191)),
            INDEX idx_ip_hash (ip_hash),
            INDEX idx_first_seen (first_seen),
            INDEX idx_resolved (resolved)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_redirects );
        dbDelta( $sql_logs );
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options(): void {
        $defaults = [
            'linkforge_log_retention_days'   => 90,
            'linkforge_fuzzy_threshold'      => 0.85,
            'linkforge_buffer_flush_minutes' => 5,
            'linkforge_rate_limit_per_ip'    => 60,
            'linkforge_rate_limit_window'    => 300,
            'linkforge_ignore_extensions'    => 'ico,css,js,jpg,jpeg,png,gif,svg,webp,woff,woff2,ttf,eot,map',
            'linkforge_ip_anonymize'         => true,
            'linkforge_logging_enabled'      => true,
            'linkforge_ai_enabled'           => false,
            'linkforge_ai_api_key'           => '',
            'linkforge_ai_confidence'        => 0.85,
            'linkforge_ai_daily_budget'      => 100,
        ];

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Register scheduled events for async processing.
     */
    private static function schedule_events(): void {
        if ( ! wp_next_scheduled( 'linkforge_flush_log_buffer' ) ) {
            wp_schedule_event( time(), 'linkforge_five_minutes', 'linkforge_flush_log_buffer' );
        }

        if ( ! wp_next_scheduled( 'linkforge_garbage_collect' ) ) {
            wp_schedule_event( time(), 'daily', 'linkforge_garbage_collect' );
        }

        if ( ! wp_next_scheduled( 'linkforge_resolve_chains' ) ) {
            wp_schedule_event( time(), 'weekly', 'linkforge_resolve_chains' );
        }
    }
}
