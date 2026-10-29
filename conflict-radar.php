<?php
/**
 * Plugin Name:       Conflict Radar – Troubleshooting & Plugin Conflict Finder
 * Plugin URI:        https://github.com/PixelShinobi/conflict-radar
 * Description:       Find which plugin breaks your site. Conflict Radar runs an automated bisect in a troubleshooting session only you can see, and tells you the culprit — or that it isn't a plugin at all.
 * Version:           0.1.0
 * Author:            Jizhou Yang
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       conflict-radar
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package ConflictRadar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Milestone 1 ships the WordPress-free bisect engine in src/Bisect/ only.
// The plugin bootstrap (troubleshooting mode, admin UI) arrives in Milestone 2.
$conflict_radar_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $conflict_radar_autoload ) ) {
	require_once $conflict_radar_autoload;
}
