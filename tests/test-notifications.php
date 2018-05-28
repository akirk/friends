<?php
/**
 * Class Friends_NoticiationTest
 *
 * @package Friends
 */

/**
 * Test the Notifications
 */
class Friends_NotificationTest extends WP_UnitTestCase {
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
	 * User ID of a friend at me.local
	 *
	 * @var int
	 */
	private $me_id;

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

		// Usually these users would be on two different sites.
		// For purposes of unit testing we're doing this all on one site.
		$this->friend_id = $this->factory->user->create(
			array(
				'user_login' => 'friend.local',
				'user_email' => 'friend@friend.local',
				'role'       => 'friend',
			)
		);

		$this->me_id = $this->factory->user->create(
			array(
				'user_login' => 'me.local',
				'role'       => 'friend',
			)
		);

		$token = sha1( wp_generate_password( 256 ) );
		update_user_option( $this->me_id, 'friends_out_token', $token );
		update_user_option( $this->friend_id, 'friends_in_token', $token );

		$token = sha1( wp_generate_password( 256 ) );
		update_user_option( $this->friend_id, 'friends_out_token', $token );
		update_user_option( $this->me_id, 'friends_in_token', $token );
	}

	/**
	 * Test notifications of a new post.
	 */
	public function test_notify_new_post() {
		$that = $this;
		add_filter(
			'notify_user_about_friend_post', function( $do_send ) use ( $that ) {
				$that->assertTrue( $do_send );
				return $do_send;
			}, 10
		);
		add_filter(
			'friends_send_mail', function( $do_send, $to, $subject, $message, $headers ) use ( $that ) {
				$that->assertEquals( $subject, sprintf( '[%s] New Friend Post: %s', WP_TESTS_TITLE, 'First Friend Post' ) );
				$that->assertEquals( $to, WP_TESTS_EMAIL );
				$that->assertTrue( $do_send );
				return false;
			}, 10, 5
		);

		if ( ! class_exists( 'SimplePie', false ) ) {
			require_once( ABSPATH . WPINC . '/class-simplepie.php' );
		}
		update_option( 'siteurl', 'http://friend.local' );

		$file = new SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$user = new WP_User( $this->me_id );

		$friends = Friends::get_instance();
		$friends->feed->process_friend_feed( $user, $feed );
	}

	/**
	 * Test notifications of a new post.
	 */
	public function test_no_notify_new_post() {
		$that = $this;
		add_filter(
			'notify_user_about_friend_post', function( $do_send ) use ( $that ) {
				$that->assertFalse( $do_send );
				return $do_send;
			}, 10
		);

		if ( ! class_exists( 'SimplePie', false ) ) {
			require_once( ABSPATH . WPINC . '/class-simplepie.php' );
		}
		update_option( 'siteurl', 'http://friend.local' );

		$file = new SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$user = new WP_User( $this->me_id );

		$test_user = get_user_by( 'email', WP_TESTS_EMAIL );
		update_user_option( $test_user->ID, 'friends_no_new_post_notification_' . $this->me_id, true );

		$friends = Friends::get_instance();
		$friends->feed->process_friend_feed( $user, $feed );
	}
}
