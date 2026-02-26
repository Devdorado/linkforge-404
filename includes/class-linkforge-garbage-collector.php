<?php
/**
 * Garbage Collector — automatic log retention enforcement.
 *
 * PRD §5.6 FR-602: Configurable retention (30/60/90 days), cron-based cleanup.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Garbage_Collector {

    /**
     * Run the garbage collection.
     *
     * Deletes log entries older than the configured retention period.
     * Called by WP Cron hook `linkforge_garbage_collect` (daily).
     */
    public function run(): void {
        global $wpdb;

        $retention_days = (int) get_option( 'linkforge_log_retention_days', 90 );

        if ( $retention_days <= 0 ) {
            return; // Retention disabled — keep everything.
        }

        $table    = $wpdb->prefix . 'linkforge_logs';
        $cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );

        // Delete in batches to avoid long-running queries on large tables.
        $batch_size = 1000;
        $total      = 0;

        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $deleted = (int) $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table} WHERE first_seen < %s LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $cutoff,
                $batch_size
            ) );

            $total += $deleted;

            // Yield to prevent timeouts on shared hosting.
            if ( $deleted >= $batch_size ) {
                usleep( 50_000 ); // 50ms.
            }
        } while ( $deleted >= $batch_size );

        if ( $total > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[LinkForge 404] Garbage collector removed {$total} log entries older than {$retention_days} days." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
