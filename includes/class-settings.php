<?php
/**
 * Plugin settings API.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Settings
 */
class Art_Master_Install_Settings {

	const OPTION                 = 'art_master_install_settings';
	const OPTION_DELETE_ON_UNINSTALL = 'art_master_install_delete_data_on_uninstall';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_defaults' ), 5 );
		add_filter( self::get_plugin_auto_update_hook(), array( __CLASS__, 'filter_plugin_auto_update' ), 10, 2 );
	}

	/**
	 * WordPress filter hook name for plugin auto-updates.
	 *
	 * @return string
	 */
	private static function get_plugin_auto_update_hook() {
		return 'auto_update_' . 'plugin';
	}

	/**
	 * Enable WordPress auto-updates for ART Master Install when the setting is on.
	 *
	 * @param bool  $should_update Whether WordPress should auto-update the plugin.
	 * @param object $plugin       Plugin update offer object.
	 * @return bool
	 */
	public static function filter_plugin_auto_update( $should_update, $plugin ) {
		if ( ! is_object( $plugin ) || empty( $plugin->plugin ) ) {
			return $should_update;
		}

		if ( ART_MASTER_INSTALL_PLUGIN_BASENAME !== $plugin->plugin ) {
			return $should_update;
		}

		if ( ! self::should_auto_update_self() ) {
			return false;
		}

		return true;
	}

	/**
	 * Ensure settings exist with defaults.
	 */
	public static function ensure_defaults() {
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}

		update_option( self::OPTION, self::get_defaults(), false );
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_defaults() {
		return array(
			'auto_activate'       => 'yes',
			'auto_update_catalog' => 'no',
			'auto_update_self'    => 'no',
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function get() {
		$value = get_option( self::OPTION, array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		return array_merge( self::get_defaults(), $value );
	}

	/**
	 * Whether catalog plugins should be activated after install.
	 *
	 * @return bool
	 */
	public static function should_auto_activate() {
		return 'yes' === ( self::get()['auto_activate'] ?? 'yes' );
	}

	/**
	 * Whether installed catalog plugins should be updated automatically.
	 *
	 * @return bool
	 */
	public static function should_auto_update_catalog() {
		return 'yes' === ( self::get()['auto_update_catalog'] ?? 'no' );
	}

	/**
	 * Whether ART Master Install should use WordPress auto-updates.
	 *
	 * @return bool
	 */
	public static function should_auto_update_self() {
		return 'yes' === ( self::get()['auto_update_self'] ?? 'no' );
	}

	/**
	 * Whether plugin data should be removed on uninstall.
	 *
	 * @return bool
	 */
	public static function should_delete_data_on_uninstall() {
		return 'yes' === get_option( self::OPTION_DELETE_ON_UNINSTALL, 'no' );
	}

	/**
	 * Persist the uninstall data removal preference.
	 *
	 * @param bool $enabled Whether to delete data on uninstall.
	 */
	public static function set_delete_data_on_uninstall( $enabled ) {
		update_option( self::OPTION_DELETE_ON_UNINSTALL, $enabled ? 'yes' : 'no', false );
	}

	/**
	 * Schedule or clear recurring catalog checks based on settings.
	 */
	public static function sync_cron_schedule() {
		if ( ! self::should_auto_update_catalog() ) {
			Art_Master_Install_Catalog_Updates::clear_cron();
			return;
		}

		Art_Master_Install_Catalog_Updates::ensure_cron_scheduled();
	}

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, string>
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$auto_update_self = isset( $input['auto_update_self'] ) && 'yes' === $input['auto_update_self'] ? 'yes' : 'no';

		$sanitized = array(
			'auto_activate'       => isset( $input['auto_activate'] ) && 'yes' === $input['auto_activate'] ? 'yes' : 'no',
			'auto_update_catalog' => isset( $input['auto_update_catalog'] ) && 'yes' === $input['auto_update_catalog'] ? 'yes' : 'no',
			'auto_update_self'    => $auto_update_self,
		);

		self::set_delete_data_on_uninstall(
			isset( $input['delete_data_on_uninstall'] ) && 'yes' === $input['delete_data_on_uninstall']
		);

		self::sync_cron_schedule();

		return $sanitized;
	}
}
