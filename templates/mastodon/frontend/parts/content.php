<?php
/**
 * Mastodon theme: post content template.
 *
 * @package Friends
 */

$template_loader = Friends\Friends::template_loader();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'card translator-exclude' ); ?>>
	<?php $template_loader->get_template_part( 'frontend/parts/header', get_post_format(), $args ); ?>
	<div class="card-title">
		<?php $template_loader->get_template_part( 'frontend/parts/title', get_post_format(), $args ); ?>
	</div>
	<div class="card-body">
		<?php $template_loader->get_template_part( 'frontend/parts/entry-content', get_post_format(), $args ); ?>
	</div>
	<?php $template_loader->get_template_part( 'frontend/parts/footer', get_post_format(), $args ); ?>
</article>
