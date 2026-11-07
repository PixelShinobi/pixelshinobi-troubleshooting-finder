<?php
/**
 * Uninstall: remove every trace of PixelShinobi Troubleshooting Finder.
 *
 * Deletes the session and bisect options (on every site of a
 * multisite) and the must-use loader file. The real active_plugins
 * option was never written by this plugin, so there is nothing to
 * restore.
 *
 * @package ConflictRadar
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$conflict_radar_sites = array( get_current_blog_id() );
if ( is_multisite() && function_exists( 'get_sites' ) ) {
	$conflict_radar_sites = get_sites( array( 'fields' => 'ids' ) );
}

foreach ( $conflict_radar_sites as $conflict_radar_site_id ) {
	switch_to_blog( (int) $conflict_radar_site_id );
	delete_option( 'conflict_radar_session' );
	delete_option( 'conflict_radar_bisect' );
	restore_current_blog();
}

$conflict_radar_loader = ( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' ) . '/conflict-radar-troubleshoot.php';
if ( file_exists( $conflict_radar_loader ) ) {
	wp_delete_file( $conflict_radar_loader );
}
