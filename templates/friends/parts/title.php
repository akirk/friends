<?php
/**
 * This template contains the content title part for an article on /friends/.
 *
 * @package Friends
 */

?><h4 class="card-title">
	<?php if ( Friends::CPT === get_post_type() ) : ?>
		<?php
		$friends->frontend->link(
			get_the_permalink(),
			get_the_title()
		);
		?>
	<?php else : ?>
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	<?php endif; ?>
</h4>
