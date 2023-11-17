<?php
/**
 * This template contains the Friends Settings.
 *
 * @package Friends
 */

$comment_registration_class = '';
if ( ! $args['comment_registration'] ) {
	$comment_registration_class = 'hidden';
}

$codeword_class = '';
if ( 'friends' === $args['codeword'] || ! $args['require_codeword'] ) {
	$codeword_class = 'hidden';
}

do_action( 'friends_settings_before_form' );

?>
<form method="post">
	<?php wp_nonce_field( 'friends-settings' ); ?>
	<table class="form-table">
		<tbody>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<tr>
				<th scope="row"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Comments' ); ?></th>
				<td>
					<fieldset>
						<label for="comment_registration">
							<input name="comment_registration" type="checkbox" id="comment_registration" value="1" <?php checked( '1', $args['comment_registration'] ); ?> />
							<span><?php esc_html_e( 'Only people in your network can comment.', 'friends' ); ?></span>
						</label>
					</fieldset>
					<div id="comment_registration_options" class="<?php echo esc_attr( $comment_registration_class ); ?>">
						<fieldset>
							<label for="comment_registration_message">
								<p><?php esc_html_e( 'Display this message for logged-out users:', 'friends' ); ?></p>
							</label>
							<p>
								<textarea name="comment_registration_message" id="comment_registration_message" class="regular-text" rows="3" cols="80" placeholder="<?php echo esc_attr( $args['comment_registration_default'] ); ?>"><?php echo esc_html( $args['comment_registration_message'] ); ?></textarea>
							</p>
							<p class="description">
								<?php
								echo wp_kses(
									sprintf(
										// translators: %1$s is the translation for "my network", %2$s is the URL for your public profile page.
										__( 'We\'ll link "%1$s" to <a href=%2$s>your public profile page</a>.', 'friends' ),
										$args['my_network'],
										$args['public_profile_link']
									),
									array(
										'a' => array(
											'href' => array(),
										),
									)
								);
								?>
							</p>
						</fieldset>
					</div>
				</td>
			</tr>
					<?php
			endif;
			if ( $args['potential_main_users']->get_total() > 1 ) :
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Main Friend User', 'friends' ); ?></th>
					<td>
					<?php if ( current_user_can( 'manage_options' ) ) { ?>
						<select name="main_user_id">
							<?php foreach ( $args['potential_main_users']->get_results() as $potential_main_user ) : ?>
								<option value="<?php echo esc_attr( $potential_main_user->ID ); ?>" <?php selected( $args['main_user_id'], $potential_main_user->ID ); ?>><?php echo esc_html( $potential_main_user->display_name ); ?></option>

							<?php endforeach; ?>
						</select>
						<p class="description"><span><?php esc_html_e( 'Since there are multiple users on this site, we need to know which one should be considered the main one.', 'friends' ); ?></span> <span><?php esc_html_e( 'They can edit friends-related settings.', 'friends' ); ?></span> <span><?php esc_html_e( 'Whenever a friends-related action needs to be associated with a user, this one will be chosen.', 'friends' ); ?></span></p>
							<?php
					} else {
						$c = 0;
						foreach ( $args['potential_main_users']->get_results() as $potential_main_user ) {
							$c += 1;
							if ( $potential_main_user->ID === $args['main_user_id'] ) {
								?>
									<span id="main_user_id"><?php echo esc_html( $potential_main_user->display_name ); ?></span>
									<?php
							}
						}
						?>
							<span> (
						<?php
						echo esc_html(
							sprintf(
							// translators: %s is a number of users.
								_n( '%s potential user', '%s potential users', $c, 'friends' ),
								$c
							)
						);
						?>
							) </span>
							<p class="description"><?php esc_html_e( 'An administrator can change this.', 'friends' ); ?></p>
							<?php
					}
					?>
					</td>
				</tr>
					<?php
			endif;
			?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Friend Requests', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="require_codeword">
							<input name="require_codeword" type="checkbox" id="require_codeword" value="1" <?php checked( '', $codeword_class ); ?>>
							<span><?php esc_html_e( 'Require a code word to send you friend request', 'friends' ); ?></span>
						</label>
					</fieldset>
					<div id="codeword_options" class="<?php echo esc_attr( $codeword_class ); ?>">
						<fieldset>
							<label for="codeword">
								<span><?php esc_html_e( 'This code word must be provided to send you a friend request:', 'friends' ); ?></span>
								<input name="codeword" type="text" id="codeword" placeholder="friends" value="<?php echo esc_attr( $args['codeword'] ); ?>" />
							</label>
							<p class="description">
								<?php esc_html_e( "You'll need to communicate the code word to potential friends through another medium.", 'friends' ); ?>
							</p>
						</fieldset>
						<fieldset>
							<label for="wrong_codeword_message">
								<p><?php esc_html_e( 'Error message for a wrong code word:', 'friends' ); ?></p>
							</label>
							<p>
								<textarea name="wrong_codeword_message" id="wrong_codeword_message" class="regular-text" rows="3" cols="80" placeholder="<?php echo esc_attr( __( 'Return this message to the friend requestor if a wrong code word was provided.', 'friends' ) ); ?>"><?php echo esc_html( $args['wrong_codeword_message'] ); ?></textarea>
							</p>
						</fieldset>
					</div>
				</td>
			</tr>
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
					<p class="description"><?php esc_html_e( 'You can also change this setting for each friend separately.', 'friends' ); ?></p>
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
			<tr>
				<th scope="row"><?php esc_html_e( 'Roles', 'friends' ); ?></th>
				<td>
					<select name="default_role">
						<?php
						foreach ( $args['friend_roles'] as $role => $title ) :
							?>
							<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $args['default_role'], $role ); ?>><?php echo esc_html( $title ); ?></option>

						<?php endforeach; ?>
					</select>
					<p class="description">
						<span><?php esc_html_e( 'When accepting a friend request, first assign this role.', 'friends' ); ?></span>
						<span><?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?></span>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Retention', 'friends' ); ?></th>
				<td>
					<fieldset>
						<div>
							<input type="checkbox" name="friends_enable_retention_days" id="friends_enable_retention_days" value="1" <?php checked( '1', $args['retention_days_enabled'] ); ?> />
							<span id="friends_enable_retention_days_line" class="<?php echo esc_attr( $args['retention_days_enabled'] ? '' : 'disabled' ); ?>">
							<?php
							echo '<span>';
							echo wp_kses(
								sprintf(
									// translators: %s is an input field that allows specifying a number.
									__( 'Only keep posts for %s days.', 'friends' ),
									'<input type="number" min="1" id="friends_retention_days" name="friends_retention_days" value="' . esc_attr( $args['retention_days'] ) . '"' . ( $args['retention_days_enabled'] ? '' : ' disabled="disabled"' ) . ' size="3">'
								),
								array(
									'input' => array(
										'type'     => array(),
										'min'      => array(),
										'id'       => array(),
										'name'     => array(),
										'value'    => array(),
										'size'     => array(),
										'disabled' => array(),
									),
								)
							);
							echo '</span> <span>';
							echo esc_html(
								sprintf(
								// translators: %s is a date.
									__( 'Earliest post: %s', 'friends' ),
									/* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ date_i18n( __( 'F j, Y' ), $args['earliest_post_date'] )
								)
							);
							echo '</span>';
							?>
							</span>
						</div>
						<div>
							<input type="checkbox" name="friends_enable_retention_number" id="friends_enable_retention_number" value="1" <?php checked( '1', $args['retention_number_enabled'] ); ?> />
							<span id="friends_enable_retention_number_line" class="<?php echo esc_attr( $args['retention_number_enabled'] ? '' : 'disabled' ); ?>">
							<?php
							echo '<span>';
							echo wp_kses(
								sprintf(
									// translators: %s is an input field that allows specifying a number.
									__( 'Only keep the last %s posts.', 'friends' ),
									'<input type="number" min="1" id="friends_retention_number" name="friends_retention_number" value="' . esc_attr( $args['retention_number'] ) . '"' . ( $args['retention_number_enabled'] ? '' : ' disabled="disabled"' ) . ' size="3">'
								),
								array(
									'input' => array(
										'type'     => array(),
										'min'      => array(),
										'id'       => array(),
										'name'     => array(),
										'value'    => array(),
										'size'     => array(),
										'disabled' => array(),
									),
								)
							);
							echo '</span> <span>';
							echo esc_html(
								sprintf(
								// translators: %s is a date.
									__( 'Current number of posts: %s', 'friends' ),
									number_format_i18n( $args['post_count'] )
								)
							);
							echo '</span>';
							?>
							</span>
						</div>
					</fieldset>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								// translators: %s is a size in bytes or kilo bytes (kB).
								__( 'Currently the posts use %s of disk space.', 'friends' ),
								size_format( $args['total_size'], 1 )
							)
						);
						?>
					</p>
					<p class="description">
						<span><?php esc_html_e( 'If you need to limit the amount of space, choose one of the options above (they can be combined).', 'friends' ); ?></span>
						<span>
						<?php
						esc_html_e( 'The next auto-delete will kick in when refreshing the feeds.', 'friends' );
						?>
						</span>
					</p>
					<p class="description">
						<?php
						esc_html_e( 'You can also specify this for individual friends.', 'friends' );
						?>
					</p>
				</td>
			</tr>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Post Formats', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="limit_homepage_post_format">
								<?php
								$select = '<select name="limit_homepage_post_format" id="limit_homepage_post_format">';
								$select .= '<option value="0"' . selected( $args['limit_homepage_post_format'], false, false ) . '>' . esc_html( _x( 'All', 'All post-formats', 'friends' ) ) . '</option>';
								foreach ( $args['post_format_strings'] as $format => $title ) {
									// translators: %s is a post format title.
									$select .= '<option value="' . esc_attr( $format ) . '"' . selected( $args['limit_homepage_post_format'], $format, false ) . '>' . esc_html( sprintf( _x( '%s only', 'post-format only', 'friends' ), $title ) ) . '</option>';
								}
								$select .= '</select>';

								echo wp_kses(
									sprintf(
										// translators: %s is a Select dropdown of post formats, e.g. "All" or "Standard only" (see "post-format only").
										__( 'On your homepage, show %s posts.', 'friends' ),
										$select
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
						</label>
						<br/>

						<label for="force_enable_post_formats">
							<input name="force_enable_post_formats" type="checkbox" id="force_enable_post_formats" value="1" <?php checked( '1', $args['force_enable_post_formats'] ); ?>>
							<span><?php esc_html_e( 'Always enable Post Formats, regardless of the theme support.', 'friends' ); ?></span>
							<p class="description">
								<?php
								echo wp_kses(
									__( 'With <a href="https://wordpress.org/support/article/post-formats/#supported-formats">Post Formats</a> you can categorize your content in a more detailed way. Examples for post formats are "photo" or "link."', 'friends' ),
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
						</label><br/>

						<label for="expose_post_format_feeds">
							<?php if ( current_theme_supports( 'post-format-feeds' ) ) : ?>
								<span><?php esc_html_e( 'Your theme already supports exposing Post Formats as alternate feeds on your homepage.', 'friends' ); ?></span>
							<?php else : ?>
							<input name="expose_post_format_feeds" type="checkbox" id="expose_post_format_feeds" value="1" <?php checked( '1', $args['expose_post_format_feeds'] ); ?>>
								<span>
								<?php
								// translators: %s is a HTML snippet.
								echo wp_kses( sprintf( __( 'Expose Post Formats as alternate feeds on your homepage (as %s).', 'friends' ), '<code>&lt;link rel="alternate"/ &gt;</code>' ), array( 'code' => array() ) );
								?>
								</span>
						<?php endif; ?>
					</fieldset>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Reactions', 'friends' ); ?></th>
				<td>
					<fieldset>
						<span><?php esc_html_e( 'Allow these emojis for reactions:', 'friends' ); ?></span>

						<ol id="available-emojis">
						<?php
						foreach ( Friends\Reactions::get_available_emojis() as $id => $data ) {
							Friends\Friends::template_loader()->get_template_part(
								'admin/add-reaction-li',
								null,
								array(
									'id'    => $id,
									'emoji' => $data->char,
								)
							);
						}
						?>
						</ol>
						<a href="" id="admin-add-emoji"><?php esc_html_e( 'Add an emoji', 'friends' ); ?></a>
						<?php Friends\Friends::template_loader()->get_template_part( 'admin/reactions-picker' ); ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row" rowspan="2"><?php esc_html_e( 'Frontend', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="frontend-default-view">
							<span><?php esc_html_e( 'Default view:', 'friends' ); ?></span>
							<select name="frontend_default_view" id="frontend-default-view">
								<option value="expanded"<?php selected( $args['frontend_default_view'], 'expanded' ); ?>><?php esc_html_e( 'Expanded', 'friends' ); ?></option>
								<option value="collapsed"<?php selected( $args['frontend_default_view'], 'collapsed' ); ?>><?php esc_html_e( 'Collapsed', 'friends' ); ?></option>
							</select>
						</label>
					</fieldset>
				</td>
			</tr>
			<?php if ( ! function_exists( 'classicpress_version' ) ) : ?>
			<tr>
				<td>
					<fieldset>
						<label for="blocks_everywhere">
							<input name="blocks_everywhere" type="checkbox" id="blocks_everywhere" value="1" <?php checked( '1', $args['blocks_everywhere'] ); ?> disabled="disabled" />
							<span><?php esc_html_e( 'Unfortunately, Gutenberg on the frontend is currently unavailable.', 'friends' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row" rowspan="2"><?php esc_html_e( 'Feed Reader', 'friends' ); ?></th>
				<td>
					<span>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'Download the <a href=%s>Private OPML file (contains private urls!)</a> and import it to your feed reader.', 'friends' ), esc_url( home_url( '/friends/opml/?auth=' . $args['private_rss_key'] ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
					</span>
					<span>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'Alternative: <a href=%s>Public OPML file (only public urls)</a>.', 'friends' ), esc_url( home_url( '/friends/opml/?public' ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
					</span>
					<p class="description">
					<?php
					echo __( 'If your feed reader supports it, you can also subscribe to this URL as the OPML file gets updated as you add or remove friends.', 'friends' );
					?>
					</p>
				</td>
			</tr>
			<tr>
				<td>
					<span>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'You can also subscribe to a <a href=%s>compiled RSS feed of friend posts</a>.', 'friends' ), esc_url( home_url( '/friends/feed/?auth=' . $args['private_rss_key'] ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
					</span>
					<p class="description">
					<?php
					echo __( 'Please be careful what you do with these feeds as they might contain private posts of your friends.', 'friends' );
					?>
					</p>

				</td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'friends_settings_form_bottom' ); ?>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
	<?php if ( ! current_user_can( 'manage_options' ) ) : ?>
		<p class="description">
			<?php esc_html_e( 'Administrators have access to these additional settings:', 'friends' ); ?>
			<ul class="ul-disc">
				<?php
				foreach ( array(
					__( 'Comments' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					__( 'Post Formats', 'friends' ),
				) as $setting ) :
					?>
					<li><?php echo esc_html( $setting ); ?></li>
				<?php endforeach; ?>
			</ul>
		</p>
	<?php endif; ?>
</form>
<?php
do_action( 'friends_settings_after_form' );
