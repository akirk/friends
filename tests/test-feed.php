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

		$this->friend_id = $this->factory->user->create(
			array(
				'user_login' => 'friend.local',
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
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
	 * Common code for testing parsing a feed.
	 *
	 * @param      SimplePie_File $file        The SimplePie_File.
	 * @param      int            $new_items1  Number of new items to be found in the first attempt.
	 * @param      int            $new_items2  Number of new items to be found in the second attempt.
	 */
	private function feed_parsing_test( SimplePie_File $file, $new_items1 = 1, $new_items2 = 0 ) {
		$parser = new Friends_Feed_Parser_SimplePie;

		$user = new Friend_User( $this->friend_id );
		$term = new WP_Term(
			(object) array(
				'url' => $user->user_url . '/feed/',
			)
		);
		$user_feed = new Friend_User_Feed( $term, $user );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed );
		$this->assertCount( $new_items1, $new_items );

		$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed );
		$this->assertCount( $new_items2, $new_items );
	}

	/**
	 * Test parsing a feed.
	 */
	public function test_parse_feed() {
		$this->feed_parsing_test( new SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' ) );
	}

	/**
	 * Test parsing a feed with ampersand URLs.
	 */
	public function test_parse_feed_with_url_ampersand() {
		$this->feed_parsing_test( new SimplePie_File( __DIR__ . '/data/friend-feed-url-ampersand.rss' ) );
	}

	/**
	 * Test parsing a feed with identical posts.
	 */
	public function test_parse_feed_with_identical_posts() {
		$this->feed_parsing_test( new SimplePie_File( __DIR__ . '/data/friend-feed-identical-posts.rss' ) );
	}

	/**
	 * Test parsing a feed with identical posts after the fold.
	 */
	public function test_parse_feed_with_identical_posts_after_fold() {
		$this->feed_parsing_test( new SimplePie_File( __DIR__ . '/data/friend-feed-identical-posts-after-fold.rss' ), 11 );
	}

	/**
	 * Fetch our own feed with a friend authentication.
	 */
	public function test_parse_own_feed_with_correct_friend_auth() {
		$friends = Friends::get_instance();
		$feed_url = $friends->access_control->append_auth( 'https://me.local/?feed=rss2', new Friend_User( $this->friend_id ) );
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
}
