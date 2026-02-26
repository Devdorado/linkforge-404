<?php
/**
 * Plugin deactivation handler.
 *
 * Cleans up scheduled events. Does NOT drop tables (that happens in uninstall.php).
 *
 * @package LinkForge404
 */

declare(strict_types=1);

namespace LinkForge404;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Linkforge_Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'linkforge_flush_log_buffer' );
        wp_clear_scheduled_hook( 'linkforge_garbage_collect' );
        wp_clear_scheduled_hook( 'linkforge_resolve_chains' );
        wp_clear_scheduled_hook( 'linkforge_broken_link_scan' );
        wp_clear_scheduled_hook( 'linkforge_ai_batch_process' );

        flush_rewrite_rules();
    }
}
