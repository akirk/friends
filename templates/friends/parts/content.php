<?php
/**
 * This template contains the fallback part for any article on /friends/.
 *
 * @package Friends
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
	include __DIR__ . '/header.php';
	include __DIR__ . '/title.php';
	include __DIR__ . '/entry-content.php';
	include __DIR__ . '/footer.php';
	?>
</article>
