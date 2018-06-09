<?php
/**
 * This template contains the HTML for the New Friend Post notification e-mail.
 *
 * @package Friends
 */

// translators: %s is a user display name.
printf( __( 'Howdy, %s!' ), $user->display_name );
echo PHP_EOL;

// translators: %s is a username.
printf( __( 'You have received a new friend request from %s.', 'friends' ), $friend_user->display_name );
echo PHP_EOL . PHP_EOL;

// translators: %s is a URL.
printf( strip_tags( __( 'Go to your <a href=%s>admin page</a> to review the request and approve or delete it.', 'friends' ) ) );
echo PHP_EOL . PHP_EOL;

echo self_admin_url( 'users.php?role=friend_request' );

echo PHP_EOL . PHP_EOL;
_e( 'Best, the Friends plugin', 'friends' );
