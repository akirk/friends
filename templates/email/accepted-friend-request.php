<?php
/**
 * This template contains the HTML for the Accepted Friend Request notification e-mail.
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
	echo esc_html( sprintf( __( 'Good news, %s has accepted your friend request.', 'friends' ), $args['friend_user']->display_name ) );
	?>
</p>

<p>
	<?php
	// translators: %s is a URL.
	echo wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> and look at their posts.', 'friends' ), esc_url( $args['friend_user']->get_local_friends_page_url() ) ), array( 'a' => array( 'href' => true ) ) );
	?>
</p>
