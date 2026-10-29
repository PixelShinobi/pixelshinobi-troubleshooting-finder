<?php
/**
 * Tests for the bisect session: phases, conclusions, state round-trips.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Tests\Unit\Bisect;

use ConflictRadar\Bisect\BisectConclusion;
use ConflictRadar\Bisect\BisectRunner;
use ConflictRadar\Bisect\BisectSession;
use LogicException;
use PHPUnit\Framework\TestCase;

final class BisectSessionTest extends TestCase {

	public function test_no_candidates_completes_immediately(): void {
		$session = new BisectSession( array() );

		$this->assertTrue( $session->is_complete() );
		$this->assertNull( $session->next_subset() );
		$this->assertSame( BisectConclusion::NO_CANDIDATES, $session->conclusion()->type() );
		$this->assertSame( 0, $session->steps_taken() );
	}

	public function test_not_reproducible_when_the_full_site_is_clean(): void {
		$session = new BisectSession( array( 'a', 'b', 'c' ) );

		$this->assertSame( array( 'a', 'b', 'c' ), $session->next_subset() );
		$session->record_result( false );

		$this->assertTrue( $session->is_complete() );
		$this->assertSame( BisectConclusion::NOT_REPRODUCIBLE, $session->conclusion()->type() );
		$this->assertSame( 1, $session->steps_taken() );
	}

	public function test_not_a_candidate_when_the_fault_survives_everything_off(): void {
		$session = new BisectSession( array( 'a', 'b', 'c' ) );

		$session->next_subset();
		$session->record_result( true );
		$this->assertSame( array(), $session->next_subset() );
		$session->record_result( true );

		$this->assertTrue( $session->is_complete() );
		$this->assertSame( BisectConclusion::NOT_A_CANDIDATE, $session->conclusion()->type() );
		$this->assertSame( 2, $session->steps_taken() );
	}

	public function test_recording_without_a_test_in_progress_throws(): void {
		$session = new BisectSession( array( 'a', 'b' ) );

		$this->expectException( LogicException::class );
		$session->record_result( true );
	}

	public function test_next_subset_is_stable_until_answered(): void {
		$session = new BisectSession( array( 'a', 'b', 'c', 'd' ) );

		$first  = $session->next_subset();
		$second = $session->next_subset();

		$this->assertSame( $first, $second );
	}

	public function test_suspects_shrink_monotonically_while_narrowing(): void {
		$candidates = array();
		for ( $i = 1; $i <= 16; $i++ ) {
			$candidates[] = 'p' . $i;
		}
		$culprit = 'p11';
		$oracle  = function ( array $active ) use ( $culprit ): bool {
			return in_array( $culprit, $active, true );
		};

		$session   = new BisectSession( $candidates );
		$previous  = count( $candidates );
		$narrowing = 0;
		while ( ! $session->is_complete() ) {
			$subset = $session->next_subset();
			$phase  = $session->phase();
			$session->record_result( $oracle( $subset ) );
			if ( BisectSession::PHASE_NARROWING === $phase ) {
				$narrowing++;
				$current = count( $session->suspects() );
				$this->assertLessThan( $previous, $current, 'Suspects must shrink at every narrowing step.' );
				$previous = $current;
			}
		}

		$this->assertGreaterThanOrEqual( 3, $narrowing );
		$this->assertSame( BisectConclusion::CULPRIT_FOUND, $session->conclusion()->type() );
		$this->assertSame( 'p11', $session->conclusion()->culprit() );
		$this->assertSame( array( 'p11' ), $session->suspects() );
		$this->assertCount( 15, $session->cleared() );
	}

	public function test_interaction_is_reported_instead_of_blaming_an_innocent_plugin(): void {
		// The fault appears only when 'a' and 'c' are active together.
		$oracle = function ( array $active ): bool {
			return in_array( 'a', $active, true ) && in_array( 'c', $active, true );
		};

		$session    = BisectRunner::run( array( 'a', 'b', 'c', 'd' ), $oracle );
		$conclusion = $session->conclusion();

		$this->assertSame( BisectConclusion::INTERACTION_SUSPECTED, $conclusion->type() );
		$this->assertNull( $conclusion->culprit() );
		$this->assertSame( array( 'a', 'b', 'c', 'd' ), $conclusion->smallest_fault_set() );
	}

	public function test_serialize_and_restore_mid_search_continues_identically(): void {
		$candidates = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$candidates[] = 'p' . $i;
		}
		$oracle = function ( array $active ): bool {
			return in_array( 'p9', $active, true );
		};

		// Reference run: record every subset the planner asks for.
		$reference = new BisectSession( $candidates );
		$asked     = array();
		while ( ! $reference->is_complete() ) {
			$subset  = $reference->next_subset();
			$asked[] = $subset;
			$reference->record_result( $oracle( $subset ) );
		}

		// Interrupted run: three steps, then a JSON round-trip of the state.
		$restored = new BisectSession( $candidates );
		for ( $i = 0; $i < 3; $i++ ) {
			$subset = $restored->next_subset();
			$this->assertSame( $asked[ $i ], $subset );
			$restored->record_result( $oracle( $subset ) );
		}
		$state    = json_decode( json_encode( $restored->to_array() ), true );
		$restored = BisectSession::from_array( $state );

		$step = 3;
		while ( ! $restored->is_complete() ) {
			$subset = $restored->next_subset();
			$this->assertSame( $asked[ $step ], $subset, 'Restored session must ask the same questions.' );
			$step++;
			$restored->record_result( $oracle( $subset ) );
		}

		$this->assertSame( $reference->steps_taken(), $restored->steps_taken() );
		$this->assertSame( $reference->conclusion()->type(), $restored->conclusion()->type() );
		$this->assertSame( 'p9', $restored->conclusion()->culprit() );
	}

	public function test_a_completed_session_round_trips_through_an_array(): void {
		$oracle = function ( array $active ): bool {
			return in_array( 'b', $active, true );
		};
		$session  = BisectRunner::run( array( 'a', 'b', 'c' ), $oracle );
		$restored = BisectSession::from_array( $session->to_array() );

		$this->assertTrue( $restored->is_complete() );
		$this->assertSame( BisectConclusion::CULPRIT_FOUND, $restored->conclusion()->type() );
		$this->assertSame( 'b', $restored->conclusion()->culprit() );
		$this->assertSame( $session->steps_taken(), $restored->steps_taken() );
	}
}
