<?php
/**
 * This template contains the Admin Send Friend Request form.
 *
 * @package Friends
 */

$c = -1;
$unsupported_feeds = array();
$friends_host = false;
if ( $friends_plugin ) {
	$friends_host = parse_url( $friends_plugin, PHP_URL_HOST );
}

foreach ( $feeds as $feed_url => $details ) {
	if ( 'unsupported' === $details['parser'] ) {
		$unsupported_feeds[ $feed_url ] = $details;
		unset( $feeds[ $feed_url ] );
	} elseif ( ! isset( $details['post-format'] ) && $friends_host && ! isset( $post_formats['autodetect'] ) && parse_url( $feed_url, PHP_URL_HOST ) === $friends_host ) {
		$feeds[ $feed_url ]['post-format'] = 'autodetect';
		$feeds[ $feed_url ]['autoselect'] = true;
	}
}

?><div class="wrap"><form method="post">
	<?php wp_nonce_field( 'add-friend' ); ?>
	<p>
		<?php
		// translators: %s is a URL.
		echo wp_kses(
			// translators: %1$s is a URL, %2$s is an admin link.
			sprintf( __( 'You\'re looking to add the URL %1$s (<a href=%2$s>edit</a>). The site has been analyzed and you now have the following options:', 'friends' ), '<strong>' . esc_html( $friend_url ) . '</strong>', '"' . admin_url( 'admin.php?page=add-friend&url=' . $friend_url ) . '"' ),
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
							<option value="<?php echo esc_attr( $role ); ?>"<?php selected( $default_role, $role ); ?>><?php echo esc_html( $title ); ?></option>
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
					<input type="text" autofocus id="message" name="message" value="<?php echo esc_attr( $message ); ?>" placeholder="<?php esc_attr_e( 'Enter a message for your friend', 'friends' ); ?>" class="large-text" maxlength="2000" />
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
								<?php
								if ( 'post-format' === $key ) {
									continue;
								}
								?>
								<input type="hidden" name="feeds[<?php echo esc_attr( $c ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" />
							<?php endforeach; ?>
							<label><input type="checkbox" name="subscribe[]" value="<?php echo esc_attr( $feed_url ); ?>" <?php echo ( isset( $details['autoselect'] ) && $details['autoselect'] ) ? ' checked' : ''; ?> />
								<?php
								$select = '<select name="feeds[' . esc_attr( $c ) . '][post-format]">';
								foreach ( $post_formats as $format => $title ) {
									$select .= '<option value="' . esc_attr( $format ) . '"' . selected( $details['post-format'], $format, false ) . '>' . esc_html( $title ) . '</option>';
								}
								$select .= '</select>';

								echo wp_kses(
									sprintf(
									// translators: 1: is a link to a feed with its name as text, 2: url for a preview, 3: a select dropdown with post formats.
										__( 'Subscribe %1$s (<a href=%2$s>preview</a>) as %3$s', 'friends' ),
										'<a href="' . esc_attr( $feed_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $details['title'] ) . '</a>',
										'"' . esc_url( wp_nonce_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=add-friend&parser=' . $details['parser'] . '&preview=' . urlencode( $feed_url ) ) ), 'preview-feed-' . $feed_url ) ) . '" target="_blank"',
										'</label>' . $select
									),
									array(
										'select' => array(
											'name' => array(),
										),
										'label'  => array(),
										'option' => array(
											'value'    => array(),
											'selected' => array(),
										),
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
								echo esc_html( sprintf( __( 'Type: %s', 'friends' ), $details['mime-type'] ) );
								echo ' | ';
								// translators: %s is the name of a parser, e.g. simplepie.
								echo esc_html( sprintf( __( 'Parser: %s', 'friends' ), $details['parser'] ) );
								echo ' | ';
								// translators: %s is relation to the URL, e.g. self or alternate.
								echo esc_html( sprintf( __( 'rel: %s', 'friends' ), $details['rel'] ) );
								?>
							</p>
						</li>
					<?php endforeach; ?>
				</ul>
					<?php if ( ! empty( $unsupported_feeds ) ) : ?>
						<p class="description details">
							<?php
							echo wp_kses(
								// translators: %s is a URL to the plugin install page.
								sprintf( _n( 'The following feed is not supported. <a href=%s>There might be a plugin available</a> to add support for it.', 'The following feeds are not supported. <a href=%s>There might be a plugin available</a> to add support for them.', count( $unsupported_feeds ), 'friends' ), '"' . self_admin_urL( 'plugins.php?s=friends' ) ) . '"',
								array( 'a' => array( 'href' => array() ) )
							);
							?>
						</p>
						<?php endif; ?>

				<ul>
					<?php foreach ( $unsupported_feeds as $feed_url => $details ) : ?>
						<?php $c += 1; ?>
						<li>
							<?php foreach ( $details as $key => $value ) : ?>
								<input type="hidden" name="feeds[<?php echo esc_attr( $c ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" />
							<?php endforeach; ?>
								<?php
								echo wp_kses(
									sprintf(
										// translators: %s is a link to a feed with its name as text.
										__( 'Unsupported: %s', 'friends' ),
										'<a href="' . esc_attr( $feed_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $details['title'] ) . '</a>'
									),
									array(
										'a' => array(
											'href'   => array(),
											'rel'    => array(),
											'target' => array(),
										),
									)
								);
								?>
						</li>
					<?php endforeach; ?>

					</ul>
					<p>
						<a href="" id="add-another-feed"><?php esc_html_e( '+ Add another feed' ); ?></a> | <a href="" id="show-details"><?php esc_html_e( 'Show more feed details' ); ?></a>
					</p>
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
