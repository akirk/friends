<?php
/**
 * This template contains the content part for an article on /friends/.
 *
 * @version 1.0
 * @package Friends
 */

$content = get_the_content();
?>
<div class="card-body">
	<?php
	if ( empty( $content ) ) {
		the_title();
	} else {
		the_content();
	}

	Friends\Friends::template_loader()->get_template_part( 'frontend/parts/link-preview', get_post_format(), $args );
	?>
</div>
