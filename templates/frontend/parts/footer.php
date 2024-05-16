<?php
/**
 * This template contains the content footer part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

?><footer class="entry-meta card-footer">
	<?php if ( in_array( get_post_type(), apply_filters( 'friends_frontend_post_types', array() ), true ) ) : ?>
		<?php
		Friends\Friends::template_loader()->get_template_part( 'frontend/parts/tags', null, $args );
		do_action( 'friends_post_footer_first' );
		Friends\Friends::template_loader()->get_template_part( 'frontend/parts/reactions', null, $args );
		Friends\Friends::template_loader()->get_template_part( 'frontend/parts/comments', null, $args );
		do_action( 'friends_post_footer_last' );
		?>
	<?php endif; ?>
</footer>
<?php

Friends\Friends::template_loader()->get_template_part( 'frontend/parts/comments-content', null, $args );
