<?php
/**
 * This template contains the HTML for the Accepted Friend Post notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

// This is a text e-mail, not a place for HTML escaping.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

// translators: %s is a user display name.
printf( __( 'Hi %s!', 'friends' ), $args['user']->display_name );
echo PHP_EOL;

// translators: %s is a username.
printf( __( 'Good news, %s has accepted your friend request.', 'friends' ), $args['friend_user']->display_name );
echo PHP_EOL . PHP_EOL;

// translators: %s is a URL.
printf( wp_strip_all_tags( __( 'Go to your <a href=%s>friends page</a> and look at their posts.', 'friends' ) ) );
echo PHP_EOL . PHP_EOL;

echo $args['friend_user']->get_local_friends_page_url();
