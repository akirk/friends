<?php
/**
 * This template contains the HTML for the Friend Message Received notification e-mail.
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
	printf( __( 'We just received a message from %s:', 'friends' ), esc_html( $args['friend_user']->display_name ) );
	?>
</p>

<blockquote class="message-content">
<?php
	echo wp_kses_post( apply_filters( 'friends_rewrite_mail_html', $args['message'], $args ) );
?>
</blockquote>


<p>
	<?php
	// translators: %s is a URL.
	printf( __( 'Go to your <a href=%s>friends page</a> to respond.', 'friends' ), esc_url( $args['friend_user']->get_local_friends_page_url() ) );
	?>
</p>
