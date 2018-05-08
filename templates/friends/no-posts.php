<?php
/**
 * This template is shown on /friends/ when there are no posts.
 *
 * @package Friends
 */

?><?php include __DIR__ . '/../header.php'; ?>
	<h1>
	<?php
	/* translators: %s is a username. */
	printf( __( 'Hi, %s!', 'friends' ), wp_get_current_user()->user_login );
	?>
	</h1>

	<p><?php _e( "Your friends haven't posted anything yet!", 'friends' ); ?></p>
<?php include __DIR__ . '/../footer.php'; ?>
