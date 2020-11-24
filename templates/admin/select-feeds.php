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
		echo wp_kses(
			// translators: %1$s is a URL, %2$s is an admin link.
			sprintf( __( 'You\'re looking to add the URL %1$s (<a href="%2$s">edit</a>). The site has been analyzed and you now have the following options:', 'friends' ), '<strong>' . esc_html( $friend_url ) . '</strong>', admin_url( 'admin.php?page=add-friend' ) ),
			array(
				'strong' => array(),
				'a'      => array( 'href' => array() ),
			)
		);
		?>
	</p>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="user_login"><?php esc_html_e( 'Username', 'friends' ); ?></label></th>
				<td>
					<input type="text" id="user_login" name="user_login" value="<?php echo esc_attr( $friend_user_login ); ?>" required placeholder="" class="regular-text" />
					<p class="description details hidden"><small>
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
					<label><input type="checkbox" id="friendship" name="friendship" value="<?php echo esc_attr( $friends_plugin ); ?>" checked /> <?php esc_html_e( 'Send request for friendship.', 'friends' ); ?></label>
					<label><?php esc_html_e( 'Their role will be:' ); ?> <select name="role" id="friendship-status">
						<?php
						foreach ( $friend_roles as $role => $title ) :
							?>
							<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $default_role, $role ); ?>><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
					</select></label>
					<p class="description">
						<?php esc_html_e( 'When the other side accepts your friend request, a trusted connection between your sites is established.' ); ?>
					</p>
					<p><small><a href="" id="send-friends-advanced"><?php esc_html_e( 'Add optional information »', 'friends' ); ?></a></small></p>
				</td>
			</tr>
			<tr class="friends-advanced hidden">
				<th scope="row"><label for="message"><?php esc_html_e( 'Message (Optional)', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="message" name="message" value="<?php echo esc_attr( $message ); ?>" placeholder="<?php esc_attr_e( 'Optionally enter a message for your friend', 'friends' ); ?>" class="large-text" maxlength="2000" />
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
							<label><input type="checkbox" name="subscribe[]" value="<?php echo esc_attr( $feed_url ); ?>" <?php echo ( isset( $details['autoselect'] ) && $details['autoselect'] ) ? ' checked' : ''; ?> />
								<?php
								$select = '<select name="feeds[' . esc_attr( $c ) . '][post-format]">';
								foreach ( $post_formats as $format => $title ) {
									$select .= '<option value="' . esc_attr( $format ) . '" ' . selected( $details['post-format'], $format, false ) . '>' . esc_html( $title ) . '</option>';
								}
								$select .= '</select>';

								echo wp_kses(
									sprintf(
									// translators: %1$s is a link to a feed with its name as text, %2$s is a select dropdown with post formats.
										esc_html( __( 'Subscribe %1$s as %2$s', 'friends' ) ),
										'<a href="' . esc_attr( $feed_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $details['title'] ) . '</a>',
										$select
									),
									array(
										'select' => array( 'name' => array() ),
										'option' => array( 'value' => array() ),
										'a'      => array(
											'href'   => array(),
											'rel'    => array(),
											'target' => array(),
										),
									)
								);
								?>
							<p class="description details hidden">
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
							<a href="" id="add-another-feed"><?php esc_html_e( '+ Add another feed' ); ?></a> | <a href="" id="show-details"><?php esc_html_e( 'Show more feed details' ); ?></a>
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
