<?php
/**
 * This template contains the Admin Suggest Friends Plugin form.
 *
 * @package Friends
 */

?><form method="post">
	<?php wp_nonce_field( 'suggest-friends-plugin' ); ?>
	<label><?php _e( 'To:', 'friends' ); ?>  <input type="text" name="to" value="<?php echo esc_attr( $to ); ?>" autofocus /></label><br/><br/>
	<label><?php _e( 'Subject:', 'friends' ); ?> <input type="text" name="subject" value="<?php echo esc_attr( $subject ); ?>" /></label><br/><br/>
	<textarea rows="20" cols="60" name="message"><?php echo wp_kses( $message, array( 'br' => array() ) ); ?></textarea>
	<br/><br/>
	<label><input type="radio" name="sendstyle" value="email" checked="checked" /> <?php _e( 'Send via the e-mail function of this WordPress', 'friends' ); ?></label><br/>
	<label><input type="radio" name="sendstyle" value="email" /> <?php _e( 'Generate a mailto link', 'friends' ); ?></label>
	<br/><br/>
	<button><?php _e( 'Send E-Mail', 'friends' ); ?></button>
</form>
