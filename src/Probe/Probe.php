<?php
/**
 * Classifies a probed page as faulting or fine.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Probe;

/**
 * The bisect's "oracle", reduced to a pure decision.
 *
 * The WordPress layer fetches a page inside the troubleshooting session;
 * this class decides from the response whether the fault is present.
 * Keeping the decision pure makes it testable without WordPress, and the
 * rules are deliberately conservative — a page that always "faults" (for
 * example because the expected text never appears on it) fails the
 * everything-off baseline too, so the search reports "not a plugin"
 * instead of blaming one.
 */
final class Probe {

	/**
	 * Fault signatures WordPress and PHP leave on a broken page.
	 * "Fatal error:" alone is too weak (a blog post can quote one), so it
	 * only counts in PHP's uncaught-error form.
	 */
	const FAULT_PATTERN = '/(There has been a critical error on this website|Fatal error\s*(?:<\/b>)?\s*:\s+Uncaught)/';

	/**
	 * Decide whether a probed response shows the fault.
	 *
	 * @param int         $status HTTP status code; 0 means the request
	 *                            itself failed (transport error, timeout).
	 * @param string      $body   Response body.
	 * @param string|null $marker Text that must appear on a healthy page
	 *                            (for example the site title). When given,
	 *                            its absence is a fault even on a 200.
	 */
	public static function is_fault( int $status, string $body, $marker = null ): bool {
		if ( 0 === $status || $status >= 500 ) {
			return true;
		}
		if ( 1 === preg_match( self::FAULT_PATTERN, $body ) ) {
			return true;
		}
		if ( null !== $marker && '' !== $marker && false === strpos( $body, $marker ) ) {
			return true;
		}

		return false;
	}
}
