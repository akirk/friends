<?php
/**
 * This template contains the friend editor.
 *
 * @package Friends
 */

$has_last_log = false;
?><form method="post">
	<?php wp_nonce_field( 'edit-friend-' . $friend->ID ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="url"><?php esc_html_e( 'URL' ); ?></label></th>
				<td><input type="text" name="user_url" value="<?php echo esc_attr( $friend->user_url ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="url"><?php esc_html_e( 'Feeds', 'friends' ); ?></label></th>
				<td>
					<?php if ( empty( $friend->get_active_feeds() ) ) : ?>
						<?php _e( 'There are no active feeds.', 'friends' ); ?>
					<?php endif; ?>
					<table class="feed-table<?php echo empty( $friend->get_active_feeds() ) ? ' hidden' : ''; ?>">
						<thead>
							<tr>
								<th class="checkbox"><?php _e( 'Active', 'friends' ); ?></th>
								<th><?php _e( 'Feed URL', 'friends' ); ?></th>
								<th><?php _e( 'Parser', 'friends' ); ?></th>
								<th><?php _e( 'Post Format' ); ?></th>
								<th><?php _e( 'Remarks', 'friends' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $friend->get_feeds() as $term_id => $feed ) :
							if ( $feed->get_last_log() ) {
								$has_last_log = true;
								$last_log = $feed->get_last_log();
							}
							?>
							<tr class="<?php echo $feed->get_active() ? 'active' : 'inactive hidden'; ?>">
								<td><input type="checkbox" name="feeds[<?php echo esc_attr( $term_id ); ?>][active]" value="1" aria-label="<?php _e( 'Feed is active', 'friends' ); ?>"<?php checked( $feed->get_active() ); ?> /></td>
								<td><input type="url" name="feeds[<?php echo esc_attr( $term_id ); ?>][url]" value="<?php echo esc_attr( $feed->get_url() ); ?>" size="20" aria-label="<?php _e( 'Feed URL', 'friends' ); ?>" /></td>
								<td><select name="feeds[<?php echo esc_attr( $term_id ); ?>][parser]" aria-label="<?php _e( 'Parser', 'friends' ); ?>">
									<?php foreach ( $registered_parsers as $slug => $parser_name ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $slug, $feed->get_parser() ); ?>><?php echo esc_html( strip_tags( $parser_name ) ); ?></option>
									<?php endforeach; ?>
								</select> <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=add-friend&parser=' . urlencode( $feed->get_parser() ) . '&preview=' . urlencode( $feed->get_url() ) ) ), 'preview-feed' ) ); ?>" class="preview-parser" target="_blank" rel="noopener noreferrer"><?php _e( 'Preview', 'friends' ); ?></a></td>
								<td><select name="feeds[<?php echo esc_attr( $term_id ); ?>][post-format]" aria-label="<?php _e( 'Post Format' ); ?>">
									<?php foreach ( $post_formats as $format => $title ) : ?>
										<option value="<?php echo esc_attr( $format ); ?>"<?php selected( $format, $feed->get_post_format() ); ?>><?php echo esc_html( $title ); ?></option>
									<?php endforeach; ?>
								</select></td>
								<td><input type="text" name="feeds[<?php echo esc_attr( $term_id ); ?>][title]" value="<?php echo esc_attr( $feed->get_title() ); ?>" size="20" aria-label="<?php _e( 'Feed Name', 'friends' ); ?>" /></td>
							</tr>
							<tr class="<?php echo $feed->get_active() ? 'active' : 'inactive hidden'; ?> lastlog hidden">
								<td colspan="5" class="notice"><?php echo esc_html( $feed->get_last_log() ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( count( $friend->get_active_feeds() ) !== count( $friend->get_feeds() ) ) : ?>
					<a href="" class="show-inactive-feeds"><?php _e( 'Show inactive feeds', 'friends' ); ?></a> |
					<?php endif; ?>
					<?php if ( $has_last_log ) : ?>
					<a href="" class="show-log-lines"><?php _e( 'Show log output', 'friends' ); ?></a>
					<?php endif; ?>
				</td>
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
				<th><?php esc_html_e( 'Created', 'friends' ); ?></th>
				<td><?php echo date_i18n( __( 'F j, Y g:i a' ), strtotime( $friend->user_registered ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Last feed retrieval', 'friends' ); ?></th>
				<td>
					<?php echo date_i18n( __( 'F j, Y g:i a' ), strtotime( substr( $last_log, 0, 19 ) ) ); ?>:
					<em><?php echo esc_html( substr( $last_log, 20 ) ); ?></em>
				</td>
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
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $friend->ID ) ), 'add-friend-' . $friend->ID, 'add-friend' ) ); ?>"><?php esc_html_e( 'Resend Friend Request', 'friends' ); ?></a>
						</p>
					<?php elseif ( $friend->has_cap( 'subscription' ) ) : ?>
						<?php echo esc_html( _x( 'Subscription', 'User role', 'friends' ) ); ?>
						<p class="description">
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $friend->ID ) ), 'add-friend-' . $friend->ID, 'add-friend' ) ); ?>"><?php esc_html_e( 'Send Friend Request', 'friends' ); ?></a>
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
				<?php
				$friends_display_name = get_user_option( 'friends_display_name_' . $friend->ID, $friend );
				?>
				<th><label for="friends_display_name"><?php esc_html_e( 'Display Name', 'friends' ); ?></label></th>
				<td><input type="text" name="friends_display_name" id="friends_display_name" value="<?php echo esc_attr( $friends_display_name ? $friends_display_name : $friend->display_name ); ?>" class="regular-text" /> <p class="description"><?php esc_html_e( 'Careful, your friend can discover this.', 'friends' ); ?></p></td>
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
		echo wp_kses( sprintf( __( 'To unfriend this user, just <a href=%s>delete them on the users page</a>.', 'friends' ), '"' . self_admin_url( 'users.php?s=' . urlencode( $friend->user_login ) ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</p>
</form>
