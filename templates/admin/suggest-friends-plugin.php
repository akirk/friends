<?php
/**
 * This template contains the Admin Suggest Friends Plugin form.
 *
 * @package Friends
 */

?><form method="post">
	<?php wp_nonce_field( 'suggest-friends-plugin' ); ?>
	<label><?php _e( 'To:' ); ?>  <input type="text" name="to" value="<?php echo esc_attr( $to ); ?>" autofocus /></label><br/><br/>
	<label><?php _e( 'Subject:', 'friends' ); ?> <input type="text" name="subject" value="<?php echo esc_attr( $subject ); ?>" size="50" /></label><br/><br/>
	<textarea rows="20" cols="60" name="message"><?php echo wp_kses( $message, array( 'br' => array() ) ); ?></textarea>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Send E-Mail', 'friends' ); ?>">
		<a href="<?php echo esc_url( self_admin_url( 'admin.php?page=edit-friend&user=' . $friend->ID ) ); ?>" style="margin-left: 1em"><?php _e( 'Back' ); ?></a>
	</p>
</form>
