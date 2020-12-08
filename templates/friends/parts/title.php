<?php
/**
 * This template contains the content title part for an article on /friends/.
 *
 * @package Friends
 */

?><h4 class="card-title">
	<?php if ( $friends->post_types->is_cached_post_type( get_post_type() ) ) : ?>
		<?php if ( $recommendation ) : ?>
			<?php
			$friends->frontend->link(
				get_the_permalink(),
				// translators: %s is a post title.
				sprintf( __( 'Recommendation: %s', 'friends' ), get_the_title() )
			);
			?>
		<?php else : ?>
			<?php
			$friends->frontend->link(
				get_the_permalink(),
				get_the_title()
			);
			?>
		<?php endif; ?>
	<?php else : ?>
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	<?php endif; ?>
</h4>
