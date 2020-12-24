<?php
/**
 * This template contains the content part for an article on /friends/.
 *
 * @package Friends
 */

?>
<div class="card-body">
	<?php
	if ( empty( get_the_content() ) ) {
		the_title();
	} else {
		the_content();
	}
	?>
</div>
