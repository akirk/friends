<?php
/**
 * This contains the ractions picker.
 *
 * @package Friends
 */

?>
<div id="friends-recommendation-form" style="display: none">
	<div class="message"></div>
	<form>
		<?php if ( 0 === $friends->get_total() ) : ?>
			<?php esc_html_e( "Unfortunately, you don't have any friends yet that you could recommend this to.", 'friends' ); ?>
			<br />
			<a href="<?php echo esc_url( self_admin_url( 'admin.php?page=send-friend-request' ) ); ?>"><?php esc_html_e( 'Send Friend Request', 'friends' ); ?></a>
		<?php else : ?>
			<?php wp_nonce_field( 'friends-recommendation', '_ajax_nonce' ); ?>
			<input type="hidden" name="post_id" />
			<input type="hidden" name="action" value="friends_recommend_post" />
			<fieldset>
				<legend><?php esc_html_e( 'Message', 'friends' ); ?></legend>
					<textarea name="message" rows="3" cols="40" placeholder="<?php esc_attr_e( 'Optionally explain your friend why you recommend this.', 'friends' ); ?>"></textarea>
			</fieldset>

			<fieldset>
				<legend><?php esc_html_e( 'Select Friends', 'friends' ); ?></legend>
				<select name="friends[]" multiple="multiple">
					<?php foreach ( $friends->get_results() as $friend_user ) : ?>
						<option value="<?php echo esc_attr( $friend_user->ID ); ?>" selected="selected"><?php echo esc_html( $friend_user->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</fieldset>

			<button><?php esc_html_e( 'Send Recommendation', 'friends' ); ?></button>
		<?php endif; ?>
	</form>
</div>
