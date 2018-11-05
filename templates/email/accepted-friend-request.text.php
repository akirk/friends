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
printf( __( 'Good news, %s has accepted your friend request.', 'friends' ), $friend_user->display_name );
echo PHP_EOL . PHP_EOL;

// translators: %s is a URL.
printf( strip_tags( __( 'Go to your <a href=%s>friends page</a> and look at their posts.', 'friends' ) ) );
echo PHP_EOL . PHP_EOL;

echo site_url( '/friends/' . $friend_user->user_login . '/' );

echo PHP_EOL . PHP_EOL;

// translators: %s is a site name.
printf( __( 'This notification was sent by the Friends plugin on %s.', 'friends' ), is_multisite() ? get_site_option( 'site_name' ) : get_option( 'blogname' ) );
