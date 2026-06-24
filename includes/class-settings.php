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
			'auto_activate' => 'yes',
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
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, string>
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		return array(
			'auto_activate' => isset( $input['auto_activate'] ) && 'yes' === $input['auto_activate'] ? 'yes' : 'no',
		);
	}
}
