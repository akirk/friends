<?php
/**
 * This template contains the activitypub boost button.
 *
 * @version 1.0
 * @package Friends
 */

if ( \Friends\User::get_post_author( get_post() )->ID === get_current_user_id() ) {
	return;
}
?>
<a tabindex="0" href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-activitypub-boost' ) ); ?>"  class="btn ml-1 friends-activitypub-boost has-icon-right">
	<i class="dashicons dashicons-controls-repeat"></i> <?php echo esc_html_x( 'Boost', 'button', 'friends' ); ?>
	<?php if ( get_post_meta( get_the_ID(), 'boosted', true ) ) : ?>
		<i class="friends-activitypub-boost-status dashicons dashicons-saved"></i>
	<?php else : ?>
		<i class="friends-activitypub-boost-status"></i>
	<?php endif; ?>
</a>
