<?php
/**
 * This template contains the notification manager.
 *
 * @version 1.0
 * @package Friends
 */

if ( $args['no_new_post_notification'] ) : ?>
	<span class="description"><?php esc_html_e( 'You have generally disabled new post notifications for yourself.', 'friends' ); ?> <a href="<?php echo esc_url( $args['friends_settings_url'] ); ?>"><?php esc_html_e( 'Change setting', 'friends' ); ?></a></span>
<?php endif; ?>

<form method="post">
	<?php wp_nonce_field( 'notification-manager' ); ?>
	<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-bottom: 2em; margin-right: 1em">
		<thead>
			<tr>
				<th class="column-primary column-friend"><?php esc_html_e( 'Friend', 'friends' ); ?></th>
				<th class="column-friends-page-feeds"><?php esc_html_e( 'Show on friends page' ); ?></th>
				<th class="column-email-notification"><?php esc_html_e( 'E-Mail Notification' ); ?></th>
				<?php do_action( 'friends_notification_manager_header' ); ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $args['friend_users'] as $friend_user ) : ?>
				<tr>
					<td class="column-friend">
						<a href="<?php echo esc_url( apply_filters( 'get_edit_user_link', $friend_user->user_url, $friend_user->ID ) ); ?>"><?php echo esc_html( $friend_user->display_name ); ?></a>
						<input type="hidden" name="friend_listed[]" value="<?php echo esc_attr( $friend_user->ID ); ?>" />
					</td>
					<td class="column-friends-page-feeds">
						<input name="show_on_friends_page[<?php echo esc_attr( $friend_user->ID ); ?>]" type="checkbox" id="show_on_friends_page" value="1" <?php checked( '1', ! in_array( $friend_user->ID, $args['hide_from_friends_page'] ) ); ?> />
					</td>
					<td class="column-email-notification">
						<input name="new_post_notification[<?php echo esc_attr( $friend_user->ID ); ?>]" type="checkbox" id="friends_new_post_notification" value="1" <?php checked( '1', ! get_user_option( 'friends_no_new_post_notification_' . $friend_user->ID ) ); ?> <?php echo $args['no_new_post_notification'] ? 'disabled="disabled"' : ''; ?>
						/>
					</td>
				<?php do_action( 'friends_notification_manager_row', $friend_user ); ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
