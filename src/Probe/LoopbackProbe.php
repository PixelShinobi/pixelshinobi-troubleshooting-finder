<?php
/**
 * Fetches a page from inside the troubleshooting session.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Probe;

use ConflictRadar\Troubleshoot\SessionManager;
use WP_Http_Cookie;

/**
 * The transport half of the bisect's oracle: a loopback request to the
 * site itself, carrying the troubleshooting cookie so the mu-plugin
 * loader applies the session's plugin set to it. The verdict on the
 * response belongs to {@see Probe}; this class only fetches.
 */
final class LoopbackProbe {

	/**
	 * Fetch a URL as the troubleshooting session sees it.
	 *
	 * @param string $url   Absolute URL on this site.
	 * @param string $token The session cookie value.
	 * @return array{status: int, body: string} Status 0 means the
	 *         request itself failed (transport error or timeout).
	 */
	public static function fetch( string $url, string $token ): array {
		$cookie = new WP_Http_Cookie(
			array(
				'name'  => SessionManager::COOKIE,
				'value' => $token,
			)
		);

		$response = wp_remote_get(
			add_query_arg( 'conflict_radar_probe', wp_generate_password( 8, false ), $url ),
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'cookies'     => array( $cookie ),
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 0,
				'body'   => '',
			);
		}

		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => (string) wp_remote_retrieve_body( $response ),
		);
	}
}
