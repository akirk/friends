<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" class="friends-request-inline">
	<?php wp_nonce_field( 'friends_request' ); ?>
	<input type="hidden" name="action" value="friends_request" />
	<p><?php _e( "This will send a friend request to the WordPress site you can enter below. If the other site doesn't have friends plugin installed, you'll be subscribed to that site.", 'friends' ); ?></p>
	<label>Site: <input type="text" autofocus name="site_url" value="<?php if ( ! empty( $_GET['url'] ) ) echo esc_attr( $_GET['url'] ); ?>" required placeholder="Enter your friend's WordPress URL" size="90" /></label>
	<button><?php _e( 'Initiate Friend Request', 'friends' ); ?></button>
</form>
