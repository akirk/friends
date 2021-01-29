<?php
/**
 * Displayed if there are no posts.
 *
 * @version 1.0
 * @package Friends
 */

if ( isset( $_GET['p'] ) && $_GET['p'] > 1 ) {
	esc_html_e( 'No further posts of your friends could were found.', 'friends' );
} else {
	esc_html_e( "Your friends haven't posted anything yet!", 'friends' );
}
