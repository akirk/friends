<?php
/**
 * This template contains the content footer part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

?><footer class="entry-meta card-footer">
	<?php if ( in_array( get_post_type(), Friends::get_frontend_post_types(), true ) ) : ?>
		<?php
		do_action( 'friends_post_footer_first' );
		Friends::template_loader()->get_template_part( 'frontend/parts/reactions', null, $args );
		Friends::template_loader()->get_template_part( 'frontend/parts/comments', null, $args );
		do_action( 'friends_post_footer_last' );
		?>
	<?php endif; ?>
</footer>
