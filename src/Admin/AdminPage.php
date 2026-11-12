<?php
/**
 * The Tools → PixelShinobi Troubleshooting Finder page and its handlers.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar\Admin;

use ConflictRadar\Bisect\BisectConclusion;
use ConflictRadar\Bisect\BisectJob;
use ConflictRadar\Debug\DebugInfo;
use ConflictRadar\Troubleshoot\SessionManager;

/**
 * One page, three jobs: manual troubleshooting mode, the automated
 * bisect, and the redacted debug bundle. State-changing actions run
 * through admin-post with nonces and the manage_options capability;
 * the bisect's stepper runs through admin-ajax, one probe per call.
 */
final class AdminPage {

	public const HOOK_SUFFIX = 'tools_page_conflict-radar';

	/**
	 * Add the page under Tools.
	 */
	public static function register_menu(): void {
		add_management_page(
			__( 'PixelShinobi Troubleshooting Finder', 'pixelshinobi-troubleshooting-finder' ),
			__( 'PixelShinobi Troubleshooting Finder', 'pixelshinobi-troubleshooting-finder' ),
			'manage_options',
			'conflict-radar',
			array( self::class, 'render' )
		);
	}

	/**
	 * Enqueue the stepper script and styles on this page only.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( self::HOOK_SUFFIX !== $hook_suffix ) {
			return;
		}
		$job = BisectJob::current();
		wp_enqueue_style( 'conflict-radar-admin', plugins_url( 'assets/admin.css', CONFLICT_RADAR_FILE ), array(), CONFLICT_RADAR_VERSION );
		wp_enqueue_script( 'conflict-radar-admin', plugins_url( 'assets/admin.js', CONFLICT_RADAR_FILE ), array(), CONFLICT_RADAR_VERSION, true );
		wp_localize_script(
			'conflict-radar-admin',
			'ConflictRadar',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cr_bisect' ),
				'running' => null !== $job && 'running' === $job['status'],
			)
		);
	}

	/**
	 * A persistent admin-bar entry while the current user owns a
	 * troubleshooting session — the exit is always one click away.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar.
	 */
	public static function admin_bar( $wp_admin_bar ): void {
		$session = SessionManager::current();
		if ( null === $session || get_current_user_id() !== (int) $session['user_id'] ) {
			return;
		}
		$wp_admin_bar->add_node(
			array(
				'id'    => 'conflict-radar-ts',
				'title' => __( 'Troubleshooting mode is ON — open PixelShinobi Troubleshooting Finder', 'pixelshinobi-troubleshooting-finder' ),
				'href'  => admin_url( 'tools.php?page=conflict-radar' ),
			)
		);
	}

