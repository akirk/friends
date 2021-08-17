<?php
/**
 * This template contains the HTML for the Friend Message Received notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

$ensure_linebreaks     = preg_replace( '/\<br(\s*)?\/?\>/i', PHP_EOL, $args['message'] );
$plain_text            = strip_tags( $ensure_linebreaks );
$normalized_whitespace = preg_replace( '/(' . PHP_EOL . '\s*' . PHP_EOL . ')+/m', PHP_EOL . PHP_EOL, $plain_text );
$quoted_text           = '> ' . str_replace( PHP_EOL, PHP_EOL . '> ', trim( $normalized_whitespace ) );

// translators: %s is a user display name.
printf( __( 'Howdy, %s!' ), $args['user']->display_name );
echo PHP_EOL;

// translators: %s is a username.
printf( __( 'We just received a message from %s:', 'friends' ), $args['friend_user']->display_name );
echo PHP_EOL . PHP_EOL;

echo $quoted_text;

// translators: %s is a URL.
printf( strip_tags( __( 'Go to your <a href=%s>friends page</a> to respond.', 'friends' ) ) );
echo PHP_EOL . PHP_EOL;

echo home_url( $args['friend_user']->get_local_friends_page_url() . '/' );
