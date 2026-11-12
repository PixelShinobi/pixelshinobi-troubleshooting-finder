<?php
/**
 * Plugin Name:       PixelShinobi Troubleshooting Finder
 * Plugin URI:        https://github.com/PixelShinobi/pixelshinobi-troubleshooting-finder
 * Description:       Find which plugin breaks your site. PixelShinobi Troubleshooting Finder runs an automated bisect in a troubleshooting session only you can see, and tells you the culprit — or that it isn't a plugin at all.
 * Version:           0.1.1
 * Author:            Jizhou Yang
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pixelshinobi-troubleshooting-finder
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONFLICT_RADAR_VERSION', '0.1.1' );
define( 'CONFLICT_RADAR_FILE', __FILE__ );

// In a release build, Composer's autoloader ships in vendor/. Cloning
// the repo and copying the folder into wp-content/plugins must also
// work, so a tiny PSR-4 fallback covers the no-vendor case.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( $class_name ) {
			$prefix = 'ConflictRadar\\';
			if ( 0 === strpos( $class_name, $prefix ) ) {
				$path = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
				if ( file_exists( $path ) ) {
					require_once $path;
				}
			}
		}
	);
}

ConflictRadar\Plugin::init();

register_activation_hook( __FILE__, array( 'ConflictRadar\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ConflictRadar\\Plugin', 'deactivate' ) );
