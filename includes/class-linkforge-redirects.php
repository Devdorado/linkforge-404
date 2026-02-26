<?php
/**
 * Redirects CRUD model — data access for {prefix}_linkforge_redirects.
 *
 * All queries use $wpdb->prepare() for SQL injection prevention (NFR §6.4).
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Redirects {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'linkforge_redirects';
    }

    /**
     * Create a new redirect rule.
     *
     * @param string $url_from    Source URL path.
     * @param string $url_to      Target URL.
     * @param string $match_type  One of: exact, regex, fuzzy, ai.
     * @param int    $status_code HTTP status code (301, 302, 307, 410).
     * @param int    $group_id    Optional group ID.
     * @return int|false Insert ID or false on failure.
     */
    public function create(
        string $url_from,
        string $url_to,
        string $match_type = 'exact',
        int $status_code = 301,
        int $group_id = 0
    ): int|false {
        global $wpdb;

        $valid_types  = [ 'exact', 'regex', 'fuzzy', 'ai' ];
        $valid_codes  = [ 301, 302, 307, 410 ];
        $match_type   = in_array( $match_type, $valid_types, true ) ? $match_type : 'exact';
        $status_code  = in_array( $status_code, $valid_codes, true ) ? $status_code : 301;

        // Normalize source URL.
        $url_from = '/' . ltrim( $url_from, '/' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $this->table,
            [
                'url_from'    => substr( $url_from, 0, 2048 ),
                'url_to'      => substr( $url_to, 0, 2048 ),
                'match_type'  => $match_type,
                'status_code' => $status_code,
                'group_id'    => $group_id,
                'hit_count'   => 0,
                'is_active'   => 1,
            ],
            [ '%s', '%s', '%s', '%d', '%d', '%d', '%d' ]
        );

        return false !== $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Get a single redirect by ID.
     *
     * @return object|null
     */
    public function get( int $id ): ?object {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $id
        ) );

        return $row ?: null;
    }

    /**
     * Update a redirect rule.
     *
     * @param int                  $id   Redirect ID.
     * @param array<string, mixed> $data Column => value pairs to update.
     * @return bool True on success.
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        $allowed = [ 'url_from', 'url_to', 'match_type', 'status_code', 'group_id', 'is_active' ];
        $update  = [];
        $formats = [];

        foreach ( $data as $key => $value ) {
            if ( ! in_array( $key, $allowed, true ) ) {
                continue;
            }

            $update[ $key ] = $value;
            $formats[]      = is_int( $value ) ? '%d' : '%s';
        }

        if ( empty( $update ) ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update( $this->table, $update, [ 'id' => $id ], $formats, [ '%d' ] );

        return false !== $result;
    }

    /**
     * Delete a redirect rule.
     *
     * @return bool True if a row was deleted.
     */
    public function delete( int $id ): bool {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );

        return $result > 0;
    }

    /**
     * List redirects with pagination.
     *
     * @return array{items: array<object>, total: int, page: int, per_page: int}
     */
    public function list(
        int $page = 1,
        int $per_page = 25,
        string $order_by = 'hit_count',
        string $order = 'DESC'
    ): array {
        global $wpdb;

        $allowed_order_by = [ 'id', 'url_from', 'url_to', 'match_type', 'status_code', 'hit_count', 'is_active', 'created_at', 'updated_at' ];
        $order_by         = in_array( $order_by, $allowed_order_by, true ) ? $order_by : 'hit_count';
        $order            = in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $order ) : 'DESC';

        $offset = ( $page - 1 ) * $per_page;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );

        return [
            'items'    => $items ?: [],
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * Bulk update status (activate/deactivate).
     *
     * @param array<int> $ids       Redirect IDs.
     * @param bool       $is_active New active state.
     * @return int Number of updated rows.
     */
    public function bulk_toggle( array $ids, bool $is_active ): int {
        global $wpdb;

        if ( empty( $ids ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $active       = $is_active ? 1 : 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table} SET is_active = %d WHERE id IN ({$placeholders})",
            $active,
            ...$ids
        ) );

        return (int) $wpdb->rows_affected;
    }

    /**
     * Bulk delete redirect rules.
     *
     * @param array<int> $ids Redirect IDs.
     * @return int Number of deleted rows.
     */
    public function bulk_delete( array $ids ): int {
        global $wpdb;

        if ( empty( $ids ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table} WHERE id IN ({$placeholders})",
            ...$ids
        ) );

        return (int) $wpdb->rows_affected;
    }

    /**
     * Count total active redirects (for dashboard stats).
     */
    public function count_active(): int {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    /**
     * Export all redirects for .htaccess or Nginx generation.
     *
     * @param string $format 'apache' or 'nginx'.
     * @return string Server config block.
     */
    public function export_server_rules( string $format = 'apache' ): string {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rules = $wpdb->get_results(
            "SELECT url_from, url_to, match_type, status_code FROM {$this->table} WHERE is_active = 1 ORDER BY match_type ASC, id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        if ( ! $rules ) {
            return '';
        }

        $lines = [];

        if ( 'nginx' === $format ) {
            $lines[] = '# LinkForge 404 — Generated Nginx Rewrite Rules';
            $lines[] = '# Generated: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';
            $lines[] = '';

            foreach ( $rules as $r ) {
                $flag = 301 === (int) $r->status_code ? 'permanent' : 'redirect';

                if ( 'regex' === $r->match_type ) {
                    $lines[] = "rewrite {$r->url_from} {$r->url_to} {$flag};";
                } else {
                    if ( 410 === (int) $r->status_code ) {
                        $lines[] = "location = {$r->url_from} { return 410; }";
                    } else {
                        $lines[] = "location = {$r->url_from} { return {$r->status_code} {$r->url_to}; }";
                    }
                }
            }
        } else {
            $lines[] = '# LinkForge 404 — Generated Apache Rewrite Rules';
            $lines[] = '# Generated: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';
            $lines[] = '';
            $lines[] = '<IfModule mod_rewrite.c>';
            $lines[] = 'RewriteEngine On';

            foreach ( $rules as $r ) {
                $flag = 301 === (int) $r->status_code ? '[R=301,L]' : "[R={$r->status_code},L]";

                if ( 410 === (int) $r->status_code ) {
                    $flag = '[G,L]';
                }

                if ( 'regex' === $r->match_type ) {
                    $lines[] = "RewriteRule {$r->url_from} {$r->url_to} {$flag}";
                } else {
                    $escaped = preg_quote( ltrim( $r->url_from, '/' ), '/' );
                    $lines[] = "RewriteRule ^{$escaped}$ {$r->url_to} {$flag}";
                }
            }

            $lines[] = '</IfModule>';
        }

        return implode( "\n", $lines ) . "\n";
    }
}
