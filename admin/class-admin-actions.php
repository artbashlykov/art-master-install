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
	const AJAX_ACTION    = 'art_master_install_catalog_action';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION_INSTALL, array( __CLASS__, 'handle_install' ) );
		add_action( 'admin_post_' . self::ACTION_UPDATE, array( __CLASS__, 'handle_update' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'handle_ajax' ) );
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
	 * AJAX install/update without page reload.
	 */
	public static function handle_ajax() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$catalog_action = isset( $_POST['catalog_action'] ) ? sanitize_key( wp_unslash( $_POST['catalog_action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';

		if ( ! in_array( $catalog_action, array( 'install', 'update' ), true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Неизвестное действие каталога.', 'art-master-install' ),
				),
				400
			);
		}

		$overwrite = ( 'update' === $catalog_action );
		$result    = self::process_catalog_action( $slug, $overwrite );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'slug'    => $slug,
				),
				400
			);
		}

		wp_send_json_success( $result );
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

		$result = self::process_catalog_action( $slug, $overwrite );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_result( 'error', $slug, $result->get_error_message() );
		}

		self::redirect_with_result( $result['result'], $slug );
	}

	/**
	 * Run install or update for a catalog slug.
	 *
	 * @param string $slug      Catalog slug.
	 * @param bool   $overwrite Whether to overwrite an existing package.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function process_catalog_action( $slug, $overwrite ) {
		$allowed = $overwrite
			? Art_Master_Install_Security::can_update()
			: Art_Master_Install_Security::can_install();

		if ( ! $allowed ) {
			return new WP_Error(
				'art_master_install_forbidden',
				__( 'Недостаточно прав.', 'art-master-install' )
			);
		}

		if ( '' === $slug || null === Art_Master_Install_Catalog::get_item( $slug ) ) {
			return new WP_Error(
				'art_master_install_unknown_slug',
				__( 'Неизвестный плагин каталога.', 'art-master-install' )
			);
		}

		$item_state = Art_Master_Install_Catalog::get_item_state( $slug, false );

		if ( $overwrite ) {
			if ( null === $item_state || empty( $item_state['is_installed'] ) ) {
				return new WP_Error(
					'art_master_install_not_installed',
					__( 'Плагин не установлен — обновление невозможно.', 'art-master-install' )
				);
			}
		} elseif ( null !== $item_state && ! empty( $item_state['is_installed'] ) ) {
			return new WP_Error(
				'art_master_install_already_installed',
				__( 'Плагин уже установлен.', 'art-master-install' )
			);
		}

		$install_result = Art_Master_Install_Installer::install_from_github( $slug, $overwrite );

		if ( is_wp_error( $install_result ) ) {
			return $install_result;
		}

		$item = Art_Master_Install_Catalog::get_item( $slug );
		if ( is_array( $item ) && ! empty( $item['github'] ) ) {
			Art_Master_Install_Github::clear_release_cache( (string) $item['github'] );
		}

		$fresh_state = Art_Master_Install_Catalog::get_item_state( $slug, true );
		if ( null === $fresh_state ) {
			return new WP_Error(
				'art_master_install_state_failed',
				__( 'Не удалось получить состояние плагина после установки.', 'art-master-install' )
			);
		}

		$result_code = 'installed';
		$message     = sprintf(
			/* translators: %s: plugin name */
			__( 'Плагин «%s» установлен из GitHub.', 'art-master-install' ),
			(string) $fresh_state['name']
		);

		if ( $overwrite ) {
			$result_code = 'updated';
			$message     = sprintf(
				/* translators: %s: plugin name */
				__( 'Плагин «%s» обновлён из GitHub.', 'art-master-install' ),
				(string) $fresh_state['name']
			);
		} elseif ( 'installed_activated' === $install_result ) {
			$result_code = 'installed_activated';
			$message     = sprintf(
				/* translators: %s: plugin name */
				__( 'Плагин «%s» установлен и активирован.', 'art-master-install' ),
				(string) $fresh_state['name']
			);
		}

		$payload = Art_Master_Install_Catalog_UI::get_client_payload( $fresh_state );

		if ( ! empty( $payload['actions']['activate'] ) ) {
			$payload['activate_url'] = self::get_activate_url( (string) $fresh_state['plugin_file'] );
		}

		return array(
			'result'  => $result_code,
			'message' => $message,
			'state'   => $payload,
		);
	}

	/**
	 * @param string      $result  Result code.
	 * @param string      $slug    Catalog slug.
	 * @param string|null $message Optional error message.
	 */
	private static function redirect_with_result( $result, $slug, $message = null ) {
		$args = array(
			'page'                      => Art_Master_Install_Admin_Settings::PAGE_SETTINGS,
			'art-master-install-result' => sanitize_key( $result ),
			'art-master-install-slug'   => sanitize_key( $slug ),
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
