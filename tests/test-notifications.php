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
				'user_email' => 'friend@friend.local',
				'role'       => 'friend',
			)
		);

		remove_filter( 'friends_send_mail', '__return_false' );
	}

	/**
	 * Test notifications of a new post.
	 */
	public function test_notify_new_post() {
		$that = $this;
		add_filter(
			'notify_user_about_friend_post',
			function( $do_send ) use ( $that ) {
				$that->assertTrue( $do_send );
				return $do_send;
			},
			10
		);
		add_filter(
			'friends_send_mail',
			function( $do_send, $to, $subject, $message, $headers ) use ( $that ) {
				// translators: %1$s is the site name, %2$s is the subject.
				$that->assertEquals( $subject, sprintf( _x( '[%1$s] %2$s', 'email subject', 'friends' ), 'friend.local', 'First Friend Post' ) );
				$that->assertEquals( $to, WP_TESTS_EMAIL );
				$that->assertTrue( $do_send );
				return false;
			},
			10,
			5
		);
		$friends = Friends::get_instance();

		if ( ! class_exists( 'SimplePie', false ) ) {
			spl_autoload_register( array( $friends->feed, 'wp_simplepie_autoload' ) );

			require_once __DIR__ . '/../lib/SimplePie.php';
		}
		update_option( 'siteurl', 'http://me.local' );

		$file = new SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$user = new WP_User( $this->friend_id );

		$friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
	}

	/**
	 * Test notifications of a new post.
	 */
	public function test_no_notify_new_post_single_setting() {
		$that = $this;
		add_filter(
			'notify_user_about_friend_post',
			function( $do_send ) use ( $that ) {
				$that->assertFalse( $do_send );
				return $do_send;
			},
			10
		);

		if ( ! class_exists( 'SimplePie', false ) ) {
			require_once( ABSPATH . WPINC . '/class-simplepie.php' );
		}
		update_option( 'siteurl', 'http://me.local' );

		$file = new SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );

		$feed = new SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$user = new WP_User( $this->friend_id );

		$test_user = get_user_by( 'email', WP_TESTS_EMAIL );
		update_user_option( $test_user->ID, 'friends_no_new_post_notification_' . $this->friend_id, true );

		$friends = Friends::get_instance();
		$friends->feed->process_friend_feed( $user, $feed, Friends::CPT );
	}

	/**
	 * Test notifications of a friend request.
	 */
	public function test_no_notify_friend_request() {
		$that = $this;
		add_filter(
			'notify_user_about_friend_request',
			function( $do_send ) use ( $that ) {
				$that->assertFalse( $do_send );
				return $do_send;
			},
			10
		);

		update_option( 'siteurl', 'http://me.local' );

		$test_user = get_user_by( 'email', WP_TESTS_EMAIL );
		update_user_option( $test_user->ID, 'friends_no_friend_request_notification', true );

		$me_id = $this->factory->user->create(
			array(
				'user_login' => 'me.local',
				'user_email' => 'me@me.local',
				'role'       => 'friend_request',
			)
		);
	}

	/**
	 * Test notifications of a friend request.
	 */
	public function test_notify_friend_request() {
		$that = $this;
		add_filter(
			'notify_user_about_friend_request',
			function( $do_send ) use ( $that ) {
				$that->assertTrue( $do_send );
				return $do_send;
			},
			10
		);
		add_filter(
			'friends_send_mail',
			function( $do_send, $to, $subject, $message, $headers ) use ( $that ) {
				// translators: %s is a user display name.
				$partial_subject = sprintf( __( '%s sent a Friend Request', 'friends' ), 'me.local' );
				// translators: %1$s is the site name, %2$s is the subject.
				$that->assertEquals( $subject, sprintf( _x( '[%1$s] %2$s', 'email subject', 'friends' ), defined( 'MULTISITE' ) && MULTISITE ? WP_TESTS_TITLE . ' Network' : WP_TESTS_TITLE, $partial_subject ) );
				$that->assertEquals( $to, WP_TESTS_EMAIL );
				$that->assertTrue( $do_send );
				return false;
			},
			10,
			5
		);

		update_option( 'siteurl', 'http://me.local' );

		$me_id = $this->factory->user->create(
			array(
				'user_login' => 'me.local',
				'user_email' => 'me@me.local',
				'role'       => 'friend_request',
			)
		);
	}

	/**
	 * Test notifications of an accepted friend request.
	 */
	public function test_notify_accepted_friend_request() {
		$that = $this;
		add_filter(
			'friends_send_mail',
			function( $do_send, $to, $subject, $message, $headers ) use ( $that ) {
				// translators: %s is a user display name.
				$partial_subject = sprintf( __( '%s accepted your Friend Request', 'friends' ), 'me.local' );
				// translators: %1$s is the site name, %2$s is the subject.
				$that->assertEquals( $subject, sprintf( _x( '[%1$s] %2$s', 'email subject', 'friends' ), defined( 'MULTISITE' ) && MULTISITE ? WP_TESTS_TITLE . ' Network' : WP_TESTS_TITLE, $partial_subject ) );
				$that->assertEquals( $to, WP_TESTS_EMAIL );
				$that->assertTrue( $do_send );
				return false;
			},
			10,
			5
		);

		update_option( 'siteurl', 'http://me.local' );

		$test_user = get_user_by( 'email', WP_TESTS_EMAIL );
		update_user_option( $test_user->ID, 'friends_no_friend_request_notification', true );

		$me_id = $this->factory->user->create(
			array(
				'user_login' => 'me.local',
				'user_email' => 'me@me.local',
				'role'       => 'friend_request',
			)
		);

		$me = new WP_User( $me_id );
		$me->set_role( 'friend' );
		do_action( 'notify_accepted_friend_request', $me );
	}
}
