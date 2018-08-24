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
				<th scope="row"><?php esc_html_e( 'Main Friend User', 'friends' ); ?></th>
				<td>
					<select name="main_user_id">
						<?php foreach ( $potential_main_users->get_results() as $potential_main_user ) : ?>
							<option value="<?php echo esc_attr( $potential_main_user->ID ); ?>" <?php selected( $main_user_id, $current_user->ID ); ?>><?php echo esc_html( $potential_main_user->display_name ); ?></option>

						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'When remotely reacting to a post, it will be attributed to this user.', 'friends' ); ?></p>
				</td>
			</tr>
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
				<th scope="row">
				<?php
				esc_html_e( 'E-Mail Notifications', 'friends' );
				?>
				</th>
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
				<p class="description"><?php esc_html_e( 'You can also change these settings on a friend by friend basis.', 'friends' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Recommendations', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="autosend_recommendations">
							<input name="autosend_recommendations" type="checkbox" id="autosend_recommendations" value="1" <?php checked( '1', ! get_user_option( 'friends_no_autosend_recommendations' ) ); ?>>
							<?php esc_html_e( 'Automatically recommend posts to friends when you reacted to them', 'friends' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Feed Reader', 'friends' ); ?></th>
				<td>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'Download <a href=%s>this OPML file</a> and import it to your feed reader.', 'friends' ), esc_url( self_admin_url( 'admin.php?page=friends-opml' ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
