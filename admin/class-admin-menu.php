<?php
/**
 * Admin assets and plugin list links.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Admin_Menu
 */
class Art_Master_Install_Admin_Menu {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . ART_MASTER_INSTALL_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta_forge' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta_strip_details' ), 100, 2 );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_' . Art_Master_Install_Admin_Settings::PAGE_SETTINGS !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'art-master-install-admin',
			ART_MASTER_INSTALL_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ART_MASTER_INSTALL_VERSION
		);
	}

	/**
	 * Add catalog link on the plugins list page.
	 *
	 * @param array<int, string> $links Plugin action links.
	 * @return array<int, string>
	 */
	public static function plugin_action_links( $links ) {
		if ( ! Art_Master_Install_Security::can_manage() ) {
			return $links;
		}

		$catalog_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Art_Master_Install_Admin_Settings::get_page_url() ),
			esc_html__( 'Плагины Арта', 'art-master-install' )
		);

		return array_merge( array( $catalog_link ), $links );
	}

	/**
	 * Add author materials link on plugins page (before PUC «Check for updates»).
	 *
	 * @param array<int, string> $links Plugin row meta links.
	 * @param string             $file  Plugin basename.
	 * @return array<int, string>
	 */
	public static function plugin_row_meta_forge( $links, $file ) {
		if ( ART_MASTER_INSTALL_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( ART_MASTER_INSTALL_AUTHOR_URL ),
			esc_html__( 'Больше материалов автора', 'art-master-install' )
		);

		return $links;
	}

	/**
	 * Remove PUC «View details» link from plugin row meta.
	 *
	 * @param array<int, string> $links Plugin row meta links.
	 * @param string             $file  Plugin basename.
	 * @return array<int, string>
	 */
	public static function plugin_row_meta_strip_details( $links, $file ) {
		if ( ART_MASTER_INSTALL_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		return array_values(
			array_filter(
				$links,
				static function ( $link ) {
					return false === strpos( $link, 'open-plugin-details-modal' )
						&& false === strpos( $link, 'plugin-install.php?tab=plugin-information' );
				}
			)
		);
	}
}
