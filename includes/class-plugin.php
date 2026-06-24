<?php
/**
 * Main plugin bootstrap.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Plugin
 */
class Art_Master_Install_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Art_Master_Install_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether admin modules were initialized.
	 *
	 * @var bool
	 */
	private static $admin_initialized = false;

	/**
	 * @return Art_Master_Install_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
	}

	/**
	 * Load required class files.
	 */
	private function load_dependencies() {
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-settings.php';
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-security.php';
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-github.php';
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-catalog.php';
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-catalog-ui.php';
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-installer.php';
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-updater.php';
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'admin/class-admin-settings.php';
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'admin/class-admin-menu.php';
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'admin/class-admin-actions.php';
	}

	/**
	 * Register hooks and initialize modules.
	 */
	public function run() {
		add_action( 'init', array( 'Art_Master_Install_Settings', 'init' ) );
		add_action( 'admin_init', array( $this, 'init_admin' ) );
	}

	/**
	 * Initialize admin modules.
	 */
	public function init_admin() {
		if ( self::$admin_initialized ) {
			return;
		}

		self::$admin_initialized = true;

		Art_Master_Install_Updater::init();
		Art_Master_Install_Admin_Settings::init();
		Art_Master_Install_Admin_Menu::init();
		Art_Master_Install_Admin_Actions::init();
	}
}
