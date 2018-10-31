<?php
/**
 * This template contains the Admin Send Friend Request form.
 *
 * @package Friends
 */

?><div class="wrap"><form method="post">
	<?php wp_nonce_field( 'send-friend-request' ); ?>
	<p>
		<?php esc_html_e( "This will set up a connection between your site and your friend's site.", 'friends' ); ?>

	</p>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="friend_url"><?php esc_html_e( 'Site', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="friend_url" name="friend_url" value="<?php echo esc_attr( $friend_url ); ?>" required placeholder="<?php _e( "Enter your friend's site URL", 'friends' ); ?>" class="regular-text" />
					<p class="description" id="friend_url-description">
						<?php esc_html_e( "If the site is not compatible with the Friends plugin, you'll subscribe the site's RSS feed.", 'friends' ); ?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<input type="submit" name="request-friendship" class="button button-primary" value="<?php echo esc_attr_x( 'Send Friend Request', 'button', 'friends' ); ?>" />

	<input type="submit" name="just-subscribe" class="button" value="<?php echo esc_attr_x( 'Just subscribe', 'button', 'friends' ); ?>" />
</form>

<?php if ( ! empty( $friend_requests ) ) : ?>
	<table class="wp-list-table widefat fixed striped" style="margin-top: 2em; margin-right: 1em">
		<thead>
			<tr>
				<th style="width: 20em" class="column-primary column-site"><?php _e( 'Site', 'friends' ); ?></th>
				<th style="width: 10em" class="column-date"><?php _e( 'Date' ); ?></th>
				<th class="column-status"><?php _e( 'Status', 'friends' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $friend_requests as $friend_user ) : ?>
			<tr>
				<td class="site column-site column-primary" data-colname="<?php esc_attr_e( 'Site', 'friends' ); ?>">
					<a href="<?php echo esc_url( apply_filters( 'get_edit_user_link', $friend_user->user_url, $friend_user->ID ) ); ?>"><?php echo esc_html( $friend_user->display_name ); ?></a>
					<button type="button" class="toggle-row"><span class="screen-reader-text"><?php _e( 'Show more details' ); ?></span></button>
				</td>
				<td class="date column-date" data-colname="<?php esc_attr_e( 'Date' ); ?>"><?php echo date_i18n( __( 'F j, Y g:i a' ), strtotime( $friend_user->user_registered ) ); ?></td>
				<td class="status column-status" data-colname="<?php esc_attr_e( 'Status', 'friends' ); ?>"><?php echo esc_html( $roles[ $friend_user->roles[0] ] ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
<?php endif; ?>
