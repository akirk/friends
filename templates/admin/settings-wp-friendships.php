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
							++$c;
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
				<th scope="row"><?php esc_html_e( 'Roles', 'friends' ); ?></th>
				<td>
					<select name="default_role">
						<?php
						foreach ( $args['friend_roles'] as $_role => $_title ) :
							?>
							<option value="<?php echo esc_attr( $_role ); ?>" <?php selected( $args['default_role'], $_role ); ?>><?php echo esc_html( $_title ); ?></option>

						<?php endforeach; ?>
					</select>
					<p class="description">
						<span><?php esc_html_e( 'When accepting a friend request, first assign this role.', 'friends' ); ?></span>
						<span><?php esc_html_e( 'An Acquaintance has friend status but cannot read private posts.', 'friends' ); ?></span>
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
