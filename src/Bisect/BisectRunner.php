<?php
/**
 * Drives a bisect session to completion.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Bisect;

use RuntimeException;

/**
 * Runs a {@see BisectSession} against an oracle: a callable that takes the
 * set of plugins to activate and answers whether the fault appears.
 *
 * In production the oracle is expensive (apply the set, probe a page), so
 * Milestone 2 drives the session one step per request instead. The runner
 * exists for tests and for any future CLI, where finishing in one process
 * is fine.
 */
final class BisectRunner {

	/**
	 * Run a search to completion and return the finished session.
	 *
	 * @param string[] $candidates Plugin IDs to search.
	 * @param callable $oracle     function (string[] $active): bool —
	 *                             true when the fault appears.
	 * @param int      $max_steps  Safety bound; a valid search over n
	 *                             candidates needs at most 2 + log2(n) + 1
	 *                             tests, so the default is generous.
	 * @return BisectSession The completed session.
	 * @throws RuntimeException When the step bound is exceeded, which
	 *                          means the oracle contradicts itself.
	 */
	public static function run( array $candidates, callable $oracle, int $max_steps = 64 ): BisectSession {
		$session = new BisectSession( $candidates );

		while ( ! $session->is_complete() ) {
			if ( $session->steps_taken() >= $max_steps ) {
				$message = sprintf(
					'Bisect exceeded %d steps; the oracle is inconsistent with a single-culprit search.',
					$max_steps
				);
				throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput -- Engine exception, never printed by this layer; wp-admin escapes what it prints.
			}
			$subset = $session->next_subset();
			$session->record_result( (bool) call_user_func( $oracle, $subset ) );
		}

		return $session;
	}
}
