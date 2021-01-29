<?php
/**
 * This template contains the HTML for the New Friend Request notification e-mail.
 *
 * @version 1.0
 * @package Friends
 */

?>
<?php include __DIR__ . '/header.php'; ?>
<p>
	<?php
	// translators: %s is a user display name.
		printf( __( 'Howdy, %s!' ), $args['user']->display_name );
	?>
</p>

<p>
	<?php
	// translators: %s is a username.
	printf( __( 'Good news, %s has accepted your friend request.', 'friends' ), $args['friend_user']->display_name );
	?>
</p>

<p>
	<?php
	// translators: %s is a URL.
	printf( __( 'Go to your <a href=%s>friends page</a> and look at their posts.', 'friends' ), site_url( $args['friend_user']->get_local_friends_page_url() . '/' ) );
	?>
</p>

<p>
	<?php
		// translators: %s is a site name.
		echo wp_kses( sprintf( __( 'This notification was sent by the Friends plugin on %s.', 'friends' ), '<a href="' . esc_attr( site_url() ) . '">' . ( is_multisite() ? get_site_option( 'site_name' ) : get_option( 'blogname' ) ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
	?>
</p>
<?php include __DIR__ . '/footer.php'; ?>
