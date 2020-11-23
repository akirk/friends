<?php
/**
 * This template contains the Admin Send Friend Request form.
 *
 * @package Friends
 */

$c = -1;
?><div class="wrap"><form method="post">
	<?php wp_nonce_field( 'add-friend' ); ?>
	<p>
		<?php
		// translators: %s is a URL.
		echo wp_kses( sprintf( __( 'The provided URL %s was analyzed.', 'friends' ), '<strong>' . esc_html( $friend_url ) . '</strong>' ), array( 'strong' => array() ) );
		echo ' ';
		if ( count( $feeds ) > 1 ) {
			esc_html_e( "Select one or more feeds you'd like to follow:", 'friends' );
		} else {
			esc_html_e( "You'll be subscribed to this feed:", 'friends' );
		}
		?>
	</p>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="user_login"><?php esc_html_e( 'Username', 'friends' ); ?></label></th>
				<td>
					<input type="text" id="user_login" name="user_login" value="<?php echo esc_attr( $friend_user_login ); ?>" required placeholder="" class="regular-text" />
					<p class="description"><small>
						<?php
						// translators: %s is a URL.
						echo esc_html( sprintf( __( 'API URL: %s', 'friends' ), $friends_plugin ) );
						?>
						</small>
					</p>
				</td>
			</tr>
			<?php if ( $friends_plugin ) : ?>
			<tr>
				<th scope="row"><label for="friendship"><?php esc_html_e( 'Friendship', 'friends' ); ?></label></th>
				<td  title="<?php echo esc_attr( $friends_plugin ); ?>">
					<input type="checkbox" id="friendship" name="friendship" value="<?php echo esc_attr( $friends_plugin ); ?>" checked /> <?php esc_html_e( 'Send request for friendship.', 'friends' ); ?>
					<?php esc_html_e( 'Their role will be:' ); ?> <select name="role" id="friendship-status">
						<?php
						foreach ( $friend_roles as $role => $title ) :
							?>
							<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $default_role, $role ); ?>><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'When the other side accepts your friend request, a trusted connection between your sites is established.' ); ?>
					</p>
					<p><small><a href="" id="send-friends-advanced"><?php esc_html_e( 'More Options »', 'friends' ); ?></a></small></p>
				</td>
			</tr>
			<tr class="friends-advanced hidden">
				<th scope="row"><label for="message"><?php esc_html_e( 'Message (Optional)', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="message" name="message" value="<?php echo esc_attr( $message ); ?>" placeholder="<?php esc_attr_e( 'Optionally enter a message for your friend', 'friends' ); ?>" class="large-text" />
					<p class="description" id="message-description">
						<?php esc_html_e( 'The short message you supply will be sent along with your friend request.', 'friends' ); ?>
					</p>
				</td>
			</tr>
			<tr class="friends-advanced hidden">
				<th scope="row"><label for="codeword"><?php esc_html_e( 'Code word (Optional)', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="codeword" name="codeword" value="<?php echo esc_attr( $codeword ); ?>" placeholder="None" class="regular-text" />
					<p class="description" id="codeword-description">
						<?php esc_html_e( 'Your friend might have told you to provide something here.', 'friends' ); ?>
					</p>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<th scope="row"><label for="friendship"><?php esc_html_e( 'Friendship', 'friends' ); ?></label></th>
				<td>
					<p class="description">
						<?php
						// translators: %s is a URL.
						echo wp_kses( sprintf( __( 'No friends plugin could be found on %s, therefore only subscription options are available.', 'friends' ), '<strong>' . esc_html( $friend_url ) . '</strong>' ), array( 'strong' => array() ) );
						?>
					</p>
				</td>
			</tr>
		<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Subscription', 'friends' ); ?></th>
				<td>
					<ul>
					<?php foreach ( $feeds as $feed_url => $details ) : ?>
						<?php $c += 1; ?>
						<li>
							<?php foreach ( $details as $key => $value ) : ?>
								<input type="hidden" name="feeds[<?php echo esc_attr( $c ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" />
							<?php endforeach; ?>
							<input type="checkbox" name="subscribe[]" value="<?php echo esc_attr( $feed_url ); ?>" <?php echo ( isset( $details['autoselect'] ) && $details['autoselect'] ) ? ' checked' : ''; ?> />
							Subscribe
							<a href="<?php echo esc_attr( $feed_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $details['title'] ); ?></a>
							<p class="description">
								<?php
								// translators: %s is the type of a feed, for example Atom or RSS.
								echo esc_html( sprintf( __( 'Type: %s', 'friends' ), $details['type'] ) );
								echo ' ';
								// translators: %s is the name of a parser, e.g. simplepie.
								echo esc_html( sprintf( __( 'Parser: %s', 'friends' ), $details['parser'] ) );
								?>
							</p>
						</li>
					<?php endforeach; ?>
						<li>
							<a href="" id="add-another-feed"><?php esc_html_e( '+ Add another feed' ); ?></a>
						</li>
					</ul>
				</td>
			</tr>
			<tr class="another-feed hidden">
				<th scope="row"></th>
				<td>
					<input type="url" name="add_feed[]" value="" placeholder="Add another feed URL" />
					<input type="submit" class="button" name="step2" value="<?php echo esc_attr_x( 'Check & add feed', 'button', 'friends' ); ?>" />
					<p class="description">
						<?php
						// translators: %s is a list of parser names.
						echo esc_html( sprintf( __( 'The following parsers are available: %s', 'friends' ), implode( ', ', $registered_parsers ) ) );
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<input type="submit" class="button button-primary" name="step3" value="<?php echo esc_attr_x( 'Add Friend »', 'button', 'friends' ); ?>" />
				</td>
			</tr>
		</tbody>
	</table>

</form>
