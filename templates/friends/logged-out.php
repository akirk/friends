<?php include __DIR__ . '/../header.php'; ?>
	<h1><?php _e( 'Hi, friend!', 'friends' ); ?></h1>

	<p><?php _e( 'Do we know each other? If you also have a WordPress, we could become friends.', 'friends' ); ?></p>
	<form id="friend-request">
		<label>
			<span>
				<?php _e( "What's your WordPress site URL?", 'friends' ); ?>
			</span>
			<input type="url" name="user_url" />
		</label>
		<button>Send Friend Request</button>
	</form>
<script type="text/javascript">
	jQuery( function( $ ) {
		$( '#friend-request' ).on( 'submit', function() {
			location.href = $( 'input[name=user_url]' ).val() + '/wp-admin/admin.php?page=send-friend-request&url=<?php echo esc_js( site_url() ); ?>';
			return false;
		})
		$( 'input[name=user_url]' ).on( 'change', function() {
			if ( ! this.value.match( /^https?:\/\// ) && this.value.match( /[a-z0-9-]+[.][a-z]+/i ) ) {
				this.value = 'http://' + this.value;
			}
		})
	});
</script>
<?php include __DIR__ . '/../footer.php'; ?>
