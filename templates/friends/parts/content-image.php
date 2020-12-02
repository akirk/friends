<?php
/**
 * This template contains the part for a post-format imgae on /friends/.
 *
 * @package Friends
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
	include __DIR__ . '/header.php';
	include __DIR__ . '/entry-content.php';
	include __DIR__ . '/footer.php';
	?>
</article>
