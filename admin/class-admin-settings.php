<?php
/**
 * Admin catalog page.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Admin_Settings
 */
class Art_Master_Install_Admin_Settings {

	const PAGE_SETTINGS = 'art-master-install';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_pages' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_plugins_url' ) );
		add_filter( 'option_page_capability_art_master_install_settings_group', array( __CLASS__, 'get_settings_capability' ) );
	}

	/**
	 * Capability for saving catalog settings via options.php.
	 *
	 * @return string
	 */
	public static function get_settings_capability() {
		return Art_Master_Install_Security::can_install() ? 'install_plugins' : 'manage_options';
	}

	/**
	 * Register settings group.
	 */
	public static function register_settings() {
		register_setting(
			'art_master_install_settings_group',
			Art_Master_Install_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Art_Master_Install_Settings', 'sanitize' ),
			)
		);
	}

	/**
	 * Register catalog page under Settings.
	 */
	public static function register_admin_pages() {
		add_options_page(
			__( 'Плагины Арта', 'art-master-install' ),
			__( 'Плагины Арта', 'art-master-install' ),
			'manage_options',
			self::PAGE_SETTINGS,
			array( __CLASS__, 'render_catalog_page' )
		);
	}

	/**
	 * Redirect old Plugins submenu URL to Settings.
	 */
	public static function redirect_legacy_plugins_url() {
		global $pagenow;

		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Legacy URL redirect only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::PAGE_SETTINGS !== $page ) {
			return;
		}

		wp_safe_redirect( self::get_page_url() );
		exit;
	}

	/**
	 * Render plugins catalog page.
	 */
	public static function render_catalog_page() {
		if ( ! Art_Master_Install_Security::can_view_catalog() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-master-install' ) );
		}

		$catalog_items = Art_Master_Install_Catalog::get_all_states( false );
		$last_check_label = Art_Master_Install_Catalog_Updates::get_last_check_label();
		$master_update    = Art_Master_Install_Updater::get_self_update_state( false );

		include ART_MASTER_INSTALL_PLUGIN_DIR . 'admin/views/page-plugins.php';
	}

	/**
	 * Show a success notice after options.php saves settings.
	 */
	public static function render_settings_saved_notice() {
		$settings_updated = filter_input( INPUT_GET, 'settings-updated', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( empty( $settings_updated ) ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		esc_html_e( 'Настройки сохранены.', 'art-master-install' );
		echo '</p></div>';
	}

	/**
	 * Render admin notices after install/update actions.
	 */
	public static function render_notices() {
		$result = filter_input( INPUT_GET, 'art-master-install-result', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( empty( $result ) ) {
			return;
		}

		$result = sanitize_key( (string) $result );

		$slug_input = filter_input( INPUT_GET, 'art-master-install-slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$slug       = is_string( $slug_input ) ? sanitize_key( $slug_input ) : '';

		$raw_message = filter_input( INPUT_GET, 'art-master-install-message', FILTER_DEFAULT );
		$message     = is_string( $raw_message )
			? sanitize_text_field( rawurldecode( wp_unslash( $raw_message ) ) )
			: '';

		$item = '' !== $slug ? Art_Master_Install_Catalog::get_item( $slug ) : null;
		$name = is_array( $item ) ? (string) $item['name'] : $slug;

		if ( 'installed_activated' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf(
				/* translators: %s: plugin name */
				esc_html__( 'Плагин «%s» установлен и активирован.', 'art-master-install' ),
				esc_html( $name )
			);
			echo '</p></div>';
			return;
		}

		if ( 'installed' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf(
				/* translators: %s: plugin name */
				esc_html__( 'Плагин «%s» установлен из GitHub.', 'art-master-install' ),
				esc_html( $name )
			);
			echo '</p></div>';
			return;
		}

		if ( 'updated' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf(
				/* translators: %s: plugin name */
				esc_html__( 'Плагин «%s» обновлён из GitHub.', 'art-master-install' ),
				esc_html( $name )
			);
			echo '</p></div>';
			return;
		}

		if ( 'error' === $result ) {
			echo '<div class="notice notice-error is-dismissible"><p>';
			if ( '' !== $message ) {
				echo esc_html( $message );
			} else {
				esc_html_e( 'Не удалось выполнить действие с плагином.', 'art-master-install' );
			}
			echo '</p></div>';
		}
	}

	/**
	 * Catalog page URL.
	 *
	 * @return string
	 */
	public static function get_page_url() {
		return add_query_arg(
			array(
				'page' => self::PAGE_SETTINGS,
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Admin screen hook suffixes for the catalog page.
	 *
	 * @return array<int, string>
	 */
	public static function get_page_hooks() {
		return array(
			'settings_page_' . self::PAGE_SETTINGS,
		);
	}
}
