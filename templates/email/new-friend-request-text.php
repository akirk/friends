<?php
/**
 * This template contains the HTML for the New Friend Request notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

// translators: %s is a user display name.
printf( __( 'Howdy, %s!', 'friends' ), $args['user']->display_name );
echo PHP_EOL;

// translators: %s is a username.
printf( __( 'You have received a new friend request from %s.', 'friends' ), $args['friend_user']->display_name );
echo PHP_EOL . PHP_EOL;

// translators: %s is a URL.
printf( wp_strip_all_tags( __( 'Go to your <a href=%s>admin page</a> to review the request and approve or delete it.', 'friends' ) ) );
echo PHP_EOL . PHP_EOL;

echo self_admin_url( 'users.php?role=friend_request' );

echo PHP_EOL . PHP_EOL;

// translators: %s is a site name.
printf( __( 'This notification was sent by the Friends plugin on %s.', 'friends' ), get_option( 'blogname' ) );
