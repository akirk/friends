<?php
/**
 * This template contains the send message form on /friends/.
 *
 * @package Friends
 */

if ( ! isset( $args['subject'] ) && ! isset( $args['reply_to'] ) ) {
	?>
	<div class="card mt-2 p-2" id="friends-send-new-message" style="display: none">
	<?php
}
?>
<form method="post" class="form-horizontal">
	<input type="hidden" name="friends_message_recipient" value="<?php echo esc_attr( $args['friend_user']->user_login ); ?>">
	<?php if ( isset( $args['reply_to'] ) ) : ?>
		<input type="hidden" name="friends_message_reply_to" value="<?php echo esc_attr( $args['reply_to'] ); ?>">
	<?php endif; ?>
	<?php wp_nonce_field( 'friends_send_message' ); ?>
	<div class="form-group">
		<div class="col-2 col-sm-12">
			<label class="form-label" for="friend_message_to"><?php esc_html_e( 'To', 'friends' ); ?></label>
		</div>
		<div class="col-8 col-sm-12">
			<?php if ( count( $args['accounts'] ) > 1 ) : ?>
				<select name="friends_message_account" id="friend_message_to">
				<?php foreach ( $args['accounts'] as $url => $name ) : ?>
					<option value="<?php echo esc_attr( $url ); ?>"><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input type="hidden" name="friends_message_account" value="<?php echo esc_attr( key( $args['accounts'] ) ); ?>">
				<span id="friend_message_to"><?php echo esc_html( reset( $args['accounts'] ) ); ?></span>
			<?php endif; ?>
		</div>
	</div>
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
		<div class="col-8 col-sm-12<?php echo esc_attr( $args['blocks-everywhere'] ? ' blocks-everywhere iso-editor__loading' : '' ); ?>">
			<textarea class="form-input friends-message-message<?php echo esc_attr( $args['blocks-everywhere'] ? ' blocks-everywhere-enabled' : '' ); ?>" name="friends_message_message" placeholder="" rows="3"></textarea>
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
		<?php
		if ( ! empty( $args['reply_to'] ) ) {
			?>
			<div class="col-2 col-sm-12" style="text-align: right">
				<button class="btn btn-link btn-sm delete-conversation text-error" name="friends_message_delete_conversation"><?php esc_html_e( 'Delete conversation', 'friends' ); ?></button>
			</div>
			<?php
		}
		?>
	</div>
</form>
<?php
if ( ! isset( $args['subject'] ) ) {
	?>
	</div>
	<?php
}
