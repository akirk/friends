<?php
/**
 * This template contains the HTML for the New Friend Request notification e-mail.
 *
 * @package Friends
 */

?>
<?php include __DIR__ . '/header.php'; ?>
<p>
	<?php
	// translators: %s is a user display name.
		printf( __( 'Howdy, %s!' ), $user->display_name );
	?>
</p>

<p>
	<?php
	// translators: %s is a username.
	printf( __( 'Good news, %s has accepted your friend request.', 'friends' ), $friend_user->display_name );
	?>
</p>

<p>
	<?php
	// translators: %s is a URL.
	printf( __( 'Go to your <a href=%s>friends page</a> and look at their posts.', 'friends' ), site_url( '/friends/' . $friend_user->user_login . '/' ) );
	?>
</p>

<p>
	<?php
		esc_html_e( 'Best, the Friends plugin', 'friends' );
	?>
</p>
<?php include __DIR__ . '/footer.php'; ?>
