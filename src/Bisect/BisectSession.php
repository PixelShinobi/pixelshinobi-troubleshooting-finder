<?php
/**
 * The bisect planner.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Bisect;

use LogicException;

/**
 * Plans a plugin-conflict bisect as a series of yes/no tests.
 *
 * The session never runs a test itself. It answers two questions for its
 * caller: which set of plugins should be active for the next test
 * ({@see next_subset()}), and what the tests so far prove
 * ({@see conclusion()}). The caller runs the test — in Milestone 2, by
 * applying the set in a troubleshooting session and probing a page — and
 * reports the outcome with {@see record_result()}.
 *
 * A search runs in phases:
 *
 *  1. baseline-all:  every candidate active. No fault means the symptom
 *     cannot be reproduced, and there is nothing to find.
 *  2. baseline-none: no candidate active. A fault here means no candidate
 *     causes it (theme, environment, or something outside the list).
 *  3. narrowing:     activate the first half of the suspects. A fault puts
 *     the culprit in that half; no fault puts it in the other half. Each
 *     answer at least halves the suspects.
 *  4. confirm:       one suspect remains. It is blamed only if a test with
 *     it alone active has shown the fault — an earlier step often proves
 *     this already, otherwise one confirmation test runs. If it cannot
 *     fail alone, the break needs a combination of plugins and the
 *     verdict is INTERACTION_SUSPECTED, never a guess.
 *
 * All state is plain data: {@see to_array()} / {@see from_array()}
 * round-trip the session so Milestone 2 can persist it between requests.
 */
final class BisectSession {

	public const PHASE_BASELINE_ALL  = 'baseline-all';
	public const PHASE_BASELINE_NONE = 'baseline-none';
	public const PHASE_NARROWING     = 'narrowing';
	public const PHASE_CONFIRM       = 'confirm';
	public const PHASE_DONE          = 'done';

	/**
	 * Candidate plugin IDs, in the caller's order, deduplicated.
	 *
	 * @var string[]
	 */
	private $candidates;

	/**
	 * Plugins still under suspicion.
	 *
	 * @var string[]
	 */
	private $suspects;

	/**
	 * Plugins proven innocent (deactivated in a test that still failed,
	 * or active in a test that cleared the half they were in).
	 *
	 * @var string[]
	 */
	private $cleared = array();

	/**
	 * One of the PHASE_* constants.
	 *
	 * @var string
	 */
	private $phase;

	/**
	 * The subset handed out by next_subset() and not yet answered.
	 *
	 * @var string[]|null
	 */
	private $awaiting;

	/**
	 * Every answered test: list of ['subset' => string[], 'fault' => bool].
	 *
	 * @var array[]
	 */
	private $history = array();

	/**
	 * Smallest set of plugins seen producing the fault, if any.
	 *
	 * @var string[]|null
	 */
	private $smallest_fault_set;

	/**
	 * The verdict, once the search is done.
	 *
	 * @var BisectConclusion|null
	 */
	private $conclusion;

	/**
	 * Start a search over the given candidates.
	 *
	 * @param string[] $candidates Plugin IDs to search, in a stable order.
	 */
	public function __construct( array $candidates ) {
		$this->candidates = array_values( array_unique( array_map( 'strval', $candidates ) ) );
		$this->suspects   = $this->candidates;

		if ( array() === $this->candidates ) {
			$this->phase      = self::PHASE_DONE;
			$this->conclusion = new BisectConclusion( BisectConclusion::NO_CANDIDATES );
		} else {
			$this->phase = self::PHASE_BASELINE_ALL;
		}
	}

	/**
	 * Whether the search has reached a verdict.
	 */
	public function is_complete(): bool {
		return self::PHASE_DONE === $this->phase;
	}

	/**
	 * The current phase: one of the PHASE_* constants.
	 */
	public function phase(): string {
		return $this->phase;
	}

	/**
	 * The verdict, or null while the search is still running.
	 *
	 * @return BisectConclusion|null
	 */
	public function conclusion() {
		return $this->conclusion;
	}

	/**
	 * Plugins still under suspicion.
	 *
	 * @return string[]
	 */
	public function suspects(): array {
		return $this->suspects;
	}

	/**
	 * Plugins proven innocent so far.
	 *
	 * @return string[]
	 */
	public function cleared(): array {
		return $this->cleared;
	}

	/**
	 * Every answered test, in the order they were answered.
	 *
	 * @return array[]
	 */
	public function history(): array {
		return $this->history;
	}

	/**
	 * How many tests have been answered so far.
	 */
	public function steps_taken(): int {
		return count( $this->history );
	}

	/**
	 * The set of plugins to activate for the next test, or null when the
	 * search is complete. Calling this again before record_result()
	 * returns the same set, so a caller can safely ask once per request.
	 *
	 * @return string[]|null
	 */
	public function next_subset() {
		if ( $this->is_complete() ) {
			return null;
		}
		if ( null !== $this->awaiting ) {
			return $this->awaiting;
		}

		switch ( $this->phase ) {
			case self::PHASE_BASELINE_ALL:
				$this->awaiting = $this->candidates;
				break;
			case self::PHASE_BASELINE_NONE:
				$this->awaiting = array();
				break;
			case self::PHASE_NARROWING:
				$half           = intdiv( count( $this->suspects ) + 1, 2 );
				$this->awaiting = array_slice( $this->suspects, 0, $half );
				break;
			case self::PHASE_CONFIRM:
				$this->awaiting = array( $this->suspects[0] );
				break;
		}

		return $this->awaiting;
	}

