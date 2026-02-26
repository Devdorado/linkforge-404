<?php
/**
 * WP-CLI commands for LinkForge 404.
 *
 * @package LinkForge404
 */

namespace LinkForge404;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manage 404 redirects and logs from the command line.
 *
 * ## EXAMPLES
 *
 *     wp linkforge redirect list
 *     wp linkforge redirect add /old-page /new-page --status=301
 *     wp linkforge redirect delete 42
 *     wp linkforge flush
 *     wp linkforge export --format=apache
 *     wp linkforge stats
 */
class Linkforge_CLI {

    /**
     * List all redirects.
     *
     * ## OPTIONS
     *
     * [--active]
     * : Show only active redirects.
     *
     * [--format=<format>]
     * : Output format. table, csv, json, yaml.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp linkforge redirect list --active --format=json
     *
     * @subcommand list
     */
    public function redirect_list( $args, $assoc_args ) {
        $model = new Linkforge_Redirects();
        $items = $model->list( 1, 9999 );

        if ( isset( $assoc_args['active'] ) ) {
            $items['items'] = array_filter( $items['items'], function ( $item ) {
                return (int) $item->is_active === 1;
            } );
        }

        if ( empty( $items['items'] ) ) {
            WP_CLI::log( 'No redirects found.' );
            return;
        }

        $fields = [ 'id', 'url_from', 'url_to', 'match_type', 'status_code', 'is_active', 'hit_count' ];
        $format = $assoc_args['format'] ?? 'table';

        WP_CLI\Utils\format_items( $format, $items['items'], $fields );
    }

    /**
     * Add a new redirect.
     *
     * ## OPTIONS
     *
     * <source>
     * : Source URL path (e.g. /old-page).
     *
     * <target>
     * : Target URL (absolute or relative).
     *
     * [--type=<type>]
     * : Match type.
     * ---
     * default: exact
     * options:
     *   - exact
     *   - regex
     * ---
     *
     * [--status=<code>]
     * : HTTP status code.
     * ---
     * default: 301
     * options:
     *   - 301
     *   - 302
     *   - 307
     *   - 410
     * ---
     *
     * ## EXAMPLES
     *
     *     wp linkforge redirect add /old /new --status=301
     *     wp linkforge redirect add '^/blog/(\d+)' '/posts/$1' --type=regex --status=302
     *
     * @subcommand add
     */
    public function redirect_add( $args, $assoc_args ) {
        list( $source, $target ) = $args;

        $model = new Linkforge_Redirects();
        $id    = $model->create(
            $source,
            $target,
            $assoc_args['type'] ?? 'exact',
            (int) ( $assoc_args['status'] ?? 301 )
        );

        if ( $id ) {
            WP_CLI::success( "Redirect #{$id} created: {$source} → {$target}" );
        } else {
            WP_CLI::error( 'Failed to create redirect.' );
        }
    }

    /**
     * Delete a redirect by ID.
     *
     * ## OPTIONS
     *
     * <id>
     * : Redirect ID to delete.
     *
     * ## EXAMPLES
     *
     *     wp linkforge redirect delete 42
     *
     * @subcommand delete
     */
    public function redirect_delete( $args ) {
        $model  = new Linkforge_Redirects();
        $result = $model->delete( (int) $args[0] );

        if ( $result ) {
            WP_CLI::success( "Redirect #{$args[0]} deleted." );
        } else {
            WP_CLI::error( "Redirect #{$args[0]} not found." );
        }
    }

    /**
     * Flush the 404 log buffer into the database.
     *
     * Forces an immediate buffer flush instead of waiting for the scheduled cron event.
     *
     * ## EXAMPLES
     *
     *     wp linkforge flush
     */
    public function flush() {
        $privacy = new Linkforge_Privacy();
        $logger  = new Linkforge_Logger( $privacy );
        $logger->flush_buffer();
        WP_CLI::success( 'Log buffer flushed to database.' );
    }

    /**
     * Run garbage collection on old log entries.
     *
     * Removes log entries older than the configured retention period.
     *
     * ## EXAMPLES
     *
     *     wp linkforge gc
     *
     * @subcommand gc
     */
    public function garbage_collect() {
        $gc = new Linkforge_Garbage_Collector();
        $gc->run();
        WP_CLI::success( 'Garbage collection completed.' );
    }

    /**
     * Export redirect rules for a web server.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Server format.
     * ---
     * default: apache
     * options:
     *   - apache
     *   - nginx
     * ---
     *
     * [--output=<file>]
     * : Write rules to a file instead of stdout.
     *
     * ## EXAMPLES
     *
     *     wp linkforge export --format=nginx
     *     wp linkforge export --format=apache --output=/tmp/redirects.conf
     */
    public function export( $args, $assoc_args ) {
        $model  = new Linkforge_Redirects();
        $format = $assoc_args['format'] ?? 'apache';
        $rules  = $model->export_server_rules( $format );

        if ( empty( $rules ) ) {
            WP_CLI::warning( 'No active redirects to export.' );
            return;
        }

        if ( isset( $assoc_args['output'] ) ) {
            file_put_contents( $assoc_args['output'], $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions
            WP_CLI::success( "Rules written to {$assoc_args['output']}" );
        } else {
            WP_CLI::log( $rules );
        }
    }

    /**
     * Show plugin statistics.
     *
     * ## EXAMPLES
     *
     *     wp linkforge stats
     */
    public function stats() {
        global $wpdb;

        $redirects_table = $wpdb->prefix . 'linkforge_redirects';
        $logs_table      = $wpdb->prefix . 'linkforge_logs';

        $active_redirects  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$redirects_table} WHERE is_active = 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total_redirects   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$redirects_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $redirect_hits     = (int) $wpdb->get_var( "SELECT COALESCE(SUM(hit_count), 0) FROM {$redirects_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $unresolved_404s   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total_404_hits    = (int) $wpdb->get_var( "SELECT COALESCE(SUM(hit_count), 0) FROM {$logs_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        $cache_type = wp_using_ext_object_cache() ? 'external (Redis/Memcached)' : 'built-in';

        $items = [
            [ 'metric' => 'Active Redirects',  'value' => "{$active_redirects} / {$total_redirects}" ],
            [ 'metric' => 'Total Redirect Hits', 'value' => number_format( $redirect_hits ) ],
            [ 'metric' => 'Unresolved 404s',    'value' => number_format( $unresolved_404s ) ],
            [ 'metric' => 'Total 404 Hits',     'value' => number_format( $total_404_hits ) ],
            [ 'metric' => 'Object Cache',       'value' => $cache_type ],
            [ 'metric' => 'IP Anonymization',   'value' => get_option( 'linkforge_anonymize_ip', true ) ? 'enabled' : 'disabled' ],
            [ 'metric' => 'Plugin Version',     'value' => LINKFORGE_VERSION ],
        ];

        WP_CLI\Utils\format_items( 'table', $items, [ 'metric', 'value' ] );
    }
}
