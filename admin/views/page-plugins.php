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
	<h1><?php esc_html_e( 'Плагины Арта', 'art-master-install' ); ?></h1>

	<div id="art-master-install-notices" class="art-master-install-notices" aria-live="polite"></div>

	<?php Art_Master_Install_Admin_Settings::render_notices(); ?>
	<?php Art_Master_Install_Admin_Settings::render_settings_saved_notice(); ?>

	<?php if ( ! Art_Master_Install_Security::can_manage() ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Просмотр каталога доступен, но для установки и обновления плагинов нужны права install_plugins и update_plugins.', 'art-master-install' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="art-master-install-panel" style="margin-top:10px;">
		<div class="art-master-install-panel-head">
			<h2><?php esc_html_e( 'Доступные плагины', 'art-master-install' ); ?></h2>
			<div class="art-master-install-toolbar">
				<p class="description art-master-install-last-check" id="art-master-install-last-check">
					<?php echo esc_html( $last_check_label ); ?>
				</p>
				<button type="button" class="button" id="art-master-install-check-updates">
					<?php esc_html_e( 'Проверить обновления', 'art-master-install' ); ?>
				</button>
			</div>
		</div>

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
				<?php if ( empty( $catalog_items ) ) : ?>
					<tr>
						<td colspan="4" class="art-master-install-empty">
							<?php esc_html_e( 'Плагины пока не добавлены в каталог.', 'art-master-install' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $catalog_items as $catalog_item ) : ?>
						<?php
						$status_badge = Art_Master_Install_Catalog_UI::get_status_badge( $catalog_item );
						$actions        = Art_Master_Install_Catalog_UI::get_actions_config( $catalog_item );
						?>
						<tr
							class="art-master-install-row"
							data-slug="<?php echo esc_attr( (string) $catalog_item['slug'] ); ?>"
							data-status="<?php echo esc_attr( (string) $catalog_item['status'] ); ?>"
						>
							<td>
								<strong><?php echo esc_html( (string) $catalog_item['name'] ); ?></strong>
								<?php if ( ! empty( $catalog_item['latest_version'] ) ) : ?>
									<br>
									<span class="description">
										<?php
										printf(
											/* translators: %s: latest release version */
											esc_html__( 'Последний релиз: %s', 'art-master-install' ),
											esc_html( (string) $catalog_item['latest_version'] )
										);
										?>
									</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) $catalog_item['description'] ); ?></td>
							<td class="art-master-install-status-cell">
								<span class="<?php echo esc_attr( $status_badge['class'] ); ?> art-master-install-status-badge">
									<?php echo esc_html( $status_badge['label'] ); ?>
								</span>

								<?php if ( ! empty( $catalog_item['installed_version'] ) ) : ?>
									<br><span class="description art-master-install-status-version">
									<?php
									printf(
										/* translators: %s: installed version */
										esc_html__( 'Версия: %s', 'art-master-install' ),
										esc_html( (string) $catalog_item['installed_version'] )
									);
									?>
									</span>
								<?php endif; ?>
							</td>
							<td class="art-master-install-actions">
								<?php if ( ! empty( $actions['install'] ) && Art_Master_Install_Security::can_install() ) : ?>
									<button
										type="button"
										class="button button-primary art-master-install-action"
										data-action="install"
										data-slug="<?php echo esc_attr( (string) $catalog_item['slug'] ); ?>"
									>
										<?php esc_html_e( 'Установить', 'art-master-install' ); ?>
									</button>
								<?php endif; ?>

								<?php if ( ! empty( $actions['update'] ) && Art_Master_Install_Security::can_update() ) : ?>
									<button
										type="button"
										class="button button-primary art-master-install-action"
										data-action="update"
										data-slug="<?php echo esc_attr( (string) $catalog_item['slug'] ); ?>"
									>
										<?php esc_html_e( 'Обновить', 'art-master-install' ); ?>
									</button>
								<?php endif; ?>

								<?php if ( ! empty( $actions['activate'] ) && current_user_can( 'activate_plugins' ) ) : ?>
									<a class="button" href="<?php echo esc_url( Art_Master_Install_Admin_Actions::get_activate_url( (string) $catalog_item['plugin_file'] ) ); ?>">
										<?php esc_html_e( 'Активировать', 'art-master-install' ); ?>
									</a>
								<?php endif; ?>

								<?php if ( ! empty( $actions['up_to_date'] ) ) : ?>
									<span class="description"><?php esc_html_e( 'Актуальная версия', 'art-master-install' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ( Art_Master_Install_Security::can_install() ) : ?>
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
							<?php esc_html_e( 'Активировать плагин автоматически', 'art-master-install' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'После установки из каталога плагин сразу включается без перехода на экран «Плагины».', 'art-master-install' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Обновления', 'art-master-install' ); ?></th>
					<td>
						<label for="art_master_install_auto_update_catalog">
							<input
								type="checkbox"
								id="art_master_install_auto_update_catalog"
								name="<?php echo esc_attr( Art_Master_Install_Settings::OPTION ); ?>[auto_update_catalog]"
								value="yes"
								<?php checked( Art_Master_Install_Settings::should_auto_update_catalog() ); ?>
							>
							<?php esc_html_e( 'Автоматически обновлять плагины из каталога', 'art-master-install' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'ART Master Install будет проверять GitHub два раза в сутки и устанавливать новые версии установленных плагинов каталога.', 'art-master-install' ); ?>
						</p>
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
							<?php esc_html_e( 'Включает автообновление через стандартный механизм WordPress. На экране «Плагины» также появится ссылка «Проверить обновления».', 'art-master-install' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php endif; ?>
</div>
