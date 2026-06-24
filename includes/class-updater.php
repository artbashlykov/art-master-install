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
		$checker->addFilter( 'request_info_options', array( __CLASS__, 'filter_api_request_options' ) );
		$checker->allowAutoupdateField();

		$checker->getVcsApi()->enableReleaseAssets( '/\.zip($|[?&#])/i' );

		$token = Art_Master_Install_Github::get_access_token();

		if ( '' !== $token ) {
			$checker->setAuthentication( $token );
		}

		self::$checker = $checker;

		add_action( 'load-plugins.php', array( __CLASS__, 'maybe_check_for_updates' ), 99 );
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_check_for_updates' ), 99 );
	}

	/**
	 * Add GitHub-required headers to Plugin Update Checker API requests.
	 *
	 * @param array<string, mixed> $options wp_remote_get() options.
	 * @return array<string, mixed>
	 */
	public static function filter_api_request_options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( ! isset( $options['headers'] ) || ! is_array( $options['headers'] ) ) {
			$options['headers'] = array();
		}

		$options['headers'] = array_merge( $options['headers'], Art_Master_Install_Github::get_api_headers() );

		return $options;
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
	 * Force a GitHub update check for ART Master Install.
	 *
	 * @return bool Whether a newer version was found.
	 */
	public static function force_check() {
		if ( null === self::$checker ) {
			return false;
		}

		self::$checker->checkForUpdates();
		self::mark_checked();

		return self::has_update_available();
	}

	/**
	 * Whether a newer release of ART Master Install is available.
	 *
	 * @return bool
	 */
	public static function has_update_available() {
		if ( null === self::$checker ) {
			return false;
		}

		$update = self::$checker->getUpdate();

		return null !== $update;
	}

	/**
	 * Build update state for the catalog admin UI.
	 *
	 * @param bool $force_refresh Whether to bypass cached GitHub release data.
	 * @return array<string, mixed>
	 */
	public static function get_self_update_state( $force_refresh = false ) {
		$installed_version = ART_MASTER_INSTALL_VERSION;
		$latest_version    = Art_Master_Install_Github::get_latest_version( self::GITHUB_REPO, $force_refresh );
		$update_available  = '' !== $latest_version
			&& version_compare( $installed_version, $latest_version, '<' );

		if ( $force_refresh ) {
			self::force_check();
			$update_available = self::has_update_available() || $update_available;
		}

		return array(
			'installed_version' => $installed_version,
			'latest_version'    => $latest_version,
			'update_available'  => $update_available,
			'updates_url'       => admin_url( 'update-core.php' ),
		);
	}
}
