<?php
/**
 * This template contains the friend editor.
 *
 * @version 1.0
 * @package Friends
 */

?><form method="post">
	<?php wp_nonce_field( 'edit-friend-' . $args['friend']->ID ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="friends_avatar"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Avatar' ); ?></label></th>
				<td>
				<?php echo get_avatar( $args['friend']->ID ); ?>
			</td>
			</tr>
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
				<th><label for="status"><?php esc_html_e( 'Status', 'friends' ); ?></label></th>
				<td>
					<?php echo esc_html( $args['friend']->get_role_name() ); ?>
					<?php if ( $args['friend']->has_cap( 'friend_request' ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'accept-friend-request-' . $args['friend']->ID, 'accept-friend-request' ) ); ?>"><?php esc_html_e( 'Accept Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'pending_friend_request' ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'add-friend-' . $args['friend']->ID, 'add-friend' ) ); ?>"><?php esc_html_e( 'Resend Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'subscription' ) ) : ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'add-friend-' . $args['friend']->ID, 'add-friend' ) ); ?>"><?php esc_html_e( 'Send Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'acquaintance' ) ) : ?>
						<p class="description">
							<?php
							// translators: %s is a friend role.
							echo wp_kses( sprintf( __( 'Change to %s.', 'friends' ), '<a href="' . esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'change-to-friend-' . $args['friend']->ID, 'change-to-friend' ) ) . '">' . __( 'Friend', 'friends' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
							?>
							<?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?>
						</p>
					<?php elseif ( $args['friend']->has_cap( 'friend' ) ) : ?>
						<p class="description">
						<?php
							// translators: %s is a friend role.
						echo wp_kses( sprintf( __( 'Change to %s.', 'friends' ), '<a href="' . esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $args['friend']->ID ) ), 'change-to-restricted-friend-' . $args['friend']->ID, 'change-to-restricted-friend' ) ) . '">' . __( 'Acquaintance', 'friends' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
						?>
							<?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $args['friend']->can_refresh_feeds() ) : ?>
			<tr>
				<th><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Posts' ); ?></th>
				<td>
					<fieldset>
						<label for="show_on_friends_page">
							<input name="show_on_friends_page" type="checkbox" id="show_on_friends_page" value="1" <?php checked( '1', ! in_array( $args['friend']->ID, $args['hide_from_friends_page'] ) ); ?>>
							<?php esc_html_e( 'Show posts on your friends page', 'friends' ); ?>
						</label>
					</fieldset>
					<fieldset>
					<a href="<?php echo esc_url( $args['friend']->get_local_friends_page_url() ); ?>">
						<?php
						// translators: %d is the number of posts.
						echo esc_html( sprintf( _n( 'View %d post', 'View %d posts', $args['friend_posts'], 'friends' ), $args['friend_posts'] ) );
						?>
					</a>
					<?php if ( apply_filters( 'friends_debug', false ) ) : ?>
						| <a href="<?php echo esc_url( self_admin_url( 'edit.php?post_type=' . Friends\Friends::CPT . '&author=' . $args['friend']->ID ) ); ?>">
							<?php
							// translators: %d is the number of posts.
							echo esc_html( sprintf( _n( 'View %d cached post', 'View %d cached posts', $args['friend_posts'], 'friends' ), $args['friend_posts'] ) );
							?>
						</a>

					<?php endif; ?>
					</fieldset>
					<p class="description">
					<?php
					// translators: %s is a URL.
					printf( __( '<a href=%s>Explicitly refresh</a> this feed now.', 'friends' ), esc_url( self_admin_url( 'admin.php?page=friends-refresh&user=' . $args['friend']->ID ) ) );
					?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'New Post Notification', 'friends' ); ?></th>
				<td>
					<?php if ( get_user_option( 'friends_no_new_post_notification' ) ) : ?>
						<span class="description"><?php esc_html_e( 'You have generally disabled new post notifications for yourself.', 'friends' ); ?> <a href="<?php echo esc_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=friends-settings' ) ) ); ?>"><?php esc_html_e( 'Change this setting', 'friends' ); ?></a></span>
					<?php else : ?>
					<fieldset>
						<label for="friends_new_post_notification">
							<input name="friends_new_post_notification" type="checkbox" id="friends_new_post_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_new_post_notification_' . $args['friend']->ID ) ); ?> />
							<?php esc_html_e( 'Send me an e-mail for posts of this friend', 'friends' ); ?>
						</label>
					</fieldset>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Keyword Notification', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="friends_keyword_notification">
							<input name="friends_keyword_notification" type="checkbox" id="friends_keyword_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_keyword_notification_' . $args['friend']->ID ) ); ?> />
							<?php
							echo wp_kses_post(
								sprintf(
									// translators: %s is a URL.
									__( 'Send me an e-mail for posts of this friend if matches one of <a href="%s">my keywords</a>', 'friends' ),
									esc_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=friends-settings' ) ) )
								)
							);
							?>
						</label>
					</fieldset>
				</td>
			</tr>
			<?php endif; ?>
			<?php do_action( 'friends_edit_friend_table_end', $args['friend'] ); ?>
		</tbody>
	</table>
	<?php do_action( 'friends_edit_friend_after_form', $args['friend'] ); ?>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
	<p class="description" id="friend_url-description">
		<?php
		// translators: %s is the user URL.
		echo wp_kses( sprintf( __( 'To unfriend this user, just <a href=%s>delete them on the users page</a>.', 'friends' ), '"' . self_admin_url( 'users.php?s=' . urlencode( $args['friend']->user_login ) ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</p>
</form>
