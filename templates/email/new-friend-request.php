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
	printf( __( 'You have received a new friend request from %s.', 'friends' ), $friend_user->display_name );
	?>
</p>

<p>
	<?php
	// translators: %s is a URL.
	printf( __( 'Go to your <a href=%s>admin page</a> to review the request and approve or delete it.', 'friends' ), self_admin_url( 'users.php?role=friend_request' ) );
	?>
</p>

<p>
	<?php
		esc_html_e( 'Best, the Friends plugin', 'friends' );
	?>
</p>
<?php include __DIR__ . '/footer.php'; ?>
