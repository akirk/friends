<?php
/**
 * This template contains the Admin Send Friend Request form.
 *
 * @package Friends
 */

$c = -1;
$hidden_feed_count = 0;
$unsupported_feeds = array();
$friends_host = false;
if ( $args['friends_plugin'] ) {
	$friends_host = wp_parse_url( $args['friends_plugin'], PHP_URL_HOST );
}

foreach ( $args['feeds'] as $feed_url => $details ) {
	if ( ! isset( $details['parser'] ) || 'unsupported' === $details['parser'] ) {
		$unsupported_feeds[ $feed_url ] = $details;
		unset( $args['feeds'][ $feed_url ] );
		continue;
	}
	if ( ! isset( $details['url'] ) ) {
		$args['feeds'][ $feed_url ]['url'] = $feed_url;
	}
	if ( ! isset( $details['autoselect'] ) ) {
		$args['feeds'][ $feed_url ]['autoselect'] = false;
	}
}

?><div class="wrap"><form method="post">
	<?php wp_nonce_field( 'add-friend' ); ?>
	<input type="hidden" name="friend_url" value="<?php echo esc_url( $args['friend_url'] ); ?>" />
	<p>
		<?php
		// translators: %s is a URL.
		echo wp_kses(
			// translators: %1$s is a URL, %2$s is an admin link.
			sprintf( __( 'You\'re looking to add the URL %1$s (<a href=%2$s>edit</a>). The site has been analyzed and you now have the following options:', 'friends' ), '<strong>' . esc_html( $args['friend_url'] ) . '</strong>', '"' . admin_url( 'admin.php?page=add-friend&url=' . $args['friend_url'] ) . '"' ),
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
				<th scope="row"><label for="display_name"><?php esc_html_e( 'Display Name', 'friends' ); ?></label></th>
				<td>
				<?php if ( ! empty( $args['friends_multisite_display_name'] ) ) : ?>
					<?php echo esc_html( $args['friends_multisite_display_name'] ); ?>
					<input type="hidden" id="display_name" name="display_name" value="<?php echo esc_attr( $args['friend_display_name'] ); ?>" />
				<?php else : ?>
					<input type="text" id="display_name" name="display_name" value="<?php echo esc_attr( $args['friend_display_name'] ); ?>" required placeholder="" class="regular-text" />
				<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="user_login"><?php esc_html_e( 'Username', 'friends' ); ?></label></th>
				<td>
					<?php if ( ! empty( $args['friends_multisite_user_login'] ) ) : ?>
						<?php echo esc_html( $args['friends_multisite_user_login'] ); ?>
						<input type="hidden" id="user_login" name="user_login" value="<?php echo esc_attr( $args['friends_multisite_user_login'] ); ?>" />
					<?php else : ?>
						<input type="text" id="user_login" name="user_login" value="<?php echo esc_attr( $args['friend_user_login'] ); ?>" data-original="<?php echo esc_attr( $args['friend_user_login'] ); ?>" required placeholder="" class="regular-text" />
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $args['friends_plugin'] ) : ?>
			<tr>
				<th scope="row"><label for="friendship"><?php esc_html_e( 'Friendship', 'friends' ); ?></label></th>
				<td  title="<?php echo esc_attr( $args['friends_plugin'] ); ?>">
					<label><input type="checkbox" id="friendship" name="friendship" value="<?php echo esc_attr( $args['friends_plugin'] ); ?>" checked /> <?php esc_html_e( 'Send request for friendship', 'friends' ); ?></label> —
					<label><?php esc_html_e( 'Their role will be:', 'friends' ); ?> <select name="role" id="friendship-status">
						<?php
						foreach ( $args['friend_roles'] as $_role => $_title ) :
							?>
							<option value="<?php echo esc_attr( $_role ); ?>"<?php selected( $args['default_role'], $_role ); ?>><?php echo esc_html( $_title ); ?></option>
						<?php endforeach; ?>
					</select></label>
					<p class="description details hidden"><small>
						<?php
						// translators: %s is a URL.
						echo esc_html( sprintf( __( 'API URL: %s', 'friends' ), $args['friends_plugin'] ) );
						?>
						</small>
					</p>
					<p class="description">
						<?php esc_html_e( 'When the other side accepts your friend request, a trusted connection between your sites is established.', 'friends' ); ?>
					</p>
					<p><small><a href="" id="send-friends-advanced"><?php esc_html_e( 'Add optional information »', 'friends' ); ?></a></small></p>
				</td>
			</tr>
			<tr class="friends-advanced hidden">
				<th scope="row"><label for="message"><?php esc_html_e( 'Message (Optional)', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="message" name="message" value="<?php echo esc_attr( $args['message'] ); ?>" placeholder="<?php esc_attr_e( 'Enter a message for your friend', 'friends' ); ?>" class="large-text" maxlength="2000" />
					<p class="description" id="message-description">
						<?php esc_html_e( 'The short message you supply will be sent along with your friend request.', 'friends' ); ?>
					</p>
				</td>
			</tr>
			<tr class="friends-advanced hidden">
				<th scope="row"><label for="codeword"><?php esc_html_e( 'Code word (Optional)', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="codeword" name="codeword" value="<?php echo esc_attr( $args['codeword'] ); ?>" placeholder="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_attr_e( 'None' ); ?>" class="regular-text" />
					<p class="description" id="codeword-description">
						<?php esc_html_e( 'Your friend might have told you to provide something here.', 'friends' ); ?>
					</p>
				</td>
			</tr>
			<?php elseif ( get_option( 'friends_enable_wp_friendships' ) ) : ?>
			<tr>
				<th scope="row"><label for="friendship"><?php esc_html_e( 'Friendship', 'friends' ); ?></label></th>
				<td>
					<p class="description">
						<?php
						// translators: %s is a URL.
						echo wp_kses( sprintf( __( 'No friends plugin could be found on %s, therefore only subscription options are available.', 'friends' ), '<strong>' . esc_html( $args['friend_url'] ) . '</strong>' ), array( 'strong' => array() ) );
						?>
					</p>
				</td>
			</tr>
		<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Subscription', 'friends' ); ?></th>
				<td>
					<?php if ( ! empty( $args['feeds_notice'] ) ) : ?>
						<strong><?php echo wp_kses( $args['feeds_notice'], array( 'a' => array( 'href' => array() ) ) ); ?></strong>
					<?php endif; ?>
					<ul>
					<?php foreach ( $args['feeds'] as $feed_url => $details ) : ?>
						<?php
						++$c;
						$classes = '';
						if ( $c > 0 && ! $details['autoselect'] ) {
							++$hidden_feed_count;
							$classes .= 'rel-alternate hidden';
						}
						?>
						<li class="<?php echo esc_attr( $classes ); ?>">
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
								if ( ! isset( $details['post-format'] ) ) {
									$details['post-format'] = 'standard';
								}
								$select = '<select name="feeds[' . esc_attr( $c ) . '][post-format]">';
								foreach ( $args['post_formats'] as $format => $_title ) {
									$select .= '<option value="' . esc_attr( $format ) . '"' . selected( $details['post-format'], $format, false ) . '>' . esc_html( $_title ) . '</option>';
								}
								$select .= '</select>';

								echo wp_kses(
									sprintf(
									// translators: 1: is a link to a feed with its name as text, 2: url for a preview, 3: a select dropdown with post formats.
										__( 'Subscribe %1$s (<a href=%2$s>preview</a>) as %3$s', 'friends' ),
										'<a href="' . esc_attr( $feed_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $details['title'] ) . '</a>',
										'"' . esc_url(
											wp_nonce_url(
												add_query_arg( '_wp_http_referer', add_query_arg( 'parser', $details['parser'], add_query_arg( 'preview', $feed_url, remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=add-friend' ) ) ) ),
												'preview-feed'
											)
										) . '" target="_blank"',
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
							<p class="description details">
								<?php
								// translators: %s is the type of a feed, for example Atom or RSS.
								echo esc_html( sprintf( __( 'Type: %s', 'friends' ), $details['type'] ) );
								echo ' | ';

								$select = '<select name="feeds[' . esc_attr( $c ) . '][parser]">';
								foreach ( $args['registered_parsers'] as $slug => $parser_name ) {
									$select .= '<option value="' . esc_attr( $slug ) . '"' . selected( $details['parser'], $slug, false ) . '>' . esc_html( wp_strip_all_tags( $parser_name ) ) . '</option>';
								}
								if ( ! isset( $args['registered_parsers'][ $details['parser'] ] ) ) {
									// translators: %s is the name of a deleted parser.
									$select .= '<option value="' . esc_attr( $details['parser'] ) . '" selected="selected">' . esc_html( sprintf( __( '%s (deleted)', 'friends' ), $details['parser'] ) ) . '</option>';
								}
								$select .= '</select>';

								echo wp_kses(
									// translators: %s is the name of a parser, e.g. simplepie.
									sprintf( __( 'Parser: %s', 'friends' ), $select ),
									array(
										'select' => array(
											'name' => array(),
										),
										'label'  => array(),
										'option' => array(
											'value'    => array(),
											'selected' => array(),
										),
									)
								);
								echo ' | ';
								// translators: %s is relation to the URL, e.g. self or alternate.
								echo esc_html( sprintf( __( 'rel: %s', 'friends' ), $details['rel'] ) );
								if ( isset( $details['additional-info'] ) ) {
									echo '<br>' . wp_kses(
										$details['additional-info'],
										array(
											'b'  => true,
											'tt' => true,
											'a'  => array( 'href' => array() ),
										)
									);
								}
								?>
							</p>
						</li>
					<?php endforeach; ?>
				</ul>
					<p>
						<a href="" id="show-alternate-feeds">
							<?php
							// translators: %d is the number of feeds.
							echo esc_html( sprintf( _n( '%d more feed is available', '%d more feeds are available', $hidden_feed_count, 'friends' ), $hidden_feed_count ) );
							?>
						</a>
						| <a href="" id="show-details"><?php esc_html_e( 'Display feed metadata', 'friends' ); ?></a>
					</p>
					<?php if ( ! empty( $unsupported_feeds ) ) : ?>
						<p>
							<small>
								<a href="" id="show-unsupported-feeds">
									<?php
									// translators: %d is the number of feeds.
									echo esc_html( sprintf( _n( 'Show %d unsupported feed', 'Show %d unsupported feeds', count( $unsupported_feeds ), 'friends' ), count( $unsupported_feeds ) ) );
									?>
								</a>
							</small>
						</p>
						<div id="unsupported-feeds" class="hidden">
							<p class="description details">
								<?php
								echo wp_kses(
									// translators: %s is a URL to the plugin install page.
									sprintf( _n( '<a href=%s>There might be a plugin available</a> to add support for it.', '<a href=%s>There might be a plugin available</a> to add support for them.', count( $unsupported_feeds ), 'friends' ), '"' . self_admin_url( 'admin.php?page=friends-plugins' ) . '"' ),
									array( 'a' => array( 'href' => array() ) )
								);
								?>
							</p>
							<ul>
								<?php foreach ( $unsupported_feeds as $feed_url => $details ) : ?>
									<?php ++$c; ?>
									<li>
										<?php foreach ( $details as $key => $value ) : ?>
											<input type="hidden" name="feeds[<?php echo esc_attr( $c ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" />
										<?php endforeach; ?>
											<?php
											echo wp_kses(
												sprintf(
													// translators: %s is a link to a feed with its name as text.
													__( 'Unsupported: %s', 'friends' ),
													'<a href="' . esc_attr( $feed_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $details['title'] ) . '</a> <small>' . esc_html( $feed_url ) . '</small>'
												),
												array(
													'a' => array(
														'href'   => array(),
														'rel'    => array(),
														'target' => array(),
													),
													'small' => array(),
												)
											);
											?>
										<p class="description details hidden">
											<?php
											// translators: %s is the type of a feed, for example Atom or RSS.
											echo esc_html( sprintf( __( 'Type: %s', 'friends' ), $details['type'] ) );
											echo ' | ';
											// translators: %s is relation to the URL, e.g. self or alternate.
											echo esc_html( sprintf( __( 'rel: %s', 'friends' ), $details['rel'] ) );
											?>
										</p>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="another-feed hidden">
				<th scope="row"></th>
				<td>
					<input type="url" name="add_feed[]" value="" placeholder="Add another feed URL" />
					<input type="submit" class="button" name="step2" value="<?php echo esc_attr_x( 'Check & add feed', 'button', 'friends' ); ?>" />
					<p class="description">
						<?php
						echo wp_kses(
							// translators: %s is a list of parser names.
							sprintf( __( 'The following parsers are available: %s', 'friends' ), implode( ', ', $args['registered_parsers'] ) ),
							array(
								'a' => array(
									'href'   => array(),
									'rel'    => array(),
									'target' => array(),
								),
							)
						);
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
