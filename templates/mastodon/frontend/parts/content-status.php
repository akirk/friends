<?php
/**
 * Mastodon theme: status post content template.
 *
 * @package Friends
 */

$template_loader = Friends\Friends::template_loader();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'card translator-exclude' ); ?>>
	<?php $template_loader->get_template_part( 'frontend/parts/header', 'status', $args ); ?>
	<div class="card-body">
		<?php $template_loader->get_template_part( 'frontend/parts/entry-content', 'status', $args ); ?>
	</div>
	<?php $template_loader->get_template_part( 'frontend/parts/footer', 'status', $args ); ?>
</article>
