<?php
/**
 * Regression tests for outgoing direct message mentions.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Exercise the renderer used for both delivery and Mastodon API responses.
 */
class ActivityPub_Transformer_Message_Test extends \WP_UnitTestCase {
	/**
	 * Messages with existing links, plain mentions, and protected HTML.
	 *
	 * @return array Test cases.
	 */
	public function mention_content_provider() {
		$link = '<a rel="mention" class="u-url mention" href="https://skribeworks.com/@skribe">@skribe</a>';
		return array(
			'original report'                            => array(
				'<a href="https://skribeworks.com/@skribe">@skribe</a> DM test',
				'<a href="https://skribeworks.com/@skribe">@skribe</a> DM test',
			),
			'existing mention with reordered attributes' => array(
				'<a href="https://skribeworks.com/@skribe" class="mention u-url" rel="mention">@skribe</a> hi',
				'<a href="https://skribeworks.com/@skribe" class="mention u-url" rel="mention">@skribe</a> hi',
			),
			'plain short mention'                        => array( '@skribe hi', $link . ' hi' ),
			'short mention before punctuation'           => array( 'Hello @skribe.', 'Hello ' . $link . '.' ),
			'full mention before punctuation'            => array( 'Hello @skribe@skribeworks.com.', 'Hello ' . $link . '.' ),
			'plain full mention'                         => array( '@skribe@skribeworks.com hi', $link . ' hi' ),
			'no mention'                                 => array( 'hi', $link . ' hi' ),
			'different username'                         => array( '@skribetest hi', $link . ' @skribetest hi' ),
			'different domain'                           => array( '@skribe@elsewhere.example hi', $link . ' @skribe@elsewhere.example hi' ),
			'handle in another link'                     => array(
				'<a href="https://elsewhere.example/@skribe">@skribe</a> hi',
				$link . ' <a href="https://elsewhere.example/@skribe">@skribe</a> hi',
			),
			'handle in an attribute'                     => array(
				'<span title="@skribe">hi</span>',
				$link . ' <span title="@skribe">hi</span>',
			),
			'handle in code'                             => array( '<code>@skribe</code> hi', $link . ' <code>@skribe</code> hi' ),
		);
	}

	/**
	 * The renderer must never insert a mention link inside existing HTML.
	 *
	 * @dataProvider mention_content_provider
	 * @param string $content Submitted content.
	 * @param string $expected Expected rendered content without paragraph wrapper.
	 */
	public function test_rendered_mentions( $content, $expected ) {
		require_once dirname( __DIR__ ) . '/feed-parsers/activitypub/class-activitypub-transformer-message.php';
		$post        = new \WP_Post(
			(object) array(
				'ID'           => 0,
				'post_content' => $content,
			)
		);
		$transformer = new class( $post ) extends ActivityPub_Transformer_Message {
			/**
			 * Supply a recipient without a remote WebFinger lookup.
			 *
			 * @return array Recipient handles and actor URLs.
			 */
			protected function get_mentions() {
				return array( '@skribe@skribeworks.com' => 'https://skribeworks.com/@skribe' );
			}
		};
		$this->assertSame( '<p>' . $expected . '</p>', $transformer->get_rendered_content() );
	}
}
