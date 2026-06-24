<?php
/**
 * Plugin deactivation.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Deactivator
 */
class Art_Master_Install_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-catalog-updates.php';
		Art_Master_Install_Catalog_Updates::clear_cron();
	}
}
