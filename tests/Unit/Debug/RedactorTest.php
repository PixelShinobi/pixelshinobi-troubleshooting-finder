<?php
/**
 * Tests for the debug-bundle redactor.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Tests\Unit\Debug;

use ConflictRadar\Debug\Redactor;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase {

	public function test_emails_are_redacted(): void {
		$this->assertSame(
			'Admin: [email redacted]',
			Redactor::redact( 'Admin: admin@example.com' )
		);
	}

	public function test_unix_paths_are_redacted(): void {
		$this->assertSame(
			'Error in [path redacted] on line 3',
			Redactor::redact( 'Error in /home/hatch/site/wp-content/plugins/x/x.php on line 3' )
		);
	}

	public function test_windows_paths_are_redacted(): void {
		$this->assertSame(
			'Error in [path redacted] on line 3',
			Redactor::redact( 'Error in C:\inetpub\sites\wp\wp-config.php on line 3' )
		);
	}

	public function test_ip_addresses_are_redacted(): void {
		$this->assertSame(
			'Server: [ip redacted]',
			Redactor::redact( 'Server: 203.0.113.7' )
		);
	}

	public function test_long_hex_tokens_are_redacted(): void {
		$this->assertSame(
			'Key: [token redacted]',
			Redactor::redact( 'Key: 0123456789abcdef0123456789abcdef01234567' )
		);
	}

	public function test_urls_and_ordinary_text_pass_through(): void {
		$text = 'Site: https://example.com/wp-admin/tools.php — WordPress 6.9, PHP 8.3.6, plugin x/y.php 1.2.3';
		$this->assertSame( $text, Redactor::redact( $text ) );
	}

	public function test_short_hex_and_versions_are_not_tokens(): void {
		$text = 'Commit abc1234, version 8.3.6';
		$this->assertSame( $text, Redactor::redact( $text ) );
	}
}
