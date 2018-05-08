<form method="post">
	<?php wp_nonce_field( 'send-friend-request' ); ?>
	<p><?php esc_html_e( "This will send a friend request to the WordPress site you can enter below. If the other site doesn't have friends plugin installed, you'll be subscribed to that site.", 'friends' ); ?></p>
	<label><?php esc_html_e( 'Site:' ); ?> <input type="text" autofocus name="site_url" value="<?php echo esc_attr( $site_url ); ?>" required placeholder="Enter your friend's WordPress URL" size="90" /></label>
	<button><?php echo esc_attr_x( 'Send Friend Request', 'button', 'friends' ); ?></button>
</form>
