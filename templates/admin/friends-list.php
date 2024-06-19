<?php
/**
 * This template shows the Friends list.
 *
 * @version 1.0
 * @package Friends
 */

?>
<?php if ( empty( $args['friends'] ) ) : ?>
	<p><?php esc_html_e( "You don't have any friends or subscriptions yet.", 'friends' ); ?></a></p>
<?php else : ?>
	<?php if ( current_user_can( 'edit_users' ) ) : ?>
		<p>
		<?php
		echo wp_kses(
			sprintf(
				// translators: %s is the URL to the WordPress users admin page.
				__( 'You can also view your friends and subscriptions <a href=%s> on the WordPress users admin page</a>.', 'friends' ),
				Friends\Admin::get_users_url()
			),
			array(
				'a' => array( 'href' => array() ),
			)
		);
		?>
		</p>
	<?php endif; ?>
<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-right: 1em">
	<thead>
		<tr>
			<th class="column-primary column-site"><?php esc_html_e( 'User', 'friends' ); ?></th>
			<th class="column-url"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'URL' ); ?></th>
			<th class="column-date"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Since' ); ?></th>
			<th class="column-status"><?php esc_html_e( 'Status', 'friends' ); ?></th>
			<th class="column-type"><?php echo esc_html( _x( 'Type', 'of user', 'friends' ) ); ?></th>
			<th class="column-friends_posts"><?php esc_html_e( 'Friend Posts', 'friends' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $args['friends'] as $friend_user ) : ?>
		<tr>
			<td class="username column-username column-primary has-row-actions" data-colname="<?php esc_attr_e( 'Site', 'friends' ); ?>">
				<?php
				echo wp_kses(
					get_avatar( $friend_user->user_login, 32 ),
					array(
						'img' => array(
							'src'    => array(),
							'alt'    => array(),
							'class'  => array(),
							'width'  => array(),
							'height' => array(),
						),
					)
				);

				?>
				<a href="<?php echo esc_url( Friends\Admin::admin_edit_user_link( false, $friend_user ) ); ?>"><?php echo esc_html( $friend_user->display_name ); ?></a>
				<button type="button" class="toggle-row"><span class="screen-reader-text"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Show more details' ); ?></span></button>
				<div class="row-actions">
				<?php
				$actions = array(
					'edit' => sprintf(
						'<a href="%s">%s</a>',
						esc_url( Friends\Admin::admin_edit_user_link( false, $friend_user ) ),
						esc_html__( 'Edit', 'friends' )
					),
				);

				if ( $friend_user->has_cap( 'friend_request' ) ) {
					$actions['unfriend'] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( Friends\Admin::get_unfriend_link( $friend_user ) ),
						esc_html__( 'Delete', 'friends' )
					);
				} else {
					$actions['unfriend'] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( Friends\Admin::get_unfriend_link( $friend_user ) ),
						esc_html__( 'Unfriend', 'friends' )
					);
				}

				$sep = '';
				foreach ( Friends\Admin::user_row_actions( $actions, $friend_user ) as $key => $_action ) {
					echo esc_html( $sep );
					$sep = ' | ';
					?>
				<span class="<?php echo esc_attr( $key ); ?>">
					<?php
					echo wp_kses_post( $_action );
					?>
				</span>
					<?php
				}
				?>
				</div>
			</td>
			<td class="url column-url" data-colname="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'URL' ); ?>"><a href="<?php echo esc_url( $friend_user->user_url ); ?>"><?php echo esc_html( wp_parse_url( $friend_user->user_url, PHP_URL_HOST ) ); ?></a></td>
			<td class="date column-date" data-colname="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Since' ); ?>"><?php echo esc_html( date_i18n( __( 'F j, Y g:i a' ), strtotime( $friend_user->user_registered ) ) ); ?></td>
			<td class="status column-status" data-colname="<?php esc_attr_e( 'Status', 'friends' ); ?>"><?php echo esc_html( $friend_user->get_role_name() ); ?></td>
			<td class="status column-type" data-colname="<?php echo esc_attr( _x( 'Type', 'of user', 'friends' ) ); ?>">
				<?php if ( $friend_user instanceof Friends\Subscription ) : ?>
					<?php esc_html_e( 'Virtual User', 'friends' ); ?>
					<?php if ( apply_filters( 'friends_debug', false ) ) : ?>
						<span class="info">ID: <?php echo esc_html( $friend_user->get_term_id() ); ?></span>
					<?php endif; ?>
				<?php else : ?>
					<?php esc_html_e( 'User', 'friends' ); ?>
					<?php if ( apply_filters( 'friends_debug', false ) ) : ?>
						<span class="info">ID: <?php echo esc_html( $friend_user->ID ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</td>
			<td class="column-friends_posts" data-colname="<?php esc_attr_e( 'Friend Posts', 'friends' ); ?>"><?php echo wp_kses_post( Friends\Admin::user_list_custom_column( '', 'friends_posts', $friend_user->ID ) ); ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
	<?php endif; ?>
<p><a href="<?php echo esc_url( self_admin_url( 'admin.php?page=add-friend' ) ); ?>"><?php esc_html_e( 'Add New Friend', 'friends' ); ?></a></p>
