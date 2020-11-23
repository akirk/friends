<?php
/**
 * This template contains the Admin Send Friend Request form.
 *
 * @package Friends
 */

$quick_subscribe = _x( 'Quick Subscribe', 'button', 'friends' );

?><div class="wrap"><form method="post">
	<?php wp_nonce_field( 'add-friend' ); ?>
	<p>
		<?php esc_html_e( 'To follow a friend or to subscribe to any other feed, enter their URL here.', 'friends' ); ?>
		<?php esc_html_e( 'You can either enter a feed URL directly or the main URL which will contain pointers to the available feeds.', 'friends' ); ?>
		<?php
		// translators: %s is a URL.
		echo wp_kses( sprintf( __( 'For quick adding or following you can also <a href=%s>use a bookmarklet</a>.', 'friends' ), esc_url( self_admin_url( 'tools.php' ) ) ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</p>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="friend_url"><?php esc_html_e( 'Site', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="friend_url" name="friend_url" value="<?php echo esc_attr( $friend_url ); ?>" required placeholder="<?php _e( 'Enter URL', 'friends' ); ?>" class="regular-text" />
					<p class="description" id="friend_url-description">
						<?php esc_html_e( "In the next step we'll give you a selection of available feeds.", 'friends' ); ?>
					</p>
					<p><a href="" id="send-friends-advanced"><?php _e( 'More Options »', 'friends' ); ?></a></p>
				</td>
			</tr>
			<tr class="hidden">
				<th scope="row"><label for="message"><?php esc_html_e( 'Message (Optional)', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="message" name="message" value="<?php echo esc_attr( $message ); ?>" placeholder="<?php _e( 'Optionally enter a message for your friend', 'friends' ); ?>" class="large-text" />
					<p class="description" id="message-description">
						<?php esc_html_e( 'The short message you supply will be sent along with your friend request.', 'friends' ); ?>
					</p>
				</td>
			</tr>
			<tr class="hidden">
				<th scope="row"><label for="codeword"><?php esc_html_e( 'Code word (Optional)', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="codeword" name="codeword" value="<?php echo esc_attr( $codeword ); ?>" placeholder="None" class="regular-text" />
					<p class="description" id="codeword-description">
						<?php esc_html_e( 'Your friend might have told you to provide something here.', 'friends' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<input type="submit" class="button button-primary" value="<?php echo esc_attr_x( 'Next »', 'button', 'friends' ); ?>" />

					<input type="submit" name="quick-subscribe" class="button" value="<?php echo esc_attr( $quick_subscribe ); ?>" />

					<p class="description" id="quick-subscribe-description">
						<?php
						// translators: %s is the text for the Quick Subscribe button.
						echo wp_kses( sprintf( __( '<em>%s</em> will skip the next step and just subscribe you.', 'friends' ), $quick_subscribe ), array( 'em' => array() ) );
						?>
					</p>

				</td>
			</tr>
		</tbody>
	</table>

</form>
