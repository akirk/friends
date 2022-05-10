<?php
/**
 * Class FeedTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Mock the time() function.
 *
 * @param      bool $set_time  set the time.
 *
 * @return     bool  The (non-moving) time.
 */
function time( $set_time = false ) {
	static $time;
	if ( ! isset( $time ) ) {
		$time = \time();
	}
	if ( $set_time ) {
		$time = strtotime( $set_time, $time );
	}
	return $time;
}

/**
 * Test the Feed
 */
class FeedTest extends \WP_UnitTestCase {
	/**
	 * User ID of a friend at friend.local
	 *
	 * @var int
	 */
	private $friend_id;

	/**
	 * User ID of a alex at alexander.kirk.at
	 *
	 * @var int
	 */
	private $alex;

	/**
	 * Token for the friend at friend.local
	 *
	 * @var int
	 */
	private $friends_in_token;

	/**
	 * Setup the unit tests.
	 */
	public function set_up() {
		parent::set_up();

		$this->factory->post->create(
			array(
				'post_type'     => 'post',
				'post_title'    => 'First Friend Post',
				'post_date_gmt' => '2018-05-01 10:00:00',
				'post_status'   => 'private',
			)
		);

		$this->factory->post->create(
			array(
				'post_type'     => 'post',
				'post_title'    => 'Public Friend Post',
				'post_date_gmt' => '2018-05-02 10:00:00',
				'post_status'   => 'publish',
			)
		);

		$this->friend_id = $this->factory->user->create(
			array(
				'user_login' => 'friend.local',
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
			)
		);

		$this->alex = $this->factory->user->create(
			array(
				'user_login' => 'alexander.kirk.at',
				'user_email' => 'alex@example.org',
				'role'       => 'subscription',
			)
		);

		update_option( 'home', 'http://friend.local' );
		$this->friends_in_token = wp_generate_password( 128, false );

		if ( update_user_option( $this->friend_id, 'friends_in_token', $this->friends_in_token ) ) {
			update_option( 'friends_in_token_' . $this->friends_in_token, $this->friend_id );
		}
		// We're using the same in and out token here since we're faking this on a single install.
		update_user_option( $this->friend_id, 'friends_out_token', $this->friends_in_token );

		fetch_feed( null ); // load SimplePie.
	}

	/**
	 * From the core unit tests, a way to get the RSS2 feed. Modified to make sure any accidential output is included.
	 *
	 * @param  string $url The URL that would appear to WordPress to have been called.
	 * @return string      The returned feed.
	 *
	 * @throws \Exception Relaying any exception.
	 */
	function get_rss2( $url ) {
		ob_start();
		$this->go_to( $url );
		// Nasty hack! In the future it would better to leverage do_feed( 'rss2' ).
		global $post;
		try {
			require( ABSPATH . 'wp-includes/feed-rss2.php' );
			$out = ob_get_clean();
		} catch ( \Exception $e ) {
			$out = ob_get_clean();
			throw($e);
		}
		return $out;
	}

