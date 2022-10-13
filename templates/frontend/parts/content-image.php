<?php
/**
 * This template contains the part for a post-format imgae on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$template_loader = Friends\Friends::template_loader();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'column col-3 col-xl-4 col-lg-6 col-sm-12 col-xs-12' ); ?>>
	<div class="card">
		<?php
		$template_loader->get_template_part( 'frontend/parts/header', get_post_format(), $args );
		$template_loader->get_template_part( 'frontend/parts/title', get_post_format(), $args );
		$template_loader->get_template_part( 'frontend/parts/entry-content', get_post_format(), $args );
		$template_loader->get_template_part( 'frontend/parts/footer', get_post_format(), $args );
		?>
	</div>
</article>
