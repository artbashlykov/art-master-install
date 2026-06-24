<?php
/**
 * Admin catalog page under WordPress Settings.
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
		add_action( 'admin_menu', array( __CLASS__, 'register_options_page' ), 99999 );
	}

	/**
	 * Register catalog page under WordPress Settings menu (last item).
	 */
	public static function register_options_page() {
		add_options_page(
			__( 'Плагины Арта', 'art-master-install' ),
			__( 'Плагины Арта', 'art-master-install' ),
			'manage_options',
			self::PAGE_SETTINGS,
			array( __CLASS__, 'render_catalog_page' )
		);
	}

	/**
	 * Render plugins catalog page.
	 */
	public static function render_catalog_page() {
		if ( ! Art_Master_Install_Security::can_manage() ) {
			return;
		}

		$catalog_items = Art_Master_Install_Catalog::get_all_states( true );

		include ART_MASTER_INSTALL_PLUGIN_DIR . 'admin/views/page-plugins.php';
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
}
