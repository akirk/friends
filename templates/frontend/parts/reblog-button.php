<?php
/**
 * This template contains the reblog button.
 *
 * @version 1.0
 * @package Friends
 */

if ( \Friends\User::get_post_author( get_post() )->ID === get_current_user_id() ) {
	return;
}
?>
<a tabindex="0" href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-reblog' ) ); ?>"  class="btn ml-1 friends-reblog has-icon-right">
	<i class="dashicons dashicons-controls-repeat"></i> <?php echo esc_html_x( 'Reblog', 'button', 'friends' ); ?>
	<?php if ( get_post_meta( get_the_ID(), 'reblogged', true ) ) : ?>
		<i class="friends-reblog-status dashicons dashicons-saved"></i>
	<?php else : ?>
		<i class="friends-reblog-status"></i>
	<?php endif; ?>
</a>
