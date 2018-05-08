<?php
/**
 * This template is shown to people who visit /friends/ but are logged out.
 *
 * @package Friends
 */

?>
<?php get_header(); ?>
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
<?php get_footer(); ?>
