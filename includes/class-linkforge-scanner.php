<?php
/**
 * Broken Link Scanner — extracts and asynchronously verifies links from post content.
 *
 * Phase 2 implementation of PRD §5.1 FR-404.
 *
 * On every `save_post` event the scanner:
 *  1. Parses all `<a href="…">` from the post content.
 *  2. Stores them in the `linkforge_links` option keyed by post ID.
 *  3. Schedules a single WP-Cron event to check the links asynchronously.
 *
 * The cron callback performs HTTP HEAD requests (with GET fallback) and
 * records any broken links (4xx/5xx or timeout) as warnings in the
 * `linkforge_broken_links` option so the admin dashboard can surface them.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Scanner {

    /**
     * WP-Cron hook used by the async checker.
     */
    public const CRON_HOOK = 'linkforge_check_links';

    /**
     * HTTP timeout per request (seconds).
     */
    private const REQUEST_TIMEOUT = 10;

    /**
     * Maximum number of links to check per cron run (rate-limit).
     */
    private const BATCH_SIZE = 50;

    /**
     * Option key → pending link queue.
     */
    private const OPT_QUEUE = 'linkforge_link_queue';

    /**
     * Option key → broken links report.
     */
    private const OPT_BROKEN = 'linkforge_broken_links';

    /**
     * Register the WP-Cron callback.
     *
     * Call once during plugin init so the hook is always available.
     */
    public static function register_cron(): void {
        add_action( self::CRON_HOOK, [ new self(), 'check_links' ] );
    }

    /**
     * Extract all href values from HTML content.
     *
     * Only external/internal http(s) links are returned; anchors, mailto,
     * tel, javascript, and data URIs are excluded.
     *
     * @param string $content Post content (raw HTML).
     * @return string[] Unique absolute URLs found in the content.
     */
    public static function extract_links( string $content ): array {
        if ( '' === trim( $content ) ) {
            return [];
        }

        // Match href attributes (single or double quoted).
        if ( ! preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content, $matches ) ) {
            return [];
        }

        $site_url = trailingslashit( home_url() );
        $links    = [];

        foreach ( $matches[1] as $raw ) {
            $url = trim( $raw );

            // Skip non-http schemes.
            if ( preg_match( '/^(mailto:|tel:|javascript:|data:|#)/i', $url ) ) {
                continue;
            }

            // Convert relative URLs to absolute.
            if ( 0 === strpos( $url, '/' ) ) {
                $url = rtrim( home_url(), '/' ) . $url;
            }

            // Only keep http/https.
            if ( ! preg_match( '/^https?:\/\//i', $url ) ) {
                continue;
            }

            $links[] = $url;
        }

        return array_values( array_unique( $links ) );
    }

    /**
     * Queue links for async checking.
     *
     * Merges the new links into the pending queue and schedules a
     * single WP-Cron event (if not already scheduled).
     *
     * @param int      $post_id Post ID the links belong to.
     * @param string[] $links   Array of absolute URLs.
     */
    public static function schedule_check( int $post_id, array $links ): void {
        if ( empty( $links ) ) {
            return;
        }

        $queue = get_option( self::OPT_QUEUE, [] );

        if ( ! is_array( $queue ) ) {
            $queue = [];
        }

        $queue[ $post_id ] = [
            'links'     => $links,
            'queued_at' => time(),
        ];

        update_option( self::OPT_QUEUE, $queue, false );

        // Schedule the cron if not already pending.
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + 30, self::CRON_HOOK );
        }
    }

    /**
     * WP-Cron callback — check queued links and record broken ones.
     *
     * Processes up to BATCH_SIZE links per run. Any remaining links
     * stay in the queue for the next invocation.
     */
    public function check_links(): void {
        $queue = get_option( self::OPT_QUEUE, [] );

        if ( ! is_array( $queue ) || empty( $queue ) ) {
            return;
        }

        $broken  = get_option( self::OPT_BROKEN, [] );
        $checked = 0;

        if ( ! is_array( $broken ) ) {
            $broken = [];
        }

        foreach ( $queue as $post_id => $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['links'] ) ) {
                unset( $queue[ $post_id ] );
                continue;
            }

            $remaining = [];

            foreach ( $entry['links'] as $url ) {
                if ( $checked >= self::BATCH_SIZE ) {
                    $remaining[] = $url;
                    continue;
                }

                $result = $this->ping( $url );
                $checked++;

                if ( $result['broken'] ) {
                    $broken[ $post_id ][ $url ] = [
                        'status'     => $result['status'],
                        'reason'     => $result['reason'],
                        'checked_at' => time(),
                    ];
                } else {
                    // Remove from broken list if it was there before (link fixed).
                    unset( $broken[ $post_id ][ $url ] );
                }
            }

            if ( ! empty( $remaining ) ) {
                $queue[ $post_id ]['links'] = $remaining;
            } else {
                unset( $queue[ $post_id ] );
            }

            // Clean up empty post entries in broken list.
            if ( isset( $broken[ $post_id ] ) && empty( $broken[ $post_id ] ) ) {
                unset( $broken[ $post_id ] );
            }
        }

        update_option( self::OPT_QUEUE, $queue, false );
        update_option( self::OPT_BROKEN, $broken, false );

        // Re-schedule if there are still items in the queue.
        if ( ! empty( $queue ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + 60, self::CRON_HOOK );
        }
    }

    /**
     * Perform an HTTP HEAD (then GET fallback) to verify a URL.
     *
     * @param string $url Absolute URL.
     * @return array{broken: bool, status: int, reason: string}
     */
    private function ping( string $url ): array {
        $args = [
            'timeout'     => self::REQUEST_TIMEOUT,
            'redirection' => 5,
            'sslverify'   => false, // Many sites have cert issues; we just test reachability.
            'user-agent'  => 'LinkForge404-Scanner/1.0 (broken-link-check)',
        ];

        // Try HEAD first (cheaper).
        $response = wp_remote_head( $url, $args );

        if ( is_wp_error( $response ) ) {
            // Fallback to GET — some servers block HEAD.
            $response = wp_remote_get( $url, $args );

            if ( is_wp_error( $response ) ) {
                return [
                    'broken' => true,
                    'status' => 0,
                    'reason' => $response->get_error_message(),
                ];
            }
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        // 2xx / 3xx → OK, 4xx / 5xx → broken, 0 → unreachable.
        if ( $code >= 400 || 0 === $code ) {
            return [
                'broken' => true,
                'status' => $code,
                'reason' => wp_remote_retrieve_response_message( $response ) ?: 'HTTP ' . $code,
            ];
        }

        return [
            'broken' => false,
            'status' => $code,
            'reason' => '',
        ];
    }

    /**
     * Get all currently known broken links, optionally filtered by post.
     *
     * @param int|null $post_id Optional post ID filter.
     * @return array<int, array<string, array{status: int, reason: string, checked_at: int}>>
     */
    public static function get_broken_links( ?int $post_id = null ): array {
        $broken = get_option( self::OPT_BROKEN, [] );

        if ( ! is_array( $broken ) ) {
            return [];
        }

        if ( null !== $post_id ) {
            return isset( $broken[ $post_id ] ) ? [ $post_id => $broken[ $post_id ] ] : [];
        }

        return $broken;
    }

    /**
     * Clear broken-link data for a specific post (e.g. on delete).
     *
     * @param int $post_id Post ID.
     */
    public static function clear_post( int $post_id ): void {
        $broken = get_option( self::OPT_BROKEN, [] );

        if ( is_array( $broken ) && isset( $broken[ $post_id ] ) ) {
            unset( $broken[ $post_id ] );
            update_option( self::OPT_BROKEN, $broken, false );
        }

        $queue = get_option( self::OPT_QUEUE, [] );

        if ( is_array( $queue ) && isset( $queue[ $post_id ] ) ) {
            unset( $queue[ $post_id ] );
            update_option( self::OPT_QUEUE, $queue, false );
        }
    }
}
