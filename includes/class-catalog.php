<?php
/**
 * ART plugins catalog for GitHub distribution.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Catalog
 */
class Art_Master_Install_Catalog {

	const CATALOG_TYPE = 'plugin';

	/**
	 * Registered catalog items keyed by slug.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_items() {
		$items = array(
			'art-starter' => array(
				'slug'        => 'art-starter',
				'name'        => 'ART Starter',
				'description' => __( 'Плагин, который позволяет быстро настроить техническую часть сайта, настроить главную страницу (в формате удобной визитки на 7 шаблонов) и настроить страницу 404.', 'art-master-install' ),
				'github'      => 'artbashlykov/art-starter',
				'zip_name'    => 'art-starter.zip',
				'plugin_file' => 'art-starter/art-starter.php',
			),
			'art-lms'     => array(
				'slug'        => 'art-lms',
				'name'        => 'ART LMS',
				'description' => __( 'Мини-LMS с автовыдачей цифровых продуктов и автоприёмом платежей для физлиц, ИП и самозанятых.', 'art-master-install' ),
				'github'      => 'artbashlykov/art-lms',
				'zip_name'    => 'art-lms.zip',
				'plugin_file' => 'art-lms/art-lms.php',
			),
			'art-editor'  => array(
				'slug'        => 'art-editor',
				'name'        => 'ART Editor',
				'description' => __( 'Простой способ создавать лендинги с помощью нейросетей и редактировать их визуально, через понятный редактор HTML-блоков', 'art-master-install' ),
				'github'      => 'artbashlykov/art-editor',
				'zip_name'    => 'art-editor.zip',
				'plugin_file' => 'art-editor/art-editor.php',
			),
		);

		/**
		 * Filters ART Master Install catalog entries.
		 *
		 * @param array<string, array<string, string>> $items Catalog items.
		 */
		return (array) apply_filters( 'art_master_install_catalog', $items );
	}

	/**
	 * @param string $slug Catalog slug.
	 * @return array<string, string>|null
	 */
	public static function get_item( $slug ) {
		$slug  = sanitize_key( $slug );
		$items = self::get_items();

		return isset( $items[ $slug ] ) ? $items[ $slug ] : null;
	}

	/**
	 * Build runtime state for a catalog item.
	 *
	 * @param string $slug                  Catalog slug.
	 * @param bool   $force_refresh_latest  Whether to bypass cached GitHub release data.
	 * @return array<string, mixed>|null
	 */
	public static function get_item_state( $slug, $force_refresh_latest = false ) {
		$item = self::get_item( $slug );

		if ( null === $item ) {
			return null;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file       = $item['plugin_file'];
		$installed_plugins = get_plugins();
		$is_installed      = isset( $installed_plugins[ $plugin_file ] );
		$installed_version = $is_installed ? (string) $installed_plugins[ $plugin_file ]['Version'] : '';
		$latest_version    = Art_Master_Install_Github::get_latest_version( $item['github'], $force_refresh_latest );
		$update_available  = $is_installed
			&& '' !== $latest_version
			&& version_compare( $installed_version, $latest_version, '<' );

		$status = 'not_installed';
		if ( $is_installed ) {
			$status = is_plugin_active( $plugin_file ) ? 'active' : 'inactive';
		}

		return array_merge(
			$item,
			array(
				'catalog_type'      => self::CATALOG_TYPE,
				'status'            => $status,
				'is_installed'      => $is_installed,
				'is_active'         => 'active' === $status,
				'installed_version' => $installed_version,
				'latest_version'    => $latest_version,
				'update_available'  => $update_available,
			)
		);
	}

	/**
	 * @param bool $force_refresh_latest Whether to bypass cached GitHub release data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all_states( $force_refresh_latest = false ) {
		$states = array();

		foreach ( self::get_items() as $slug => $unused ) {
			$state = self::get_item_state( $slug, $force_refresh_latest );
			if ( null !== $state ) {
				$states[] = $state;
			}
		}

		return $states;
	}
}
