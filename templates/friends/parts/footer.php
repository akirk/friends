<?php
/**
 * This template contains the content footer part for an article on /friends/.
 *
 * @package Friends
 */

?><footer class="entry-meta">
	<?php if ( $friends->post_types->is_cached_post_type( get_post_type() ) ) : ?>
		<?php
		$friends->frontend->link(
			get_comments_link(),
			get_comments_number( '', 1, '%' ),
			array(
				'class'    => 'comments button',
				'dashicon' => 'admin-comments',
			)
		);
		?>
	<?php endif; ?>
	<?php echo $friends->reactions->post_reactions(); ?>
	<?php if ( $friends->post_types->is_cached_post_type( get_post_type() ) ) : ?>
		<?php echo $friends->recommendation->post_recommendation(); ?>
	<?php endif; ?>
</footer>
