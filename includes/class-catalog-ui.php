<?php
/**
 * Catalog table presentation helpers.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Catalog_UI
 */
class Art_Master_Install_Catalog_UI {

	/**
	 * Status badge class and label for a catalog item state.
	 *
	 * @param array<string, mixed> $item Catalog item state.
	 * @return array{class: string, label: string}
	 */
	public static function get_status_badge( array $item ) {
		$is_theme     = self::is_theme_item( $item );
		$status_class = 'art-master-install-status art-master-install-status--inactive';
		$status_label = $is_theme
			? __( 'Установлена, не активна', 'art-master-install' )
			: __( 'Установлен, не активен', 'art-master-install' );

		if ( 'not_installed' === $item['status'] ) {
			$status_class = 'art-master-install-status art-master-install-status--not-installed';
			$status_label = $is_theme
				? __( 'Не установлена', 'art-master-install' )
				: __( 'Не установлен', 'art-master-install' );
		} elseif ( 'active' === $item['status'] ) {
			if ( ! empty( $item['update_available'] ) ) {
				$status_class = 'art-master-install-status art-master-install-status--update';
				$status_label = $is_theme
					? __( 'Активна, доступно обновление', 'art-master-install' )
					: __( 'Активен, доступно обновление', 'art-master-install' );
			} else {
				$status_class = 'art-master-install-status art-master-install-status--active';
				$status_label = $is_theme
					? __( 'Активна', 'art-master-install' )
					: __( 'Активен', 'art-master-install' );
			}
		} elseif ( ! empty( $item['update_available'] ) ) {
			$status_class = 'art-master-install-status art-master-install-status--update';
			$status_label = $is_theme
				? __( 'Установлена, доступно обновление', 'art-master-install' )
				: __( 'Установлен, доступно обновление', 'art-master-install' );
		}

		return array(
			'class' => $status_class,
			'label' => $status_label,
		);
	}

	/**
	 * Serialize catalog row state for AJAX responses.
	 *
	 * @param array<string, mixed> $item Catalog item state.
	 * @return array<string, mixed>
	 */
	public static function get_client_payload( array $item ) {
		$badge = self::get_status_badge( $item );

		return array(
			'slug'              => (string) $item['slug'],
			'catalog_type'      => self::get_catalog_type( $item ),
			'status'            => (string) $item['status'],
			'installed_version' => (string) $item['installed_version'],
			'status_class'      => $badge['class'],
			'status_label'      => $badge['label'],
			'actions'           => self::get_actions_config( $item ),
		);
	}

	/**
	 * Which action buttons should be shown for a row.
	 *
	 * @param array<string, mixed> $item Catalog item state.
	 * @return array<string, bool>
	 */
	public static function get_actions_config( array $item ) {
		if ( 'not_installed' === $item['status'] ) {
			return array(
				'install' => true,
			);
		}

		$actions = array();

		if ( ! empty( $item['update_available'] ) ) {
			$actions['update'] = true;
		}

		if ( 'inactive' === $item['status'] ) {
			$actions['activate'] = true;
		}

		if ( empty( $item['update_available'] ) && 'active' === $item['status'] ) {
			$actions['up_to_date'] = true;
		}

		return $actions;
	}

	/**
	 * @param array<string, mixed> $item Catalog item state.
	 * @return string plugin|theme
	 */
	public static function get_catalog_type( array $item ) {
		return ! empty( $item['catalog_type'] ) && 'theme' === $item['catalog_type']
			? 'theme'
			: Art_Master_Install_Catalog::CATALOG_TYPE;
	}

	/**
	 * @param array<string, mixed> $item Catalog item state.
	 * @return bool
	 */
	public static function is_theme_item( array $item ) {
		return 'theme' === self::get_catalog_type( $item );
	}
}
