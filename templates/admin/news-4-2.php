<?php
/**
 * This template contains the Friends 4.2 news entry.
 *
 * @package Friends
 */

?>
<div class="friends-news-entry-body">
	<h2><?php esc_html_e( 'Friends 4.2: Direct Messages', 'friends' ); ?></h2>

	<div class="friends-news-changes">
		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Direct Messages Page', 'friends' ); ?></h4>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						// translators: %s is the direct messages page link.
						__( 'Read and reply to direct messages from %s with a conversation list, chat-style thread view, live refresh, drafts, profile links, and support for messages from ActivityPub users you do not follow.', 'friends' ),
						'<a href="' . esc_url( home_url( '/friends/messages/' ) ) . '">' . esc_html__( 'the direct messages page', 'friends' ) . '</a>'
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
			<h4><?php esc_html_e( 'Custom Emoji in Actor Names', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'ActivityPub actor names now render custom emoji in timelines, profiles, and boosted posts.', 'friends' ); ?></p>
		</div>
	</div>
</div>
