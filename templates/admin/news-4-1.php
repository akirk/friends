<?php
/**
 * This template contains the Friends 4.1 news entry.
 *
 * @package Friends
 */

?>
<div class="friends-news-entry-body">
	<h2><?php esc_html_e( 'Friends 4.1: Twitter Theme, Browser Extension & AI Assistant', 'friends' ); ?></h2>

	<div class="friends-news-changes">
		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Twitter Theme', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'A Twitter-inspired frontend theme with a familiar three-column layout, an inline compose box, and a right-column widget area. Pick it from Friends → Settings → Theme.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Browser Extension', 'friends' ); ?></h4>
			<p>
				<?php esc_html_e( 'Subscribe to any site you are visiting with one click by installing the Friends browser extension for Chrome or Firefox. If the extension needs an API key, it will show a link that takes you directly to Friends → Browser Extension.', 'friends' ); ?>
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
			<h4><?php esc_html_e( 'AI Assistant Abilities', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'Friends now registers WordPress Abilities so AI Assistant can help manage subscriptions, feeds, feed refreshes, and cached timeline items.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'ActivityPub Safety', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'Cached friend posts are no longer federated by the ActivityPub plugin, so remote content stored in your reader is not announced as your site\'s own activity.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Theme Polish', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'The shipped frontend themes now show and pad welcome content correctly, making first-run screens feel less abrupt.', 'friends' ); ?></p>
		</div>
	</div>
</div>
