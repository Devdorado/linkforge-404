<?php
/**
 * Plugin Name:       LinkForge 404
 * Plugin URI:        https://devdorado.com/linkforge-404
 * Description:       [BETA] Enterprise-taugliches WordPress-Plugin für intelligentes 404-Management und proaktive Fehlerlink-Erkennung. Dieses Plugin wird aktuell intensiv getestet und laufend verbessert. Asynchrones Logging, mehrstufige Rerouting-Algorithmen (Exact, Regex, Fuzzy, KI) und DSGVO-konformes Privacy-by-Design.
 * Version:           1.0.0-beta
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Devdorado
 * Author URI:        https://devdorado.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       linkforge-404
 * Domain Path:       /languages
 * Support:           support@devdorado.com
 *
 * @package LinkForge404
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*──────────────────────────────────────────────────────────────
 | Constants
 ──────────────────────────────────────────────────────────────*/

define( 'LINKFORGE_VERSION', '1.0.0-beta' );
define( 'LINKFORGE_PLUGIN_FILE', __FILE__ );
define( 'LINKFORGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LINKFORGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LINKFORGE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/*──────────────────────────────────────────────────────────────
 | Autoloader
 ──────────────────────────────────────────────────────────────*/

spl_autoload_register( static function ( string $class ): void {
    $prefix    = 'LinkForge404\\';
    $base_dirs = [
        LINKFORGE_PLUGIN_DIR . 'includes/',
        LINKFORGE_PLUGIN_DIR . 'admin/',
        LINKFORGE_PLUGIN_DIR . 'cli/',
    ];

    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }

    $relative = str_replace( $prefix, '', $class );
    $file     = 'class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative ) ) . '.php';

    foreach ( $base_dirs as $dir ) {
        $path = $dir . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
} );

/*──────────────────────────────────────────────────────────────
 | Activation / Deactivation
 |
 | Loaded explicitly — autoloaders are unreliable during the
 | activation sandbox on some hosts.
 ──────────────────────────────────────────────────────────────*/

require_once LINKFORGE_PLUGIN_DIR . 'includes/class-linkforge-activator.php';
require_once LINKFORGE_PLUGIN_DIR . 'includes/class-linkforge-deactivator.php';

register_activation_hook( __FILE__, [ \LinkForge404\Linkforge_Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \LinkForge404\Linkforge_Deactivator::class, 'deactivate' ] );

/*──────────────────────────────────────────────────────────────
 | Bootstrap
 ──────────────────────────────────────────────────────────────*/

add_action( 'plugins_loaded', static function (): void {
    load_plugin_textdomain( 'linkforge-404', false, dirname( LINKFORGE_PLUGIN_BASENAME ) . '/languages' );

    $core = new \LinkForge404\Linkforge_Core();
    $core->init();
}, 10 );

/*──────────────────────────────────────────────────────────────
 | WP-CLI Registration
 ──────────────────────────────────────────────────────────────*/

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'linkforge', \LinkForge404\Linkforge_CLI::class );
}
