<?php
/**
 * This template contains the friend editor.
 *
 * @version 1.0
 * @package Friends
 */

$available_avatars = apply_filters( 'friends_potential_avatars', array(), $args['friend'] );

?><form method="post">
	<?php wp_nonce_field( 'edit-friend-' . $args['friend']->user_login ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="friends_avatar"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Avatar' ); ?></label></th>
				<td>
				<?php echo get_avatar( $args['friend']->user_login ); ?>
			</td>
			</tr>
			<tr>
				<th><label for="friends_set_avatar"><?php esc_html_e( 'Update Avatar', 'friends' ); ?></label></th>
				<td id="friend-avatar-setting">
					<?php
					foreach ( $available_avatars as $avatar => $_title ) {
						?>
						<a href="" data-nonce="<?php echo esc_attr( wp_create_nonce( 'set-avatar-' . $args['friend']->user_login ) ); ?>" data-user="<?php echo esc_attr( $args['friend']->user_login ); ?>" class="set-avatar"><img src="<?php echo esc_url( $avatar ); /* phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage */ ?>" alt="<?php echo esc_attr( $_title ); ?>" title="<?php echo esc_attr( $_title ); ?>" width="32" height="32" /></a>
						<?php
					}
					if ( ! empty( $available_avatars ) ) :
						?>
					<p class="description">
						<?php esc_html_e( 'Click to set as new avatar.', 'friends' ); ?><br/>
					</p>
					<?php endif; ?>
						<input type="url" id="new-avatar-url" placeholder="<?php esc_attr_e( 'Enter an image URL', 'friends' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'set-avatar-' . $args['friend']->user_login ) ); ?>" data-user="<?php echo esc_attr( $args['friend']->user_login ); ?>" value="<?php echo esc_attr( $args['friend']->get_avatar_url() ); ?>"/>
						<button id='set-avatar-url'><?php esc_html_e( 'Use this URL', 'friends' ); ?></button>
						<p class="description">
							<?php esc_html_e( 'Please specify a square, not too large image URL here.', 'friends' ); ?><br/>
						</p>
				</td>
			</tr>

			<?php do_action( 'friends_edit_friend_after_avatar', $args['friend'] ); ?>
			<tr>
				<th><label for="friends_display_name"><?php esc_html_e( 'Display Name', 'friends' ); ?></label></th>
				<td><input type="text" name="friends_display_name" id="friends_display_name" value="<?php echo esc_attr( $args['friend']->display_name ); ?>" class="regular-text" /> <p class="description"><?php esc_html_e( 'Careful, your friend can discover this.', 'friends' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="friends_description"><?php esc_html_e( 'Description', 'friends' ); ?></label></th>
				<td><textarea name="friends_description" id="friends_description" rows="5" cols="30"><?php echo esc_html( $args['friend']->description ); ?></textarea><p class="description"><?php esc_html_e( 'Careful, your friend can discover this.', 'friends' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="url"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'URL' ); ?></label></th>
				<td><input type="text" name="user_url" value="<?php echo esc_attr( $args['friend']->user_url ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Created', 'friends' ); ?></th>
				<td><?php echo esc_html( date_i18n( /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ __( 'F j, Y g:i a' ), strtotime( $args['friend']->user_registered ) ) ); ?></td>
			</tr>
			<tr>
				<th><label for="status"><?php echo esc_html( _x( 'Type', 'of user', 'friends' ) ); ?></label></th>
				<td>
					<?php if ( $args['friend'] instanceof Friends\Subscription ) : ?>
						<?php esc_html_e( 'Virtual User', 'friends' ); ?>
						<?php if ( apply_filters( 'friends_debug', false ) ) : ?>
							<span class="info">ID: <?php echo esc_html( $args['friend']->get_term_id() ); ?></span>
						<?php endif; ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', rawurlencode( remove_query_arg( '_wp_http_referer' ) ), add_query_arg( 'user', $args['friend']->user_login, self_admin_url( 'admin.php?page=edit-friend' ) ) ), 'convert-to-user-' . $args['friend']->user_login, 'convert-to-user' ) ); ?>"><?php esc_html_e( 'Convert to User', 'friends' ); ?></a>
						</p>
					<?php else : ?>
						<?php esc_html_e( 'User', 'friends' ); ?>
						<?php if ( apply_filters( 'friends_debug', false ) ) : ?>
							<span class="info">ID: <?php echo esc_html( $args['friend']->ID ); ?></span>
						<?php endif; ?>
						<p class="description">
							<?php if ( $args['friend']->has_cap( 'friend' ) || $args['friend']->has_cap( 'pending_friend_request' ) || $args['friend']->has_cap( 'friend_request' ) ) : ?>
							<?php else : ?>
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', rawurlencode( remove_query_arg( '_wp_http_referer' ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->user_login ) ), 'convert-from-user-' . $args['friend']->user_login, 'convert-from-user' ) ); ?>"><?php esc_html_e( 'Convert to Virtual User', 'friends' ); ?></a>
						<?php endif; ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="status"><?php esc_html_e( 'Status', 'friends' ); ?></label></th>
				<td>
					<?php echo esc_html( $args['friend']->get_role_name() ); ?>
					<?php if ( $args['friend']->has_cap( 'friend_request' ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->user_login ) ), 'accept-friend-request-' . $args['friend']->user_login, 'accept-friend-request' ) ); ?>"><?php esc_html_e( 'Accept Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'pending_friend_request' ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', rawurlencode( remove_query_arg( '_wp_http_referer' ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->user_login ) ), 'add-friend-' . $args['friend']->user_login, 'add-friend' ) ); ?>"><?php esc_html_e( 'Resend Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'subscription' ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', rawurlencode( remove_query_arg( '_wp_http_referer' ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->user_login ) ), 'add-friend-' . $args['friend']->user_login, 'add-friend' ) ); ?>"><?php esc_html_e( 'Send Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'acquaintance' ) ) : ?>
						<p class="description">
							<?php
							// translators: %s is a friend role.
							echo wp_kses( sprintf( __( 'Change to %s.', 'friends' ), '<a href="' . esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', rawurlencode( remove_query_arg( '_wp_http_referer' ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->user_login ) ), 'change-to-friend-' . $args['friend']->user_login, 'change-to-friend' ) ) . '">' . __( 'Friend', 'friends' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
							?>
							<?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'friend' ) ) : ?>
						<p class="description">
						<?php
							// translators: %s is a friend role.
						echo wp_kses( sprintf( __( 'Change to %s.', 'friends' ), '<a href="' . esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', rawurlencode( remove_query_arg( '_wp_http_referer' ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->user_login ) ), 'change-to-restricted-friend-' . $args['friend']->user_login, 'change-to-restricted-friend' ) ) . '">' . __( 'Acquaintance', 'friends' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
						?>
							<?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php do_action( 'friends_edit_friend_table_end', $args['friend'] ); ?>
		</tbody>
	</table>
	<?php do_action( 'friends_edit_friend_after_form', $args['friend'] ); ?>
	<p class="submit">
		<div class="button unfriend"><a class="unfriend" href="<?php echo esc_url( Friends\Admin::get_unfriend_link( $args['friend'] ) ); ?>"><?php esc_html_e( 'Unfriend', 'friends' ); ?></a></div>
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
