<?php
/**
 * Google Reader theme: status post content template.
 * Uses the same layout as regular posts.
 *
 * @package Friends
 */

$template_loader = Friends\Friends::template_loader();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'card column col-12 translator-exclude' ); ?>>
	<div class="card-header">
		<?php $template_loader->get_template_part( 'frontend/parts/header', 'status', $args ); ?>
	</div>
	<div class="card-body">
		<?php $template_loader->get_template_part( 'frontend/parts/entry-content', 'status', $args ); ?>
	</div>
	<?php $template_loader->get_template_part( 'frontend/parts/footer', 'status', $args ); ?>
</article>
