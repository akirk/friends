<?php
/**
 * This template contains the HTML for the New Friend Post notification e-mail.
 *
 * @package Friends
 */

echo __( 'Hi there,', 'friends' );
echo PHP_EOL . PHP_EOL;

echo __( "I'm using the Friends plugin for WordPress to share things with just my friends.", 'friends' );
echo ' ';
echo __( "If you'd install it, too, we could connect our WordPresses and become friends.", 'friends' );
echo ' ';
echo __( 'Then you could also see the private posts on my blog.', 'friends' );
echo ' ';
echo __( "Let's try! Please install the friends plugin and send me a friend request:", 'friends' );
echo PHP_EOL . PHP_EOL;

echo trailingslashit( $friend->user_url ) . 'wp-admin/plugin-install.php?s=friends-plugin&tab=search&type=tag';
echo PHP_EOL;

echo Friends::PLUGIN_URL;
echo PHP_EOL . PHP_EOL;

echo __( 'Via the following link you should then be able to send me a friend request:', 'friends' );
echo PHP_EOL . PHP_EOL;

echo trailingslashit( $friend->user_url ) . 'wp-admin/admin.php?page=add-friend&url=' . site_url();
echo PHP_EOL . PHP_EOL;

echo __( 'Best,', 'friends' );
echo PHP_EOL;
echo wp_get_current_user()->display_name;
