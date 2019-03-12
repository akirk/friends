<?php
/**
 * This template contains the friend editor.
 *
 * @package Friends
 */

?><form method="post">
	<?php wp_nonce_field( 'edit-friend-' . $friend->ID ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="url"><?php esc_html_e( 'URL' ); ?></label></th>
				<td><input type="text" name="user_url" value="<?php echo esc_attr( $friend->user_url ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="url"><?php esc_html_e( 'Feed URL', 'friends' ); ?></label></th>
				<td><input type="text" name="friends_feed_url" value="<?php echo esc_attr( get_user_option( 'friends_feed_url', $friend->ID ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Will discover the feed from the URL above', 'friends' ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="url"><?php esc_html_e( 'Posts' ); ?></label></th>
				<td>
					<fieldset>
						<label for="show_on_friends_page">
							<input name="show_on_friends_page" type="checkbox" id="show_on_friends_page" value="1" <?php checked( '1', ! in_array( $friend->ID, $hide_from_friends_page ) ); ?>>
							<?php esc_html_e( 'Show posts on your friends page', 'friends' ); ?>
						</label>
					</fieldset>
					<fieldset>
					<a href="<?php echo esc_url( site_url( '/friends/' . $friend->user_login . '/' ) ); ?>">
						<?php
						// translators: %d is the number of posts.
						echo esc_html( sprintf( _n( 'View %d post', 'View %d posts', $friend_posts->found_posts, 'friends' ), $friend_posts->found_posts ) );
						?>
					</a>
					</fieldset>
					<p class="description">
					<?php
					// translators: %s is a URL.
					printf( __( '<a href=%s>Explicitly refresh</a> this feed now.', 'friends' ), esc_url( self_admin_url( 'admin.php?page=friends-refresh&user=' . $friend->ID ) ) );
					?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="url"><?php esc_html_e( 'Created', 'friends' ); ?></label></th>
				<td><?php echo date_i18n( __( 'F j, Y g:i a' ), strtotime( $friend->user_registered ) ); ?></td>
			</tr>
			<tr>
				<th><label for="status"><?php esc_html_e( 'Status', 'friends' ); ?></label></th>
				<td>
					<?php if ( $friend->has_cap( 'friend_request' ) ) : ?>
						<?php echo esc_html( _x( 'Friend Request', 'User role', 'friends' ) ); ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $friend->ID ) ), 'accept-friend-request-' . $friend->ID, 'accept-friend-request' ) ); ?>"><?php esc_html_e( 'Accept Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $friend->has_cap( 'pending_friend_request' ) ) : ?>
						<?php echo esc_html( _x( 'Pending Friend Request', 'User role', 'friends' ) ); ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=send-friend-request&url=' . $friend->user_url ) ), 'send-friend-request-' . $friend->ID, 'send-friend-request' ) ); ?>"><?php esc_html_e( 'Resend Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $friend->has_cap( 'subscription' ) ) : ?>
						<?php echo esc_html( _x( 'Subscription', 'User role', 'friends' ) ); ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=send-friend-request&url=' . $friend->user_url ) ), 'send-friend-request-' . $friend->ID, 'send-friend-request' ) ); ?>"><?php esc_html_e( 'Send Friend Request', 'friends' ); ?></a>
							<?php _e( 'or' ); ?>
							<a href="<?php echo esc_url( self_admin_url( 'admin.php?page=suggest-friends-plugin&user=' . $friend->ID ) ); ?>"><?php esc_html_e( 'Suggest Friends Plugin', 'friends' ); ?></a>
						</p>
					<?php elseif ( $friend->has_cap( 'acquaintance' ) ) : ?>
						<?php echo esc_html( _x( 'Acquaintance', 'User role', 'friends' ) ); ?>
						<p class="description">
							<?php
							// translators: %s is a friend role.
							echo wp_kses( sprintf( __( 'Change to %s.', 'friends' ), '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $friend->ID ) ), 'change-to-friend-' . $friend->ID, 'change-to-friend' ) ) . '">' . __( 'Friend', 'friends' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
							?>
							<?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?>
						</p>
					<?php elseif ( $friend->has_cap( 'friend' ) ) : ?>
						<?php echo esc_html( _x( 'Friend', 'User role', 'friends' ) ); ?>
						<p class="description">
						<?php
							// translators: %s is a friend role.
						echo wp_kses( sprintf( __( 'Change to %s.', 'friends' ), '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $friend->ID ) ), 'change-to-restricted-friend-' . $friend->ID, 'change-to-restricted-friend' ) ) . '">' . __( 'Acquaintance', 'friends' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
						?>
							<?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="friends_display_name"><?php esc_html_e( 'Display Name', 'friends' ); ?></label></th>
				<td><input type="text" name="friends_display_name" id="friends_display_name" value="<?php echo esc_attr( get_user_option( 'friends_display_name_' . $friend->ID, $user ) ?: $friend->display_name ); ?>" class="regular-text" /> <p class="description"><?php esc_html_e( 'Careful, your friend can discover this.', 'friends' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'New Post Notification', 'friends' ); ?></th>
				<td>
					<?php if ( get_user_option( 'friends_no_new_post_notification' ) ) : ?>
						<span class="description"><?php esc_html_e( 'You have generally disabled new post notifications for yourself.', 'friends' ); ?> <a href="<?php echo esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=friends-settings' ) ) ); ?>"><?php esc_html_e( 'Change setting', 'friends' ); ?></a></span>
					<?php else : ?>
					<fieldset>
						<label for="friends_new_post_notification">
							<input name="friends_new_post_notification" type="checkbox" id="friends_new_post_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_new_post_notification_' . $friend->ID ) ); ?>>
							<?php esc_html_e( 'Send me an e-mail for posts of this friend', 'friends' ); ?>
						</label>
					</fieldset>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rules', 'friends' ); ?></th>
				<td><a href="<?php echo self_admin_url( 'admin.php?page=edit-friend-rules&user=' . $friend->ID ); ?>">
					<?php
					// translators: %d is the number of rules.
					echo esc_html( sprintf( _n( '%d rule', '%d rules', count( $rules ), 'friends' ), count( $rules ) ) );
					?>
				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes' ); ?>">
	</p>
	<p class="description" id="friend_url-description">
		<?php
		// translators: %s is the user URL.
		echo wp_kses( sprintf( __( 'To unfriend this user, just <a href=%s>delete them on the users page</a>.', 'friends' ), '"' . self_admin_url( 'users.php' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</p>
</form>
