<?php
/**
 * Class Friends_NoticiationTest
 *
 * @package Friends
 */

/**
 * Test the Notifications
 */
class Friends_FeedTest extends WP_UnitTestCase {
	/**
	 * Current User ID
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * User ID of a friend at friend.local
	 *
	 * @var int
	 */
	private $friend_id;

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

		$this->friend_id = $this->factory->user->create(
			array(
				'user_login' => 'friend.local',
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
			)
		);
		$friends         = Friends::get_instance();

		if ( ! class_exists( 'SimplePie', false ) ) {
			spl_autoload_register( array( $friends->feed, 'wp_simplepie_autoload' ) );

			require_once __DIR__ . '/../lib/SimplePie.php';
		}
	}

	/**
	 * Test parsing a feed.
	 */
	public function test_parse_feed() {
		$file = new SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$user = new WP_User( $this->friend_id );

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_friend_feed( $user, $feed );
		$this->assertCount( 1, $new_items );

		$new_items = $friends->feed->process_friend_feed( $user, $feed );
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

		$user = new WP_User( $this->friend_id );

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_friend_feed( $user, $feed );
		$this->assertCount( 1, $new_items );

		$new_items = $friends->feed->process_friend_feed( $user, $feed );
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

		$user = new WP_User( $this->friend_id );

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_friend_feed( $user, $feed );
		$this->assertCount( 1, $new_items );

		$new_items = $friends->feed->process_friend_feed( $user, $feed );
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

		$user = new WP_User( $this->friend_id );

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_friend_feed( $user, $feed );
		$this->assertCount( 11, $new_items );

		$new_items = $friends->feed->process_friend_feed( $user, $feed );
		$this->assertCount( 0, $new_items );
	}
}
