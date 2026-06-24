<?php
/**
 * Scheduled and manual catalog update checks.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Catalog_Updates
 */
class Art_Master_Install_Catalog_Updates {

	const CRON_HOOK            = 'art_master_install_catalog_update_check';
	const LAST_CHECK_TRANSIENT = 'art_mi_last_catalog_check';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_check' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule_cron' ), 20 );
	}

	/**
	 * Schedule cron when auto-update settings are enabled.
	 */
	public static function maybe_schedule_cron() {
		Art_Master_Install_Settings::sync_cron_schedule();
	}

	/**
	 * Schedule recurring catalog checks if missing.
	 */
	public static function ensure_cron_scheduled() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
	}

	/**
	 * Clear scheduled catalog checks.
	 */
	public static function clear_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * @return int Unix timestamp of the last catalog check, or 0.
	 */
	public static function get_last_check_timestamp() {
		return (int) get_site_transient( self::LAST_CHECK_TRANSIENT );
	}

	/**
	 * Store the timestamp of the latest catalog check.
	 */
	public static function mark_checked() {
		set_site_transient( self::LAST_CHECK_TRANSIENT, time(), WEEK_IN_SECONDS );
	}

	/**
	 * Human-readable last check label for the admin UI.
	 *
	 * @return string
	 */
	public static function get_last_check_label() {
		$timestamp = self::get_last_check_timestamp();

		if ( $timestamp <= 0 ) {
			return __( 'Проверка ещё не выполнялась', 'art-master-install' );
		}

		return sprintf(
			/* translators: %s: localized date and time */
			__( 'Последняя проверка: %s', 'art-master-install' ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp )
		);
	}

	/**
	 * Refresh catalog states from GitHub and optionally apply auto-updates.
	 *
	 * @param bool $force_refresh Whether to bypass cached GitHub release data.
	 * @param bool $apply_auto_updates Whether to install available updates when enabled.
	 * @return array<string, mixed>
	 */
	public static function check_all( $force_refresh = true, $apply_auto_updates = false ) {
		self::mark_checked();

		$states         = Art_Master_Install_Catalog::get_all_states( $force_refresh );
		$updates_count  = 0;
		$updated_slugs  = array();
		$payload_items  = array();

		foreach ( $states as $state ) {
			if ( ! empty( $state['update_available'] ) ) {
				++$updates_count;

				if ( $apply_auto_updates && Art_Master_Install_Settings::should_auto_update_catalog() ) {
					$update_result = Art_Master_Install_Installer::install_from_github( (string) $state['slug'], true );

					if ( ! is_wp_error( $update_result ) ) {
						$item = Art_Master_Install_Catalog::get_item( (string) $state['slug'] );
						if ( is_array( $item ) && ! empty( $item['github'] ) ) {
							Art_Master_Install_Github::clear_release_cache( (string) $item['github'] );
						}

						$fresh_state = Art_Master_Install_Catalog::get_item_state( (string) $state['slug'], true );
						if ( is_array( $fresh_state ) ) {
							$state = $fresh_state;
						}

						$updated_slugs[] = (string) $state['slug'];
					}
				}
			}

			$payload_items[] = Art_Master_Install_Catalog_UI::get_client_payload( $state );
		}

		$master_state = Art_Master_Install_Updater::get_self_update_state( $force_refresh );

		return array(
			'items'           => $payload_items,
			'updates_count'   => $updates_count,
			'updated_slugs'   => $updated_slugs,
			'last_checked'    => self::get_last_check_label(),
			'message'         => self::build_check_message( $updates_count, $updated_slugs, $master_state ),
			'master_update'   => $master_state,
		);
	}

	/**
	 * Cron callback: refresh catalog cache and apply auto-updates when enabled.
	 */
	public static function run_scheduled_check() {
		if ( ! Art_Master_Install_Settings::should_auto_update_catalog()
			&& ! Art_Master_Install_Settings::should_auto_update_self() ) {
			self::clear_cron();
			return;
		}

		$apply_auto = Art_Master_Install_Settings::should_auto_update_catalog()
			|| Art_Master_Install_Settings::should_auto_update_self();

		self::check_all( true, $apply_auto );

		if ( Art_Master_Install_Settings::should_auto_update_self() ) {
			Art_Master_Install_Updater::maybe_auto_update_self();
		}
	}

	/**
	 * @param int                  $updates_count Available updates count.
	 * @param array<int, string>   $updated_slugs Slugs updated during this check.
	 * @param array<string, mixed> $master_state  Master plugin update state.
	 * @return string
	 */
	private static function build_check_message( $updates_count, array $updated_slugs, array $master_state ) {
		$messages = array();

		if ( ! empty( $updated_slugs ) ) {
			$messages[] = sprintf(
				/* translators: %d: number of updated plugins */
				_n(
					'Автоматически обновлён %d плагин из каталога.',
					'Автоматически обновлено %d плагина из каталога.',
					count( $updated_slugs ),
					'art-master-install'
				),
				count( $updated_slugs )
			);
		}

		if ( $updates_count > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of available updates */
				_n(
					'Доступно обновление для %d плагина из каталога.',
					'Доступно обновления для %d плагинов из каталога.',
					$updates_count,
					'art-master-install'
				),
				$updates_count
			);
		} elseif ( empty( $updated_slugs ) ) {
			$messages[] = __( 'Все плагины каталога актуальны.', 'art-master-install' );
		}

		if ( ! empty( $master_state['update_available'] ) ) {
			$messages[] = sprintf(
				/* translators: %s: latest release version */
				__( 'Для ART Master Install доступна версия %s.', 'art-master-install' ),
				(string) $master_state['latest_version']
			);
		}

		return implode( ' ', $messages );
	}
}
