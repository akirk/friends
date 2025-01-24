<?php
/**
 * This template contains info for a plugin.
 *
 * @version 1.0
 * @package Friends
 */

$api = $args['api'];
$more_info_url = $api->details;

?><div class="plugin-card plugin-card-<?php echo esc_attr( $api->slug ); ?>">
	<div class="plugin-card-top">
		<div class="name column-name">
			<h3>
				<a class="thickbox open-plugin-details-modal" href="<?php echo esc_url( $more_info_url ); ?>"><?php echo esc_html( $api->name ); ?> <?php echo esc_html( $api->version ); ?></a>
			</h3>
		</div>

		<div class="desc column-description">
			<p><?php echo esc_html( $api->short_description ); ?></p>
			<?php if ( isset( $api->comment ) ) : ?>
			<p>
				<strong><?php esc_html_e( 'Comment from the author of the Friends Plugin', 'friends' ); ?></strong><br/>
				<?php echo esc_html( $api->comment ); ?>
			</p>
			<?php endif; ?>
			<p class="authors">
				<cite>
					<?php
					echo wp_kses(
						sprintf(
							// translators: %s is a plugin author.
							__( 'By %s' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
							$api->author
						),
						array( 'a' => array( 'href' => array() ) )
					);
					?>
				</cite>
			</p>
		</div>
	</div>

	<div class="plugin-card-bottom">
		<a class="<?php echo esc_attr( $args['button_classes'] ); ?>" data-slug="<?php echo esc_attr( $api->slug ); ?>" data-name="<?php echo esc_attr( $api->name ); ?>" href="<?php echo esc_url( $args['install_url'] ); ?>" aria-label="<?php echo /* translators: %1$s is a plugin name, %2$s is a plugin version. */ esc_html( sprintf( __( 'Install %1$s %2$s now', 'friends' ), $api->name, $api->version ) ); ?>"><?php echo esc_html( $args['button_text'] ); ?></a>

		<a class="button deactivate <?php echo esc_attr( $args['deactivate_button_class'] ); ?>"
			data-slug="<?php echo esc_attr( $api->slug ); ?>"
			data-name="<?php echo esc_attr( $api->name ); ?>"
			href="<?php echo esc_url( $args['install_url'] ); ?>">
			<?php
			echo esc_html(
				sprintf(
					// translators: %s: Plugin name.
					_x( 'Deactivate %s', 'plugin' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					$api->name
				)
			);
			?>
		</a>

		<a class="details thickbox open-plugin-details-modal" style="line-height: 2.3em" href="<?php echo esc_url( $more_info_url ); ?>" aria-label="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ echo /* translators: %s is a plugin name. */ esc_html( sprintf( __( 'More information about %s' ), $api->name ) ); ?>" data-title="<?php echo esc_attr( $api->name ); ?>"><?php esc_html_e( 'More Details' ); ?></a>

	</div>
</div>
