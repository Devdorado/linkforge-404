<?php
/**
 * 404 Interceptor — hooks into template_redirect and runs the rerouting cascade.
 *
 * PRD §5.1 (FR-101 through FR-105) and §7.2 (Rerouting Cascade).
 *
 * Cascade order:
 *   1. Exact match (indexed DB lookup)
 *   2. Regex match (PCRE with capture groups)
 *   3. Fuzzy match — Jaro-Winkler (Phase 2)
 *   4. AI semantic match (Phase 3)
 *   5. Log the miss
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Interceptor {

    /**
     * Maximum redirect hops to follow when resolving chains.
     */
    private const MAX_CHAIN_DEPTH = 5;

    private Linkforge_Logger $logger;

    public function __construct( Linkforge_Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Main 404 handler — hooked at priority 999 on template_redirect.
     *
     * FR-101: Hook in template_redirect with is_404() check.
     * FR-405: Cascade logic: Exact → Regex → Fuzzy → AI → Log.
     */
    public function handle_404(): void {
        if ( ! is_404() ) {
            return;
        }

        // Safety: never let the interceptor crash the site.
        try {
            $this->process_404();
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[LinkForge 404] handle_404 error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            // Fall through silently — let WordPress render its normal 404 template.
        }
    }

    /**
     * Internal 404 processing — separated so handle_404() can wrap it in try-catch.
     */
    private function process_404(): void {
        // Verify the redirects table exists before any DB work.
        if ( ! $this->tables_exist() ) {
            return;
        }

        $requested_url = $this->get_requested_path();

        // FR-204: Skip static assets.
        if ( $this->is_ignored_asset( $requested_url ) ) {
            return;
        }

        // FR-205: Rate limiting.
        if ( $this->is_rate_limited() ) {
            status_header( 429 );
            nocache_headers();
            echo '<h1>429 Too Many Requests</h1>';
            exit;
        }

        // ── Cascade Stage 1: Exact Match (FR-401) ──────────────────
        $match = $this->match_exact( $requested_url );
        if ( $match ) {
            $this->do_redirect( $match, $requested_url );
            return;
        }

        // ── Cascade Stage 2: Regex Match (FR-402) ──────────────────
        $match = $this->match_regex( $requested_url );
        if ( $match ) {
            $this->do_redirect( $match, $requested_url );
            return;
        }

        // ── Cascade Stage 3: Fuzzy Match (FR-403) — Phase 2 ───────
        $match = $this->match_fuzzy( $requested_url );
        if ( $match ) {
            $this->do_redirect( $match, $requested_url );
            return;
        }

        // ── Cascade Stage 4: AI Match (FR-404) — Phase 3 ──────────
        // Queued for async processing; no synchronous redirect.

        // ── Cascade Stage 5: Log the miss (FR-201/202) ────────────
        $this->logger->log_404( $requested_url );
    }

    /**
     * Verify that the plugin's DB tables exist.
     * Prevents fatal SQL errors if activation failed or tables were dropped.
     */
    private function tables_exist(): bool {
        global $wpdb;

        // Cache the result for the duration of the request.
        static $exists = null;
        if ( null !== $exists ) {
            return $exists;
        }

        $table  = $wpdb->prefix . 'linkforge_redirects';
        $result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $exists = ( null !== $result );

        return $exists;
    }

    /**
     * Get the requested path relative to the home URL.
     */
    private function get_requested_path(): string {
        $home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
        $request   = isset( $_SERVER['REQUEST_URI'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '';

        // Strip home path prefix and query string.
        if ( '' !== $home_path && str_starts_with( $request, $home_path ) ) {
            $request = substr( $request, strlen( $home_path ) );
        }

        $request = strtok( $request, '?' ) ?: $request;

        return '/' . ltrim( $request, '/' );
    }

    /**
     * FR-204: Check if the URL points to a static asset that should be ignored.
     */
    private function is_ignored_asset( string $url ): bool {
        $ignored_csv = (string) get_option( 'linkforge_ignore_extensions', 'ico,css,js,jpg,jpeg,png,gif,svg,webp,woff,woff2,ttf,eot,map' );
        $extensions  = array_map( 'trim', explode( ',', $ignored_csv ) );

        $path = strtok( $url, '?' ) ?: $url;
        $ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        return in_array( $ext, $extensions, true );
    }

    /**
     * FR-205: Check if the current IP has exceeded the rate limit.
     */
    private function is_rate_limited(): bool {
        $limit  = (int) get_option( 'linkforge_rate_limit_per_ip', 60 );
        $window = (int) get_option( 'linkforge_rate_limit_window', 300 );

        if ( $limit <= 0 ) {
            return false;
        }

        $ip  = $this->get_client_ip();
        $key = 'linkforge_rl_' . md5( $ip );

        $count = (int) get_transient( $key );

        if ( $count >= $limit ) {
            return true;
        }

        set_transient( $key, $count + 1, $window );

        return false;
    }

    /**
     * Stage 1: Exact match lookup — O(log n) via B-Tree index.
     *
     * @return object{url_to: string, status_code: int}|null
     */
    private function match_exact( string $url ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'linkforge_redirects';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT url_to, status_code FROM {$table} WHERE url_from = %s AND match_type = 'exact' AND is_active = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $url
        ) );

        return $row ?: null;
    }

    /**
     * Stage 2: Regex match — iterate over regex rules with PCRE.
     *
     * Typically < 50 rules so O(n) iteration is acceptable.
     *
     * @return object{url_to: string, status_code: int}|null
     */
    private function match_regex( string $url ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'linkforge_redirects';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rules = $wpdb->get_results(
            "SELECT url_from, url_to, status_code FROM {$table} WHERE match_type = 'regex' AND is_active = 1 ORDER BY id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        if ( ! $rules ) {
            return null;
        }

        foreach ( $rules as $rule ) {
            $pattern = '@' . str_replace( '@', '\\@', $rule->url_from ) . '@i';

            if ( @preg_match( $pattern, $url, $matches ) ) {
                // Substitute capture groups ($1, $2, …) in target URL.
                $target = $rule->url_to;
                for ( $i = 1, $max = count( $matches ); $i < $max; $i++ ) {
                    $target = str_replace( '$' . $i, $matches[ $i ], $target );
                }

                return (object) [
                    'url_to'      => $target,
                    'status_code' => (int) $rule->status_code,
                ];
            }
        }

        return null;
    }

    /**
     * Stage 3: Fuzzy match — Jaro-Winkler similarity against published post slugs.
     *
     * Phase 2: Compares the 404 path against all published slugs using the
     * Jaro-Winkler distance algorithm and redirects to the best match when
     * the similarity score exceeds the configurable threshold.
     *
     * @return object{url_to: string, status_code: int}|null
     */
    private function match_fuzzy( string $url ): ?object {
        if ( ! class_exists( __NAMESPACE__ . '\\Linkforge_Matcher_Fuzzy' ) ) {
            return null; // Graceful degradation if class file is missing.
        }

        $threshold = (float) get_option( 'linkforge_fuzzy_threshold', 0.85 );
        $matcher   = new Linkforge_Matcher_Fuzzy();

        return $matcher->find_best_match( $url, $threshold );
    }

    /**
     * Execute the redirect, resolving chains first (FR-103, FR-104).
     *
     * @param object $match        Object with url_to and status_code.
     * @param string $original_url The originally requested URL.
     */
    private function do_redirect( object $match, string $original_url ): void {
        $target      = $match->url_to;
        $status_code = (int) $match->status_code;

        // FR-102: Validate status code.
        if ( ! in_array( $status_code, [ 301, 302, 307, 410 ], true ) ) {
            $status_code = 301;
        }

        // 410 Gone — no redirect, just respond.
        if ( 410 === $status_code ) {
            status_header( 410 );
            nocache_headers();
            echo '<h1>410 Gone</h1><p>This resource has been permanently removed.</p>';
            exit;
        }

        // FR-103: Resolve redirect chains (A → B → C becomes A → C).
        $target = $this->resolve_chain( $target );

        // FR-104: Loop detection — target must not equal the original URL.
        if ( $this->normalize_url( $target ) === $this->normalize_url( $original_url ) ) {
            // Loop detected; fall through to 404 logging instead.
            $this->logger->log_404( $original_url );
            return;
        }

        // Increment hit counter.
        $this->increment_hit_count( $original_url );

        // Full URL for external targets; relative for internal.
        if ( ! wp_http_validate_url( $target ) ) {
            $target = home_url( $target );
        }

        // Send redirect.
        nocache_headers();
        wp_redirect( esc_url_raw( $target ), $status_code, 'LinkForge 404' );
        exit;
    }

    /**
     * FR-103: Walk the redirect chain up to MAX_CHAIN_DEPTH hops.
     */
    private function resolve_chain( string $target ): string {
        global $wpdb;

        $table   = $wpdb->prefix . 'linkforge_redirects';
        $visited = [];

        for ( $i = 0; $i < self::MAX_CHAIN_DEPTH; $i++ ) {
            $norm = $this->normalize_url( $target );

            if ( isset( $visited[ $norm ] ) ) {
                break; // Loop detected in chain.
            }

            $visited[ $norm ] = true;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $next = $wpdb->get_var( $wpdb->prepare(
                "SELECT url_to FROM {$table} WHERE url_from = %s AND match_type = 'exact' AND is_active = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $target
            ) );

            if ( ! $next ) {
                break;
            }

            $target = $next;
        }

        return $target;
    }

    /**
     * Increment the hit_count on the matched redirect rule.
     */
    private function increment_hit_count( string $url_from ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'linkforge_redirects';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET hit_count = hit_count + 1 WHERE url_from = %s AND is_active = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $url_from
        ) );
    }

    /**
     * Normalize a URL path for comparison.
     */
    private function normalize_url( string $url ): string {
        $url = strtolower( trim( $url ) );
        $url = strtok( $url, '?' ) ?: $url;

        return '/' . trim( $url, '/' );
    }

    /**
     * Get the client IP address.
     */
    private function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare.
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                // HTTP_X_FORWARDED_FOR may contain multiple IPs; take the first.
                $ip = strtok( $ip, ',' ) ?: $ip;

                if ( filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ) {
                    return trim( $ip );
                }
            }
        }

        return '0.0.0.0';
    }
}
