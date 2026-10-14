<?php
/**
 * This template contains the Friends 4.4 news entry.
 *
 * @package Friends
 */

?>
<div class="friends-news-entry-body">
	<h2><?php esc_html_e( 'Friends 4.4: Content Warnings', 'friends' ); ?></h2>

	<div class="friends-news-changes">
		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Content Warnings', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'Posts and direct messages with a content warning now show the warning first, with the content hidden until you choose Show content. This works across the Friends themes, so you can decide when to read a post or message.', 'friends' ); ?></p>
			<p><?php esc_html_e( 'When writing a direct message, select the content warning checkbox beside Send to add an optional warning.', 'friends' ); ?></p>
			<p><?php esc_html_e( 'Warnings are also preserved when sending and receiving direct messages through connected Mastodon apps using the Enable Mastodon Apps plugin.', 'friends' ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( home_url( '/friends/messages/' ) ); ?>">
					<?php esc_html_e( 'Go to Messages', 'friends' ); ?>
				</a>
			</p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'More Reliable Feed Imports', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'When feed imports run at the same time, the same item is no longer stored twice. Feed URLs containing ampersands are also preserved correctly on newer WordPress versions, keeping links intact and avoiding duplicate posts.', 'friends' ); ?></p>
		</div>
	</div>
</div>
