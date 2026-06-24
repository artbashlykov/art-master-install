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
		$status_class = 'art-master-install-status art-master-install-status--inactive';
		$status_label = __( 'Установлен, не активен', 'art-master-install' );

		if ( 'not_installed' === $item['status'] ) {
			$status_class = 'art-master-install-status art-master-install-status--not-installed';
			$status_label = __( 'Не установлен', 'art-master-install' );
		} elseif ( 'active' === $item['status'] ) {
			if ( ! empty( $item['update_available'] ) ) {
				$status_class = 'art-master-install-status art-master-install-status--update';
				$status_label = __( 'Активен, доступно обновление', 'art-master-install' );
			} else {
				$status_class = 'art-master-install-status art-master-install-status--active';
				$status_label = __( 'Активен', 'art-master-install' );
			}
		} elseif ( ! empty( $item['update_available'] ) ) {
			$status_class = 'art-master-install-status art-master-install-status--update';
			$status_label = __( 'Установлен, доступно обновление', 'art-master-install' );
		}

		return array(
			'class' => $status_class,
			'label' => $status_label,
		);
	}

	/**
	 * Pending status badge for queued or in-progress actions.
	 *
	 * @param string $phase queued|installing|activating|updating.
	 * @return array{class: string, label: string}
	 */
	public static function get_pending_status_badge( $phase ) {
		$labels = array(
			'queued'     => __( 'В очереди…', 'art-master-install' ),
			'installing' => __( 'Устанавливается…', 'art-master-install' ),
			'activating' => __( 'Активируется…', 'art-master-install' ),
			'updating'   => __( 'Обновляется…', 'art-master-install' ),
		);

		return array(
			'class' => 'art-master-install-status art-master-install-status--pending',
			'label' => $labels[ $phase ] ?? $labels['installing'],
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
			'name'              => (string) $item['name'],
			'status'            => (string) $item['status'],
			'installed_version' => (string) $item['installed_version'],
			'latest_version'    => (string) $item['latest_version'],
			'update_available'  => ! empty( $item['update_available'] ),
			'is_active'         => ! empty( $item['is_active'] ),
			'plugin_file'       => (string) $item['plugin_file'],
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
}
