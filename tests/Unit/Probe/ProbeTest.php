<?php
/**
 * Tests for the probe classifier.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Tests\Unit\Probe;

use ConflictRadar\Probe\Probe;
use PHPUnit\Framework\TestCase;

final class ProbeTest extends TestCase {

	public function test_server_errors_are_faults(): void {
		$this->assertTrue( Probe::is_fault( 500, '' ) );
		$this->assertTrue( Probe::is_fault( 503, 'Service Unavailable' ) );
	}

	public function test_a_failed_request_is_a_fault(): void {
		$this->assertTrue( Probe::is_fault( 0, '' ) );
	}

	public function test_a_healthy_page_is_not_a_fault(): void {
		$this->assertFalse( Probe::is_fault( 200, '<html><title>My Site</title></html>' ) );
	}

	public function test_wordpress_critical_error_page_is_a_fault(): void {
		$body = '<p>There has been a critical error on this website.</p>';
		$this->assertTrue( Probe::is_fault( 200, $body ) );
	}

	public function test_an_uncaught_php_error_is_a_fault(): void {
		$body = '<b>Fatal error</b>:  Uncaught Error: Call to undefined function missing() in /wp-content/plugins/x/x.php:12';
		$this->assertTrue( Probe::is_fault( 200, $body ) );
	}

	public function test_a_post_quoting_a_fatal_error_is_not_a_fault(): void {
		$body = '<article>Today we debug a Fatal error message together.</article>';
		$this->assertFalse( Probe::is_fault( 200, $body ) );
	}

	public function test_a_404_is_not_by_itself_a_fault(): void {
		$this->assertFalse( Probe::is_fault( 404, 'Not Found' ) );
	}

	public function test_the_marker_must_appear(): void {
		$this->assertFalse( Probe::is_fault( 200, '<title>My Site</title>', 'My Site' ) );
		$this->assertTrue( Probe::is_fault( 200, '<title>Something Else</title>', 'My Site' ) );
		$this->assertTrue( Probe::is_fault( 404, 'Not Found', 'My Site' ) );
	}

	public function test_an_empty_marker_is_ignored(): void {
		$this->assertFalse( Probe::is_fault( 200, '<title>Anything</title>', '' ) );
		$this->assertFalse( Probe::is_fault( 200, '<title>Anything</title>', null ) );
	}
}
