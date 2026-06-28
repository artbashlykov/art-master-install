<?php
/**
 * Install and update ART themes from GitHub release zips.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Theme_Installer
 */
class Art_Master_Install_Theme_Installer {

	/**
	 * Install a catalog theme from the latest GitHub release zip.
	 *
	 * @param string $slug Catalog slug.
	 * @param bool   $overwrite Whether to overwrite an existing package.
	 * @return true|'installed_activated'|WP_Error
	 */
	public static function install_from_github( $slug, $overwrite = false ) {
		$item = Art_Master_Install_Theme_Catalog::get_item( $slug );

		if ( null === $item ) {
			return new WP_Error(
				'art_master_install_unknown_theme',
				__( 'Тема не найдена в каталоге ART.', 'art-master-install' )
			);
		}

		self::load_upgrader_dependencies();

		$package  = Art_Master_Install_Github::get_release_zip_url( $item['github'], $item['zip_name'] );
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );

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
			$message  = ! empty( $messages ) ? implode( ' ', $messages ) : __( 'Не удалось установить тему из GitHub.', 'art-master-install' );

			return new WP_Error( 'art_master_install_theme_install_failed', $message );
		}

		if ( $overwrite || ! Art_Master_Install_Settings::should_auto_activate() ) {
			return true;
		}

		$activated = self::activate_catalog_theme( (string) $item['stylesheet'] );

		if ( is_wp_error( $activated ) ) {
			return $activated;
		}

		return 'installed_activated';
	}

	/**
	 * Activate a catalog theme after install.
	 *
	 * @param string $stylesheet Theme stylesheet slug.
	 * @return true|WP_Error
	 */
	public static function activate_catalog_theme( $stylesheet ) {
		$stylesheet = sanitize_key( $stylesheet );

		if ( '' === $stylesheet ) {
			return new WP_Error(
				'art_master_install_theme_invalid',
				__( 'Некорректный идентификатор темы.', 'art-master-install' )
			);
		}

		if ( ! current_user_can( 'switch_themes' ) ) {
			return new WP_Error(
				'art_master_install_theme_forbidden',
				__( 'Недостаточно прав для активации темы.', 'art-master-install' )
			);
		}

		$theme = wp_get_theme( $stylesheet );

		if ( ! $theme->exists() ) {
			return new WP_Error(
				'art_master_install_theme_missing',
				__( 'Тема не найдена после установки.', 'art-master-install' )
			);
		}

		if ( get_stylesheet() === $stylesheet ) {
			return true;
		}

		switch_theme( $stylesheet );

		if ( get_stylesheet() !== $stylesheet ) {
			return new WP_Error(
				'art_master_install_theme_activate_failed',
				__( 'Не удалось активировать тему.', 'art-master-install' )
			);
		}

		return true;
	}

	/**
	 * Load WordPress theme upgrader dependencies.
	 */
	private static function load_upgrader_dependencies() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
	}
}
