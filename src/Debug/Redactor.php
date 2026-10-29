<?php
/**
 * Scrubs private values out of debug text.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Debug;

/**
 * Makes a debug bundle safe to paste into a public support thread.
 *
 * The bundle is built from site data (versions, plugin list, server
 * values), and some of that data is private: email addresses, absolute
 * filesystem paths, IP addresses and long token-like strings. This
 * redactor is a pure string transform so its behavior is pinned by unit
 * tests; the WordPress layer additionally never collects salts, keys,
 * passwords or auth cookies in the first place.
 *
 * Ordinary text — including URLs — passes through untouched.
 */
final class Redactor {

	/**
	 * Redact private values from a block of text.
	 *
	 * @param string $text The text to scrub.
	 */
	public static function redact( string $text ): string {
		// Email addresses.
		$text = preg_replace(
			'/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
			'[email redacted]',
			$text
		);

		// IPv4 addresses.
		$text = preg_replace(
			'/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
			'[ip redacted]',
			$text
		);

		// Windows absolute paths (drive-letter paths).
		$text = preg_replace(
			'#[A-Za-z]:\\\\(?:[^\s\\\\]+\\\\)*[^\s\\\\]*#',
			'[path redacted]',
			$text
		);

		// Unix absolute paths with at least two segments, such as a
		// wp-content path under a home directory. The lookbehind keeps
		// URL paths intact: their slashes are preceded by a colon, a
		// slash, or a word character.
		$text = preg_replace(
			'#(?<![:\w/])/(?:[A-Za-z0-9._-]+/)+[A-Za-z0-9._-]*#',
			'[path redacted]',
			$text
		);

		// Long hex strings: API keys, hashes, session tokens.
		$text = preg_replace(
			'/\b[0-9a-fA-F]{32,}\b/',
			'[token redacted]',
			$text
		);

		return $text;
	}
}
