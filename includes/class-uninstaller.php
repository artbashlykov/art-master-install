<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Uninstaller
 */
class Art_Master_Install_Uninstaller {

	const DELETE_DATA_OPTION = 'art_master_install_delete_data_on_uninstall';
	const CRON_HOOK          = 'art_master_install_catalog_update_check';
	const PUC_OPTION         = 'external_updates-art-master-install';

	/**
	 * Run uninstall cleanup when the admin opted in.
	 */
	public static function run() {
		if ( ! self::is_delete_data_enabled() ) {
			return;
		}

		self::clear_cron();
		self::delete_plugin_options();
		self::delete_transients();
	}

	/**
	 * Whether the site admin enabled data removal on uninstall.
	 *
	 * @return bool
	 */
	public static function is_delete_data_enabled() {
		return 'yes' === get_option( self::DELETE_DATA_OPTION, 'no' );
	}

	/**
	 * Clear scheduled catalog update checks.
	 */
	private static function clear_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Delete plugin options from the database.
	 */
	private static function delete_plugin_options() {
		global $wpdb;

		delete_option( self::PUC_OPTION );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Bulk cleanup during uninstall.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'art_master_install_' ) . '%'
			)
		);
	}

	/**
	 * Delete plugin transients and site transients.
	 */
	private static function delete_transients() {
		global $wpdb;

		$like_transient = $wpdb->esc_like( '_transient_art_mi_' ) . '%';
		$like_timeout   = $wpdb->esc_like( '_transient_timeout_art_mi_' ) . '%';
		$like_site      = $wpdb->esc_like( '_site_transient_art_mi_' ) . '%';
		$like_site_to   = $wpdb->esc_like( '_site_transient_timeout_art_mi_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Bulk cleanup during uninstall.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$like_transient,
				$like_timeout,
				$like_site,
				$like_site_to
			)
		);
	}
}
