<?php
/**
 * Install and update ART plugins from GitHub release zips.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Installer
 */
class Art_Master_Install_Installer {

	/**
	 * Install a catalog plugin from the latest GitHub release zip.
	 *
	 * @param string $slug Catalog slug.
	 * @param bool   $overwrite Whether to overwrite an existing package.
	 * @return true|'installed_activated'|WP_Error
	 */
	public static function install_from_github( $slug, $overwrite = false ) {
		$item = Art_Master_Install_Catalog::get_item( $slug );

		if ( null === $item ) {
			return new WP_Error(
				'art_master_install_unknown_slug',
				__( 'Плагин не найден в каталоге ART.', 'art-master-install' )
			);
		}

		self::load_upgrader_dependencies();

		$package  = Art_Master_Install_Github::get_release_zip_url( $item['github'], $item['zip_name'] );
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		$result = $upgrader->install(
			$package,
			array(
				'overwrite_package' => (bool) $overwrite,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			$messages = method_exists( $skin, 'get_error_messages' ) ? $skin->get_error_messages() : array();
			$message  = ! empty( $messages ) ? implode( ' ', $messages ) : __( 'Не удалось установить плагин из GitHub.', 'art-master-install' );

			return new WP_Error( 'art_master_install_install_failed', $message );
		}

		if ( $overwrite || ! Art_Master_Install_Settings::should_auto_activate() ) {
			return true;
		}

		$activated = self::activate_catalog_plugin( (string) $item['plugin_file'] );

		if ( is_wp_error( $activated ) ) {
			return $activated;
		}

		return 'installed_activated';
	}

	/**
	 * Activate a catalog plugin after install.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return true|WP_Error
	 */
	private static function activate_catalog_plugin( $plugin_file ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( $plugin_file ) ) {
			return true;
		}

		$activate_result = activate_plugin( $plugin_file, '', false, true );

		if ( is_wp_error( $activate_result ) ) {
			return new WP_Error(
				'art_master_install_activate_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Плагин установлен, но не удалось активировать: %s', 'art-master-install' ),
					$activate_result->get_error_message()
				)
			);
		}

		return true;
	}

	/**
	 * Load WordPress upgrader dependencies.
	 */
	private static function load_upgrader_dependencies() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
	}
}
