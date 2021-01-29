<?php
/**
 * This template contains the Friends Settings.
 *
 * @package Friends
 */

$codeword_class = '';
if ( 'friends' === $args['codeword'] || ! $args['friends_require_codeword'] ) {
	$codeword_class = 'hidden';
}
do_action( 'friends_settings_before_form' );

?><form method="post">
	<?php wp_nonce_field( 'friends-settings' ); ?>
	<table class="form-table">
		<tbody>
			<?php
			if ( $args['potential_main_users']->get_total() > 1 ) :
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Main Friend User', 'friends' ); ?></th>
					<td>
						<select name="main_user_id">
							<?php foreach ( $args['potential_main_users']->get_results() as $potential_main_user ) : ?>
								<option value="<?php echo esc_attr( $potential_main_user->ID ); ?>" <?php selected( $args['main_user_id'], $potential_main_user->ID ); ?>><?php echo esc_html( $potential_main_user->display_name ); ?></option>

							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Since there are multiple users on this site, we need to know which one should be considered the main one.', 'friends' ); ?></p>
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
							<?php esc_html_e( 'Require a code word to send you friend request', 'friends' ); ?>
						</label>
					</fieldset>
					<div id="codeword_options" class="<?php echo $codeword_class; ?>">
						<fieldset>
							<label for="codeword">
								<?php _e( 'This code word must be provided to send you a friend request:', 'friends' ); ?> <input name="codeword" type="text" id="codeword" placeholder="friends" value="<?php echo esc_attr( $args['codeword'] ); ?>" />
							</label>
							<p class="description">
								<?php _e( "You'll need to communicate the code word to potential friends through another medium.", 'friends' ); ?>
							</p>
						</fieldset>
						<fieldset>
							<label for="wrong_codeword_message">
								<p><?php _e( 'Error message for a wrong code word:', 'friends' ); ?></p>
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
							<input name="friend_request_notification" type="checkbox" id="friend_request_notification" value="1" <?php checked( '1', ! $args['friends_no_friend_request_notification'] ); ?>>
							<?php esc_html_e( 'Friend Requests', 'friends' ); ?>
						</label>
						<br />
						<label for="new_post_notification">
							<input name="new_post_notification" type="checkbox" id="new_post_notification" value="1" <?php checked( '1', ! $args['no_new_post_notification'] ); ?>>
							<?php esc_html_e( 'New Posts', 'friends' ); ?>
						</label>
					</fieldset>
				<p class="description"><?php esc_html_e( 'You can also change this setting for each friend separately.', 'friends' ); ?></p>
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
					<?php esc_html_e( 'When accepting a friend request, first assign this role.', 'friends' ); ?>
					<?php
					esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' );
					?>
					</p>
				</td>
			</tr>
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
							<input name="force_enable_post_formats" type="checkbox" id="force_enable_post_formats" value="1" <?php checked( '1', $args['friends_force_enable_post_formats'] ); ?>>
							<?php esc_html_e( 'Always enable Post Formats, regardless of the theme support.', 'friends' ); ?>
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
								<?php esc_html_e( 'Your theme already supports exposing Post Formats as alternate feeds on your homepage.' ); ?>
							<?php else : ?>
							<input name="expose_post_format_feeds" type="checkbox" id="expose_post_format_feeds" value="1" <?php checked( '1', $args['expose_post_format_feeds'] ); ?>>
								<?php
								// translators: %s is a HTML snippet.
								echo wp_kses( sprintf( __( 'Expose Post Formats as alternate feeds on your homepage (as %s).', 'friends' ), '<code>&lt;link rel="alternate"/ &gt;</code>' ), array( 'code' => array() ) );
								?>
						<?php endif; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row" rowspan="2"><?php esc_html_e( 'Feed Reader', 'friends' ); ?></th>
				<td>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'Download <a href=%s>this OPML file</a> and import it to your feed reader.', 'friends' ), esc_url( site_url( '/friends/opml/?auth=' . $args['private_rss_key'] ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
					<p class="description">
					<?php
					echo __( 'If your feed reader supports it, you can also subscribe to this URL as the OPML file gets updated as you add or remove friends.', 'friends' );
					?>
					</p>
				</td>
			</tr>
			<tr>
				<td>
					<?php
					// translators: %s is a URL.
					echo wp_kses( sprintf( __( 'You can also subscribe to a <a href=%s>compiled RSS feed of friend posts</a>.', 'friends' ), esc_url( site_url( '/friends/feed/?auth=' . $args['private_rss_key'] ) ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
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
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
<?php
do_action( 'friends_settings_after_form' );
