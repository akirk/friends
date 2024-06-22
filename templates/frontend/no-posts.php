<?php
/**
 * Displayed if there are no posts.
 *
 * @version 1.0
 * @package Friends
 */

if ( isset( $_GET['p'] ) && $_GET['p'] > 1 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	esc_html_e( 'No further posts of your friends could were found.', 'friends' );
} else {
	esc_html_e( 'Unfortunately, we could not find a post.', 'friends' );
}
