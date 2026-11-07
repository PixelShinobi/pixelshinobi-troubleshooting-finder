<?php
/**
 * PixelShinobi Troubleshooting Finder troubleshooting loader — installed as a must-use plugin.
 *
 * This file is copied into wp-content/mu-plugins while a troubleshooting
 * session exists and removed when the session ends. It must stay
 * STANDALONE: it runs before the main plugin (and possibly without it),
 * so it cannot use PixelShinobi Troubleshooting Finder classes. Style sniffs are excluded for
 * this path in phpcs.xml.dist; keep the code simple and safe by hand.
 *
 * How it works: the admin who started the session carries a cookie with
 * an unguessable token. Requests bearing it — and only those — get a
 * filtered active-plugins list (and optionally a different theme), so
 * visitors keep seeing the real site. The token is the authorization:
 * its SHA-256 hash is stored in the `conflict_radar_session` option.
 *
 * Failsafes, in order: the session expires (default one hour) and stops
 * applying; the exit link below clears it immediately; deleting this
 * file from mu-plugins always ends troubleshooting at once.
 *
 * @package ConflictRadar
 */

// This file is only ever loaded by WordPress as a must-use plugin.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( function () {
	$cr_cookie = 'conflict_radar_ts';
	$cr_token  = '';
	if ( isset( $_COOKIE[ $cr_cookie ] ) && is_string( $_COOKIE[ $cr_cookie ] ) ) {
		$cr_token = $_COOKIE[ $cr_cookie ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Value is only hashed and compared below, never output or stored.
	}

	$cr_session = get_option( 'conflict_radar_session' );
	if ( ! is_array( $cr_session ) || empty( $cr_session['token_hash'] ) || empty( $cr_session['expires'] ) ) {
		return;
	}

	// Expired sessions stop applying everywhere; cleanup happens on init.
	if ( time() > (int) $cr_session['expires'] ) {
		add_action(
			'init',
			function () {
				delete_option( 'conflict_radar_session' );
			}
		);
		return;
	}

	// The exit link carries the token itself, so it works from any
	// browser where the admin can paste it — even if wp-admin is broken.
	$cr_exit = '';
	if ( isset( $_GET['conflict-radar-exit'] ) && is_string( $_GET['conflict-radar-exit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The unguessable token IS the credential here; there is no session nonce to check.
		$cr_exit = $_GET['conflict-radar-exit']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification -- The unguessable token IS the credential here; there is no session nonce to check.
	}
	if ( '' !== $cr_exit && hash_equals( (string) $cr_session['token_hash'], hash( 'sha256', $cr_exit ) ) ) {
		add_action(
			'init',
			function () use ( $cr_cookie ) {
				delete_option( 'conflict_radar_session' );
				setcookie( $cr_cookie, '', time() - 3600, '/' );
				wp_safe_redirect( home_url( '/' ) );
				exit;
			}
		);
		return;
	}

	// Only requests carrying the session cookie are filtered.
	if ( '' === $cr_token || ! hash_equals( (string) $cr_session['token_hash'], hash( 'sha256', $cr_token ) ) ) {
		return;
	}

	$cr_wanted = isset( $cr_session['plugins'] ) && is_array( $cr_session['plugins'] ) ? $cr_session['plugins'] : array();
	$cr_self   = isset( $cr_session['self'] ) ? (string) $cr_session['self'] : '';

	add_filter(
		'pre_option_active_plugins',
		function () use ( $cr_wanted, $cr_self ) {
			// Intersect with the real active list, read straight from
			// the database so this filter cannot feed on itself: a
			// plugin deactivated in another browser mid-session must
			// not spring back to life inside the session.
			global $wpdb;
			$cr_real = array();
			if ( isset( $wpdb ) && $wpdb ) {
				$cr_row = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'active_plugins'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only lookup of the unfiltered option; no user input involved.
				$cr_val = maybe_unserialize( $cr_row );
				if ( is_array( $cr_val ) ) {
					$cr_real = $cr_val;
				}
			}
			$cr_active = array() === $cr_real ? $cr_wanted : array_values( array_intersect( $cr_real, $cr_wanted ) );
			if ( '' !== $cr_self && ! in_array( $cr_self, $cr_active, true ) ) {
				// PixelShinobi Troubleshooting Finder itself always stays on, so the admin
				// can always reach the exit.
				$cr_active[] = $cr_self;
			}
			return $cr_active;
		}
	);

	if ( ! empty( $cr_session['theme'] ) ) {
		$cr_theme = (string) $cr_session['theme'];
		add_filter(
			'pre_option_template',
			function () use ( $cr_theme ) {
				return $cr_theme;
			}
		);
		add_filter(
			'pre_option_stylesheet',
			function () use ( $cr_theme ) {
				return $cr_theme;
			}
		);
	}
} )();
