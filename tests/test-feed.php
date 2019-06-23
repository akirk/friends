<?php
/**
 * Class Friends_FeedTest
 *
 * @package Friends
 */

/**
 * Test the Feed
 */
class Friends_FeedTest extends WP_UnitTestCase {
	/**
	 * User ID of a friend at friend.local
	 *
	 * @var int
	 */
	private $friend_id;

	/**
	 * Token for the friend at friend.local
	 *
	 * @var int
	 */
	private $friends_in_token;

	/**
	 * Setup the unit tests.
	 */
	public function setUp() {
		parent::setUp();

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

		$this->friend_id        = $this->factory->user->create(
			array(
				'user_login' => 'friend.local',
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
			)
		);
		$friends                = Friends::get_instance();
		$this->friends_in_token = sha1( wp_generate_password( 256 ) );
		if ( update_user_option( $this->friend_id, 'friends_in_token', $this->friends_in_token ) ) {
			update_option( 'friends_in_token_' . $this->friends_in_token, $this->friend_id );
		}

		if ( ! class_exists( 'SimplePie', false ) ) {
			spl_autoload_register( array( $friends->feed, 'wp_simplepie_autoload' ) );

			require_once __DIR__ . '/../lib/SimplePie.php';
		}
	}

	/**
	 * From the core unit tests, a way to get the RSS2 feed. Modified to make sure any accidential output is included.
	 *
	 * @param  string $url The URL that would appear to WordPress to have been called.
	 * @return string      The returned feed.
	 *
	 * @throws Exception Relaying any exception.
	 */
	function get_rss2( $url ) {
		ob_start();
		$this->go_to( $url );
		// Nasty hack! In the future it would better to leverage do_feed( 'rss2' ).
		global $post;
		try {
			require( ABSPATH . 'wp-includes/feed-rss2.php' );
			$out = ob_get_clean();
		} catch ( Exception $e ) {
			$out = ob_get_clean();
			throw($e);
		}
		return $out;
	}

	/**
	 * Test parsing a feed.
	 */
	public function test_parse_feed() {
		$file = new SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$user = new Friend_User( $this->friend_id );

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
		$this->assertCount( 1, $new_items );

		$new_items = $friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
		$this->assertCount( 0, $new_items );
	}

	/**
	 * Test parsing a feed with ampersand URLs.
	 */
	public function test_parse_feed_with_url_ampersand() {
		$file = new SimplePie_File( __DIR__ . '/data/friend-feed-url-ampersand.rss' );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$user = new Friend_User( $this->friend_id );

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
		$this->assertCount( 1, $new_items );

		$new_items = $friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
		$this->assertCount( 0, $new_items );
	}

	/**
	 * Test parsing a feed with identical posts.
	 */
	public function test_parse_feed_with_identical_posts() {
		$file = new SimplePie_File( __DIR__ . '/data/friend-feed-identical-posts.rss' );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$user = new Friend_User( $this->friend_id );

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
		$this->assertCount( 1, $new_items );

		$new_items = $friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
		$this->assertCount( 0, $new_items );
	}

	/**
	 * Test parsing a feed with identical posts after the fold.
	 */
	public function test_parse_feed_with_identical_posts_after_fold() {
		$file = new SimplePie_File( __DIR__ . '/data/friend-feed-identical-posts-after-fold.rss' );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$user = new Friend_User( $this->friend_id );

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
		$this->assertCount( 11, $new_items );

		$new_items = $friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
		$this->assertCount( 0, $new_items );
	}

	/**
	 * Fetch our own feed with a friend authentication.
	 */
	public function test_parse_own_feed_with_correct_friend_auth() {
		$feed = $this->get_rss2( 'https://me.local/?feed=rss2&friend=' . $this->friends_in_token );
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
}
