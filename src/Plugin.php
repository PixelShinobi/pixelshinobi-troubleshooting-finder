<?php
/**
 * The plugin's hook wiring.
 *
 * @package ConflictRadar
 */

declare(strict_types=1);

namespace ConflictRadar;

use ConflictRadar\Admin\AdminPage;
use ConflictRadar\Bisect\BisectJob;
use ConflictRadar\Troubleshoot\SessionManager;

/**
 * Registers every hook the plugin needs. All behavior lives in the
 * feature classes; this is only the wiring diagram.
 */
final class Plugin {

	/**
	 * Register hooks. Called once from the plugin bootstrap.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( AdminPage::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( AdminPage::class, 'enqueue' ) );
		add_action( 'admin_bar_menu', array( AdminPage::class, 'admin_bar' ), 100 );
		add_action( 'admin_notices', array( AdminPage::class, 'session_notice' ) );
		add_action( 'admin_init', array( SessionManager::class, 'watchdog' ) );

		add_action( 'admin_post_cr_start_session', array( AdminPage::class, 'handle_start_session' ) );
		add_action( 'admin_post_cr_stop_session', array( AdminPage::class, 'handle_stop_session' ) );
		add_action( 'admin_post_cr_save_session_plugins', array( AdminPage::class, 'handle_save_session_plugins' ) );
		add_action( 'admin_post_cr_start_bisect', array( AdminPage::class, 'handle_start_bisect' ) );
		add_action( 'admin_post_cr_cancel_bisect', array( AdminPage::class, 'handle_cancel_bisect' ) );
		add_action( 'admin_post_cr_dismiss_bisect', array( AdminPage::class, 'handle_dismiss_bisect' ) );
		add_action( 'admin_post_cr_download_debug', array( AdminPage::class, 'handle_download_debug' ) );

		add_action( 'wp_ajax_conflict_radar_bisect_step', array( AdminPage::class, 'ajax_bisect_step' ) );
	}

	/**
	 * On activation: heal any loader/session mismatch, nothing more.
	 * No options are created until the admin actually starts something.
	 */
	public static function activate(): void {
		SessionManager::watchdog();
	}

	/**
	 * On deactivation: end any session and forget any bisect, so a
	 * deactivated plugin never leaves troubleshooting filtering behind.
	 */
	public static function deactivate(): void {
		BisectJob::dismiss();
		SessionManager::stop();
	}
}
