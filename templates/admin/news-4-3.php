<?php
/**
 * This template contains the Friends 4.3 news entry.
 *
 * @package Friends
 */

?>
<div class="friends-news-entry-body">
	<h2><?php esc_html_e( 'Friends 4.3: Link Previews', 'friends' ); ?></h2>

	<div class="friends-news-changes">
		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Link Previews', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'When someone you follow shares a news article, their post arrives as plain text and a bare link. The preview card you see on Mastodon is rendered by their server and is not part of what gets sent across the fediverse, so it never reached your feed.', 'friends' ); ?></p>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						// translators: %s is a link to the friends page.
						__( 'Friends now builds that card itself: %s shows the linked page\'s title, description and image below the post, in every theme.', 'friends' ),
						'<a href="' . esc_url( home_url( '/friends/' ) ) . '">' . esc_html__( 'your feed', 'friends' ) . '</a>'
					),
					array(
						'a' => array(
							'href' => true,
						),
					)
				);
				?>
			</p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Only Where It Adds Something', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'A card appears when a status contains a link but no image or video of its own, so posts that already show a photo are left alone. Mentions, hashtags and links back to the poster\'s own site are skipped.', 'friends' ); ?></p>
			<p><?php esc_html_e( 'The linked page is downloaded in the background after a post arrives, never while you wait for a page to load, and the result is stored with the post so each link is only fetched once.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Your Call', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'Showing a preview means your site contacts the linked website. If you\'d rather it didn\'t, you can turn link previews off entirely — no cards are shown and no requests are made.', 'friends' ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=friends-settings' ) ); ?>">
					<?php esc_html_e( 'Go to Settings', 'friends' ); ?>
				</a>
			</p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Direct Messages in Mastodon Apps', 'friends' ); ?></h4>
			<?php
			if ( class_exists( 'Enable_Mastodon_Apps\Mastodon_API' ) ) {
				$mastodon_apps_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=enable-mastodon-apps' ) ) . '">' . esc_html__( 'Enable Mastodon Apps', 'friends' ) . '</a>';
			} else {
				$mastodon_apps_link = '<a href="' . esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=enable-mastodon-apps&TB_iframe=true' ) ) . '" class="thickbox open-plugin-details-modal install-now" target="_blank">' . esc_html__( 'Enable Mastodon Apps', 'friends' ) . '</a>';
			}
			?>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						// translators: %s is a link to the Enable Mastodon Apps plugin.
						__( 'Incoming direct messages now raise a notification in Mastodon apps connected through %s, so a new message reaches you there instead of waiting to be discovered.', 'friends' ),
						$mastodon_apps_link
					),
					array(
						'a' => array(
							'href'   => true,
							'class'  => true,
							'target' => true,
						),
					)
				);
				?>
			</p>
		</div>
	</div>
</div>
