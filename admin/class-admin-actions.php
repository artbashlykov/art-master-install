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

	const AJAX_ACTION = 'art_master_install_catalog_action';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'handle_ajax' ) );
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$catalog_type = isset( $_POST['catalog_type'] ) ? sanitize_key( wp_unslash( $_POST['catalog_type'] ) ) : Art_Master_Install_Catalog::CATALOG_TYPE;

		if ( 'check_updates' === $catalog_action ) {
			if ( ! Art_Master_Install_Security::can_view_catalog() ) {
				wp_send_json_error(
					array(
						'message' => __( 'Недостаточно прав.', 'art-master-install' ),
					),
					403
				);
			}

			$result = Art_Master_Install_Catalog_Updates::check_all( true, false );

			wp_send_json_success(
				array(
					'message'       => $result['message'],
					'updates_count' => $result['updates_count'],
				)
			);
		}

		if ( ! in_array( $catalog_action, array( 'install', 'update' ), true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Неизвестное действие каталога.', 'art-master-install' ),
				),
				400
			);
		}

		$overwrite = ( 'update' === $catalog_action );
		$result    = self::process_catalog_action( $slug, $overwrite, $catalog_type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'      => $result->get_error_message(),
					'slug'         => $slug,
					'catalog_type' => $catalog_type,
				),
				400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Run install or update for a catalog slug.
	 *
	 * @param string $slug         Catalog slug.
	 * @param bool   $overwrite    Whether to overwrite an existing package.
	 * @param string $catalog_type plugin|theme.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function process_catalog_action( $slug, $overwrite, $catalog_type = Art_Master_Install_Catalog::CATALOG_TYPE ) {
		$catalog_type = self::normalize_catalog_type( $catalog_type );

		$allowed = $overwrite
			? self::can_update_catalog_type( $catalog_type )
			: self::can_install_catalog_type( $catalog_type );

		if ( ! $allowed ) {
			return new WP_Error(
				'art_master_install_forbidden',
				__( 'Недостаточно прав.', 'art-master-install' )
			);
		}

		if ( '' === $slug || null === self::get_catalog_item( $slug, $catalog_type ) ) {
			return new WP_Error(
				'art_master_install_unknown_slug',
				self::is_theme_catalog_type( $catalog_type )
					? __( 'Неизвестная тема каталога.', 'art-master-install' )
					: __( 'Неизвестный плагин каталога.', 'art-master-install' )
			);
		}

		$item_state = self::get_catalog_item_state( $slug, $catalog_type, false );

		if ( $overwrite ) {
			if ( null === $item_state || empty( $item_state['is_installed'] ) ) {
				return new WP_Error(
					'art_master_install_not_installed',
					self::is_theme_catalog_type( $catalog_type )
						? __( 'Тема не установлена — обновление невозможно.', 'art-master-install' )
						: __( 'Плагин не установлен — обновление невозможно.', 'art-master-install' )
				);
			}
		} elseif ( null !== $item_state && ! empty( $item_state['is_installed'] ) ) {
			return new WP_Error(
				'art_master_install_already_installed',
				self::is_theme_catalog_type( $catalog_type )
					? __( 'Тема уже установлена.', 'art-master-install' )
					: __( 'Плагин уже установлен.', 'art-master-install' )
			);
		}

		$install_result = self::is_theme_catalog_type( $catalog_type )
			? Art_Master_Install_Theme_Installer::install_from_github( $slug, $overwrite )
			: Art_Master_Install_Installer::install_from_github( $slug, $overwrite );

		if ( is_wp_error( $install_result ) ) {
			return $install_result;
		}

		$item = self::get_catalog_item( $slug, $catalog_type );
		if ( is_array( $item ) && ! empty( $item['github'] ) ) {
			Art_Master_Install_Github::clear_release_cache( (string) $item['github'] );
		}

		$fresh_state = self::get_catalog_item_state( $slug, $catalog_type, true );
		if ( null === $fresh_state ) {
			return new WP_Error(
				'art_master_install_state_failed',
				self::is_theme_catalog_type( $catalog_type )
					? __( 'Не удалось получить состояние темы после установки.', 'art-master-install' )
					: __( 'Не удалось получить состояние плагина после установки.', 'art-master-install' )
			);
		}

		if ( $overwrite ) {
			if ( self::is_theme_catalog_type( $catalog_type ) ) {
				$message = sprintf(
					/* translators: %s: catalog item name */
					__( 'Тема «%s» обновлена из GitHub.', 'art-master-install' ),
					(string) $fresh_state['name']
				);
			} else {
				$message = sprintf(
					/* translators: %s: catalog item name */
					__( 'Плагин «%s» обновлён из GitHub.', 'art-master-install' ),
					(string) $fresh_state['name']
				);
			}
		} elseif ( 'installed_activated' === $install_result ) {
			if ( self::is_theme_catalog_type( $catalog_type ) ) {
				$message = sprintf(
					/* translators: %s: catalog item name */
					__( 'Тема «%s» установлена и активирована.', 'art-master-install' ),
					(string) $fresh_state['name']
				);
			} else {
				$message = sprintf(
					/* translators: %s: catalog item name */
					__( 'Плагин «%s» установлен и активирован.', 'art-master-install' ),
					(string) $fresh_state['name']
				);
			}
		} elseif ( self::is_theme_catalog_type( $catalog_type ) ) {
			$message = sprintf(
				/* translators: %s: catalog item name */
				__( 'Тема «%s» установлена из GitHub.', 'art-master-install' ),
				(string) $fresh_state['name']
			);
		} else {
			$message = sprintf(
				/* translators: %s: catalog item name */
				__( 'Плагин «%s» установлен из GitHub.', 'art-master-install' ),
				(string) $fresh_state['name']
			);
		}

		$payload = Art_Master_Install_Catalog_UI::get_client_payload( $fresh_state );

		if ( ! empty( $payload['actions']['activate'] ) ) {
			if ( self::is_theme_catalog_type( $catalog_type ) ) {
				$payload['activate_url'] = self::get_activate_theme_url( (string) $fresh_state['stylesheet'] );
			} else {
				$payload['activate_url'] = self::get_activate_url( (string) $fresh_state['plugin_file'] );
			}
		}

		return array(
			'message' => $message,
			'state'   => $payload,
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

	/**
	 * Activate theme URL.
	 *
	 * @param string $stylesheet Theme stylesheet slug.
	 * @return string
	 */
	public static function get_activate_theme_url( $stylesheet ) {
		$stylesheet = sanitize_key( $stylesheet );

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'activate',
					'stylesheet' => $stylesheet,
				),
				admin_url( 'themes.php' )
			),
			'switch-theme_' . $stylesheet
		);
	}

	/**
	 * @param string $catalog_type Raw catalog type.
	 * @return string plugin|theme
	 */
	private static function normalize_catalog_type( $catalog_type ) {
		return Art_Master_Install_Theme_Catalog::CATALOG_TYPE === sanitize_key( (string) $catalog_type )
			? Art_Master_Install_Theme_Catalog::CATALOG_TYPE
			: Art_Master_Install_Catalog::CATALOG_TYPE;
	}

	/**
	 * @param string $catalog_type plugin|theme.
	 * @return bool
	 */
	private static function is_theme_catalog_type( $catalog_type ) {
		return Art_Master_Install_Theme_Catalog::CATALOG_TYPE === self::normalize_catalog_type( $catalog_type );
	}

	/**
	 * @param string $slug Catalog slug.
	 * @param string $catalog_type plugin|theme.
	 * @return array<string, string>|null
	 */
	private static function get_catalog_item( $slug, $catalog_type ) {
		if ( self::is_theme_catalog_type( $catalog_type ) ) {
			return Art_Master_Install_Theme_Catalog::get_item( $slug );
		}

		return Art_Master_Install_Catalog::get_item( $slug );
	}

	/**
	 * @param string $slug Catalog slug.
	 * @param string $catalog_type plugin|theme.
	 * @param bool   $force_refresh Whether to bypass cached GitHub release data.
	 * @return array<string, mixed>|null
	 */
	private static function get_catalog_item_state( $slug, $catalog_type, $force_refresh ) {
		if ( self::is_theme_catalog_type( $catalog_type ) ) {
			return Art_Master_Install_Theme_Catalog::get_item_state( $slug, $force_refresh );
		}

		return Art_Master_Install_Catalog::get_item_state( $slug, $force_refresh );
	}

	/**
	 * @param string $catalog_type plugin|theme.
	 * @return bool
	 */
	private static function can_install_catalog_type( $catalog_type ) {
		return self::is_theme_catalog_type( $catalog_type )
			? Art_Master_Install_Security::can_install_themes()
			: Art_Master_Install_Security::can_install();
	}

	/**
	 * @param string $catalog_type plugin|theme.
	 * @return bool
	 */
	private static function can_update_catalog_type( $catalog_type ) {
		return self::is_theme_catalog_type( $catalog_type )
			? Art_Master_Install_Security::can_update_themes()
			: Art_Master_Install_Security::can_update();
	}
}
