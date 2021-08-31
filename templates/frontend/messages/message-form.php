<?php
/**
 * This template contains the send message form on /friends/.
 *
 * @package Friends
 */

if ( ! isset( $args['subject'] ) ) {
	?>
	<div class="card mt-2 p-2" id="friends-send-new-message" style="display: none">
	<?php
}
?>
<form method="post" class="form-horizontal">
	<input type="hidden" name="friends_message_recipient" value="<?php echo esc_attr( $args['friend_user']->ID ); ?>">
	<?php wp_nonce_field( 'friends_send_message' ); ?>
	<?php if ( isset( $args['subject'] ) ) : ?>
		<input type="hidden" name="friends_message_subject" value="<?php echo esc_attr( $args['subject'] ); ?>">
	<?php else : ?>
	<div class="form-group">
		<div class="col-2 col-sm-12">
			<label class="form-label" for="subject"><?php esc_html_e( 'Subject', 'friends' ); ?></label>
		</div>
		<div class="col-8 col-sm-12">
			<?php if ( isset( $args['subject'] ) && $args['subject'] ) : ?>
				<input type="hidden" name="friends_message_subject" value="<?php echo esc_attr( $args['subject'] ); ?>" />
			<?php else : ?>
				<input class="form-input" type="text" name="friends_message_subject" value="" placeholder="<?php esc_attr_e( 'Subject (optional)', 'friends' ); ?>" />
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="form-group">
		<div class="col-2 col-sm-12">
			<label class="form-label" for="friends_message_message"><?php esc_html_e( 'Message', 'friends' ); ?></label>
		</div>
		<div class="col-8 col-sm-12 gutenberg-everywhere">
			<textarea class="form-input friends-message-message" name="friends_message_message" id="friends_message_message" placeholder="" rows="3"></textarea>
		<?php
		do_action( 'friends_message_form' );
		?>
		</div>
	</div>
	<div class="form-group">
		<div class="col-2 col-sm-12">
		</div>
		<div class="col-6 col-sm-12">
			<button class="btn"><?php esc_html_e( 'Send', 'friends' ); ?></button>
		</div>
		<div class="col-2 col-sm-12" style="text-align: right">
			<button class="btn btn-link btn-sm delete-conversation text-error" name="friends_message_delete_conversation"><?php esc_html_e( 'Delete conversation', 'friends' ); ?></button>
		</div>
	</div>
</form>
<?php
if ( ! isset( $args['subject'] ) ) {
	?>
	</div>
	<?php
}
