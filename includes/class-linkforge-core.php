<?php
/**
 * Core orchestrator — wires all hooks and components.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Core {

    private Linkforge_Interceptor      $interceptor;
    private Linkforge_Logger           $logger;
    private Linkforge_Privacy          $privacy;
    private Linkforge_Garbage_Collector $garbage_collector;

    /**
     * Initialize all components and register hooks.
     */
    public function init(): void {
        // Custom cron schedule (5 minutes).
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

        // Core components.
        $this->privacy           = new Linkforge_Privacy();
        $this->logger            = new Linkforge_Logger( $this->privacy );
        $this->garbage_collector = new Linkforge_Garbage_Collector();
        $this->interceptor       = new Linkforge_Interceptor( $this->logger );

        // Frontend hook — the main 404 intercept (PRD FR-101).
        add_action( 'template_redirect', [ $this->interceptor, 'handle_404' ], 999 );

        // Async processing hooks.
        add_action( 'linkforge_flush_log_buffer', [ $this->logger, 'flush_buffer' ] );
        add_action( 'linkforge_garbage_collect', [ $this->garbage_collector, 'run' ] );

        // Admin hooks.
        if ( is_admin() ) {
            $admin = new Linkforge_Admin();
            $admin->init();
        }

        // REST API endpoints.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Privacy hooks (GDPR exporter/eraser).
        add_filter( 'wp_privacy_personal_data_exporters', [ $this->privacy, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers', [ $this->privacy, 'register_eraser' ] );

        // Phase 2: Broken link scanner cron callback.
        Linkforge_Scanner::register_cron();

        // Phase 2: Broken link detection on post save.
        add_action( 'save_post', [ $this, 'on_save_post' ], 20, 2 );

        // Phase 2: Invalidate fuzzy-match slug cache when content changes.
        add_action( 'save_post', [ Linkforge_Matcher_Fuzzy::class, 'invalidate_cache' ], 25 );
        add_action( 'delete_post', [ Linkforge_Matcher_Fuzzy::class, 'invalidate_cache' ], 25 );
    }

    /**
     * Add custom cron interval (5 minutes) for log buffer flush.
     *
     * @param array<string,array<string,mixed>> $schedules Existing schedules.
     * @return array<string,array<string,mixed>>
     */
    public function add_cron_schedules( array $schedules ): array {
        $schedules['linkforge_five_minutes'] = [
            'interval' => 300,
            'display'  => esc_html__( 'Every Five Minutes (LinkForge)', 'linkforge-404' ),
        ];

        return $schedules;
    }

    /**
     * Register REST API routes for AJAX dashboard operations.
     */
    public function register_rest_routes(): void {
        register_rest_route( 'linkforge/v1', '/redirects', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_get_redirects' ],
            'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
        ] );

        register_rest_route( 'linkforge/v1', '/redirects', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_create_redirect' ],
            'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
        ] );

        register_rest_route( 'linkforge/v1', '/redirects/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [ $this, 'rest_delete_redirect' ],
            'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
        ] );

        register_rest_route( 'linkforge/v1', '/logs', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_get_logs' ],
            'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
        ] );

        register_rest_route( 'linkforge/v1', '/logs/resolve', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rest_resolve_logs' ],
            'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
        ] );

        register_rest_route( 'linkforge/v1', '/stats', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_get_stats' ],
            'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
        ] );
    }

    /**
     * REST: List redirects.
     */
    public function rest_get_redirects( \WP_REST_Request $request ): \WP_REST_Response {
        $redirects_model = new Linkforge_Redirects();

        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 25 ) ) );
        $order_by = sanitize_key( (string) ( $request->get_param( 'orderby' ) ?: 'hit_count' ) );
        $order    = strtoupper( (string) ( $request->get_param( 'order' ) ?: 'DESC' ) );
        $order    = in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'DESC';

        $result = $redirects_model->list( $page, $per_page, $order_by, $order );

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * REST: Create redirect.
     */
    public function rest_create_redirect( \WP_REST_Request $request ): \WP_REST_Response {
        $redirects_model = new Linkforge_Redirects();

        $url_from    = sanitize_text_field( (string) $request->get_param( 'url_from' ) );
        $url_to      = esc_url_raw( (string) $request->get_param( 'url_to' ) );
        $match_type  = sanitize_key( (string) ( $request->get_param( 'match_type' ) ?: 'exact' ) );
        $status_code = (int) ( $request->get_param( 'status_code' ) ?: 301 );

        if ( empty( $url_from ) ) {
            return new \WP_REST_Response( [ 'error' => 'url_from is required' ], 400 );
        }

        $id = $redirects_model->create( $url_from, $url_to, $match_type, $status_code );

        if ( false === $id ) {
            return new \WP_REST_Response( [ 'error' => 'Failed to create redirect' ], 500 );
        }

        return new \WP_REST_Response( [ 'id' => $id ], 201 );
    }

    /**
     * REST: Delete redirect.
     */
    public function rest_delete_redirect( \WP_REST_Request $request ): \WP_REST_Response {
        $redirects_model = new Linkforge_Redirects();
        $id              = (int) $request->get_param( 'id' );

        $deleted = $redirects_model->delete( $id );

        return new \WP_REST_Response( [ 'deleted' => $deleted ], $deleted ? 200 : 404 );
    }

    /**
     * REST: List 404 logs.
     */
    public function rest_get_logs( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $table    = $wpdb->prefix . 'linkforge_logs';
        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 25 ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe (prefix + constant).

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY hit_count DESC, last_seen DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        return new \WP_REST_Response( [
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ], 200 );
    }

    /**
     * REST: Bulk resolve logs (create redirects from selected 404 entries).
     */
    public function rest_resolve_logs( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $log_ids     = array_map( 'intval', (array) $request->get_param( 'log_ids' ) );
        $url_to      = esc_url_raw( (string) $request->get_param( 'url_to' ) );
        $status_code = (int) ( $request->get_param( 'status_code' ) ?: 301 );

        if ( empty( $log_ids ) ) {
            return new \WP_REST_Response( [ 'error' => 'log_ids required' ], 400 );
        }

        $logs_table      = $wpdb->prefix . 'linkforge_logs';
        $redirects_model = new Linkforge_Redirects();
        $created         = 0;

        foreach ( $log_ids as $log_id ) {
            $log = $wpdb->get_row( $wpdb->prepare(
                "SELECT url FROM {$logs_table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $log_id
            ) );

            if ( ! $log ) {
                continue;
            }

            // Create redirect and mark log as resolved.
            $target = ! empty( $url_to ) ? $url_to : home_url( '/' );
            $rid    = $redirects_model->create( $log->url, $target, 'exact', $status_code );

            if ( false !== $rid ) {
                $wpdb->update( $logs_table, [ 'resolved' => 1 ], [ 'id' => $log_id ], [ '%d' ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                ++$created;
            }
        }

        return new \WP_REST_Response( [ 'created' => $created ], 200 );
    }

    /**
     * REST: Dashboard stats.
     */
    public function rest_get_stats( \WP_REST_Request $request ): \WP_REST_Response {
        global $wpdb;

        $redirects_table = $wpdb->prefix . 'linkforge_redirects';
        $logs_table      = $wpdb->prefix . 'linkforge_logs';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats = [
            'total_redirects'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$redirects_table}" ),
            'active_redirects'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$redirects_table} WHERE is_active = 1" ),
            'total_redirect_hits'=> (int) $wpdb->get_var( "SELECT COALESCE(SUM(hit_count), 0) FROM {$redirects_table}" ),
            'total_404_entries'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table}" ),
            'unresolved_404s'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE resolved = 0" ),
            'total_404_hits'     => (int) $wpdb->get_var( "SELECT COALESCE(SUM(hit_count), 0) FROM {$logs_table}" ),
            'top_404s'           => $wpdb->get_results( "SELECT url, hit_count, last_seen FROM {$logs_table} WHERE resolved = 0 ORDER BY hit_count DESC LIMIT 10" ),
        ];
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return new \WP_REST_Response( $stats, 200 );
    }

    /**
     * Stub: Extract links on post save for broken link detection (Phase 2).
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public function on_save_post( int $post_id, \WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( ! in_array( $post->post_status, [ 'publish', 'draft' ], true ) ) {
            return;
        }

        // Phase 2: Extract links and queue for async broken-link checking.
        $links = Linkforge_Scanner::extract_links( $post->post_content );

        if ( ! empty( $links ) ) {
            Linkforge_Scanner::schedule_check( $post_id, $links );
        }
    }
}
