<?php
/**
 * This template contains the HTML for the New Friend Post notification e-mail.
 *
 * @package Friends
 */

$ensure_linebreaks     = preg_replace( '/\<br(\s*)?\/?\>/i', PHP_EOL, $post->post_content );
$plain_text            = strip_tags( $ensure_linebreaks );
$normalized_whitespace = preg_replace( '/(' . PHP_EOL . '\s*' . PHP_EOL . ')+/m', PHP_EOL . PHP_EOL, $plain_text );
$quoted_text           = '> ' . str_replace( PHP_EOL, PHP_EOL . '> ', trim( $normalized_whitespace ) );

// translators: %s is a user display name.
printf( __( 'Howdy, %s!' ), $user->display_name );
echo PHP_EOL;

printf(
	// translators: %1$s is a username, %2$s is a post title.
	__( 'Your friend %1$s has published a new post: %2$s', 'friends' ),
	$author->display_name,
	$post->post_title
);

echo get_permalink( $post ), PHP_EOL;
printf(
	// translators: %1$s is a username, %2$s is a post title.
	__( 'View this post on your site: %s', 'friends' ),
	site_url( '/friends/' . $post->ID . '/' )
);
echo PHP_EOL, PHP_EOL;

echo $quoted_text;

echo PHP_EOL, PHP_EOL;

_e( "Unsubscribe from this friend's posts", 'friends' );
echo ' ', self_admin_url( 'admin.php?page=edit-friend&user=' . $author->ID );

echo PHP_EOL, PHP_EOL;
_e( 'Best, the Friends plugin', 'friends' );
