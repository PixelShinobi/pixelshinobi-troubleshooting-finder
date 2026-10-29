<?php
/**
 * Drives a bisect one HTTP step at a time.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Bisect;

use ConflictRadar\Probe\LoopbackProbe;
use ConflictRadar\Probe\Probe;
use ConflictRadar\Troubleshoot\SessionManager;

/**
 * The WordPress half of the bisect: it owns a troubleshooting session
 * and a stored job (option `conflict_radar_bisect`, never autoloaded),
 * and performs ONE probe per call so a search survives request time
 * limits on cheap hosting — the same batching discipline as Upgrade
 * Radar's scanner. The admin page's JavaScript calls step() repeatedly;
 * each step applies the planner's next subset inside the session,
 * probes the target page, and records the answer.
 *
 * Job record: status ('running'|'complete'), the BisectSession state,
 * target url, marker, the candidates with display names, the last
 * step's detail, and — once complete — the conclusion.
 */
final class BisectJob {

	public const OPTION = 'conflict_radar_bisect';

	/**
	 * Start a search: opens a troubleshooting session (default theme
	 * forced when one is available, per the plan) and stores the job.
	 *
	 * @param string[] $candidates Plugin basenames to search.
	 * @param string   $url        Page to probe.
	 * @param string   $marker     Text a healthy page must contain, '' for none.
	 */
	public static function start( array $candidates, string $url, string $marker ): void {
		$theme   = '';
		$default = wp_get_theme( WP_DEFAULT_THEME );
		if ( $default->exists() ) {
			$theme = $default->get_stylesheet();
		}

		SessionManager::start( get_current_user_id(), $theme );

		$names = array();
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all = get_plugins();
		foreach ( $candidates as $basename ) {
			$names[ $basename ] = isset( $all[ $basename ]['Name'] ) ? $all[ $basename ]['Name'] : $basename;
		}

		$session = new BisectSession( $candidates );
		update_option(
			self::OPTION,
			array(
				'status'     => 'running',
				'session'    => $session->to_array(),
				'url'        => $url,
				'marker'     => $marker,
				'names'      => $names,
				'last_step'  => null,
				'conclusion' => null,
				'started'    => time(),
			),
			false
		);
	}

	/**
	 * The stored job, or null when there is none.
	 *
	 * @return array|null
	 */
	public static function current() {
		$job = get_option( self::OPTION );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * Run one probe step and return a progress snapshot for the UI.
	 *
	 * @return array{complete: bool, steps: int, phase: string, suspects: int, fault: bool|null, subset_size: int|null, conclusion: array|null}
	 */
	public static function step(): array {
		$job = self::current();
		if ( null === $job ) {
			return array(
				'complete'    => true,
				'steps'       => 0,
				'phase'       => BisectSession::PHASE_DONE,
				'suspects'    => 0,
				'fault'       => null,
				'subset_size' => null,
				'conclusion'  => null,
			);
		}

		$session = SessionManager::current();
		$bisect  = BisectSession::from_array( (array) $job['session'] );

		if ( $bisect->is_complete() || null === $session ) {
			return self::snapshot( $job, $bisect, null, null );
		}

		$subset = $bisect->next_subset();
		SessionManager::set_plugins( null === $subset ? array() : $subset );

		$result = LoopbackProbe::fetch( (string) $job['url'], (string) $session['token'] );
		$fault  = Probe::is_fault( $result['status'], $result['body'], '' === $job['marker'] ? null : (string) $job['marker'] );
		$bisect->record_result( $fault );

		$job['session']   = $bisect->to_array();
		$job['last_step'] = array(
			'subset' => $subset,
			'fault'  => $fault,
			'status' => $result['status'],
			'at'     => time(),
		);
		if ( $bisect->is_complete() ) {
			$job['status']     = 'complete';
			$job['conclusion'] = $bisect->conclusion()->to_array();
			// The search is over: hand the browser back to the admin
			// by ending the troubleshooting session.
			SessionManager::stop();
		}
		update_option( self::OPTION, $job, false );

		return self::snapshot( $job, $bisect, $fault, null === $subset ? null : count( $subset ) );
	}

	/**
	 * Abandon the search: the job and the session both go away.
	 */
	public static function cancel(): void {
		delete_option( self::OPTION );
		SessionManager::stop();
	}

	/**
	 * Forget a finished job (the result display) without touching
	 * anything else.
	 */
	public static function dismiss(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Shape the progress payload the admin page polls for.
	 *
	 * @param array         $job         The stored job.
	 * @param BisectSession $bisect      The planner.
	 * @param bool|null     $fault       The step's answer, when one ran.
	 * @param int|null      $subset_size How many plugins the step enabled.
	 */
	private static function snapshot( array $job, BisectSession $bisect, $fault, $subset_size ): array {
		return array(
			'complete'    => $bisect->is_complete(),
			'steps'       => $bisect->steps_taken(),
			'phase'       => $bisect->phase(),
			'suspects'    => count( $bisect->suspects() ),
			'fault'       => $fault,
			'subset_size' => $subset_size,
			'conclusion'  => isset( $job['conclusion'] ) ? $job['conclusion'] : null,
		);
	}
}
