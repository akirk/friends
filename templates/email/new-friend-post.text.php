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

echo $quoted_text;

echo PHP_EOL, PHP_EOL;

printf(
	// translators: %1$s is a username, %2$s is a URL.
	__( 'This post was published by your friend %1$s at %2$s', 'friends' ),
	$author->display_name,
	get_permalink( $post )
);

echo PHP_EOL;
printf(
	// translators: %s is a URL.
	__( 'You can also view this post on your friends page: %s', 'friends' ),
	site_url( '/friends/' . $post->ID . '/' )
);
echo PHP_EOL, PHP_EOL;

printf(
	// translators: %s is a URL.
	__( 'Manage your subscription settings at %s', 'friends' ),
	$author->display_name,
	self_admin_url( 'admin.php?page=friends-settings' )
);
echo PHP_EOL;

printf(
	// translators: %1$s is a username, %2$s is a URL.
	__( 'Or just unsubscribe from %1$s\'s posts at %2$s', 'friends' ),
	$author->display_name,
	self_admin_url( 'admin.php?page=edit-friend&user=' . $author->ID )
);
echo PHP_EOL, PHP_EOL;

echo PHP_EOL, PHP_EOL;
_e( 'This notification was brought to you by the Friends plugin.', 'friends' );
