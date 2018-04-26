<?php include __DIR__ . '/../header.php'; ?>
	<h1><?php printf( __( 'Hi, %s!', 'friends' ), wp_get_current_user()->user_login ); ?></h1>

	<p><?php _e( "Your friends haven't posted anything yet!", 'friends' ); ?></p>
<?php include __DIR__ . '/../footer.php'; ?>
