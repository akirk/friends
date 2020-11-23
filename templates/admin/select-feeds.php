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
				<th scope="row"><label for="username"><?php esc_html_e( 'Username', 'friends' ); ?></label></th>
				<td>
					<input type="text" id="username" name="username" value="<?php echo esc_attr( $friend_username ); ?>" required placeholder="" class="regular-text" />
					<p class="description" id="username-description">
					</p>
				</td>
			</tr>
			<?php if ( $friends_plugin ) : ?>
			<tr>
				<th scope="row"><label for="friendship"><?php esc_html_e( 'Friendship', 'friends' ); ?></label></th>
				<td>
					<input type="checkbox" id="friendship" name="friendship" value="request" checked /> <?php esc_html_e( 'Send request for friendship.', 'friends' ); ?>
					<?php _e( 'Their role will be:' ); ?> <select name="role" id="friendship-status">
						<?php
						foreach ( $friend_roles as $role => $title ) :
							?>
							<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $default_role, $role ); ?>><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'When the other side accepts your friend request, a trusted connection between your sites is establised.' ); ?>
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
						echo wp_kses( sprintf( __( 'No friends plugin could be found on %s.', 'friends' ), '<strong>' . esc_html( $friend_url ) . '</strong>' ), array( 'strong' => array() ) );
						?>
					</p>
				</td>
			</tr>
		<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Feeds', 'friends' ); ?></th>
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
							<a href="">+ Add another feed</a>
							<input type="url" name="add_feed[]" value="" placeholder="Add another feed URL" />
							<p class="description">
								<?php
								// translators: %s is a list of parser names.
								echo esc_html( sprintf( __( 'The following parsers are available: %s', 'friends' ), implode( ', ', $registered_parsers ) ) );
								?>
						</li>
					</ul>
				</td>
			</tr>
		</tbody>
	</table>

</form>
