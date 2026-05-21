<?php
/**
 * This template contains the Friends 4.1 news entry.
 *
 * @package Friends
 */

?>
<div class="friends-news-entry-body">
	<h2><?php esc_html_e( 'Friends 4.1: Add Friend Frontend, Twitter Theme & Browser Extension', 'friends' ); ?></h2>

	<div class="friends-news-changes">
		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Add Friend on the Frontend', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'Add new subscriptions from /friends/add-friend/ without opening wp-admin. You can review discovered feeds from a single URL, a Mastodon-style handle, pasted URL lists, pasted OPML, or uploaded OPML files, then preview items and follow selected entries in bulk.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Browser Extension', 'friends' ); ?></h4>
			<p>
				<?php esc_html_e( 'Subscribe to any site you are visiting with one click by installing the Friends browser extension for Chrome or Firefox.', 'friends' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'The same connection lets companion plugins add quick actions to the extension popup, such as saving the current page to a collection or sending it to your eReader.', 'friends' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="https://chromewebstore.google.com/detail/friends/ledbghpaplkpclndlommpbokndieflhl" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Install for Chrome', 'friends' ); ?>
				</a>
				<a class="button button-primary" href="https://addons.mozilla.org/en-US/firefox/addon/wpfriends/" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Install for Firefox', 'friends' ); ?>
				</a>
			</p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Twitter Theme', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'A Twitter-inspired frontend theme with a familiar three-column layout, an inline compose box, and a right-column widget area. Pick it from Friends → Settings → Theme.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'AI Assistant Abilities', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'Friends now registers WordPress Abilities so AI Assistant can help manage subscriptions, feeds, feed refreshes, and cached timeline items.', 'friends' ); ?></p>
		</div>
	</div>
</div>
