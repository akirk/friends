<?php
/**
 * Class Friends_AbilitiesTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Abilities API integration callbacks.
 */
class AbilitiesTest extends \WP_UnitTestCase {
	/**
	 * Ability integration under test.
	 *
	 * @var Abilities
	 */
	private $abilities;

	/**
	 * Setup the unit tests.
	 */
	public function set_up() {
		parent::set_up();

		User_Query::$cache = false;
		$this->abilities = Friends::get_instance()->abilities;
		add_filter( 'friends_pre_check_url', '__return_true' );
	}

	/**
	 * Tear down the unit tests.
	 */
	public function tear_down() {
		remove_filter( 'friends_pre_check_url', '__return_true' );
		parent::tear_down();
	}

	/**
	 * Test that AI Assistant domain hints include Friends terms.
	 */
	public function test_register_ability_domain() {
		$domains = $this->abilities->register_ability_domain( array() );

		$this->assertArrayHasKey( 'friends', $domains );
		$this->assertStringContainsString( 'RSS', $domains['friends'] );
		$this->assertStringContainsString( 'ActivityPub', $domains['friends'] );
	}

	/**
	 * Test listing subscriptions includes feed details.
	 */
	public function test_list_subscriptions_includes_feeds() {
		$subscription = User::create( 'alice.example', 'subscription', 'https://alice.example/', 'Alice Example' );
		$subscription->subscribe(
			'https://alice.example/feed/',
			array(
				'parser'      => 'simplepie',
				'post-format' => 'status',
				'mime-type'   => 'application/rss+xml',
				'title'       => 'Alice Feed',
			)
		);

		$result = $this->abilities->list_subscriptions(
			array(
				'search' => 'alice',
				'limit'  => 5,
			)
		);

		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 'alice.example', $result['subscriptions'][0]['username'] );
		$this->assertSame( 'Alice Example', $result['subscriptions'][0]['name'] );
		$this->assertSame( 1, $result['subscriptions'][0]['active_feed_count'] );
		$this->assertSame( 'Alice Feed', $result['subscriptions'][0]['feeds'][0]['title'] );
	}

	/**
	 * Test adding a subscription with explicit feed URLs.
	 */
	public function test_add_subscription_with_explicit_feed_url() {
		$result = $this->abilities->add_subscription(
			array(
				'url'          => 'https://bob.example/',
				'feed_urls'    => array( 'https://bob.example/feed/' ),
				'username'     => 'bob.example',
				'display_name' => 'Bob Example',
				'refresh'      => false,
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( 'bob.example', $result['subscription']['username'] );
		$this->assertSame( 'Bob Example', $result['subscription']['name'] );
		$this->assertCount( 1, $result['activated_feeds'] );
		$this->assertSame( 'https://bob.example/feed/', $result['activated_feeds'][0]['url'] );
		$this->assertArrayNotHasKey( 'refresh', $result );
	}

	/**
	 * Test listing feed items returns subscription context.
	 */
	public function test_list_feed_items_returns_author_context() {
		$subscription = User::create( 'timeline.example', 'subscription', 'https://timeline.example/', 'Timeline Example' );
		$feed = $subscription->subscribe(
			'https://timeline.example/feed/',
			array(
				'parser'      => 'simplepie',
				'post-format' => 'status',
				'mime-type'   => 'application/rss+xml',
				'title'       => 'Timeline Feed',
			)
		);

		$post_id = $subscription->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_status'  => 'private',
				'post_title'   => 'A timeline item',
				'post_content' => 'This is cached friend post content.',
				'guid'         => 'https://timeline.example/posts/1',
			),
			true
		);
		wp_set_object_terms( $post_id, $feed->get_id(), User_Feed::TAXONOMY );

		$result = $this->abilities->list_feed_items(
			array(
				'subscription_id' => $subscription->ID,
				'limit'           => 10,
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 'timeline.example', $result['subscription']['username'] );
		$this->assertSame( 'A timeline item', $result['items'][0]['title'] );
		$this->assertSame( 'timeline.example', $result['items'][0]['author']['username'] );
		$this->assertStringContainsString( 'cached friend post content', $result['items'][0]['content_text'] );
	}

	/**
	 * Test refreshing one feed by feed ID.
	 */
	public function test_refresh_single_feed_by_id() {
		fetch_feed( '' ); // Load SimplePie.

		$friends = Friends::get_instance();
		$friends->feed->register_parser( 'abilities-local', new Feed_Parser_Local_File( $friends->feed ) );

		$subscription = User::create( 'refresh.example', 'subscription', 'https://refresh.example/', 'Refresh Example' );
		$feed = $subscription->subscribe(
			__DIR__ . '/data/friend-feed-1-public-post.rss',
			array(
				'parser'      => 'abilities-local',
				'post-format' => 'status',
				'mime-type'   => 'application/rss+xml',
				'title'       => 'Refresh Feed',
			)
		);

		$result = $this->abilities->refresh_feed(
			array(
				'feed_id' => $feed->get_id(),
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( 1, $result['feed_count'] );
		$this->assertSame( 1, $result['new_post_count'] );
		$this->assertCount( 1, $result['new_post_ids'] );
		$this->assertSame( 'refresh.example', $result['subscription']['username'] );
		$this->assertSame( $feed->get_id(), $result['refreshed_feeds'][0]['id'] );
		$this->assertSame( 'Refresh Feed', $result['refreshed_feeds'][0]['title'] );
	}
}
