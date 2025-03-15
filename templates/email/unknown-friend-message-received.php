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
		printf( __( 'Hi %s!', 'friends' ), esc_html( $args['user']->display_name ) );
	?>
</p>

<p>
	<?php
	// translators: %s is a username.
	printf( __( 'We just received a message from %s:', 'friends' ), esc_html( $args['sender_name'] ) );
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
	printf( __( 'Maybe you want to <a href=%s>follow them</a> to respond?', 'friends' ), esc_attr( home_url( '?add-friend=' . esc_url( $args['feed_url'] ) ) ) );
	?>
</p>
