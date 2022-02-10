<?php
/**
 * This template contains the content title part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

?><h4 class="card-title">
	<?php if ( Friends\Friends::CPT === get_post_type() ) : ?>
		<?php
		$args['friends']->frontend->link(
			get_the_permalink(),
			get_the_title()
		);
		?>
	<?php else : ?>
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	<?php endif; ?>
</h4>
