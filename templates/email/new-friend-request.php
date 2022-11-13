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
		printf( __( 'Howdy, %s!', 'friends' ), esc_html( $args['user']->display_name ) );
	?>
</p>

<p>
	<?php
	// translators: %s is a username.
	printf( __( 'You have received a new friend request from %s.', 'friends' ), esc_html( $args['friend_user']->display_name ) );
	?>
</p>

<p>
	<?php
	// translators: %s is a URL.
	printf( __( 'Go to your <a href=%s>admin page</a> to review the request and approve or delete it.', 'friends' ), esc_url( self_admin_url( 'users.php?role=friend_request' ) ) );
	?>
</p>

<p>
	<?php
		// translators: %s is a site name.
		echo wp_kses( sprintf( __( 'This notification was sent by the Friends plugin on %s.', 'friends' ), '<a href="' . esc_attr( home_url() ) . '">' . get_option( 'blogname' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
	?>
</p>
