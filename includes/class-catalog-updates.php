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
	const CRON_RECURRENCE      = 'daily';
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
		$event = wp_get_scheduled_event( self::CRON_HOOK );

		if ( $event instanceof stdClass && self::CRON_RECURRENCE !== $event->schedule ) {
			self::clear_cron();
		}

		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, self::CRON_RECURRENCE, self::CRON_HOOK );
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

		$plugin_result = self::collect_catalog_payload(
			Art_Master_Install_Catalog::get_all_states( $force_refresh ),
			Art_Master_Install_Catalog::CATALOG_TYPE,
			$apply_auto_updates
		);

		$theme_result = self::collect_catalog_payload(
			Art_Master_Install_Theme_Catalog::get_all_states( $force_refresh ),
			Art_Master_Install_Theme_Catalog::CATALOG_TYPE,
			$apply_auto_updates
		);

		$updates_count = $plugin_result['updates_count'] + $theme_result['updates_count'];
		$updated_slugs = array_merge( $plugin_result['updated_slugs'], $theme_result['updated_slugs'] );
		$master_state  = Art_Master_Install_Updater::get_self_update_state( $force_refresh );

		return array(
			'updates_count' => $updates_count,
			'updated_slugs' => $updated_slugs,
			'message'       => self::build_check_message(
				$plugin_result,
				$theme_result,
				$master_state
			),
		);
	}

	/**
	 * Cron callback: refresh catalog cache and apply auto-updates when enabled.
	 */
	public static function run_scheduled_check() {
		if ( ! Art_Master_Install_Settings::should_auto_update_catalog() ) {
			self::clear_cron();
			return;
		}

		self::check_all( true, true );
	}

	/**
	 * Build payloads and optionally auto-update one catalog type.
	 *
	 * @param array<int, array<string, mixed>> $states Catalog states.
	 * @param string                           $catalog_type plugin|theme.
	 * @param bool                             $apply_auto_updates Whether to install available updates.
	 * @return array{updates_count: int, updated_slugs: array<int, string>}
	 */
	private static function collect_catalog_payload( array $states, $catalog_type, $apply_auto_updates ) {
		$updates_count = 0;
		$updated_slugs = array();

		foreach ( $states as $state ) {
			if ( ! empty( $state['update_available'] ) ) {
				++$updates_count;

				if ( $apply_auto_updates && Art_Master_Install_Settings::should_auto_update_catalog() ) {
					$update_result = self::apply_auto_update( $state, $catalog_type );

					if ( ! is_wp_error( $update_result ) ) {
						self::clear_item_release_cache( $state, $catalog_type );

						$fresh_state = self::get_fresh_item_state( (string) $state['slug'], $catalog_type, true );
						if ( is_array( $fresh_state ) ) {
							$state = $fresh_state;
						}

						$updated_slugs[] = (string) $state['slug'];
					}
				}
			}
		}

		return array(
			'updates_count' => $updates_count,
			'updated_slugs' => $updated_slugs,
		);
	}

	/**
	 * @param array<string, mixed> $state Catalog item state.
	 * @param string               $catalog_type plugin|theme.
	 * @return true|WP_Error
	 */
	private static function apply_auto_update( array $state, $catalog_type ) {
		$slug = (string) $state['slug'];

		if ( Art_Master_Install_Theme_Catalog::CATALOG_TYPE === $catalog_type ) {
			return Art_Master_Install_Theme_Installer::install_from_github( $slug, true );
		}

		return Art_Master_Install_Installer::install_from_github( $slug, true );
	}

	/**
	 * @param array<string, mixed> $state Catalog item state.
	 * @param string               $catalog_type plugin|theme.
	 */
	private static function clear_item_release_cache( array $state, $catalog_type ) {
		if ( empty( $state['github'] ) ) {
			return;
		}

		Art_Master_Install_Github::clear_release_cache( (string) $state['github'] );
	}

	/**
	 * @param string $slug Catalog slug.
	 * @param string $catalog_type plugin|theme.
	 * @param bool   $force_refresh Whether to bypass cached GitHub release data.
	 * @return array<string, mixed>|null
	 */
	private static function get_fresh_item_state( $slug, $catalog_type, $force_refresh ) {
		if ( Art_Master_Install_Theme_Catalog::CATALOG_TYPE === $catalog_type ) {
			return Art_Master_Install_Theme_Catalog::get_item_state( $slug, $force_refresh );
		}

		return Art_Master_Install_Catalog::get_item_state( $slug, $force_refresh );
	}

	/**
	 * @param array<string, mixed> $plugin_result Plugin catalog check result.
	 * @param array<string, mixed> $theme_result  Theme catalog check result.
	 * @param array<string, mixed> $master_state  Master plugin update state.
	 * @return string
	 */
	private static function build_check_message( array $plugin_result, array $theme_result, array $master_state ) {
		$messages      = array();
		$updated_count = count( $plugin_result['updated_slugs'] ) + count( $theme_result['updated_slugs'] );

		if ( ! empty( $plugin_result['updated_slugs'] ) ) {
			$messages[] = sprintf(
				/* translators: %d: number of updated plugins */
				_n(
					'Автоматически обновлён %d плагин из каталога.',
					'Автоматически обновлено %d плагина из каталога.',
					count( $plugin_result['updated_slugs'] ),
					'art-master-install'
				),
				count( $plugin_result['updated_slugs'] )
			);
		}

		if ( ! empty( $theme_result['updated_slugs'] ) ) {
			$messages[] = sprintf(
				/* translators: %d: number of updated themes */
				_n(
					'Автоматически обновлена %d тема из каталога.',
					'Автоматически обновлено %d тем из каталога.',
					count( $theme_result['updated_slugs'] ),
					'art-master-install'
				),
				count( $theme_result['updated_slugs'] )
			);
		}

		$available_count = (int) $plugin_result['updates_count'] + (int) $theme_result['updates_count'];

		if ( $available_count > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of available updates */
				_n(
					'Доступно обновление для %d позиции каталога.',
					'Доступно обновления для %d позиций каталога.',
					$available_count,
					'art-master-install'
				),
				$available_count
			);
		} elseif ( 0 === $updated_count ) {
			$messages[] = __( 'Все плагины и темы каталога актуальны.', 'art-master-install' );
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
