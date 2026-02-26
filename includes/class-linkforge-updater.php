<?php
/**
 * Self-Updater — automatic updates via GitHub Releases.
 *
 * Hooks into WordPress's native plugin update system so that new releases
 * published on GitHub appear as regular plugin updates in WP-Admin.
 *
 * Flow:
 *  1. WP checks for plugin updates (transient `update_plugins`).
 *  2. This class queries the GitHub Releases API for the latest release.
 *  3. If the remote version is higher than the installed version, it injects
 *     an update entry into the transient.
 *  4. WP shows "Update available" in the plugins list.
 *  5. When the user clicks "Update", WP downloads the ZIP from GitHub.
 *
 * The feature is enabled by default (`linkforge_auto_update` option) and
 * can be toggled in Settings → Updates.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Updater {

    /**
     * GitHub repository in "owner/repo" format.
     */
    private const GITHUB_REPO = 'Devdorado/linkforge-404';

    /**
     * GitHub API endpoint for the latest release.
     */
    private const API_URL = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

    /**
     * Transient key for caching the remote version check (12 hours).
     */
    private const CACHE_KEY = 'linkforge_update_check';

    /**
     * Cache TTL in seconds.
     */
    private const CACHE_TTL = 43200; // 12 hours.

    /**
     * The plugin basename (e.g. "linkforge-404/linkforge-404.php").
     */
    private string $plugin_basename;

    /**
     * Current installed version.
     */
    private string $current_version;

    public function __construct() {
        $this->plugin_basename  = LINKFORGE_PLUGIN_BASENAME;
        $this->current_version  = LINKFORGE_VERSION;
    }

    /**
     * Register all update hooks.
     *
     * Only hooks in when auto-updates are enabled (default: true).
     */
    public function init(): void {
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Inject update info into the transient.
        add_filter( 'site_transient_update_plugins', [ $this, 'check_for_update' ] );

        // Provide plugin info for the "View details" modal.
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );

        // After install, ensure the folder name is correct.
        add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );

        // Clear cache when WP forces a refresh.
        add_action( 'upgrader_process_complete', [ $this, 'clear_cache' ], 10, 0 );
    }

    /**
     * Check whether auto-updates are enabled.
     */
    public function is_enabled(): bool {
        return (bool) get_option( 'linkforge_auto_update', true );
    }

    /**
     * Filter: Inject our update data into the WP update transient.
     *
     * @param object $transient The update_plugins transient value.
     * @return object
     */
    public function check_for_update( object $transient ): object {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote = $this->get_remote_info();

        if ( null === $remote ) {
            return $transient;
        }

        if ( version_compare( $remote['version'], $this->current_version, '>' ) ) {
            $transient->response[ $this->plugin_basename ] = (object) [
                'slug'        => 'linkforge-404',
                'plugin'      => $this->plugin_basename,
                'new_version' => $remote['version'],
                'url'         => 'https://github.com/' . self::GITHUB_REPO,
                'package'     => $remote['zip_url'],
                'icons'       => [],
                'banners'     => [],
                'tested'      => '6.7',
                'requires'    => '6.4',
                'requires_php'=> '8.1',
            ];
        } else {
            // No update — remove from response if it was somehow there.
            unset( $transient->response[ $this->plugin_basename ] );

            $transient->no_update[ $this->plugin_basename ] = (object) [
                'slug'        => 'linkforge-404',
                'plugin'      => $this->plugin_basename,
                'new_version' => $this->current_version,
                'url'         => 'https://github.com/' . self::GITHUB_REPO,
                'package'     => '',
            ];
        }

        return $transient;
    }

    /**
     * Filter: Provide plugin info for the "View version details" modal.
     *
     * @param false|object|array $result The current result.
     * @param string             $action API action ("plugin_information").
     * @param object             $args   Request args.
     * @return false|object
     */
    public function plugin_info( $result, string $action, object $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || 'linkforge-404' !== $args->slug ) {
            return $result;
        }

        $remote = $this->get_remote_info();

        if ( null === $remote ) {
            return $result;
        }

        return (object) [
            'name'            => 'LinkForge 404',
            'slug'            => 'linkforge-404',
            'version'         => $remote['version'],
            'author'          => '<a href="https://devdorado.com">Devdorado</a>',
            'author_profile'  => 'https://devdorado.com',
            'homepage'        => 'https://devdorado.com/linkforge-404',
            'requires'        => '6.4',
            'tested'          => '6.7',
            'requires_php'    => '8.1',
            'downloaded'      => 0,
            'last_updated'    => $remote['published_at'],
            'sections'        => [
                'description'  => 'Enterprise-grade WordPress 404 management with async logging, multi-stage rerouting (Exact → Regex → Fuzzy → AI), and GDPR-compliant privacy-by-design.',
                'changelog'    => $remote['changelog'],
                'support'      => 'Email: <a href="mailto:support@devdorado.com">support@devdorado.com</a><br>GitHub: <a href="https://github.com/' . self::GITHUB_REPO . '/issues">Issues</a>',
            ],
            'download_link'   => $remote['zip_url'],
            'banners'         => [],
        ];
    }

    /**
     * Filter: Rename the extracted folder to match the expected plugin slug.
     *
     * GitHub ZIPs are named "linkforge-404-main" — we need "linkforge-404".
     *
     * @param bool  $response   Install response.
     * @param array $hook_extra Extra hook data.
     * @param array $result     Install result with destination.
     * @return bool|WP_Error
     */
    public function post_install( $response, array $hook_extra, array $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
            return $response;
        }

        global $wp_filesystem;

        $proper_destination = WP_PLUGIN_DIR . '/linkforge-404/';
        $current_destination = $result['destination'] ?? '';

        if ( $current_destination && $current_destination !== $proper_destination ) {
            $wp_filesystem->move( $current_destination, $proper_destination );
        }

        // Re-activate the plugin after update.
        activate_plugin( $this->plugin_basename );

        return $response;
    }

    /**
     * Clear the cached remote info so the next check fetches fresh data.
     */
    public function clear_cache(): void {
        delete_transient( self::CACHE_KEY );
    }

    /**
     * Fetch the latest release info from GitHub (cached).
     *
     * @return array{version: string, zip_url: string, changelog: string, published_at: string}|null
     */
    private function get_remote_info(): ?array {
        $cached = get_transient( self::CACHE_KEY );

        if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
            return $cached;
        }

        $response = wp_remote_get( self::API_URL, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'LinkForge404-Updater/' . $this->current_version,
            ],
        ] );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
            return null;
        }

        // Tag format: "v1.0.1-beta" or "1.0.1" — strip leading "v".
        $version = ltrim( $body['tag_name'], 'vV' );

        // Find the ZIP asset, or fall back to the GitHub source zip.
        $zip_url = $body['zipball_url'] ?? '';

        // Prefer an attached .zip asset named "linkforge-404.zip".
        if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
            foreach ( $body['assets'] as $asset ) {
                if ( isset( $asset['name'] ) && str_contains( $asset['name'], 'linkforge-404' ) && str_ends_with( $asset['name'], '.zip' ) ) {
                    $zip_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        $info = [
            'version'      => $version,
            'zip_url'      => $zip_url,
            'changelog'    => $body['body'] ?? 'No changelog provided.',
            'published_at' => $body['published_at'] ?? '',
        ];

        set_transient( self::CACHE_KEY, $info, self::CACHE_TTL );

        return $info;
    }
}
