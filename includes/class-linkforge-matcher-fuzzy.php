<?php
/**
 * Fuzzy Matcher — Jaro-Winkler similarity against published content.
 *
 * Phase 2 implementation of PRD §5.1 FR-403.
 *
 * Compares the requested 404 slug against all published post/page permalinks
 * using the Jaro-Winkler distance algorithm. Returns the best match if it
 * exceeds the configurable similarity threshold (default 0.85).
 *
 * Performance: Results are cached in a transient for 1 hour to avoid
 * recomputing the slug index on every 404 request.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Matcher_Fuzzy {

    /**
     * Transient key for the slug→URL index cache.
     */
    private const CACHE_KEY = 'linkforge_slug_index';

    /**
     * How long to cache the slug index (seconds).
     */
    private const CACHE_TTL = 3600; // 1 hour.

    /**
     * Find the best fuzzy match for a 404 URL.
     *
     * @param string $url       The requested 404 path (e.g. "/abut-us").
     * @param float  $threshold Minimum similarity score (0.0–1.0).
     * @return object{url_to: string, status_code: int}|null
     */
    public function find_best_match( string $url, float $threshold = 0.85 ): ?object {
        $slug = $this->extract_slug( $url );

        if ( '' === $slug || strlen( $slug ) < 2 ) {
            return null;
        }

        $index    = $this->get_slug_index();
        $best_url = null;
        $best_score = 0.0;

        foreach ( $index as $candidate_slug => $candidate_url ) {
            $score = $this->jaro_winkler( $slug, (string) $candidate_slug );

            if ( $score > $best_score ) {
                $best_score = $score;
                $best_url   = $candidate_url;
            }
        }

        if ( $best_score >= $threshold && null !== $best_url ) {
            return (object) [
                'url_to'      => $best_url,
                'status_code' => 301,
            ];
        }

        return null;
    }

    /**
     * Build or retrieve the slug → permalink index.
     *
     * Indexes all published posts, pages, and custom post types that have
     * public URLs. Cached as a transient for CACHE_TTL seconds.
     *
     * @return array<string, string> slug → relative permalink.
     */
    private function get_slug_index(): array {
        $cached = get_transient( self::CACHE_KEY );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $index = [];

        // Get all public post types.
        $post_types = get_post_types( [ 'public' => true ], 'names' );

        $posts = get_posts( [
            'post_type'      => array_values( $post_types ),
            'post_status'    => 'publish',
            'posts_per_page' => 5000, // Safety limit.
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ] );

        foreach ( $posts as $post_id ) {
            $permalink = get_permalink( $post_id );

            if ( ! $permalink ) {
                continue;
            }

            // Store the relative path as the index value.
            $path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
            $slug = $this->extract_slug( $path );

            if ( '' !== $slug ) {
                $index[ $slug ] = $path;
            }
        }

        // Also index taxonomy term archives.
        $taxonomies = get_taxonomies( [ 'public' => true ], 'names' );

        foreach ( $taxonomies as $taxonomy ) {
            $terms = get_terms( [
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'number'     => 1000,
            ] );

            if ( is_wp_error( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $link = get_term_link( $term );

                if ( is_wp_error( $link ) ) {
                    continue;
                }

                $path = (string) wp_parse_url( $link, PHP_URL_PATH );
                $slug = $this->extract_slug( $path );

                if ( '' !== $slug ) {
                    $index[ $slug ] = $path;
                }
            }
        }

        set_transient( self::CACHE_KEY, $index, self::CACHE_TTL );

        return $index;
    }

    /**
     * Invalidate the slug index cache.
     *
     * Called when posts are created, updated, or deleted so that the
     * fuzzy matcher sees fresh data.
     */
    public static function invalidate_cache(): void {
        delete_transient( self::CACHE_KEY );
    }

    /**
     * Extract the last meaningful slug segment from a URL path.
     *
     * "/blog/my-great-post/" → "my-great-post"
     * "/about-us"            → "about-us"
     */
    private function extract_slug( string $path ): string {
        $path = strtok( $path, '?' ) ?: $path;
        $path = trim( $path, '/' );

        if ( '' === $path ) {
            return '';
        }

        $segments = explode( '/', $path );

        return strtolower( end( $segments ) );
    }

    /*──────────────────────────────────────────────────────────────
     | Jaro-Winkler Distance Algorithm
     |
     | Pure PHP implementation — no external dependencies.
     | Returns a similarity score between 0.0 (no match) and
     | 1.0 (identical strings).
     ──────────────────────────────────────────────────────────────*/

    /**
     * Calculate the Jaro-Winkler similarity between two strings.
     *
     * @param string $s1 First string.
     * @param string $s2 Second string.
     * @param float  $p  Winkler scaling factor (default 0.1, max 0.25).
     * @return float Similarity score 0.0–1.0.
     */
    private function jaro_winkler( string $s1, string $s2, float $p = 0.1 ): float {
        if ( $s1 === $s2 ) {
            return 1.0;
        }

        $jaro = $this->jaro( $s1, $s2 );

        // Common prefix length (up to 4 characters).
        $prefix = 0;
        $max_prefix = min( 4, min( strlen( $s1 ), strlen( $s2 ) ) );

        for ( $i = 0; $i < $max_prefix; $i++ ) {
            if ( $s1[ $i ] === $s2[ $i ] ) {
                $prefix++;
            } else {
                break;
            }
        }

        return $jaro + ( $prefix * $p * ( 1.0 - $jaro ) );
    }

    /**
     * Calculate the Jaro distance between two strings.
     *
     * @param string $s1 First string.
     * @param string $s2 Second string.
     * @return float Jaro distance 0.0–1.0.
     */
    private function jaro( string $s1, string $s2 ): float {
        $len1 = strlen( $s1 );
        $len2 = strlen( $s2 );

        if ( 0 === $len1 && 0 === $len2 ) {
            return 1.0;
        }

        if ( 0 === $len1 || 0 === $len2 ) {
            return 0.0;
        }

        // Maximum matching distance.
        $match_distance = max( (int) floor( max( $len1, $len2 ) / 2 ) - 1, 0 );

        $s1_matches = array_fill( 0, $len1, false );
        $s2_matches = array_fill( 0, $len2, false );

        $matches        = 0;
        $transpositions = 0;

        // Find matching characters.
        for ( $i = 0; $i < $len1; $i++ ) {
            $start = max( 0, $i - $match_distance );
            $end   = min( $len2 - 1, $i + $match_distance );

            for ( $j = $start; $j <= $end; $j++ ) {
                if ( $s2_matches[ $j ] || $s1[ $i ] !== $s2[ $j ] ) {
                    continue;
                }

                $s1_matches[ $i ] = true;
                $s2_matches[ $j ] = true;
                $matches++;
                break;
            }
        }

        if ( 0 === $matches ) {
            return 0.0;
        }

        // Count transpositions.
        $k = 0;
        for ( $i = 0; $i < $len1; $i++ ) {
            if ( ! $s1_matches[ $i ] ) {
                continue;
            }

            while ( ! $s2_matches[ $k ] ) {
                $k++;
            }

            if ( $s1[ $i ] !== $s2[ $k ] ) {
                $transpositions++;
            }

            $k++;
        }

        return (
            ( $matches / $len1 ) +
            ( $matches / $len2 ) +
            ( ( $matches - $transpositions / 2 ) / $matches )
        ) / 3.0;
    }
}
