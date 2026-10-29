<?php
/**
 * Builds the privacy-redacted debug bundle.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Debug;

use ConflictRadar\Troubleshoot\SessionManager;

/**
 * Collects the facts a support thread actually needs — versions, theme,
 * active plugins, limits — in the spirit of Site Health's info tab, and
 * passes the whole bundle through {@see Redactor} before it is shown or
 * downloaded. Salts, keys, passwords and cookies are never collected.
 */
final class DebugInfo {

	/**
	 * The debug bundle as plain text, already redacted.
	 */
	public static function collect(): string {
		global $wp_version;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$theme          = wp_get_theme();

		$lines   = array();
		$lines[] = '=== Conflict Radar debug bundle ===';
		$lines[] = 'Generated: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC — private values (emails, paths, IPs, tokens) are redacted.';
		$lines[] = '';
		$lines[] = '--- Site ---';
		$lines[] = 'Conflict Radar: ' . CONFLICT_RADAR_VERSION;
		$lines[] = 'WordPress: ' . $wp_version . ( is_multisite() ? ' (multisite)' : '' );
		$lines[] = 'PHP: ' . phpversion() . ' (' . php_sapi_name() . ')';
		$lines[] = 'Memory limit: ' . ( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'default' );
		$lines[] = 'Max execution time: ' . (string) ini_get( 'max_execution_time' ) . 's';
		$lines[] = 'WP_DEBUG: ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'on' : 'off' );
		$lines[] = 'Server: ' . self::server_software();
		$lines[] = '';
		$lines[] = '--- Theme ---';
		$lines[] = $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ' (' . $theme->get_stylesheet() . ')';
		if ( $theme->get_template() !== $theme->get_stylesheet() ) {
			$lines[] = 'Parent theme: ' . $theme->get_template();
		}
		$lines[] = '';
		$lines[] = '--- Active plugins (' . count( $active_plugins ) . ') ---';
		foreach ( $active_plugins as $basename ) {
			$data    = isset( $all_plugins[ $basename ] ) ? $all_plugins[ $basename ] : null;
			$lines[] = null === $data
				? $basename
				: $data['Name'] . ' ' . $data['Version'] . ' (' . $basename . ')';
		}
		$lines[] = '';
		$lines[] = '--- Conflict Radar state ---';
		$lines[] = 'Troubleshooting session: ' . ( null === SessionManager::current() ? 'off' : 'on' );

		return Redactor::redact( implode( "\n", $lines ) );
	}

	/**
	 * The web server banner, when the SAPI exposes one.
	 */
	private static function server_software(): string {
		if ( ! isset( $_SERVER['SERVER_SOFTWARE'] ) || ! is_string( $_SERVER['SERVER_SOFTWARE'] ) ) {
			return 'unknown';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
	}
}
