<?php
/**
 * This template contains the activitypub announce section.
 *
 * @version 1.0
 * @package Friends
 */

if ( get_the_author_meta( 'ID' ) === get_current_user_id() ) {
	return;
}
?>
<a tabindex="0" href="#" data-id="<?php echo esc_attr( get_the_ID() ); ?>" class="btn ml-1 friends-reblog has-icon-right">
	<i class="dashicons dashicons-controls-repeat"></i> <?php echo esc_html( $args['button-label'] ); ?>
	<?php if ( get_post_meta( get_the_ID(), 'reblogged', true ) ) : ?>
		<i class="friends-reblog-status dashicons dashicons-saved"></i>
	<?php else : ?>
		<i class="friends-reblog-status"></i>
	<?php endif; ?>
</a>
