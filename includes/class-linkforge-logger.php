<?php
/**
 * Asynchronous 404 Logger — Redis/Memcached primary, flat-file fallback.
 *
 * PRD §5.2 (FR-201 through FR-205) and §7.3 (Async Architecture).
 *
 * Design principle: ZERO database writes in the frontend request lifecycle.
 * Logs are buffered in memory (object cache or temp file) and flushed every
 * 5 minutes via WP Cron / Action Scheduler.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Logger {

    /**
     * Object-cache group for 404 log buffer.
     */
    private const CACHE_GROUP = 'linkforge_log_buffer';

    /**
     * Maximum entries in a single buffer before forced flush.
     */
    private const BUFFER_MAX = 500;

    private Linkforge_Privacy $privacy;

    public function __construct( Linkforge_Privacy $privacy ) {
        $this->privacy = $privacy;
    }

    /**
     * Record a 404 hit into the in-memory buffer.
     *
     * FR-201: In-Memory buffering via object cache (Redis/Memcached).
     * FR-202: Flat-file fallback when no persistent object cache.
     * FR-204: Static asset filtering happens upstream in Interceptor.
     *
     * If immediate logging is enabled (default on sites without persistent
     * cache), writes directly to the DB so entries appear instantly.
     *
     * @param string $url The 404 URL path.
     */
    public function log_404( string $url ): void {
        try {
            if ( ! (bool) get_option( 'linkforge_logging_enabled', true ) ) {
                return;
            }

            $url = substr( $url, 0, 2048 );

            $entry = [
                'url'        => $url,
                'referrer'   => $this->get_referrer(),
                'user_agent' => $this->get_user_agent(),
                'ip_hash'    => $this->privacy->anonymize_ip( $this->get_client_ip() ),
                'timestamp'  => current_time( 'mysql', true ),
            ];

            // Immediate mode: write directly to DB (no buffer delay).
            // Enabled by default so logs appear instantly in the dashboard.
            if ( (bool) get_option( 'linkforge_immediate_logging', true ) ) {
                $this->write_to_db( $this->aggregate_entries( [ $entry ] ) );
                return;
            }

            if ( $this->has_persistent_cache() ) {
                $this->buffer_to_cache( $entry );
            } else {
                $this->buffer_to_file( $entry );
            }
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[LinkForge 404] log_404 error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }
    }

    /**
     * Flush buffered 404 entries to the database.
     *
     * FR-203: Batch INSERT via scheduled event (every 5 min).
     * Called by WP Cron hook `linkforge_flush_log_buffer`.
     */
    public function flush_buffer(): void {
        $entries = $this->has_persistent_cache()
            ? $this->read_cache_buffer()
            : $this->read_file_buffer();

        if ( empty( $entries ) ) {
            return;
        }

        // Aggregate by URL to minimize row count.
        $aggregated = $this->aggregate_entries( $entries );

        $this->write_to_db( $aggregated );
    }

    /*──────────────────────────────────────────────────────────────
     | Object Cache Buffer (Primary — Redis/Memcached)
     ──────────────────────────────────────────────────────────────*/

    /**
     * Check whether a persistent object cache (Redis, Memcached) is available.
     */
    private function has_persistent_cache(): bool {
        return wp_using_ext_object_cache();
    }

    /**
     * Append an entry to the object-cache buffer.
     */
    private function buffer_to_cache( array $entry ): void {
        $buffer = wp_cache_get( 'buffer', self::CACHE_GROUP );

        if ( ! is_array( $buffer ) ) {
            $buffer = [];
        }

        $buffer[] = $entry;

        // Force flush if buffer exceeds max to prevent memory pressure.
        if ( count( $buffer ) >= self::BUFFER_MAX ) {
            $this->write_to_db( $this->aggregate_entries( $buffer ) );
            wp_cache_delete( 'buffer', self::CACHE_GROUP );
            return;
        }

        wp_cache_set( 'buffer', $buffer, self::CACHE_GROUP, 600 );
    }

    /**
     * Read and clear the object-cache buffer.
     *
     * @return array<int, array<string, string>>
     */
    private function read_cache_buffer(): array {
        $buffer = wp_cache_get( 'buffer', self::CACHE_GROUP );
        wp_cache_delete( 'buffer', self::CACHE_GROUP );

        return is_array( $buffer ) ? $buffer : [];
    }

    /*──────────────────────────────────────────────────────────────
     | Flat-File Buffer (Fallback — Shared Hosting)
     ──────────────────────────────────────────────────────────────*/

    /**
     * Get the path to the flat-file log buffer.
     */
    private function get_buffer_file(): string {
        $dir = WP_CONTENT_DIR . '/linkforge/logs';

        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
            // Prevent directory listing / direct access.
            $htaccess = $dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            }
            $index = $dir . '/index.php';
            if ( ! file_exists( $index ) ) {
                file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            }
        }

        return $dir . '/buffer.json';
    }

    /**
     * Append an entry to the flat-file buffer.
     */
    private function buffer_to_file( array $entry ): void {
        $file = $this->get_buffer_file();

        $buffer = [];
        if ( file_exists( $file ) ) {
            $raw    = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $buffer = json_decode( $raw ?: '[]', true ) ?: [];
        }

        $buffer[] = $entry;

        // Force flush if buffer exceeds max.
        if ( count( $buffer ) >= self::BUFFER_MAX ) {
            $this->write_to_db( $this->aggregate_entries( $buffer ) );
            @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            return;
        }

        file_put_contents( $file, wp_json_encode( $buffer ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    /**
     * Read and clear the flat-file buffer.
     *
     * @return array<int, array<string, string>>
     */
    private function read_file_buffer(): array {
        $file = $this->get_buffer_file();

        if ( ! file_exists( $file ) ) {
            return [];
        }

        $raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        return json_decode( $raw ?: '[]', true ) ?: [];
    }

    /*──────────────────────────────────────────────────────────────
     | Aggregation & DB Write
     ──────────────────────────────────────────────────────────────*/

    /**
     * Aggregate multiple entries by URL (dedup + count).
     *
     * @param  array<int, array<string, string>> $entries Raw buffer entries.
     * @return array<string, array<string, mixed>> Keyed by URL.
     */
    private function aggregate_entries( array $entries ): array {
        $map = [];

        foreach ( $entries as $entry ) {
            $url = $entry['url'] ?? '';
            if ( '' === $url ) {
                continue;
            }

            if ( ! isset( $map[ $url ] ) ) {
                $map[ $url ] = [
                    'url'        => $url,
                    'referrer'   => $entry['referrer'] ?? '',
                    'user_agent' => $entry['user_agent'] ?? '',
                    'ip_hash'    => $entry['ip_hash'] ?? '',
                    'hit_count'  => 0,
                    'first_seen' => $entry['timestamp'] ?? current_time( 'mysql', true ),
                    'last_seen'  => $entry['timestamp'] ?? current_time( 'mysql', true ),
                ];
            }

            $map[ $url ]['hit_count']++;
            $map[ $url ]['last_seen'] = $entry['timestamp'] ?? current_time( 'mysql', true );
        }

        return $map;
    }

    /**
     * Write aggregated entries to the linkforge_logs table.
     *
     * Uses INSERT … ON DUPLICATE KEY UPDATE for idempotent upsert.
     *
     * @param array<string, array<string, mixed>> $aggregated URL-keyed aggregated data.
     */
    private function write_to_db( array $aggregated ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'linkforge_logs';

        // Safety: bail if the table does not exist yet.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( null === $table_exists ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[LinkForge 404] write_to_db: table ' . $table . ' does not exist — skipping write.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            return;
        }

        foreach ( $aggregated as $data ) {
            // Check if a log row for this URL already exists (and is unresolved).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE url = %s AND resolved = 0 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $data['url']
            ) );

            if ( $existing_id ) {
                // Aggregate into existing row.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $result = $wpdb->query( $wpdb->prepare(
                    "UPDATE {$table} SET hit_count = hit_count + %d, last_seen = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $data['hit_count'],
                    $data['last_seen'],
                    $existing_id
                ) );

                if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[LinkForge 404] write_to_db UPDATE failed: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
            } else {
                // Insert new row.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $result = $wpdb->insert(
                    $table,
                    [
                        'url'        => substr( $data['url'], 0, 2048 ),
                        'referrer'   => substr( $data['referrer'], 0, 2048 ),
                        'user_agent' => substr( $data['user_agent'], 0, 512 ),
                        'ip_hash'    => substr( $data['ip_hash'], 0, 64 ),
                        'hit_count'  => $data['hit_count'],
                        'first_seen' => $data['first_seen'],
                        'last_seen'  => $data['last_seen'],
                        'resolved'   => 0,
                    ],
                    [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d' ]
                );

                if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[LinkForge 404] write_to_db INSERT failed: ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
            }
        }
    }

    /*──────────────────────────────────────────────────────────────
     | Helpers
     ──────────────────────────────────────────────────────────────*/

    private function get_referrer(): string {
        $ref = isset( $_SERVER['HTTP_REFERER'] )
            ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
            : '';

        return substr( $ref, 0, 2048 );
    }

    private function get_user_agent(): string {
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
            : '';

        return substr( $ua, 0, 512 );
    }

    private function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                $ip = strtok( $ip, ',' ) ?: $ip;

                if ( filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ) {
                    return trim( $ip );
                }
            }
        }

        return '0.0.0.0';
    }
}
