<?php
/**
 * Class Friends_APITest
 *
 * @package Friends
 */

/**
 * Test the Notifications
 */
class Friends_APITest extends WP_UnitTestCase {
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
		register_post_type( 'photo' );
		Friends_API::register_post_type( 'photo' );

		$this->factory->post->create(
			array(
				'post_type'     => 'photo',
				'post_title'    => 'First Friend Photo',
				'post_date_gmt' => '2018-05-03 10:00:00',
				'post_status'   => 'private',
			)
		);

		$this->factory->post->create(
			array(
				'post_type'     => 'photo',
				'post_title'    => 'Public Friend Photo',
				'post_date_gmt' => '2018-05-04 10:00:00',
				'post_status'   => 'publish',
			)
		);

		$this->user_id = $this->factory->user->create(
			array(
				'user_login' => 'me.local',
				'user_email' => 'me@example.org',
				'role'       => 'administrator',
				'user_url'   => 'https://me.local',
			)
		);

		$this->friend_id        = $this->factory->user->create(
			array(
				'user_login' => 'friend.local',
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
				'user_url'   => 'https://friend.local',
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
		require_once __DIR__ . '/class-local-feed-fetcher.php';
		add_action( 'wp_feed_options', array( $this, 'wp_feed_options' ), 100, 2 );
		add_filter( 'local_fetch_feed', array( $this, 'local_fetch_feed' ), 100, 2 );
	}

	/**
	 * Clean up after unit tests
	 */
	public function tearDown() {
		parent::tearDown();
		unregister_post_type( 'photo' );
		Friends_API::unregister_post_type( 'photo' );
	}

	/**
	 * Simulate that a feed was retrieved
	 *
	 * @param  Local_Feed_Fetcher $file The class that SimplePie asked to retrieve the content.
	 * @return Local_Feed_Fetcher The class that has the content set to its properties.
	 *
	 * @throws Exception  This might be triggered by feed-rss2.
	 */
	public function local_fetch_feed( $file ) {
		ob_start();
		$this->go_to( $file->url );
		// Nasty hack! In the future it would better to leverage do_feed( 'rss2' ).
		global $post;
		try {
			require( ABSPATH . 'wp-includes/feed-rss2.php' );
			$out = ob_get_clean();
		} catch ( Exception $e ) {
			$out = ob_get_clean();
			throw($e);
		}
		$file->body = $out;
		return $file;
	}

	/**
	 * Configure feed downloading options
	 *
	 * @param  SimplePie $feed The SimplePie object.
	 * @param  string    $url  The URL to fetch.
	 */
	public function wp_feed_options( $feed, $url ) {
		$feed->enable_cache( false );
		$feed->set_file_class( 'Local_Feed_Fetcher' );
	}

	/**
	 * Test getting your friends posts via RSS.
	 */
	public function test_get_non_friend_posts() {
		$friend_user = new Friend_User( $this->friend_id );
		$posts = $friend_user->retrieve_posts();
		$this->assertArrayHasKey( 'post', $posts );
		$this->assertCount( 1, $posts['post'] );
	}

	/**
	 * Test getting your friends posts via RSS.
	 */
	public function test_get_friend_posts() {
		wp_set_current_user( $this->user_id );
		$friend_user = new Friend_User( $this->friend_id );
		$posts = $friend_user->retrieve_posts();
		$this->assertArrayHasKey( 'post', $posts );
		$this->assertCount( 2, $posts['post'] );
	}

	/**
	 * Test getting your friends posts via RSS.
	 */
	public function test_get_non_friend_posts_photos() {
		$friend_user = new Friend_User( $this->friend_id );
		$posts = $friend_user->retrieve_posts();
		$this->assertArrayHasKey( 'post', $posts );
		$this->assertArrayHasKey( 'photo', $posts );
		$this->assertCount( 1, $posts['post'] );
		$this->assertCount( 1, $posts['photo'] );
	}

	/**
	 * Test getting your friends posts via RSS.
	 */
	public function test_get_friend_posts_photos() {
		wp_set_current_user( $this->user_id );
		$friend_user = new Friend_User( $this->friend_id );
		$posts = $friend_user->retrieve_posts();
		$this->assertArrayHasKey( 'post', $posts );
		$this->assertArrayHasKey( 'photo', $posts );
		$this->assertCount( 2, $posts['post'] );
		$this->assertCount( 2, $posts['photo'] );
	}
}
