<?php
/**
 * This template shows the latest friends section.
 *
 * @version 1.0
 * @package Friends
 */

?>
<h3><?php esc_html_e( 'Your Friends & Subscriptions', 'friends' ); ?></h3>
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
			<th style="width: 18em" class="column-primary column-site"><?php esc_html_e( 'User', 'friends' ); ?></th>
			<th style="width: 15em" class="column-date"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Date' ); ?></th>
			<th class="column-status"><?php esc_html_e( 'Status', 'friends' ); ?></th>
			<th class="column-actions"><?php esc_html_e( 'Actions', 'friends' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $args['friends'] as $friend_user ) : ?>
		<tr>
			<td class="site column-site column-primary" data-colname="<?php esc_attr_e( 'Site', 'friends' ); ?>">
				<a href="<?php echo esc_url( apply_filters( 'get_edit_user_link', $friend_user->user_url, $friend_user->ID ) ); ?>"><?php echo esc_html( $friend_user->display_name ); ?></a>
				<button type="button" class="toggle-row"><span class="screen-reader-text"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Show more details' ); ?></span></button>
			</td>
			<td class="date column-date" data-colname="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'Date' ); ?>"><?php echo esc_html( date_i18n( __( 'F j, Y g:i a' ), strtotime( $friend_user->user_registered ) ) ); ?></td>
			<td class="status column-status" data-colname="<?php esc_attr_e( 'Status', 'friends' ); ?>"><?php echo esc_html( $friend_user->get_role_name() ); ?></td>
			<td class="column-actions" data-colname="<?php esc_attr_e( 'Actions', 'friends' ); ?>">
			<?php
			$actions = array(
				'edit' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( apply_filters( 'get_edit_user_link', $friend_user->user_url, $friend_user->ID ) ),
					esc_html__( 'Edit', 'friends' )
				),
			);
			if ( current_user_can( 'delete_users' ) ) {
				$actions['delete'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( wp_nonce_url( "users.php?action=delete&user=$friend_user->ID", 'bulk-users' ) ),
					esc_html__( 'Unfriend', 'friends' )
				);
			} else {
				$actions['delete'] = '<span title="' . esc_attr__( 'You need to be an Administrator to unfriend', 'friends' ) . '">' . esc_html__( 'Unfriend', 'friends' ) . '</span>';
			}
			$sep = '';
			foreach ( Friends\Admin::user_row_actions( $actions, $friend_user ) as $key => $action ) {
				echo esc_html( $sep );
				$sep = ' | ';
				?>
				<span class="<?php echo esc_attr( $key ); ?>">
				<?php
				echo wp_kses_post( $action );
				?>
				</span>
				<?php
			}
			?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
	<?php endif; ?>
<p><a href="<?php echo esc_url( self_admin_url( 'admin.php?page=add-friend' ) ); ?>"><?php esc_html_e( 'Add New Friend', 'friends' ); ?></a></p>
