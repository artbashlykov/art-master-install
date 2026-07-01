<?php
/**
 * ART themes catalog for GitHub distribution.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Theme_Catalog
 */
class Art_Master_Install_Theme_Catalog {

	const CATALOG_TYPE = 'theme';

	/**
	 * Registered catalog items keyed by slug.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_items() {
		$items = array(
			'art-theme' => array(
				'slug'        => 'art-theme',
				'name'        => 'ART Theme',
				'description' => __( 'Простая тема без сайдбара, направленная на увеличение скорости загрузки сайта (>95 pagespeed), оптимизированная под мобильные устройства.', 'art-master-install' ),
				'github'      => 'artbashlykov/art-theme',
				'zip_name'    => 'art-theme.zip',
				'stylesheet'  => 'art-theme',
			),
		);

		/**
		 * Filters ART Master Install theme catalog entries.
		 *
		 * @param array<string, array<string, string>> $items Theme catalog items.
		 */
		return (array) apply_filters( 'art_master_install_theme_catalog', $items );
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
	 * Build runtime state for a catalog theme.
	 *
	 * @param string $slug                 Catalog slug.
	 * @param bool   $force_refresh_latest Whether to bypass cached GitHub release data.
	 * @return array<string, mixed>|null
	 */
	public static function get_item_state( $slug, $force_refresh_latest = false ) {
		$item = self::get_item( $slug );

		if ( null === $item ) {
			return null;
		}

		$stylesheet        = (string) $item['stylesheet'];
		$theme             = wp_get_theme( $stylesheet );
		$is_installed      = $theme->exists();
		$installed_version = $is_installed ? (string) $theme->get( 'Version' ) : '';
		$latest_version    = Art_Master_Install_Github::get_latest_version( $item['github'], $force_refresh_latest );
		$update_available  = $is_installed
			&& '' !== $latest_version
			&& version_compare( $installed_version, $latest_version, '<' );

		$status = 'not_installed';
		if ( $is_installed ) {
			$status = ( get_stylesheet() === $stylesheet ) ? 'active' : 'inactive';
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
