<?php
/**
 * Navigation for a feed without a total-row count.
 *
 * @package Friends
 */

?>
<nav class="navigation posts-navigation" aria-label="<?php esc_attr_e( 'Posts', 'friends' ); ?>">
	<div class="nav-links">
		<div class="nav-previous"<?php echo empty( $args['has_more'] ) ? ' hidden' : ''; ?>><button type="button" class="btn btn-link"><?php esc_html_e( 'Load more posts', 'friends' ); ?></button></div>
	</div>
	<p class="friends-feed-status" role="status" aria-live="polite">
	<?php
	if ( empty( $args['has_more'] ) ) {
		esc_html_e( 'No more posts available.', 'friends' );
	}
	?>
	</p>
</nav>
