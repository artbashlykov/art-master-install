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
		if ( ! in_array( $hook, Art_Master_Install_Admin_Settings::get_page_hooks(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'art-master-install-admin',
			ART_MASTER_INSTALL_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ART_MASTER_INSTALL_VERSION
		);

		if ( ! Art_Master_Install_Security::can_view_catalog() ) {
			return;
		}

		wp_enqueue_script(
			'art-master-install-catalog',
			ART_MASTER_INSTALL_PLUGIN_URL . 'assets/js/admin-catalog.js',
			array(),
			ART_MASTER_INSTALL_VERSION,
			true
		);

		wp_localize_script(
			'art-master-install-catalog',
			'artMasterInstallCatalog',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'ajaxAction'   => Art_Master_Install_Admin_Actions::AJAX_ACTION,
				'nonce'        => wp_create_nonce( Art_Master_Install_Admin_Actions::AJAX_ACTION ),
				'autoActivate' => Art_Master_Install_Settings::should_auto_activate(),
				'canInstall'   => Art_Master_Install_Security::can_install(),
				'canUpdate'    => Art_Master_Install_Security::can_update(),
				'i18n'         => array(
					'install'        => __( 'Установить', 'art-master-install' ),
					'update'         => __( 'Обновить', 'art-master-install' ),
					'activate'       => __( 'Активировать', 'art-master-install' ),
					'upToDate'       => __( 'Актуальная версия', 'art-master-install' ),
					'queued'         => __( 'В очереди…', 'art-master-install' ),
					'installing'     => __( 'Устанавливается…', 'art-master-install' ),
					'activating'     => __( 'Активируется…', 'art-master-install' ),
					'updating'       => __( 'Обновляется…', 'art-master-install' ),
					'checking'       => __( 'Проверяем обновления…', 'art-master-install' ),
					'checkUpdates'   => __( 'Проверить обновления', 'art-master-install' ),
					'genericError'   => __( 'Не удалось выполнить действие с плагином.', 'art-master-install' ),
					'checkError'     => __( 'Не удалось проверить обновления.', 'art-master-install' ),
					/* translators: 1: installed version, 2: latest GitHub version or dash */
					'selfUpdateStatus' => __( 'Установленная версия: %1$s. Последний релиз на GitHub: %2$s.', 'art-master-install' ),
					'selfUpdateAvailable' => __( 'Доступно обновление ART Master Install.', 'art-master-install' ),
					'goToUpdates'    => __( 'Перейти к обновлениям', 'art-master-install' ),
					/* translators: %s: plugin version */
					'versionLabel'   => __( 'Версия: %s', 'art-master-install' ),
				),
			)
		);
	}

	/**
	 * Add catalog link on the plugins list page.
	 *
	 * @param array<int, string> $links Plugin action links.
	 * @return array<int, string>
	 */
	public static function plugin_action_links( $links ) {
		if ( ! Art_Master_Install_Security::can_view_catalog() ) {
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
