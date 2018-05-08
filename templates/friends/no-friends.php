<?php
/**
 * This template is shown to people who visit /friends/ but don't have any friends yet.
 *
 * @package Friends
 */

?>
<?php get_header(); ?>
	<h1>
	<?php
	/* translators: %s is a username. */
	printf( __( 'Hi, %s!', 'friends' ), wp_get_current_user()->user_login );
	?>
	</h1>

	<p>
	<?php
	 /* translators: %s is URL. */
	printf( __( "You don't have any friends yet! You can add friends by <a href=%s>sending friend requests</a>.", 'friends' ), admin_url( 'admin.php?page=send-friend-request' ) );
	?>
	</p>
<?php get_footer(); ?>
