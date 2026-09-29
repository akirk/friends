<?php
/**
 * This template contains the send message form on /friends/.
 *
 * @package Friends
 */

$is_new_message = ! isset( $args['reply_to'] );
if ( $is_new_message ) {
	?>
	<div class="card mt-2 p-2" id="friends-send-new-message" style="display: none">
	<?php
}

$draft_key = implode(
	':',
	array(
		'friends-message-draft',
		get_current_user_id(),
		$args['friend_user']->user_login,
		isset( $args['reply_to'] ) ? 'reply-' . absint( $args['reply_to'] ) : 'new',
	)
);
$content_warning_id = 'friends_message_content_warning_' . ( $is_new_message ? 'new' : absint( $args['reply_to'] ) );
$content_warning_toggle_id = $content_warning_id . '_toggle';
$has_content_warning = ! empty( $args['subject'] );
?>
<form method="post" class="form-horizontal" data-friends-message-draft-key="<?php echo esc_attr( $draft_key ); ?>">
	<input type="hidden" name="friends_message_recipient" value="<?php echo esc_attr( $args['friend_user']->user_login ); ?>">
	<?php if ( isset( $args['reply_to'] ) ) : ?>
		<input type="hidden" name="friends_message_reply_to" value="<?php echo esc_attr( $args['reply_to'] ); ?>">
	<?php endif; ?>
	<?php if ( ! empty( $args['redirect_to'] ) ) : ?>
		<input type="hidden" name="friends_message_redirect_to" value="<?php echo esc_url( $args['redirect_to'] ); ?>">
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
	<div class="form-group friends-message-content-warning" id="<?php echo esc_attr( $content_warning_id ); ?>_field"<?php echo $has_content_warning ? '' : ' hidden'; ?>>
		<div class="col-2 col-sm-12">
			<label class="form-label" for="<?php echo esc_attr( $content_warning_id ); ?>"><?php esc_html_e( 'Content warning', 'friends' ); ?></label>
		</div>
		<div class="col-8 col-sm-12">
			<input class="form-input" type="text" id="<?php echo esc_attr( $content_warning_id ); ?>" name="friends_message_subject" value="<?php echo isset( $args['subject'] ) ? esc_attr( $args['subject'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Content warning (optional)', 'friends' ); ?>" />
		</div>
	</div>

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
		<div class="<?php echo esc_attr( ! empty( $args['reply_to'] ) ? 'col-4' : 'col-6' ); ?> col-sm-12 friends-message-actions">
			<button class="btn"><?php esc_html_e( 'Send', 'friends' ); ?></button>
			<label class="form-checkbox friends-message-content-warning-label" for="<?php echo esc_attr( $content_warning_toggle_id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $content_warning_toggle_id ); ?>" class="friends-message-content-warning-toggle" aria-controls="<?php echo esc_attr( $content_warning_id ); ?>_field" aria-expanded="<?php echo $has_content_warning ? 'true' : 'false'; ?>"<?php checked( $has_content_warning ); ?>>
				<i class="form-icon"></i>
				<?php esc_html_e( 'Content warning', 'friends' ); ?>
			</label>
		</div>
		<?php
		if ( ! empty( $args['reply_to'] ) ) {
			?>
			<div class="col-4 col-sm-12" style="text-align: right">
				<button class="btn btn-link btn-sm delete-conversation text-error" name="friends_message_delete_conversation"><?php esc_html_e( 'Delete conversation', 'friends' ); ?></button>
			</div>
			<?php
		}
		?>
	</div>
</form>
<?php
if ( $is_new_message ) {
	?>
	</div>
	<?php
}
