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
		do_action( 'friends_post_footer_first' );
		Friends\Friends::template_loader()->get_template_part( 'frontend/parts/reactions', null, $args );

		$args['friends']->frontend->link(
			'?in_reply_to=' . urlencode( get_permalink() ),
			__( 'Reply', 'friends' ),
			array(
				'class'          => 'quick-reply btn ml-1',
				'dashicon_front' => 'admin-comments',
				'data-url'       => get_permalink(),
			)
		);

		do_action( 'friends_post_footer_last' );
		?>
	<?php endif; ?>
</footer>
<?php

Friends\Friends::template_loader()->get_template_part( 'frontend/parts/comments-content', null, $args );
