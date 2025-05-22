<?php
/**
 * This template contains the text for the New Friend Post notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

$ensure_linebreaks     = preg_replace( '/\<br(\s*)?\/?\>/i', PHP_EOL, $args['post']->post_content );
$plain_text            = wp_strip_all_tags( $ensure_linebreaks );
$normalized_whitespace = preg_replace( '/(' . PHP_EOL . '\s*' . PHP_EOL . ')+/m', PHP_EOL . PHP_EOL, $plain_text );
$quoted_text           = '> ' . str_replace( PHP_EOL, PHP_EOL . '> ', trim( $normalized_whitespace ) );

// This is a text e-mail, not a place for HTML escaping.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
echo $quoted_text;

echo PHP_EOL, PHP_EOL;

printf(
	// translators: %1$s is a username, %2$s is a URL.
	__( 'This post was published by your friend %1$s at %2$s', 'friends' ),
	$args['author']->display_name,
	get_permalink( $args['post'] )
);

echo PHP_EOL;
printf(
	// translators: %s is a URL.
	__( 'You can also view this post on your friends page: %s', 'friends' ),
	$args['author']->get_local_friends_page_url( $args['post']->ID )
);
echo PHP_EOL, PHP_EOL;

printf(
	// translators: %s is a URL.
	__( 'Manage your subscription settings at %s', 'friends' ),
	$args['author']->display_name,
	self_admin_url( 'admin.php?page=friends-settings' )
);
echo PHP_EOL;

printf(
	// translators: %1$s is a username, %2$s is a URL.
	__( 'Or just unsubscribe from %1$s\'s posts at %2$s', 'friends' ),
	$args['author']->display_name,
	self_admin_url( 'admin.php?page=edit-friend&user=' . $args['author']->user_login )
);
