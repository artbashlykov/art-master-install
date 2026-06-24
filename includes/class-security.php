<?php
/**
 * Security helpers.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Security
 */
class Art_Master_Install_Security {

	/**
	 * Check if current user can open the catalog settings page.
	 *
	 * @return bool
	 */
	public static function can_view_catalog() {
		return current_user_can( 'activate_plugins' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can access the catalog admin area.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' )
			&& current_user_can( 'install_plugins' )
			&& current_user_can( 'update_plugins' );
	}

	/**
	 * Check if current user can install catalog plugins.
	 *
	 * @return bool
	 */
	public static function can_install() {
		return current_user_can( 'manage_options' ) && current_user_can( 'install_plugins' );
	}

	/**
	 * Check if current user can update catalog plugins.
	 *
	 * @return bool
	 */
	public static function can_update() {
		return current_user_can( 'manage_options' ) && current_user_can( 'update_plugins' );
	}
}
