<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" class="friends-post-inline">
	<input type="hidden" name="action" value="friends_publish" />
	<?php wp_nonce_field( 'friends_publish' ); ?>
	<textarea name="content" rows="5" cols="70" placeholder="<?php echo esc_attr( sprintf( __( 'What are you up to, %s?', 'friends' ), wp_get_current_user()->display_name ) ); ?>"></textarea><br/>
	<button>Post to your friends</button>
	<span style="margin-left: 2em"><input type="hidden" name="status" value="private" /> Published Privately</span>
</form>
