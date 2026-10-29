<?php
/**
 * Owns the troubleshooting session record.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Troubleshoot;

/**
 * A troubleshooting session is a row in the `conflict_radar_session`
 * option (never autoloaded) plus the mu-plugin loader file. Starting a
 * session writes both and sets the session cookie in the admin's
 * browser; stopping removes all three. Nothing here ever writes the
 * real active_plugins option — the loader filters it per request for
 * cookie-carrying requests only (see mu-plugin/).
 *
 * The session record holds: token (the cookie value; stored so the
 * bisect's loopback probe can present it — it is a short-lived secret
 * in the site's own database, the same trust level as the hash),
 * token_hash, user_id, started, expires, plugins (basenames currently
 * enabled inside the session), theme (stylesheet to force, or empty),
 * and self (this plugin's basename, always kept on).
 */
final class SessionManager {

	public const OPTION      = 'conflict_radar_session';
	public const COOKIE      = 'conflict_radar_ts';
	public const LOADER      = 'conflict-radar-troubleshoot.php';
	public const DEFAULT_TTL = 3600;

	/**
	 * The current session record, or null. Expired sessions are deleted
	 * and reported as absent.
	 *
	 * @return array|null
	 */
	public static function current() {
		$session = get_option( self::OPTION );
		if ( ! is_array( $session ) || empty( $session['token_hash'] ) ) {
			return null;
		}
		if ( time() > (int) ( $session['expires'] ?? 0 ) ) {
			self::stop();
			return null;
		}

		return $session;
	}

	/**
	 * Start a session for the given user and set their cookie.
	 *
	 * @param int    $user_id      The admin starting the session.
	 * @param string $theme        Stylesheet slug to force, '' for none.
	 * @param int    $ttl          Seconds the session stays valid.
	 * @return array The stored session record.
	 */
	public static function start( int $user_id, string $theme = '', int $ttl = self::DEFAULT_TTL ): array {
		$token   = wp_generate_password( 43, false );
		$session = array(
			'token'      => $token,
			'token_hash' => hash( 'sha256', $token ),
			'user_id'    => $user_id,
			'started'    => time(),
			'expires'    => time() + $ttl,
			'plugins'    => array(),
			'theme'      => $theme,
			'self'       => plugin_basename( CONFLICT_RADAR_FILE ),
		);

		update_option( self::OPTION, $session, false );
		self::install_loader();
		self::set_cookie( $token, $session['expires'] );

		return $session;
	}

	/**
	 * End any session: record, cookie and loader all go away. Safe to
	 * call when no session exists.
	 */
	public static function stop(): void {
		delete_option( self::OPTION );
		self::remove_loader();
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, '', time() - 3600, '/' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions -- Session cookie for the troubleshooting filter; the VIP restriction does not apply to self-hosted sites.
		}
		unset( $_COOKIE[ self::COOKIE ] );
	}

	/**
	 * Set which plugin basenames are enabled inside the session.
	 *
	 * @param string[] $plugins Plugin basenames.
	 */
	public static function set_plugins( array $plugins ): void {
		$session = self::current();
		if ( null === $session ) {
			return;
		}
		$session['plugins'] = array_values( array_map( 'strval', $plugins ) );
		update_option( self::OPTION, $session, false );
	}

	/**
	 * The URL that ends the session from anywhere, token included. For
	 * display on the settings page only (capability-checked context) —
	 * it is the break-glass exit when wp-admin itself is broken.
	 */
	public static function exit_url(): string {
		$session = self::current();
		if ( null === $session ) {
			return '';
		}

		return home_url( '/?conflict-radar-exit=' . rawurlencode( (string) $session['token'] ) );
	}

	/**
	 * Keep the loader file in step with the session: present while a
	 * session exists, removed when none does. Runs on admin_init as a
	 * watchdog, so a session whose cleanup was interrupted (for example
	 * the plugin fatals mid-request) still heals itself within the hour
	 * — and instantly for admins, who trigger the watchdog.
	 */
	public static function watchdog(): void {
		$has_session = null !== self::current();
		$has_loader  = file_exists( self::loader_path() );
		if ( $has_session && ! $has_loader ) {
			self::install_loader();
		} elseif ( ! $has_session && $has_loader ) {
			self::remove_loader();
		}
	}

	/**
	 * Copy the bundled loader into the must-use plugins directory,
	 * through the WordPress filesystem API.
	 */
	public static function install_loader(): bool {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( ! WP_Filesystem() ) {
				return false;
			}
		}
		$dir = WPMU_PLUGIN_DIR;
		if ( ! $wp_filesystem->is_dir( $dir ) && ! $wp_filesystem->mkdir( $dir ) ) {
			return false;
		}
		$source = plugin_dir_path( CONFLICT_RADAR_FILE ) . 'mu-plugin/' . self::LOADER;
		return (bool) $wp_filesystem->copy( $source, $dir . '/' . self::LOADER, true );
	}

	/**
	 * Remove the loader file, if present.
	 */
	public static function remove_loader(): void {
		$path = self::loader_path();
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Where the loader lives once installed.
	 */
	public static function loader_path(): string {
		return WPMU_PLUGIN_DIR . '/' . self::LOADER;
	}

	/**
	 * Set the session cookie in the current browser.
	 *
	 * @param string $token   The session cookie value.
	 * @param int    $expires Unix timestamp when the cookie expires.
	 */
	private static function set_cookie( string $token, int $expires ): void {
		$_COOKIE[ self::COOKIE ] = $token;
		if ( headers_sent() ) {
			return;
		}
		setcookie( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions -- See stop().
			self::COOKIE,
			$token,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'httponly' => true,
				'secure'   => is_ssl(),
				'samesite' => 'Lax',
			)
		);
	}
}
