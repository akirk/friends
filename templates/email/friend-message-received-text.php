<?php
/**
 * This template contains the HTML for the Friend Message Received notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

$ensure_linebreaks     = preg_replace( '/\<br(\s*)?\/?\>/i', PHP_EOL, $args['message'] );
$plain_text            = wp_strip_all_tags( $ensure_linebreaks );
$normalized_whitespace = preg_replace( '/(' . PHP_EOL . '\s*' . PHP_EOL . ')+/m', PHP_EOL . PHP_EOL, $plain_text );
$quoted_text           = '> ' . str_replace( PHP_EOL, PHP_EOL . '> ', trim( $normalized_whitespace ) );

// This is a text e-mail, not a place for HTML escaping.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

// translators: %s is a user display name.
printf( __( 'Hi %s!', 'friends' ), $args['user']->display_name );
echo PHP_EOL;

// translators: %s is a username.
printf( __( 'We just received a message from %s:', 'friends' ), $args['friend_user']->display_name );
echo PHP_EOL . PHP_EOL;

echo $quoted_text;

// translators: %s is a URL.
printf( wp_strip_all_tags( __( 'Go to your <a href=%s>friends page</a> to respond.', 'friends' ) ) );
echo PHP_EOL . PHP_EOL;

echo $args['friend_user']->get_local_friends_page_url();
