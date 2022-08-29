<?php
/**
 * This template contains the links results
 *
 * @version 1.0
 * @package Friends
 */

?>
<ul class="friend-suggestions"">
<?php

if ( empty( $args['links'] ) ) {
	?>
	<li>Nothing found.</li>
	<?php
}
foreach ( $args['links'] as $link ) {
	?>
	<li>
	<a href="<?php echo esc_url( $link->link_url ); ?>"><?php echo esc_html( $link->link_name ); ?></a>
	<?php

	if ( ! empty( $link->link_description ) ) {
		echo ' (' . esc_html( $link->link_description ) . ') ';
	}
	?>
	</li>
	<?php
}

?>
</ul>
