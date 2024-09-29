<?php
/**
 * This template contains the notification manager.
 *
 * @version 1.0
 * @package Friends
 */

$disabled_post_formats = array();
foreach ( get_post_format_strings() as $post_format => $label ) {
	if ( get_user_option( 'friends_no_new_post_format_notification_' . $post_format ) ) {
		$disabled_post_formats[] = $post_format;
	}
}
$disabled_feed_parsers = array();
foreach ( $args['feed_parsers'] as $feed_parser => $label ) {
	if ( get_user_option( 'friends_no_new_post_by_parser_notification_' . $feed_parser ) ) {
		$disabled_feed_parsers[] = $feed_parser;
	}
}

?><form method="post">
	<?php wp_nonce_field( 'notification-manager' ); ?>
	<p class="explanation"><?php esc_html_e( 'The settings on this page apply just to you, other users can configure their own settings.', 'friends' ); ?></p>
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
						<?php if ( isset( $args['no_friend_follower_notification'] ) ) : ?>
						<label for="friend_follower_notification">
							<input name="friend_follower_notification" type="checkbox" id="friend_follower_notification" value="1" <?php checked( '1', ! $args['no_friend_follower_notification'] ); ?>>
							<span><?php esc_html_e( 'New Followers', 'friends' ); ?></span>
							<span><?php esc_html_e( '(via ActivityPub)', 'friends' ); ?></span>
						</label>
						<br />
						<?php endif; ?>
						<label for="new_post_notification">
							<input name="new_post_notification" type="checkbox" id="new_post_notification" value="1" <?php checked( '1', ! $args['no_new_post_notification'] ); ?>>
							<span><?php esc_html_e( 'New Posts', 'friends' ); ?></span>
						</label>
					</fieldset>
					<p class="description"><?php esc_html_e( 'When you disable post notifications, their individual configuration is disabled also.', 'friends' ); ?></p>

					<details class="options">
						<summary>
							<?php echo esc_html( _x( 'By Post Format', 'e-mail notifications', 'friends' ) ); ?>
							<?php
							if ( count( $disabled_post_formats ) > 0 ) {
								echo esc_html(
									sprintf(
										// translators: %d: number of selected post formats.
										_n( '(%d disabled)', '(%d disabled)', count( $disabled_post_formats ), 'friends' ),
										count( $disabled_post_formats )
									)
								);
							}
							?>
						</summary>
						<fieldset>
							<?php foreach ( get_post_format_strings() as $post_format => $label ) : ?>
								<label>
									<input name="new_post_format_notification_<?php echo esc_attr( $post_format ); ?>" type="checkbox" id="new_post_format_notification_<?php echo esc_attr( $post_format ); ?>" value="1" <?php checked( ! in_array( $post_format, $disabled_post_formats ) ); ?>>
									<span><?php echo esc_html( $label ); ?></span>
								</label>
							<?php endforeach; ?>
						</fieldset>
					</details>
					<p class="description"><?php esc_html_e( 'You can disable notifications for posts in specific post formats.', 'friends' ); ?></p>

					<details class="options">
						<summary>
							<?php echo esc_html( _x( 'By Feed Parser', 'e-mail notifications', 'friends' ) ); ?>
							<?php
							if ( count( $disabled_feed_parsers ) > 0 ) {
								echo esc_html(
									sprintf(
										// translators: %d: number of selected feed parsers.
										_n( '(%d disabled)', '(%d disabled)', count( $disabled_feed_parsers ), 'friends' ),
										count( $disabled_feed_parsers )
									)
								);
							}
							?>
							</summary>
						<fieldset>
							<?php foreach ( $args['feed_parsers'] as $feed_parser => $label ) : ?>
								<label>
									<input name="new_post_by_parser_notification_<?php echo esc_attr( $feed_parser ); ?>" type="checkbox" id="new_post_by_parser_notification_<?php echo esc_attr( $feed_parser ); ?>" value="1" <?php checked( ! in_array( $feed_parser, $disabled_feed_parsers ) ); ?>>
									<span><?php echo wp_kses( $label, array() ); ?></span>
								</label>
							<?php endforeach; ?>
						</fieldset>
					</details>
					<p class="description"><?php esc_html_e( 'You can disable notifications for posts arriving with specific feed parsers.', 'friends' ); ?></p>
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
						<label>
							<input name="keyword_notification_override" type="checkbox" id="keyword_notification_override" value="1" <?php checked( ! $args['keyword_override_disabled'] ); ?>>
							<span><?php esc_html_e( 'Notify about matching keywords even if disabled above', 'friends' ); ?></span>
						</label>
						<p class="description"><?php esc_html_e( 'For example, even if you disabled post notifications, you can still get notified about posts containing specific keywords.', 'friends' ); ?></p>

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
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>

	<h2><?php esc_html_e( 'Individual Friend Settings', 'friends' ); ?></h2>
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
