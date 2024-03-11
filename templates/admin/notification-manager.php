<?php
/**
 * This template contains the notification manager.
 *
 * @version 1.0
 * @package Friends
 */

?><form method="post">
	<?php wp_nonce_field( 'notification-manager' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
				<?php
				esc_html_e( 'E-Mail Notifications', 'friends' );
				?>
				</th>
				<td>
					<fieldset>
						<label for="friend_request_notification">
							<input name="friend_request_notification" type="checkbox" id="friend_request_notification" value="1" <?php checked( '1', ! $args['no_friend_request_notification'] ); ?>>
							<span><?php esc_html_e( 'Friend Requests', 'friends' ); ?></span>
						</label>
						<br />
						<label for="new_post_notification">
							<input name="new_post_notification" type="checkbox" id="new_post_notification" value="1" <?php checked( '1', ! $args['no_new_post_notification'] ); ?>>
							<span><?php esc_html_e( 'New Posts', 'friends' ); ?></span>
						</label>
					</fieldset>
					<p class="description"><?php esc_html_e( 'When you disable post notifications, their individual configuration is disabled also.', 'friends' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
				<?php
				esc_html_e( 'Keyword Notifications', 'friends' );
				?>
				</th>
				<td>
					<fieldset>

						<ol id="keyword-notifications">
							<li id="keyword-template" style="display: none">
								<input type="checkbox" name="notification_keywords_enabled[0]" value="1" checked />
								<input type="text" name="notification_keywords[0]" value="" placeholder="<?php esc_html_e( 'Keyword (regex allowed)', 'friends' ); ?>">
							</li>
						<?php foreach ( $args['notification_keywords'] as $i => $entry ) : ?>
							<li>
								<input type="checkbox" name="notification_keywords_enabled[<?php echo esc_attr( $i + 1 ); ?>]" value="1" <?php checked( $entry['enabled'] ); ?> />
								<input type="text" name="notification_keywords[<?php echo esc_attr( $i + 1 ); ?>]" value="<?php echo esc_attr( $entry['keyword'] ); ?>" placeholder="<?php esc_html_e( 'Keyword (regex allowed)', 'friends' ); ?>">
							</li>
						<?php endforeach; ?>
						</ol>
						<a href="" id="admin-add-keyword"><?php esc_html_e( 'Add a notification keyword', 'friends' ); ?></a>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Empty keywords will be deleted after saving. You can temporarily disable them with the checkbox.', 'friends' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-bottom: 2em; margin-right: 1em">
		<thead>
			<tr>
				<th class="column-primary column-friend"><?php esc_html_e( 'Friend', 'friends' ); ?></th>
				<th class="column-friends-page-feeds"><?php esc_html_e( 'Show on friends page', 'friends' ); ?></th>
				<th class="column-email-notification"><?php esc_html_e( 'E-Mail Notification', 'friends' ); ?></th>
				<th class="column-email-notification"><?php esc_html_e( 'Keyword Notification', 'friends' ); ?></th>
				<?php do_action( 'friends_notification_manager_header' ); ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $args['friend_users'] as $friend_user ) : ?>
				<tr>
					<td class="column-friend">
						<a href="<?php echo esc_url( Friends\Admin::get_edit_friend_link( $friend_user->user_login ) ); ?>"><?php echo esc_html( $friend_user->display_name ); ?></a>
						<input type="hidden" name="friend_listed[]" value="<?php echo esc_attr( $friend_user->user_login ); ?>" />
					</td>
					<td class="column-friends-page-feeds">
						<input name="show_on_friends_page[<?php echo esc_attr( $friend_user->user_login ); ?>]" type="checkbox" id="show_on_friends_page" value="1" <?php checked( '1', ! in_array( $friend_user->user_login, $args['hide_from_friends_page'] ) ); ?> />
					</td>
					<td class="column-email-notification">
						<?php if ( $args['no_new_post_notification'] ) : ?>
							<input disabled="disabled" type="checkbox" <?php checked( '1', ! get_user_option( 'friends_no_new_post_notification_' . $friend_user->user_login ) ); ?> />
							<input name="new_friend_post_notification[<?php echo esc_attr( $friend_user->user_login ); ?>]" type="hidden" value="<?php echo get_user_option( 'friends_no_new_post_notification_' . $friend_user->user_login ) ? '0' : '1'; ?>" />
						<?php else : ?>
						<input name="new_friend_post_notification[<?php echo esc_attr( $friend_user->user_login ); ?>]" type="checkbox" id="friends_new_friend_post_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_new_post_notification_' . $friend_user->user_login ) ); ?> <?php echo $args['no_new_post_notification'] ? 'disabled="disabled"' : ''; ?>
						/>
						<?php endif; ?>
					</td>
					<td class="column-keyword-notification">
						<input name="keyword_notification[<?php echo esc_attr( $friend_user->user_login ); ?>]" type="checkbox" id="friends_keyword_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_keyword_notification_' . $friend_user->user_login ) ); ?> <?php echo $args['no_keyword_notification'] ? 'disabled="disabled"' : ''; ?>
						/>
					</td>
				<?php do_action( 'friends_notification_manager_row', $friend_user ); ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