	/**
	 * Common code for testing parsing a feed.
	 *
	 * @param      \SimplePie_File $file  A SimplePie File.
	 * @param      User            $user   The optional user, otherwise the friend_id will be used.
	 */
	private function feed_parsing_test( \SimplePie_File $file, User $user = null ) {
		$parser = new Feed_Parser_SimplePie;

		if ( is_null( $user ) ) {
			$user = new User( $this->friend_id );
		}
		$term = new \WP_Term(
			(object) array(
				'url' => $user->user_url . '/feed/',
			)
		);
		$user_feed = new User_Feed( $term, $user );

		$friends = Friends::get_instance();

		$feed = new \SimplePie();
		do {
			$feed->set_file( $file );
			$feed->init();

			$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed );
			$file = ( yield $new_items );
		} while ( $file );
	}

	/**
	 * Test parsing a feed.
	 */
	public function test_parse_feed() {
		$feed_1_private_post = new \SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );
		$feed_parsing_test = $this->feed_parsing_test( $feed_1_private_post );
		$this->assertCount( 1, $feed_parsing_test->current() );
		$feed_parsing_test->send( $feed_1_private_post );
		$this->assertCount( 0, $feed_parsing_test->current() );
	}

	/**
	 * Test parsing a feed with ampersand URLs.
	 */
	public function test_parse_feed_with_url_ampersand() {
		$feed_url_ampersand = new \SimplePie_File( __DIR__ . '/data/friend-feed-url-ampersand.rss' );
		$feed_parsing_test = $this->feed_parsing_test( $feed_url_ampersand );
		$this->assertCount( 1, $feed_parsing_test->current() );
		$feed_parsing_test->send( $feed_url_ampersand );
		$this->assertCount( 0, $feed_parsing_test->current() );
	}

	/**
	 * Test parsing a feed with identical posts.
	 */
	public function test_parse_feed_with_identical_posts() {
		$identical_posts = new \SimplePie_File( __DIR__ . '/data/friend-feed-identical-posts.rss' );
		$feed_parsing_test = $this->feed_parsing_test( $identical_posts );
		$this->assertCount( 1, $feed_parsing_test->current() );
		$feed_parsing_test->send( $identical_posts );
		$this->assertCount( 0, $feed_parsing_test->current() );
	}

	/**
	 * Test parsing a feed with identical posts after the fold.
	 */
	public function test_parse_feed_with_identical_posts_after_fold() {
		$identical_posts_after_fold = new \SimplePie_File( __DIR__ . '/data/friend-feed-identical-posts-after-fold.rss' );
		$feed_parsing_test = $this->feed_parsing_test( $identical_posts_after_fold );
		$this->assertCount( 11, $feed_parsing_test->current() );
		$feed_parsing_test->send( $identical_posts_after_fold );
		$this->assertCount( 0, $feed_parsing_test->current() );
	}

	/**
	 * Fetch our own feed with a friend authentication.
	 */
	public function test_parse_own_feed_with_correct_friend_auth() {
		$friends = Friends::get_instance();
		$feed_url = $friends->access_control->append_auth( 'https://me.local/?feed=rss2', new User( $this->friend_id ) );
		$this->assertContains( 'me=', $feed_url );
		$feed = $this->get_rss2( $feed_url );
		$xml  = xml_to_array( $feed );

		// Get all the <item> child elements of the <channel> element.
		$items = xml_find( $xml, 'rss', 'channel', 'item' );

		// This should include private posts.
		$this->assertCount( 2, $items );
	}

	/**
	 * Fetch our own feed with an invalid friend authentication.
	 */
	public function test_parse_own_feed_with_incorrect_friend_auth() {
		$feed = $this->get_rss2( 'https://me.local/?feed=rss2&friend=1' . $this->friends_in_token );
		$xml  = xml_to_array( $feed );

		// Get all the <item> child elements of the <channel> element.
		$items = xml_find( $xml, 'rss', 'channel', 'item' );

		// This should not include private posts.
		$this->assertCount( 1, $items );
	}

	/**
	 * Fetch our own feed with no friend authentication.
	 */
	public function test_parse_own_feed_with_no_friend_auth() {
		$feed = $this->get_rss2( 'https://me.local/?feed=rss2' );
		$xml  = xml_to_array( $feed );

		// Get all the <item> child elements of the <channel> element.
		$items = xml_find( $xml, 'rss', 'channel', 'item' );

		// This should not include private posts.
		$this->assertCount( 1, $items );
	}

	public function test_feed_item_revisions_modified_content() {
		$feed_1_public_post = new \SimplePie_File( __DIR__ . '/data/friend-feed-1-public-post.rss' );
		$feed_parsing_test = $this->feed_parsing_test( $feed_1_public_post, new User( $this->alex ) );

		$new_items = $feed_parsing_test->current();
		$this->assertCount( 1, $new_items );
		$post_id = $new_items[0];
		$this->assertCount( 0, wp_get_post_revisions( $post_id ) );

		$feed_parsing_test->send( $feed_1_public_post );
		$this->assertCount( 0, $feed_parsing_test->current() );
		$this->assertCount( 0, wp_get_post_revisions( $post_id ) );

		$feed_parsing_test->send( new \SimplePie_File( __DIR__ . '/data/friend-feed-1-public-post-modified-content.rss' ) );
		$this->assertCount( 0, $feed_parsing_test->current() );
		$this->assertCount( 1, wp_get_post_revisions( $post_id ) );
	}

	public function test_feed_item_revisions_modified_title() {
		$feed_1_public_post = new \SimplePie_File( __DIR__ . '/data/friend-feed-1-public-post.rss' );
		$feed_parsing_test = $this->feed_parsing_test( $feed_1_public_post, new User( $this->alex ) );

		$new_items = $feed_parsing_test->current();
		$this->assertCount( 1, $new_items );
		$post_id = $new_items[0];
		$this->assertCount( 0, wp_get_post_revisions( $post_id ) );

		$feed_parsing_test->send( $feed_1_public_post );
		$this->assertCount( 0, $feed_parsing_test->current() );
		$this->assertCount( 0, wp_get_post_revisions( $post_id ) );

		$feed_parsing_test->send( new \SimplePie_File( __DIR__ . '/data/friend-feed-1-public-post-modified-title.rss' ) );
		$this->assertCount( 0, $feed_parsing_test->current() );
		$this->assertCount( 1, wp_get_post_revisions( $post_id ) );
	}

	public function test_feed_item_revisions_modified_title_content() {
		$feed_1_public_post = new \SimplePie_File( __DIR__ . '/data/friend-feed-1-public-post.rss' );
		$feed_parsing_test = $this->feed_parsing_test( $feed_1_public_post, new User( $this->alex ) );

		$new_items = $feed_parsing_test->current();
		$this->assertCount( 1, $new_items );
		$post_id = $new_items[0];
		$this->assertCount( 0, wp_get_post_revisions( $post_id ) );

		$feed_parsing_test->send( $feed_1_public_post );
		$this->assertCount( 0, $feed_parsing_test->current() );
		$this->assertCount( 0, wp_get_post_revisions( $post_id ) );

		$feed_parsing_test->send( new \SimplePie_File( __DIR__ . '/data/friend-feed-1-public-post-modified-title.rss' ) );
		$this->assertCount( 0, $feed_parsing_test->current() );
		$this->assertCount( 1, wp_get_post_revisions( $post_id ) );

		$feed_parsing_test->send( new \SimplePie_File( __DIR__ . '/data/friend-feed-1-public-post-modified-content.rss' ) );
		$this->assertCount( 0, $feed_parsing_test->current() );
		$this->assertCount( 2, wp_get_post_revisions( $post_id ) );
	}

	private function get_user_feed( $user, $url, $interval, $modifier ) {
		$term = User_Feed::save(
			$user,
			$url,
			array(
				'active'   => true,
				'interval' => $interval,
				'modifier' => $modifier,
			)
		);

		return new User_Feed( $term, $user );
	}

	public function test_poll_interval() {
		$user = new User( $this->alex );

		// Stop the clock by using our mock function for the first time.
		time();

		// Linear.
		$user_feed = $this->get_user_feed( $user, 'http://example.org/1', 3600, 100 );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+1 hour' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+1 hour' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );

		// Linear, migrated.
		$user_feed = $this->get_user_feed( $user, 'http://example.org/2', 0, 0 );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+1 hour' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+1 hour' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );

		// +50% upon every call.
		$user_feed = $this->get_user_feed( $user, 'http://example.org/3', 3600, 150 );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+60 minutes' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+90 minutes' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+135 minutes' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );

		// Double upon every call.
		$user_feed = $this->get_user_feed( $user, 'http://example.org/4', 3600, 200 );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+1 hour' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+2 hour' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );
		$user_feed->was_polled();

		time( '+4 hour' );
		$this->assertEquals( gmdate( 'Y-m-d H:i:s', time() - User_Feed::INTERVAL_BACKTRACK ), $user_feed->get_next_poll() );
	}

	public function get_sorted_feeds( $feeds ) {
		usort(
			$feeds,
			function( $a, $b ) {
				$c = strcmp( $a->get_next_poll(), $b->get_next_poll() );
				if ( 0 !== $c ) {
					return $c;
				}

				return strcmp( $a->get_url(), $b->get_url() );
			}
		);

		return array_map(
			function ( $feed ) {
				// Remove http://example.org/.
				return intval( substr( $feed->get_url(), 19 ) );
			},
			$feeds
		);
	}

	public function test_due_feeds() {
		$user = new User( $this->alex );

		// Stop the clock by using our mock function for the first time.
		time();

		// 1. Linear.
		$this->get_user_feed( $user, 'http://example.org/1', 3600, 100 );
		// 2. Every two hours.
		$this->get_user_feed( $user, 'http://example.org/2', 7200, 100 );
		// 3. Almost upon every call (this is to change and verify the order).
		$this->get_user_feed( $user, 'http://example.org/3', 3600, 199 );

		$due_feeds = $user->get_due_feeds();
		$this->assertEquals( array( 1, 2, 3 ), $this->get_sorted_feeds( $due_feeds ) );
		foreach ( $due_feeds as $user_feed ) {
			$user_feed->was_polled();
		}

		time( '+1 hour' );

		$due_feeds = $user->get_due_feeds();
		$this->assertEquals( array( 1, 3 ), $this->get_sorted_feeds( $due_feeds ) );
		foreach ( $due_feeds as $user_feed ) {
			$user_feed->was_polled();
		}

		time( '+1 hour' );

		$due_feeds = $user->get_due_feeds();
		$this->assertEquals( array( 1, 2 ), $this->get_sorted_feeds( $due_feeds ) );
		foreach ( $due_feeds as $user_feed ) {
			$user_feed->was_polled();
		}

		time( '+1 hour' );

		$due_feeds = $user->get_due_feeds();
		$this->assertEquals( array( 3, 1 ), $this->get_sorted_feeds( $due_feeds ) );
		foreach ( $due_feeds as $user_feed ) {
			$user_feed->was_polled();
		}

		time( '+1 hour' );

		$due_feeds = $user->get_due_feeds();
		$this->assertEquals( array( 1, 2 ), $this->get_sorted_feeds( $due_feeds ) );
		foreach ( $due_feeds as $user_feed ) {
			$user_feed->was_polled();
		}

		time( '+1 hour' );

		$due_feeds = $user->get_due_feeds();
		$this->assertEquals( array( 1 ), $this->get_sorted_feeds( $due_feeds ) );
		foreach ( $due_feeds as $user_feed ) {
			$user_feed->was_polled();
		}

		time( '+1 hour' );

		$due_feeds = $user->get_due_feeds();
		$this->assertEquals( array( 1, 2 ), $this->get_sorted_feeds( $due_feeds ) );
		foreach ( $due_feeds as $user_feed ) {
			$user_feed->was_polled();
		}

		time( '+1 hour' );

		$due_feeds = $user->get_due_feeds();
		$this->assertEquals( array( 3, 1 ), $this->get_sorted_feeds( $due_feeds ) );
		foreach ( $due_feeds as $user_feed ) {
			$user_feed->was_polled();
		}

		time( '+1 hour' );

		$due_feeds = $user->get_due_feeds();
		$this->assertEquals( array( 1, 2 ), $this->get_sorted_feeds( $due_feeds ) );
		foreach ( $due_feeds as $user_feed ) {
			$user_feed->was_polled();
		}

	}

}
