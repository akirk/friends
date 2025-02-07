<?php
/**
 * This template contains the friend editor.
 *
 * @version 1.0
 * @package Friends
 */

?><form method="post">
	<?php wp_nonce_field( 'edit-friend-notifications-' . $args['friend']->user_login ); ?>
	<table class="form-table">
		<tbody>
			<?php if ( $args['friend']->can_refresh_feeds() ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'New Post Notification', 'friends' ); ?></th>
				<td>
					<?php if ( get_user_option( 'friends_no_new_post_notification' ) ) : ?>
						<span class="description"><?php esc_html_e( 'You have generally disabled new post notifications for yourself.', 'friends' ); ?> <a href="<?php echo esc_url( add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=friends-settings' ) ) ); ?>"><?php esc_html_e( 'Change this setting', 'friends' ); ?></a></span>
					<?php else : ?>
					<fieldset>
						<label for="friends_new_post_notification">
							<input name="friends_new_post_notification" type="checkbox" id="friends_new_post_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_new_post_notification_' . $args['friend']->user_login ) ); ?> />
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
							<input name="friends_keyword_notification" type="checkbox" id="friends_keyword_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_keyword_notification_' . $args['friend']->user_login ) ); ?> />
							<?php
							echo wp_kses_post(
								sprintf(
									// translators: %s is a URL.
									__( 'Send me an e-mail for posts of this friend if matches one of <a href="%s">my keywords</a>', 'friends' ),
									esc_url( add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=friends-notification-manager' ) ) )
								)
							);
							?>
						</label>
					</fieldset>
				</td>
			</tr>
			<?php endif; ?>
			<?php do_action( 'friends_edit_friend_notifications_table_end', $args['friend'] ); ?>
		</tbody>
	</table>
	<?php do_action( 'friends_edit_friend_after_form', $args['friend'] ); ?>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