	/**
	 * While this browser is inside the session, say so on every admin
	 * screen: the admin is looking at a filtered site, not the real one.
	 */
	public static function session_notice(): void {
		if ( null === SessionManager::current() || ! isset( $_COOKIE[ SessionManager::COOKIE ] ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'PixelShinobi Troubleshooting Finder:', 'pixelshinobi-troubleshooting-finder' ) . '</strong> '
			. esc_html__( 'troubleshooting mode is on for this browser. Only you see the site this way — your visitors see the real site.', 'pixelshinobi-troubleshooting-finder' )
			. ' <a href="' . esc_url( admin_url( 'tools.php?page=conflict-radar' ) ) . '">' . esc_html__( 'Manage or exit', 'pixelshinobi-troubleshooting-finder' ) . '</a></p></div>';
	}

	// ------------------------------------------------------------------
	// admin-post handlers
	// ------------------------------------------------------------------

	/**
	 * Start a manual troubleshooting session.
	 */
	public static function handle_start_session(): void {
		self::guard( 'cr_start_session' );
		$theme = '';
		if ( isset( $_POST['cr_switch_theme'] ) && function_exists( 'wp_get_theme' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard() above.
			$default = wp_get_theme( WP_DEFAULT_THEME );
			if ( $default->exists() ) {
				$theme = $default->get_stylesheet();
			}
		}
		SessionManager::start( get_current_user_id(), $theme );
		self::back();
	}

	/**
	 * End the session; a running bisect is abandoned with it.
	 */
	public static function handle_stop_session(): void {
		self::guard( 'cr_stop_session' );
		$job = BisectJob::current();
		if ( null !== $job && 'running' === $job['status'] ) {
			BisectJob::dismiss();
		}
		SessionManager::stop();
		self::back();
	}

	/**
	 * Save which plugins are enabled inside the session (manual mode).
	 */
	public static function handle_save_session_plugins(): void {
		self::guard( 'cr_save_session_plugins' );
		$active = (array) get_option( 'active_plugins', array() );
		$wanted = array();
		if ( isset( $_POST['cr_plugins'] ) && is_array( $_POST['cr_plugins'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard() above.
			$posted = array_map( 'strval', wp_unslash( $_POST['cr_plugins'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are only intersected with the real active-plugins list below; anything else is discarded.
			$wanted = array_values( array_intersect( $active, $posted ) );
		}
		SessionManager::set_plugins( $wanted );
		self::back();
	}

	/**
	 * Start an automated bisect over the active plugins.
	 */
	public static function handle_start_bisect(): void {
		self::guard( 'cr_start_bisect' );
		$self       = plugin_basename( CONFLICT_RADAR_FILE );
		$active     = (array) get_option( 'active_plugins', array() );
		$candidates = array_values( array_diff( $active, array( $self ) ) );

		$url = isset( $_POST['cr_url'] ) ? esc_url_raw( wp_unslash( $_POST['cr_url'] ) ) : home_url( '/' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in self::guard() above; esc_url_raw sanitizes the URL.
		if ( ! self::is_local_url( $url ) ) {
			// The probe only makes sense against this site itself.
			$url = home_url( '/' );
		}
		$marker = isset( $_POST['cr_marker'] ) ? sanitize_text_field( wp_unslash( $_POST['cr_marker'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in self::guard() above.

		BisectJob::start( $candidates, $url, $marker );
		self::back();
	}

	/**
	 * Cancel a running bisect (ends the session too).
	 */
	public static function handle_cancel_bisect(): void {
		self::guard( 'cr_cancel_bisect' );
		BisectJob::cancel();
		self::back();
	}

	/**
	 * Dismiss a finished bisect's result.
	 */
	public static function handle_dismiss_bisect(): void {
		self::guard( 'cr_dismiss_bisect' );
		BisectJob::dismiss();
		self::back();
	}

	/**
	 * Download the redacted debug bundle as a text file.
	 */
	public static function handle_download_debug(): void {
		self::guard( 'cr_download_debug' );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="conflict-radar-debug.txt"' );
		echo DebugInfo::collect(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A plain-text file download, not HTML; the content is already redacted by Redactor.
		exit;
	}

	/**
	 * One bisect probe step, called repeatedly by admin.js.
	 */
	public static function ajax_bisect_step(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'cr_bisect' );
		wp_send_json_success( BisectJob::step() );
	}

	// ------------------------------------------------------------------
	// The page
	// ------------------------------------------------------------------

	/**
	 * Render the page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$session   = SessionManager::current();
		$job       = BisectJob::current();
		$names     = self::plugin_names();
		$self      = plugin_basename( CONFLICT_RADAR_FILE );
		$bisecting = null !== $job && 'running' === $job['status'];

		echo '<div class="wrap"><h1>' . esc_html__( 'PixelShinobi Troubleshooting Finder', 'pixelshinobi-troubleshooting-finder' ) . '</h1>';
		echo '<p>' . esc_html__( 'Find the plugin that breaks your site — without your visitors ever noticing. Troubleshooting here applies only to your browser.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';

		if ( null !== $job && 'complete' === $job['status'] && null !== $job['conclusion'] ) {
			self::render_verdict( $job );
		}

		self::render_bisect_section( $job, $bisecting );
		self::render_session_section( $session, $bisecting, $names );
		self::render_debug_section();

		echo '</div>';
	}

	/**
	 * The verdict box for a finished bisect.
	 *
	 * @param array $job The completed job record.
	 */
	private static function render_verdict( array $job ): void {
		$conclusion = BisectConclusion::from_array( (array) $job['conclusion'] );
		$names      = (array) $job['names'];

		echo '<div class="notice notice-info cr-verdict"><h2>' . esc_html__( 'Search result', 'pixelshinobi-troubleshooting-finder' ) . '</h2>';
		switch ( $conclusion->type() ) {
			case BisectConclusion::CULPRIT_FOUND:
				$culprit = (string) $conclusion->culprit();
				echo '<p><strong>' . esc_html( isset( $names[ $culprit ] ) ? $names[ $culprit ] : $culprit ) . '</strong> '
					. esc_html__( 'is the problem: the page breaks with this plugin active on its own. Leave it deactivated, or take this result to its author — everything else was cleared.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';
				break;
			case BisectConclusion::INTERACTION_SUSPECTED:
				$set = array();
				foreach ( $conclusion->smallest_fault_set() as $basename ) {
					$set[] = isset( $names[ $basename ] ) ? $names[ $basename ] : $basename;
				}
				echo '<p>' . esc_html__( 'No single plugin causes this — the break needs a combination. The smallest set seen breaking the page:', 'pixelshinobi-troubleshooting-finder' )
					. ' <strong>' . esc_html( implode( ', ', $set ) ) . '</strong>. '
					. esc_html__( 'Try deactivating one of these at a time; the conflict is between two or more of them.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';
				break;
			case BisectConclusion::NOT_A_CANDIDATE:
				echo '<p>' . esc_html__( 'The problem happens even with every plugin off, so it is not a plugin conflict. Look at the theme, or at the hosting/server side.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';
				break;
			case BisectConclusion::NOT_REPRODUCIBLE:
				echo '<p>' . esc_html__( 'The problem could not be reproduced with everything on. It may be intermittent, already fixed, or only happen somewhere else — try again with the exact page where it occurs.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';
				break;
			default:
				echo '<p>' . esc_html__( 'There were no plugins to search.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';
				break;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cr_dismiss_bisect' );
		echo '<input type="hidden" name="action" value="cr_dismiss_bisect" />';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Dismiss', 'pixelshinobi-troubleshooting-finder' ) . '</button></p></form></div>';
	}

	/**
	 * The bisect panel: start form, live progress, or step log.
	 *
	 * @param array|null $job       The current job, if any.
	 * @param bool       $bisecting Whether a search is running.
	 */
	private static function render_bisect_section( $job, bool $bisecting ): void {
		echo '<h2>' . esc_html__( 'Find the conflicting plugin automatically', 'pixelshinobi-troubleshooting-finder' ) . '</h2>';
		if ( $bisecting ) {
			$count = count( (array) $job['names'] );
			echo '<p id="cr-bisect-status">' . esc_html( sprintf( /* translators: %d: number of plugins being searched */ __( 'Searching %d plugins…', 'pixelshinobi-troubleshooting-finder' ), $count ) ) . '</p>';
			echo '<ol id="cr-bisect-log"></ol>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'cr_cancel_bisect' );
			echo '<input type="hidden" name="action" value="cr_cancel_bisect" />';
			echo '<p><button type="submit" class="button">' . esc_html__( 'Cancel the search', 'pixelshinobi-troubleshooting-finder' ) . '</button></p></form>';
			return;
		}
		if ( null !== $job && 'complete' === $job['status'] ) {
			self::render_step_log( $job );
		}
		echo '<p>' . esc_html__( 'PixelShinobi Troubleshooting Finder turns plugins off in halves — only for your browser — and checks the page after each step, so a conflict among 20 plugins takes a handful of checks instead of 20 rounds of manual testing. It always tests against the default theme.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cr_start_bisect' );
		echo '<input type="hidden" name="action" value="cr_start_bisect" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="cr_url">' . esc_html__( 'Page that breaks', 'pixelshinobi-troubleshooting-finder' ) . '</label></th>';
		echo '<td><input type="url" id="cr_url" name="cr_url" class="regular-text" value="' . esc_attr( home_url( '/' ) ) . '" /> ';
		echo '<span class="description">' . esc_html__( 'A page on this site where the problem shows.', 'pixelshinobi-troubleshooting-finder' ) . '</span></td></tr>';
		echo '<tr><th scope="row"><label for="cr_marker">' . esc_html__( 'Text that must appear', 'pixelshinobi-troubleshooting-finder' ) . '</label></th>';
		echo '<td><input type="text" id="cr_marker" name="cr_marker" class="regular-text" value="" /> ';
		echo '<span class="description">' . esc_html__( 'Optional. If the broken page still loads but loses content, name text a healthy page always has (for example your site title) — its absence then counts as broken.', 'pixelshinobi-troubleshooting-finder' ) . '</span></td></tr>';
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Start the search', 'pixelshinobi-troubleshooting-finder' ) . '</button></p></form>';
	}

	/**
	 * The answered steps of a finished search, for the record.
	 *
	 * @param array $job The completed job record.
	 */
	private static function render_step_log( array $job ): void {
		$state   = (array) $job['session'];
		$history = isset( $state['history'] ) && is_array( $state['history'] ) ? $state['history'] : array();
		if ( array() === $history ) {
			return;
		}
		$names = (array) $job['names'];
		echo '<h3>' . esc_html__( 'What the search tested', 'pixelshinobi-troubleshooting-finder' ) . '</h3><ol>';
		foreach ( $history as $entry ) {
			$subset = isset( $entry['subset'] ) && is_array( $entry['subset'] ) ? $entry['subset'] : array();
			$labels = array();
			foreach ( $subset as $basename ) {
				$labels[] = isset( $names[ $basename ] ) ? $names[ $basename ] : $basename;
			}
			$what = array() === $labels
				? __( 'all plugins off', 'pixelshinobi-troubleshooting-finder' )
				: implode( ', ', $labels );
			echo '<li>' . esc_html( $what ) . ' — <strong>' . ( ! empty( $entry['fault'] )
				? esc_html__( 'problem reproduced', 'pixelshinobi-troubleshooting-finder' )
				: esc_html__( 'page healthy', 'pixelshinobi-troubleshooting-finder' ) ) . '</strong></li>';
		}
		echo '</ol>';
	}

	/**
	 * The manual troubleshooting panel.
	 *
	 * @param array|null $session   The current session, if any.
	 * @param bool       $bisecting Whether the bisect owns the session.
	 * @param array      $names     Plugin basename → display name.
	 */
	private static function render_session_section( $session, bool $bisecting, array $names ): void {
		echo '<h2>' . esc_html__( 'Troubleshooting mode (manual)', 'pixelshinobi-troubleshooting-finder' ) . '</h2>';
		if ( null === $session ) {
			echo '<p>' . esc_html__( 'Turn every other plugin off — for your browser only — and flip them back on one by one yourself. Visitors keep seeing the real site, and the mode switches itself off after an hour.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'cr_start_session' );
			echo '<input type="hidden" name="action" value="cr_start_session" />';
			echo '<p><label><input type="checkbox" name="cr_switch_theme" value="1" checked /> '
				. esc_html__( 'Also switch me to the default theme', 'pixelshinobi-troubleshooting-finder' ) . '</label></p>';
			echo '<p><button type="submit" class="button">' . esc_html__( 'Start troubleshooting mode', 'pixelshinobi-troubleshooting-finder' ) . '</button></p></form>';
			return;
		}

		$expires = isset( $session['expires'] ) ? (int) $session['expires'] : 0;
		echo '<p><strong>' . esc_html__( 'Troubleshooting mode is ON', 'pixelshinobi-troubleshooting-finder' ) . '</strong> '
			. esc_html( sprintf( /* translators: %s: time the mode switches itself off */ __( 'until %s, for this browser only.', 'pixelshinobi-troubleshooting-finder' ), gmdate( 'H:i', $expires ) . ' UTC' ) ) . '</p>';

		if ( $bisecting ) {
			echo '<p>' . esc_html__( 'The automated search is using this session right now.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';
		} else {
			$enabled = isset( $session['plugins'] ) && is_array( $session['plugins'] ) ? $session['plugins'] : array();
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'cr_save_session_plugins' );
			echo '<input type="hidden" name="action" value="cr_save_session_plugins" />';
			echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Plugins enabled for you', 'pixelshinobi-troubleshooting-finder' ) . '</legend>';
			foreach ( $names as $basename => $label ) {
				if ( plugin_basename( CONFLICT_RADAR_FILE ) === $basename ) {
					continue;
				}
				echo '<label style="display:block"><input type="checkbox" name="cr_plugins[]" value="' . esc_attr( $basename ) . '"'
					. checked( in_array( $basename, $enabled, true ), true, false ) . ' /> ' . esc_html( $label ) . '</label>';
			}
			echo '</fieldset>';
			echo '<p><button type="submit" class="button">' . esc_html__( 'Apply to my view', 'pixelshinobi-troubleshooting-finder' ) . '</button></p></form>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cr_stop_session' );
		echo '<input type="hidden" name="action" value="cr_stop_session" />';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Exit troubleshooting mode', 'pixelshinobi-troubleshooting-finder' ) . '</button></p></form>';

		$exit_url = SessionManager::exit_url();
		if ( '' !== $exit_url ) {
			echo '<p class="description">' . esc_html__( 'Break-glass exit: if wp-admin ever breaks while this mode is on, visiting this link ends it immediately:', 'pixelshinobi-troubleshooting-finder' )
				. '<br /><code>' . esc_html( $exit_url ) . '</code></p>';
		}
	}

	/**
	 * The debug bundle panel: the bundle inline with a copy button (the
	 * usual next step is pasting it into a support thread), plus the
	 * original download for people who would rather attach a file.
	 */
	private static function render_debug_section(): void {
		echo '<h2>' . esc_html__( 'Debug info for support', 'pixelshinobi-troubleshooting-finder' ) . '</h2>';
		echo '<p>' . esc_html__( 'The facts a plugin author or support forum will ask for, with private values (email addresses, file paths, IP addresses, tokens) already removed. Safe to paste publicly.', 'pixelshinobi-troubleshooting-finder' ) . '</p>';
		echo '<p><textarea id="cr-debug-bundle" class="large-text code" rows="14" readonly>' . esc_textarea( DebugInfo::collect() ) . '</textarea></p>';
		echo '<p><button type="button" class="button" id="cr-copy-debug">' . esc_html__( 'Copy debug info to clipboard', 'pixelshinobi-troubleshooting-finder' ) . '</button> ';
		echo '<span id="cr-copy-status" role="status"></span></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cr_download_debug' );
		echo '<input type="hidden" name="action" value="cr_download_debug" />';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Download debug info (.txt)', 'pixelshinobi-troubleshooting-finder' ) . '</button></p></form>';
	}

	// ------------------------------------------------------------------
	// Small helpers
	// ------------------------------------------------------------------

	/**
	 * Capability + nonce gate shared by every admin-post handler.
	 *
	 * @param string $action The nonce action name.
	 */
	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'pixelshinobi-troubleshooting-finder' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirect back to the plugin page.
	 */
	private static function back(): void {
		wp_safe_redirect( admin_url( 'tools.php?page=conflict-radar' ) );
		exit;
	}

	/**
	 * Whether a URL belongs to this site (the bisect probes locally).
	 *
	 * @param string $url The URL to check.
	 */
	private static function is_local_url( string $url ): bool {
		$host      = wp_parse_url( $url, PHP_URL_HOST );
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) && is_string( $home_host ) && 0 === strcasecmp( $host, $home_host );
	}

	/**
	 * All installed plugins: basename → display name.
	 *
	 * @return array<string, string>
	 */
	private static function plugin_names(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$names = array();
		foreach ( get_plugins() as $basename => $data ) {
			$names[ $basename ] = isset( $data['Name'] ) && '' !== $data['Name'] ? $data['Name'] : $basename;
		}
		return $names;
	}
}
