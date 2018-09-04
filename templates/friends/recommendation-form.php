<?php
/**
 * This contains the ractions picker.
 *
 * @package Friends
 */

?>
<div id="friends-recommendation-form">
	<div class="message"></div>
	<form>
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
	</form>
</div>
