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
<a tabindex="0" href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-boost' ) ); ?>"  class="btn btn-link ml-1 friends-boost has-icon-right" title="<?php echo esc_attr_x( 'Boost', 'button', 'friends' ); ?>">
	<i class="dashicons dashicons-controls-repeat"></i> <span class="text"><?php echo esc_html_x( 'Boost', 'button', 'friends' ); ?></span>
	<?php if ( get_post_meta( get_the_ID(), 'boosted', true ) ) : ?>
		<i class="friends-boost-status dashicons dashicons-saved"></i>
	<?php else : ?>
		<i class="friends-boost-status"></i>
	<?php endif; ?>
</a>
