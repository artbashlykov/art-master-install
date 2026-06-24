<?php
/**
 * Plugin activation.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Activator
 */
class Art_Master_Install_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-settings.php';
		Art_Master_Install_Settings::ensure_defaults();
	}
}
