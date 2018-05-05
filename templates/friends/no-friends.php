<?php get_header(); ?>
	<h1><?php printf( __( 'Hi, %s!', 'friends' ), wp_get_current_user()->user_login ); ?></h1>

	<p><?php printf( __( "You don't have any friends yet! You can add friends by <a href=%s>sending friend requests</a>.", 'friends' ), admin_url( 'admin.php?page=send-friend-request' ) ); ?></p>
<?php get_footer(); ?>
