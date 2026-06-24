<?php
/**
 * GitHub update checker for ART Master Install.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Updater
 */
class Art_Master_Install_Updater {

	const GITHUB_REPO        = 'artbashlykov/art-master-install';
	const CHECK_TRANSIENT    = 'art_mi_update_check_at';
	const CHECK_INTERVAL     = 21600; // 6 hours.
	const CHECK_INTERVAL_MIN = 900; // 15 minutes.

	/**
	 * @var object|null
	 */
	private static $checker = null;

	/**
	 * Register update checker.
	 */
	public static function init() {
		$library = ART_MASTER_INSTALL_PLUGIN_DIR . 'vendor/' . 'plugin-' . 'update-checker' . '/' . 'plugin-' . 'update-checker.php';

		if ( ! file_exists( $library ) ) {
			return;
		}

		require_once $library;

		$factory_class = '\\' . 'Yahnis' . 'Elsts\\Plugin' . 'UpdateChecker\\v5p7\\' . 'PucFactory';
		$build_method  = 'build' . 'UpdateChecker';

		if ( ! class_exists( $factory_class ) || ! is_callable( array( $factory_class, $build_method ) ) ) {
			return;
		}

		$checker = call_user_func(
			array( $factory_class, $build_method ),
			'https://github.com/' . self::GITHUB_REPO . '/',
			ART_MASTER_INSTALL_PLUGIN_FILE,
			ART_MASTER_INSTALL_ADMIN_MENU_SLUG
		);

		$checker->addFilter( 'view_details_link', '__return_empty_string' );

		$checker->getVcsApi()->enableReleaseAssets( '/\.zip($|[?&#])/i' );

		$token = self::get_github_token();

		if ( '' !== $token ) {
			$checker->setAuthentication( $token );
		}

		self::$checker = $checker;

		add_action( 'load-plugins.php', array( __CLASS__, 'maybe_check_for_updates' ), 99 );
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_check_for_updates' ), 99 );
	}

	/**
	 * Check GitHub for plugin updates when the interval has elapsed.
	 */
	public static function maybe_check_for_updates() {
		if ( null === self::$checker || ! Art_Master_Install_Security::can_update() ) {
			return;
		}

		if ( ! self::should_check_now() ) {
			return;
		}

		self::$checker->checkForUpdates();
		self::mark_checked();
	}

	/**
	 * Whether enough time has passed since the last GitHub update check.
	 *
	 * @return bool
	 */
	private static function should_check_now() {
		$last_check = (int) get_site_transient( self::CHECK_TRANSIENT );

		if ( $last_check <= 0 ) {
			return true;
		}

		return ( time() - $last_check ) >= self::get_check_interval();
	}

	/**
	 * Store the timestamp of the latest GitHub update check.
	 */
	private static function mark_checked() {
		set_site_transient( self::CHECK_TRANSIENT, time(), self::get_check_interval() );
	}

	/**
	 * Minimum seconds between GitHub update checks on admin screens.
	 *
	 * @return int
	 */
	private static function get_check_interval() {
		/**
		 * Filters how often ART Master Install checks GitHub for plugin updates.
		 *
		 * @param int $interval Seconds between checks. Default 6 hours.
		 */
		$interval = (int) apply_filters( 'art_master_install_update_check_interval', self::CHECK_INTERVAL );

		return max( self::CHECK_INTERVAL_MIN, $interval );
	}

	/**
	 * GitHub token for private repository access.
	 *
	 * Add to wp-config.php on sites that should receive updates:
	 * define( 'ART_MASTER_INSTALL_GITHUB_TOKEN', 'your-github-token' );
	 *
	 * @return string
	 */
	private static function get_github_token() {
		$token = '';

		if ( defined( 'ART_MASTER_INSTALL_GITHUB_TOKEN' ) ) {
			$token = (string) ART_MASTER_INSTALL_GITHUB_TOKEN;
		}

		/**
		 * Filters GitHub token used to check ART Master Install updates.
		 *
		 * @param string $token GitHub personal access token.
		 */
		$token = (string) apply_filters( 'art_master_install_github_token', $token );

		return sanitize_text_field( $token );
	}
}
