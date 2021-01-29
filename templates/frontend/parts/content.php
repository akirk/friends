<?php
/**
 * This template contains the fallback part for any article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$template_loader = Friends::template_loader();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'card' ); ?>>
	<?php
	$template_loader->get_template_part( 'frontend/parts/header', get_post_format(), $args );
	$template_loader->get_template_part( 'frontend/parts/title', get_post_format(), $args );
	$template_loader->get_template_part( 'frontend/parts/entry-content', get_post_format(), $args );
	$template_loader->get_template_part( 'frontend/parts/footer', get_post_format(), $args );
	?>
</article>
