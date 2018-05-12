<?php
/**
 * This template contains the Friends Settings.
 *
 * @package Friends
 */

?><form method="post">
	<?php wp_nonce_field( 'friends-settings' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Friend Requests', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="ignore_incoming_friend_requests">
							<input name="ignore_incoming_friend_requests" type="checkbox" id="ignore_incoming_friend_requests" value="1" <?php checked( '1', get_option( 'friends_ignore_incoming_friend_requests' ) ); ?>>
							<?php esc_html_e( 'Ignore incoming friend requests', 'friends' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html( sprintf( __( 'Notifications for %s', 'friends' ), $user->display_name ) ); ?></th>
				<td>
					<fieldset>
						<label for="friend_request_notification">
							<input name="friend_request_notification" type="checkbox" id="friend_request_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_friend_request_notification' ) ); ?>>
							<?php esc_html_e( 'Friend Requests', 'friends' ); ?>
						</label>
						<br />
						<label for="new_post_notification">
							<input name="new_post_notification" type="checkbox" id="new_post_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_new_post_notification' ) ); ?>>
							<?php esc_html_e( 'New Posts', 'friends' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
