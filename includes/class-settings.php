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

	const OPTION = 'art_master_install_settings';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_defaults' ), 5 );
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
	 * Sync WordPress auto-update preference for this plugin.
	 *
	 * @param bool $enabled Whether auto-update should be enabled.
	 */
	public static function sync_self_auto_update( $enabled ) {
		$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
		$plugin_file  = ART_MASTER_INSTALL_PLUGIN_BASENAME;

		if ( $enabled ) {
			if ( ! in_array( $plugin_file, $auto_updates, true ) ) {
				$auto_updates[] = $plugin_file;
			}
		} else {
			$auto_updates = array_values(
				array_filter(
					$auto_updates,
					static function ( $item ) use ( $plugin_file ) {
						return $item !== $plugin_file;
					}
				)
			);
		}

		update_site_option( 'auto_update_plugins', $auto_updates );
	}

	/**
	 * Schedule or clear recurring catalog checks based on settings.
	 */
	public static function sync_cron_schedule() {
		if ( ! self::should_auto_update_catalog() && ! self::should_auto_update_self() ) {
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

		self::sync_self_auto_update( 'yes' === $auto_update_self );

		$sanitized = array(
			'auto_activate'       => isset( $input['auto_activate'] ) && 'yes' === $input['auto_activate'] ? 'yes' : 'no',
			'auto_update_catalog' => isset( $input['auto_update_catalog'] ) && 'yes' === $input['auto_update_catalog'] ? 'yes' : 'no',
			'auto_update_self'    => $auto_update_self,
		);

		self::sync_cron_schedule();

		return $sanitized;
	}
}