	/**
	 * Record the outcome of the test that next_subset() handed out.
	 *
	 * @param bool $fault_present Whether the fault appeared with the
	 *                            tested set active.
	 * @throws LogicException When the search is already complete, or no
	 *                        test is in progress.
	 */
	public function record_result( bool $fault_present ): void {
		if ( $this->is_complete() ) {
			throw new LogicException( 'The search is already complete.' );
		}
		if ( null === $this->awaiting ) {
			throw new LogicException( 'No test in progress: call next_subset() first.' );
		}

		$tested          = $this->awaiting;
		$this->awaiting  = null;
		$this->history[] = array(
			'subset' => $tested,
			'fault'  => $fault_present,
		);

		if ( $fault_present && ( null === $this->smallest_fault_set || count( $tested ) < count( $this->smallest_fault_set ) ) ) {
			$this->smallest_fault_set = $tested;
		}

		switch ( $this->phase ) {
			case self::PHASE_BASELINE_ALL:
				if ( ! $fault_present ) {
					$this->finish( BisectConclusion::NOT_REPRODUCIBLE, null );
				} else {
					$this->phase = self::PHASE_BASELINE_NONE;
				}
				break;

			case self::PHASE_BASELINE_NONE:
				if ( $fault_present ) {
					$this->finish( BisectConclusion::NOT_A_CANDIDATE, null );
				} elseif ( 1 === count( $this->suspects ) ) {
					$this->confirm_or_conclude();
				} else {
					$this->phase = self::PHASE_NARROWING;
				}
				break;

			case self::PHASE_NARROWING:
				if ( $fault_present ) {
					// The fault appeared without the other half, so none
					// of it can be the (single) culprit.
					$this->cleared  = array_values( array_merge( $this->cleared, array_diff( $this->suspects, $tested ) ) );
					$this->suspects = $tested;
				} else {
					$this->cleared  = array_values( array_merge( $this->cleared, $tested ) );
					$this->suspects = array_values( array_diff( $this->suspects, $tested ) );
				}
				if ( 1 === count( $this->suspects ) ) {
					$this->confirm_or_conclude();
				} elseif ( 0 === count( $this->suspects ) ) {
					// Unreachable with a consistent oracle after the
					// baselines, but never blame a plugin on bad data.
					$this->finish( BisectConclusion::INTERACTION_SUSPECTED, null );
				}
				break;

			case self::PHASE_CONFIRM:
				if ( $fault_present ) {
					$this->finish( BisectConclusion::CULPRIT_FOUND, $this->suspects[0] );
				} else {
					$this->finish( BisectConclusion::INTERACTION_SUSPECTED, null );
				}
				break;
		}
	}

	/**
	 * One suspect remains. If a past test already showed the fault with
	 * that plugin alone active, that is proof enough; otherwise ask for
	 * one confirmation test.
	 */
	private function confirm_or_conclude(): void {
		$suspect = $this->suspects[0];
		foreach ( $this->history as $entry ) {
			if ( $entry['fault'] && array( $suspect ) === $entry['subset'] ) {
				$this->finish( BisectConclusion::CULPRIT_FOUND, $suspect );
				return;
			}
		}
		$this->phase = self::PHASE_CONFIRM;
	}

	/**
	 * End the search with a verdict.
	 *
	 * @param string      $type    One of the BisectConclusion constants.
	 * @param string|null $culprit Guilty plugin ID, for CULPRIT_FOUND.
	 */
	private function finish( string $type, $culprit ): void {
		$this->phase      = self::PHASE_DONE;
		$this->conclusion = new BisectConclusion(
			$type,
			$culprit,
			null === $this->smallest_fault_set ? array() : $this->smallest_fault_set
		);
	}

	/**
	 * Serialize to a plain array, for storage between requests.
	 */
	public function to_array(): array {
		return array(
			'candidates'       => $this->candidates,
			'suspects'         => $this->suspects,
			'cleared'          => $this->cleared,
			'phase'            => $this->phase,
			'awaiting'         => $this->awaiting,
			'history'          => $this->history,
			'smallestFaultSet' => $this->smallest_fault_set,
			'conclusion'       => null === $this->conclusion ? null : $this->conclusion->to_array(),
		);
	}

	/**
	 * Restore from {@see to_array()} output.
	 *
	 * @param array $data Output of to_array().
	 */
	public static function from_array( array $data ): self {
		$session                     = new self( (array) $data['candidates'] );
		$session->suspects           = array_values( (array) $data['suspects'] );
		$session->cleared            = array_values( (array) $data['cleared'] );
		$session->phase              = (string) $data['phase'];
		$session->awaiting           = isset( $data['awaiting'] ) ? array_values( (array) $data['awaiting'] ) : null;
		$session->history            = (array) $data['history'];
		$session->smallest_fault_set = isset( $data['smallestFaultSet'] ) ? array_values( (array) $data['smallestFaultSet'] ) : null;
		$session->conclusion         = isset( $data['conclusion'] ) && is_array( $data['conclusion'] )
			? BisectConclusion::from_array( $data['conclusion'] )
			: null;

		return $session;
	}
}
