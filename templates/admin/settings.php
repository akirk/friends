<?php
/**
 * This template contains the Friends Settings.
 *
 * @package Friends
 */

$codeword_class = '';
if ( 'friends' === get_option( 'friends_codeword', 'friends' ) || ! get_option( 'friends_require_codeword' ) ) {
	$codeword_class = 'hidden';
}

?><form method="post">
	<?php wp_nonce_field( 'friends-settings' ); ?>
	<table class="form-table">
		<tbody>
			<?php
			if ( $potential_main_users->get_total() > 1 ) :
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Main Friend User', 'friends' ); ?></th>
					<td>
						<select name="main_user_id">
							<?php foreach ( $potential_main_users->get_results() as $potential_main_user ) : ?>
								<option value="<?php echo esc_attr( $potential_main_user->ID ); ?>" <?php selected( $main_user_id, $potential_main_user->ID ); ?>><?php echo esc_html( $potential_main_user->display_name ); ?></option>

							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'When remotely reacting to a post, it will be attributed to this user.', 'friends' ); ?></p>
					</td>
				</tr>
				<?php
			endif;
			?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Friend Requests', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="require_codeword">
							<input name="require_codeword" type="checkbox" id="require_codeword" value="1" <?php checked( '', $codeword_class ); ?>>
							<?php esc_html_e( 'Require a code word to send you friend request', 'friends' ); ?>
						</label>
					</fieldset>
					<div id="codeword_options" class="<?php echo $codeword_class; ?>">
						<fieldset>
							<label for="codeword">
								<?php _e( 'This code word must be provided to send you a friend request:', 'friends' ); ?> <input name="codeword" type="text" id="codeword" placeholder="friends" value="<?php echo esc_attr( get_option( 'friends_codeword', '' ) ); ?>" />
							</label>
						</fieldset>
						<fieldset>
							<label for="wrong_codeword_message">
								<p><?php _e( 'Error message for a wrong code word:', 'friends' ); ?></p>
							</label>
							<p>
								<textarea name="wrong_codeword_message" id="wrong_codeword_message" class="regular-text" rows="3" cols="80" placeholder="<?php echo esc_attr( __( 'Return this message to the friend requestor if a wrong code word was provided.' ) ); ?>"><?php echo esc_html( get_option( 'friends_wrong_codeword_message' ) ); ?></textarea>
							</p>
						</fieldset>
					</div>
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
				<p class="description"><?php esc_html_e( 'You can also change this setting for each friend separately.', 'friends' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Default Role', 'friends' ); ?></th>
				<td>
					<select name="default_role">
						<?php
						foreach ( $friend_roles as $role => $title ) :
							?>
							<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $default_role, $role ); ?>><?php echo esc_html( $title ); ?></option>

						<?php endforeach; ?>
					</select>
					<p class="description">
					<?php esc_html_e( 'When accepting a friend request, first assign this role.', 'friends' ); ?>
					<?php
					esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' );
					?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Recommendations', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="ignore_recommendations">
							<input name="ignore_recommendations" type="checkbox" id="ignore_recommendations" value="1" <?php checked( '1', get_option( 'friends_ignore_recommendations' ) ); ?>>
							<?php esc_html_e( 'Ignore post recommendations from friends', 'friends' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row" rowspan="2"><?php esc_html_e( 'Feed Reader', 'friends' ); ?></th>
				<td>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'Download <a href=%s>this OPML file</a> and import it to your feed reader.', 'friends' ), esc_url( home_url( '?friends=opml&auth=' . get_option( 'friends_private_rss_key' ) ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
					<p class="description">
					<?php
					echo __( 'If your feed reader supports it, you can also subscribe to this URL as the OPML file gets updated as you add or remove friends.', 'friends' );
					?>
					</p>
				</td>
			</tr>
			<tr>
				<td>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'You can also subscribe to a <a href=%s>compiled RSS feed of friend posts</a>.', 'friends' ), esc_url( get_post_type_archive_feed_link( Friends::CPT ) . '?auth=' . get_option( 'friends_private_rss_key' ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
					<p class="description">
					<?php
					echo __( 'Please be careful what you do with these feeds as they might contain private posts of your friends.', 'friends' );
					?>
					</p>

				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
