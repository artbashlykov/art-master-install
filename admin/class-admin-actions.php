<?php
/**
 * Catalog install/update actions.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Admin_Actions
 */
class Art_Master_Install_Admin_Actions {

	const ACTION_INSTALL = 'art_master_install_catalog_install';
	const ACTION_UPDATE  = 'art_master_install_catalog_update';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_INSTALL, array( __CLASS__, 'handle_install' ) );
		add_action( 'admin_post_' . self::ACTION_UPDATE, array( __CLASS__, 'handle_update' ) );
	}

	/**
	 * Install a catalog plugin.
	 */
	public static function handle_install() {
		self::handle_package_action( false );
	}

	/**
	 * Update a catalog plugin from GitHub.
	 */
	public static function handle_update() {
		self::handle_package_action( true );
	}

	/**
	 * @param bool $overwrite Whether to overwrite an existing install.
	 */
	private static function handle_package_action( $overwrite ) {
		$allowed = $overwrite
			? Art_Master_Install_Security::can_update()
			: Art_Master_Install_Security::can_install();

		if ( ! $allowed ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-master-install' ) );
		}

		$nonce_action = $overwrite ? self::ACTION_UPDATE : self::ACTION_INSTALL;
		check_admin_referer( $nonce_action );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above.
		$slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( $_GET['slug'] ) ) : '';

		if ( '' === $slug || null === Art_Master_Install_Catalog::get_item( $slug ) ) {
			self::redirect_with_result( 'error', $slug, __( 'Неизвестный плагин каталога.', 'art-master-install' ) );
		}

		$result = Art_Master_Install_Installer::install_from_github( $slug, $overwrite );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_result( 'error', $slug, $result->get_error_message() );
		}

		$item = Art_Master_Install_Catalog::get_item( $slug );
		if ( is_array( $item ) && ! empty( $item['github'] ) ) {
			Art_Master_Install_Github::clear_release_cache( (string) $item['github'] );
		}

		self::redirect_with_result( $overwrite ? 'updated' : 'installed', $slug );
	}

	/**
	 * @param string      $result  Result code.
	 * @param string      $slug    Catalog slug.
	 * @param string|null $message Optional error message.
	 */
	private static function redirect_with_result( $result, $slug, $message = null ) {
		$args = array(
			'page'                       => Art_Master_Install_Admin_Settings::PAGE_SETTINGS,
			'art-master-install-result'  => sanitize_key( $result ),
			'art-master-install-slug'    => sanitize_key( $slug ),
		);

		if ( is_string( $message ) && '' !== $message ) {
			$args['art-master-install-message'] = rawurlencode( $message );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Install action URL.
	 *
	 * @param string $slug Catalog slug.
	 * @return string
	 */
	public static function get_install_url( $slug ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION_INSTALL,
					'slug'   => sanitize_key( $slug ),
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_INSTALL
		);
	}

	/**
	 * Update action URL.
	 *
	 * @param string $slug Catalog slug.
	 * @return string
	 */
	public static function get_update_url( $slug ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION_UPDATE,
					'slug'   => sanitize_key( $slug ),
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION_UPDATE
		);
	}

	/**
	 * Activate plugin URL.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return string
	 */
	public static function get_activate_url( $plugin_file ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'activate',
					'plugin' => $plugin_file,
				),
				admin_url( 'plugins.php' )
			),
			'activate-plugin_' . $plugin_file
		);
	}
}
