<?php
/**
 * Catalog table rows partial.
 *
 * Expected variables: $catalog_items, $catalog_type, $empty_message.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

$catalog_type       = isset( $catalog_type ) ? sanitize_key( (string) $catalog_type ) : Art_Master_Install_Catalog::CATALOG_TYPE;
$empty_message      = isset( $empty_message ) ? (string) $empty_message : __( 'Плагины пока не добавлены в каталог.', 'art-master-install' );
$is_theme_catalog   = 'theme' === $catalog_type;
$can_install        = $is_theme_catalog ? Art_Master_Install_Security::can_install_themes() : Art_Master_Install_Security::can_install();
$can_update         = $is_theme_catalog ? Art_Master_Install_Security::can_update_themes() : Art_Master_Install_Security::can_update();
$can_activate       = $is_theme_catalog ? Art_Master_Install_Security::can_switch_themes() : current_user_can( 'activate_plugins' );

?>
<?php if ( empty( $catalog_items ) ) : ?>
	<tr>
		<td colspan="4" class="art-master-install-empty">
			<?php echo esc_html( $empty_message ); ?>
		</td>
	</tr>
<?php else : ?>
	<?php foreach ( $catalog_items as $catalog_item ) : ?>
		<?php
		$status_badge = Art_Master_Install_Catalog_UI::get_status_badge( $catalog_item );
		$actions      = Art_Master_Install_Catalog_UI::get_actions_config( $catalog_item );
		?>
		<tr
			class="art-master-install-row"
			data-slug="<?php echo esc_attr( (string) $catalog_item['slug'] ); ?>"
			data-catalog-type="<?php echo esc_attr( $catalog_type ); ?>"
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
				<?php if ( ! empty( $actions['install'] ) && $can_install ) : ?>
					<button
						type="button"
						class="button button-primary art-master-install-action"
						data-action="install"
						data-slug="<?php echo esc_attr( (string) $catalog_item['slug'] ); ?>"
						data-catalog-type="<?php echo esc_attr( $catalog_type ); ?>"
					>
						<?php esc_html_e( 'Установить', 'art-master-install' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( ! empty( $actions['update'] ) && $can_update ) : ?>
					<button
						type="button"
						class="button button-primary art-master-install-action"
						data-action="update"
						data-slug="<?php echo esc_attr( (string) $catalog_item['slug'] ); ?>"
						data-catalog-type="<?php echo esc_attr( $catalog_type ); ?>"
					>
						<?php esc_html_e( 'Обновить', 'art-master-install' ); ?>
					</button>
				<?php endif; ?>

				<?php if ( ! empty( $actions['activate'] ) && $can_activate ) : ?>
					<?php if ( $is_theme_catalog ) : ?>
						<a class="button" href="<?php echo esc_url( Art_Master_Install_Admin_Actions::get_activate_theme_url( (string) $catalog_item['stylesheet'] ) ); ?>">
							<?php esc_html_e( 'Активировать', 'art-master-install' ); ?>
						</a>
					<?php else : ?>
						<a class="button" href="<?php echo esc_url( Art_Master_Install_Admin_Actions::get_activate_url( (string) $catalog_item['plugin_file'] ) ); ?>">
							<?php esc_html_e( 'Активировать', 'art-master-install' ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( ! empty( $actions['up_to_date'] ) ) : ?>
					<span class="description"><?php esc_html_e( 'Актуальная версия', 'art-master-install' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
<?php endif; ?>
