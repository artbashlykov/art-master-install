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
		require_once ART_MASTER_INSTALL_PLUGIN_DIR . 'includes/class-catalog-updates.php';
		Art_Master_Install_Settings::ensure_defaults();

		if ( Art_Master_Install_Settings::should_auto_update_self() ) {
			Art_Master_Install_Settings::sync_self_auto_update( true );
		}

		Art_Master_Install_Settings::sync_cron_schedule();
	}
}
