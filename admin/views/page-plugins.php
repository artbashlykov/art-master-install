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

	<?php Art_Master_Install_Admin_Settings::render_notices(); ?>

	<div class="art-master-install-panel" style="margin-top:10px;">
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
				<?php if ( empty( $catalog_items ) ) : ?>
					<tr>
						<td colspan="4" class="art-master-install-empty">
							<?php esc_html_e( 'Плагины пока не добавлены в каталог.', 'art-master-install' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $catalog_items as $catalog_item ) : ?>
						<tr>
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
							<td>
								<?php
								$status_class = 'art-master-install-status art-master-install-status--inactive';

								if ( 'not_installed' === $catalog_item['status'] ) {
									$status_class = 'art-master-install-status art-master-install-status--not-installed';
									$status_label = __( 'Не установлен', 'art-master-install' );
								} elseif ( 'active' === $catalog_item['status'] ) {
									if ( ! empty( $catalog_item['update_available'] ) ) {
										$status_class = 'art-master-install-status art-master-install-status--update';
										$status_label = __( 'Активен, доступно обновление', 'art-master-install' );
									} else {
										$status_class = 'art-master-install-status art-master-install-status--active';
										$status_label = __( 'Активен', 'art-master-install' );
									}
								} else {
									$status_label = __( 'Установлен, не активен', 'art-master-install' );
								}
								?>
								<span class="<?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $status_label ); ?>
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
								<?php if ( 'not_installed' === $catalog_item['status'] ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( Art_Master_Install_Admin_Actions::get_install_url( (string) $catalog_item['slug'] ) ); ?>">
										<?php esc_html_e( 'Установить', 'art-master-install' ); ?>
									</a>
								<?php else : ?>
									<?php if ( ! empty( $catalog_item['update_available'] ) ) : ?>
										<a class="button button-primary" href="<?php echo esc_url( Art_Master_Install_Admin_Actions::get_update_url( (string) $catalog_item['slug'] ) ); ?>">
											<?php esc_html_e( 'Обновить', 'art-master-install' ); ?>
										</a>
									<?php endif; ?>

									<?php if ( 'inactive' === $catalog_item['status'] ) : ?>
										<a class="button" href="<?php echo esc_url( Art_Master_Install_Admin_Actions::get_activate_url( (string) $catalog_item['plugin_file'] ) ); ?>">
											<?php esc_html_e( 'Активировать', 'art-master-install' ); ?>
										</a>
									<?php endif; ?>

									<?php if ( empty( $catalog_item['update_available'] ) && 'active' === $catalog_item['status'] ) : ?>
										<span class="description"><?php esc_html_e( 'Актуальная версия', 'art-master-install' ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
