<?php
/**
 * Tests for the bisect runner: full searches against fake oracles.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Tests\Unit\Bisect;

use ConflictRadar\Bisect\BisectConclusion;
use ConflictRadar\Bisect\BisectRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BisectRunnerTest extends TestCase {

	/**
	 * An oracle whose fault appears exactly when $culprit is active.
	 */
	private function culprit_oracle( $culprit ): callable {
		return function ( array $active ) use ( $culprit ): bool {
			return null !== $culprit && in_array( $culprit, $active, true );
		};
	}

	/**
	 * @dataProvider culprit_positions
	 */
	public function test_finds_the_culprit( string $culprit ): void {
		$candidates = array( 'p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8' );

		$session    = BisectRunner::run( $candidates, $this->culprit_oracle( $culprit ) );
		$conclusion = $session->conclusion();

		$this->assertNotNull( $conclusion );
		$this->assertSame( BisectConclusion::CULPRIT_FOUND, $conclusion->type() );
		$this->assertSame( $culprit, $conclusion->culprit() );
		// 2 baselines + log2(8) narrowing steps + at most 1 confirmation.
		$this->assertLessThanOrEqual( 6, $session->steps_taken() );
	}

	public function culprit_positions(): array {
		return array(
			'first'  => array( 'p1' ),
			'middle' => array( 'p4' ),
			'last'   => array( 'p8' ),
		);
	}

	public function test_single_candidate_needs_no_confirmation_step(): void {
		$session = BisectRunner::run( array( 'only' ), $this->culprit_oracle( 'only' ) );

		$this->assertSame( BisectConclusion::CULPRIT_FOUND, $session->conclusion()->type() );
		$this->assertSame( 'only', $session->conclusion()->culprit() );
		// The everything-on baseline already tested the one plugin alone.
		$this->assertSame( 2, $session->steps_taken() );
	}

	/**
	 * @dataProvider two_candidate_cases
	 */
	public function test_two_candidates( string $culprit ): void {
		$session = BisectRunner::run( array( 'a', 'b' ), $this->culprit_oracle( $culprit ) );

		$this->assertSame( BisectConclusion::CULPRIT_FOUND, $session->conclusion()->type() );
		$this->assertSame( $culprit, $session->conclusion()->culprit() );
		$this->assertLessThanOrEqual( 4, $session->steps_taken() );
	}

	public function two_candidate_cases(): array {
		return array(
			array( 'a' ),
			array( 'b' ),
		);
	}

	public function test_twenty_candidates_stay_logarithmic(): void {
		$candidates = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$candidates[] = 'p' . $i;
		}

		$session = BisectRunner::run( $candidates, $this->culprit_oracle( 'p17' ) );

		$this->assertSame( BisectConclusion::CULPRIT_FOUND, $session->conclusion()->type() );
		$this->assertSame( 'p17', $session->conclusion()->culprit() );
		// Versus up to 20 manual checks.
		$this->assertLessThanOrEqual( 8, $session->steps_taken() );
	}

	public function test_duplicate_candidates_are_deduplicated(): void {
		$session = BisectRunner::run( array( 'a', 'a', 'b', 'b' ), $this->culprit_oracle( 'b' ) );

		$this->assertSame( BisectConclusion::CULPRIT_FOUND, $session->conclusion()->type() );
		$this->assertSame( 'b', $session->conclusion()->culprit() );
		$this->assertLessThanOrEqual( 4, $session->steps_taken() );
	}

	public function test_step_bound_is_enforced(): void {
		$this->expectException( RuntimeException::class );

		BisectRunner::run(
			array( 'a', 'b', 'c', 'd' ),
			$this->culprit_oracle( 'c' ),
			1
		);
	}
}
