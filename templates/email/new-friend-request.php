<?php
/**
 * This template contains the HTML for the New Friend Request notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

?>
<p>
	<?php
	// translators: %s is a user display name.
		echo esc_html( sprintf( __( 'Hi %s!', 'friends' ), $args['user']->display_name ) );
	?>
</p>

<p>
	<?php
	// translators: %s is a username.
	echo esc_html( sprintf( __( 'You have received a new friend request from %s.', 'friends' ), $args['friend_user']->display_name ) );
	?>
</p>

<p>
	<?php
	// translators: %s is a URL.
	echo wp_kses( sprintf( __( 'Go to your <a href=%s>admin page</a> to review the request and approve or delete it.', 'friends' ), esc_url( self_admin_url( 'users.php?role=friend_request' ) ) ), array( 'a' => array( 'href' => true ) ) );
	?>
</p>
