<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Art_Master_Install
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-uninstaller.php';

Art_Master_Install_Uninstaller::run();
