<?php
/**
 * Admin controller — menus, settings, asset enqueueing.
 *
 * PRD §5.5: Admin Dashboard with 404 log overview, bulk actions,
 * redirect management, and settings.
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Admin {

    /**
     * Hook capability required for the main menu.
     */
    private const CAPABILITY = 'manage_options';

    /**
     * Register all admin hooks.
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Plugin action links.
        add_filter( 'plugin_action_links_' . LINKFORGE_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );

        // Admin AJAX for legacy (non-REST) operations.
        add_action( 'wp_ajax_linkforge_export_rules', [ $this, 'ajax_export_rules' ] );
    }

    /**
     * Register admin menu and submenus.
     */
    public function register_menus(): void {
        add_menu_page(
            __( 'LinkForge 404', 'linkforge-404' ),
            __( 'LinkForge 404', 'linkforge-404' ),
            self::CAPABILITY,
            'linkforge-404',
            [ $this, 'render_dashboard' ],
            'dashicons-randomize',
            81
        );

        add_submenu_page(
            'linkforge-404',
            __( 'Dashboard', 'linkforge-404' ),
            __( 'Dashboard', 'linkforge-404' ),
            self::CAPABILITY,
            'linkforge-404',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'linkforge-404',
            __( 'Redirects', 'linkforge-404' ),
            __( 'Redirects', 'linkforge-404' ),
            self::CAPABILITY,
            'linkforge-redirects',
            [ $this, 'render_redirects' ]
        );

        add_submenu_page(
            'linkforge-404',
            __( 'Settings', 'linkforge-404' ),
            __( 'Settings', 'linkforge-404' ),
            self::CAPABILITY,
            'linkforge-settings',
            [ $this, 'render_settings' ]
        );
    }

    /**
     * Enqueue admin CSS and JS (only on our plugin pages).
     */
    public function enqueue_assets( string $hook_suffix ): void {
        $our_pages = [
            'toplevel_page_linkforge-404',
            'linkforge-404_page_linkforge-redirects',
            'linkforge-404_page_linkforge-settings',
        ];

        if ( ! in_array( $hook_suffix, $our_pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'linkforge-admin',
            LINKFORGE_PLUGIN_URL . 'admin/css/linkforge-admin.css',
            [],
            LINKFORGE_VERSION
        );

        wp_enqueue_script(
            'linkforge-admin',
            LINKFORGE_PLUGIN_URL . 'admin/js/linkforge-admin.js',
            [ 'jquery', 'wp-api-fetch' ],
            LINKFORGE_VERSION,
            true
        );

        wp_localize_script( 'linkforge-admin', 'linkforgeAdmin', [
            'restUrl'  => rest_url( 'linkforge/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'ajaxNonce'=> wp_create_nonce( 'linkforge_admin' ),
            'i18n'     => [
                'confirmDelete'  => __( 'Are you sure you want to delete the selected items?', 'linkforge-404' ),
                'confirmResolve' => __( 'Create redirect rules for the selected 404 entries?', 'linkforge-404' ),
                'saved'          => __( 'Saved successfully.', 'linkforge-404' ),
                'error'          => __( 'An error occurred. Please try again.', 'linkforge-404' ),
            ],
        ] );
    }

    /**
     * Register plugin settings using the Settings API.
     */
    public function register_settings(): void {
        $settings = [
            'linkforge_log_retention_days'   => [ 'type' => 'integer', 'default' => 90 ],
            'linkforge_fuzzy_threshold'      => [ 'type' => 'number',  'default' => 0.85 ],
            'linkforge_buffer_flush_minutes' => [ 'type' => 'integer', 'default' => 5 ],
            'linkforge_rate_limit_per_ip'    => [ 'type' => 'integer', 'default' => 60 ],
            'linkforge_rate_limit_window'    => [ 'type' => 'integer', 'default' => 300 ],
            'linkforge_ignore_extensions'    => [ 'type' => 'string',  'default' => 'ico,css,js,jpg,jpeg,png,gif,svg,webp,woff,woff2,ttf,eot,map' ],
            'linkforge_ip_anonymize'         => [ 'type' => 'boolean', 'default' => true ],
            'linkforge_logging_enabled'      => [ 'type' => 'boolean', 'default' => true ],
            'linkforge_ai_enabled'           => [ 'type' => 'boolean', 'default' => false ],
            'linkforge_ai_api_key'           => [ 'type' => 'string',  'default' => '' ],
            'linkforge_ai_confidence'        => [ 'type' => 'number',  'default' => 0.85 ],
            'linkforge_ai_daily_budget'      => [ 'type' => 'integer', 'default' => 100 ],
            'linkforge_auto_update'          => [ 'type' => 'boolean', 'default' => true ],
        ];

        foreach ( $settings as $name => $args ) {
            register_setting( 'linkforge_settings', $name, [
                'type'              => $args['type'],
                'default'           => $args['default'],
                'sanitize_callback' => match ( $args['type'] ) {
                    'integer' => 'intval',
                    'number'  => 'floatval',
                    'boolean' => 'rest_sanitize_boolean',
                    default   => 'sanitize_text_field',
                },
            ] );
        }
    }

    /**
     * Add "Settings" link to the plugins list.
     *
     * @param array<string> $links Existing action links.
     * @return array<string>
     */
    public function add_action_links( array $links ): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=linkforge-settings' ),
            __( 'Settings', 'linkforge-404' )
        );

        $support_link = sprintf(
            '<a href="mailto:support@devdorado.com">%s</a>',
            __( 'Support', 'linkforge-404' )
        );

        array_unshift( $links, $support_link );
        array_unshift( $links, $settings_link );

        return $links;
    }

    /*──────────────────────────────────────────────────────────────
     | View Renderers
     ──────────────────────────────────────────────────────────────*/

    public function render_dashboard(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'linkforge-404' ) );
        }

        require LINKFORGE_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function render_redirects(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'linkforge-404' ) );
        }

        require LINKFORGE_PLUGIN_DIR . 'admin/views/redirects.php';
    }

    public function render_settings(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'linkforge-404' ) );
        }

        require LINKFORGE_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /*──────────────────────────────────────────────────────────────
     | AJAX Handlers
     ──────────────────────────────────────────────────────────────*/

    /**
     * Export redirect rules as .htaccess or Nginx config.
     */
    public function ajax_export_rules(): void {
        check_ajax_referer( 'linkforge_admin' );

        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $format = sanitize_key( $_POST['format'] ?? 'apache' );
        $model  = new Linkforge_Redirects();
        $output = $model->export_server_rules( $format );

        wp_send_json_success( [ 'rules' => $output ] );
    }
}
