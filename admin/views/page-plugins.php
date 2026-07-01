<?php
/**
 * Plugins catalog page view.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

?>
<div class="wrap art-master-install-admin">
	<hr class="wp-header-end">

	<div class="art-master-install-page-head">
		<h1><?php esc_html_e( 'Каталог Арта', 'art-master-install' ); ?></h1>
		<div class="art-master-install-page-toolbar">
			<p class="description art-master-install-last-check" id="art-master-install-last-check">
				<?php echo esc_html( $last_check_label ); ?>
			</p>
			<button type="button" class="button" id="art-master-install-check-updates">
				<?php esc_html_e( 'Проверить обновления', 'art-master-install' ); ?>
			</button>
		</div>
	</div>

	<div id="art-master-install-notices" class="art-master-install-notices" aria-live="polite"></div>

	<?php if ( ! Art_Master_Install_Security::can_manage() || ! Art_Master_Install_Security::can_manage_themes() ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'Просмотр каталога доступен, но для установки и обновления нужны права install_plugins, update_plugins, install_themes и update_themes.', 'art-master-install' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="art-master-install-panel" style="margin-top:10px;">
		<h2><?php esc_html_e( 'Доступные темы', 'art-master-install' ); ?></h2>

		<table class="widefat striped art-master-install-table" role="presentation">
			<colgroup>
				<col class="art-master-install-col-plugin">
				<col class="art-master-install-col-description">
				<col class="art-master-install-col-status">
				<col class="art-master-install-col-actions">
			</colgroup>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Тема', 'art-master-install' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Описание', 'art-master-install' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Статус', 'art-master-install' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Действия', 'art-master-install' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$catalog_type      = Art_Master_Install_Theme_Catalog::CATALOG_TYPE;
				$catalog_items     = $theme_catalog_items;
				$empty_message     = __( 'Темы пока не добавлены в каталог.', 'art-master-install' );
				include ART_MASTER_INSTALL_PLUGIN_DIR . 'admin/views/part-catalog-rows.php';
				?>
			</tbody>
		</table>
	</div>

	<div class="art-master-install-panel">
		<h2><?php esc_html_e( 'Доступные плагины', 'art-master-install' ); ?></h2>

		<table class="widefat striped art-master-install-table" role="presentation">
			<colgroup>
				<col class="art-master-install-col-plugin">
				<col class="art-master-install-col-description">
				<col class="art-master-install-col-status">
				<col class="art-master-install-col-actions">
			</colgroup>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Плагин', 'art-master-install' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Описание', 'art-master-install' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Статус', 'art-master-install' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Действия', 'art-master-install' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$catalog_type      = Art_Master_Install_Catalog::CATALOG_TYPE;
				$catalog_items     = $plugin_catalog_items;
				$empty_message     = __( 'Плагины пока не добавлены в каталог.', 'art-master-install' );
				include ART_MASTER_INSTALL_PLUGIN_DIR . 'admin/views/part-catalog-rows.php';
				?>
			</tbody>
		</table>
	</div>

	<?php if ( Art_Master_Install_Security::can_install() || Art_Master_Install_Security::can_install_themes() ) : ?>
	<div class="art-master-install-panel">
		<h2><?php esc_html_e( 'Настройки', 'art-master-install' ); ?></h2>

		<div class="art-master-install-self-update" id="art-master-install-self-update">
			<p>
				<strong><?php esc_html_e( 'ART Master Install', 'art-master-install' ); ?></strong>
			</p>
			<p class="description art-master-install-self-update-status">
				<?php
				printf(
					/* translators: 1: installed version, 2: latest GitHub version or dash */
					esc_html__( 'Установленная версия: %1$s. Последний релиз на GitHub: %2$s.', 'art-master-install' ),
					esc_html( (string) $master_update['installed_version'] ),
					'' !== (string) $master_update['latest_version']
						? esc_html( (string) $master_update['latest_version'] )
						: '—'
				);
				?>
			</p>
			<?php if ( ! empty( $master_update['update_available'] ) ) : ?>
				<p class="art-master-install-self-update-notice">
					<?php esc_html_e( 'Доступно обновление ART Master Install.', 'art-master-install' ); ?>
					<a href="<?php echo esc_url( (string) $master_update['updates_url'] ); ?>">
						<?php esc_html_e( 'Перейти к обновлениям', 'art-master-install' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

		<form method="post" action="options.php" class="art-master-install-settings-form">
			<?php settings_fields( 'art_master_install_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'После установки', 'art-master-install' ); ?></th>
					<td>
						<label for="art_master_install_auto_activate">
							<input
								type="checkbox"
								id="art_master_install_auto_activate"
								name="<?php echo esc_attr( Art_Master_Install_Settings::OPTION ); ?>[auto_activate]"
								value="yes"
								<?php checked( Art_Master_Install_Settings::should_auto_activate() ); ?>
							>
							<?php esc_html_e( 'Активировать автоматически', 'art-master-install' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'После установки из каталога плагин или тема сразу включаются без перехода на экран «Плагины» или «Темы».', 'art-master-install' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Обновления', 'art-master-install' ); ?></th>
					<td>
						<div class="art-master-install-setting-option">
							<label for="art_master_install_auto_update_catalog">
								<input
									type="checkbox"
									id="art_master_install_auto_update_catalog"
									name="<?php echo esc_attr( Art_Master_Install_Settings::OPTION ); ?>[auto_update_catalog]"
									value="yes"
									<?php checked( Art_Master_Install_Settings::should_auto_update_catalog() ); ?>
								>
								<?php esc_html_e( 'Автоматически обновлять плагины и темы из каталога', 'art-master-install' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'ART Master Install будет проверять GitHub 1 раз в сутки и устанавливать новые версии установленных плагинов и тем каталога.', 'art-master-install' ); ?>
							</p>
						</div>
						<div class="art-master-install-setting-option">
							<label for="art_master_install_auto_update_self">
								<input
									type="checkbox"
									id="art_master_install_auto_update_self"
									name="<?php echo esc_attr( Art_Master_Install_Settings::OPTION ); ?>[auto_update_self]"
									value="yes"
									<?php checked( Art_Master_Install_Settings::should_auto_update_self() ); ?>
								>
								<?php esc_html_e( 'Автоматически обновлять ART Master Install', 'art-master-install' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Включает автообновление ART Master Install через стандартный фоновый механизм WordPress.', 'art-master-install' ); ?>
							</p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Данные при удалении', 'art-master-install' ); ?></th>
					<td>
						<div class="art-master-install-setting-option">
							<label for="art_master_install_delete_data_on_uninstall">
								<input
									type="checkbox"
									id="art_master_install_delete_data_on_uninstall"
									name="<?php echo esc_attr( Art_Master_Install_Settings::OPTION ); ?>[delete_data_on_uninstall]"
									value="yes"
									<?php checked( Art_Master_Install_Settings::should_delete_data_on_uninstall() ); ?>
								>
								<?php esc_html_e( 'Удалять все данные плагина при удалении плагина', 'art-master-install' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Если включено, при удалении ART Master Install через экран «Плагины» будут удалены настройки каталога, кэш GitHub, служебные transients и запланированные проверки обновлений. Плагины и темы, установленные через каталог, не удаляются.', 'art-master-install' ); ?>
							</p>
						</div>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php endif; ?>
</div>
