<?php
/**
 * This template contains the content footer part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

?><footer class="entry-meta card-footer">
	<?php if ( Friends::CPT === get_post_type() ) : ?>
		<?php
		$args['friends']->frontend->link(
			get_comments_link(),
			get_comments_number( '', 1, '%' ),
			array(
				'class'          => 'comments btn',
				'dashicon_front' => 'admin-comments',
			)
		);
		?>
	<?php endif; ?>
</footer>
