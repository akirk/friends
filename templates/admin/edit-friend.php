<?php
/**
 * This template contains the friend editor.
 *
 * @version 1.0
 * @package Friends
 */

$available_avatars = apply_filters( 'friends_potential_avatars', array(), $args['friend'] );

?><form method="post">
	<?php wp_nonce_field( 'edit-friend-' . $args['friend']->user_login ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="friends_avatar"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Avatar' ); ?></label></th>
				<td>
				<?php echo get_avatar( $args['friend']->user_login ); ?>
			</td>
			</tr>
			<tr>
				<th><label for="friends_set_avatar"><?php esc_html_e( 'Update Avatar', 'friends' ); ?></label></th>
				<td id="friend-avatar-setting">
					<?php
					foreach ( $available_avatars as $avatar => $_title ) {
						?>
						<a href="" data-nonce="<?php echo esc_attr( wp_create_nonce( 'set-avatar-' . $args['friend']->user_login ) ); ?>" data-user="<?php echo esc_attr( $args['friend']->user_login ); ?>" class="set-avatar"><img src="<?php echo esc_url( $avatar ); /* phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage */ ?>" alt="<?php echo esc_attr( $_title ); ?>" title="<?php echo esc_attr( $_title ); ?>" width="32" height="32" /></a>
						<?php
					}
					if ( ! empty( $available_avatars ) ) :
						?>
					<p class="description">
						<?php esc_html_e( 'Click to set as new avatar.', 'friends' ); ?><br/>
					</p>
					<?php endif; ?>
						<input type="url" id="new-avatar-url" placeholder="<?php esc_attr_e( 'Enter an image URL', 'friends' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'set-avatar-' . $args['friend']->user_login ) ); ?>" data-user="<?php echo esc_attr( $args['friend']->user_login ); ?>" value="<?php echo esc_attr( $args['friend']->get_avatar_url() ); ?>"/>
						<button id='set-avatar-url'><?php esc_html_e( 'Use this URL', 'friends' ); ?></button>
						<p class="description">
							<?php esc_html_e( 'Please specify a square, not too large image URL here.', 'friends' ); ?><br/>
						</p>
				</td>
			</tr>

			<?php do_action( 'friends_edit_friend_after_avatar', $args['friend'] ); ?>
			<tr>
				<th><label for="friends_display_name"><?php esc_html_e( 'Display Name', 'friends' ); ?></label></th>
				<td><input type="text" name="friends_display_name" id="friends_display_name" value="<?php echo esc_attr( $args['friend']->display_name ); ?>" class="regular-text" /> <p class="description"><?php esc_html_e( 'Careful, your friend can discover this.', 'friends' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="friends_description"><?php esc_html_e( 'Description', 'friends' ); ?></label></th>
				<td><textarea name="friends_description" id="friends_description" rows="5" cols="30"><?php echo esc_html( $args['friend']->description ); ?></textarea><p class="description"><?php esc_html_e( 'Careful, your friend can discover this.', 'friends' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="url"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'URL' ); ?></label></th>
				<td><input type="text" name="user_url" value="<?php echo esc_attr( $args['friend']->user_url ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="friends_user_login"><?php esc_html_e( 'Username', 'friends' ); ?></label></th>
				<td><input type="text" name="friends_user_login" id="friends_user_login" value="<?php echo esc_attr( $args['friend']->user_login ); ?>" class="regular-text" /></td>
			</tr>
			<?php do_action( 'friends_edit_friend_table_end', $args['friend'] ); ?>
			<tr>
				<th><?php esc_html_e( 'Created', 'friends' ); ?></th>
				<td><?php echo esc_html( date_i18n( /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ __( 'F j, Y g:i a' ), strtotime( $args['friend']->user_registered ) ) ); ?></td>
			</tr>
		</tbody>
	</table>
	<?php do_action( 'friends_edit_friend_after_form', $args['friend'] ); ?>
	<p class="submit">
		<div class="button unfriend"><a class="unfriend" href="<?php echo esc_url( Friends\Admin::get_unfriend_link( $args['friend'] ) ); ?>"><?php esc_html_e( 'Unfriend', 'friends' ); ?></a></div>
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
