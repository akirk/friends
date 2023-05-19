<?php
/**
 * Class Friends_NotificationTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Notifications
 */
class NotificationTest extends \WP_UnitTestCase {

	/**
	 * User ID of a friend at friend.local
	 *
	 * @var int
	 */
	private $friend_id;

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
			function( $do_send, $to, $subject ) use ( $that ) {
				// translators: %1$s is the site name, %2$s is the subject.
				$that->assertEquals( $subject, sprintf( _x( '[%1$s] %2$s', 'email subject', 'friends' ), 'friend.local', 'First Friend Post' ) );
				$that->assertEquals( $to, \WP_TESTS_EMAIL );
				$that->assertTrue( $do_send );
				return false;
			},
			10,
			3
		);
		$friends = Friends::get_instance();
		fetch_feed( null ); // load SimplePie.
		update_option( 'home', 'http://me.local' );

		$file = new \SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );
		$parser = new Feed_Parser_SimplePie;

		$user = new User( $this->friend_id );
		$term = new \WP_Term(
			(object) array(
				'term_id' => 100,
				'url'     => $user->user_url . '/feed/',
			)
		);
		$user_feed = new User_Feed( $term, $user );

		$feed = new \SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed );
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
		update_option( 'home', 'http://me.local' );

		$file = new \SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );
		$parser = new Feed_Parser_SimplePie;

		$user = new User( $this->friend_id );
		$term = new \WP_Term(
			(object) array(
				'term_id' => 100,
				'url'     => $user->user_url . '/feed/',
			)
		);
		$user_feed = new User_Feed( $term, $user );

		$test_user = get_user_by( 'email', \WP_TESTS_EMAIL );
		$this->assertInstanceOf( 'WP_User', $test_user );
		update_user_option( $test_user->ID, 'friends_no_new_post_notification_' . $this->friend_id, true );

		$feed = new \SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed );
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

		update_option( 'home', 'http://me.local' );

		$test_user = get_user_by( 'email', \WP_TESTS_EMAIL );
		$this->assertInstanceOf( 'WP_User', $test_user );
		update_user_option( $test_user->ID, 'friends_no_friend_request_notification', true );

		$this->factory->user->create(
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
			function( $do_send, $to, $subject ) use ( $that ) {
				// translators: %s is a user display name.
				$partial_subject = sprintf( __( '%s sent a Friend Request', 'friends' ), 'me.local' );
				// translators: %1$s is the site name, %2$s is the subject.
				$that->assertEquals( $subject, sprintf( _x( '[%1$s] %2$s', 'email subject', 'friends' ), \WP_TESTS_TITLE, $partial_subject ) );
				$that->assertEquals( $to, \WP_TESTS_EMAIL );
				$that->assertTrue( $do_send );
				return false;
			},
			10,
			3
		);

		update_option( 'home', 'http://me.local' );

		$this->factory->user->create(
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
			function( $do_send, $to, $subject ) use ( $that ) {
				// translators: %s is a user display name.
				$partial_subject = sprintf( __( '%s accepted your Friend Request', 'friends' ), 'me.local' );
				// translators: %1$s is the site name, %2$s is the subject.
				$that->assertEquals( $subject, sprintf( _x( '[%1$s] %2$s', 'email subject', 'friends' ), \WP_TESTS_TITLE, $partial_subject ) );
				$that->assertEquals( $to, \WP_TESTS_EMAIL );
				$that->assertTrue( $do_send );
				return false;
			},
			10,
			3
		);

		update_option( 'home', 'http://me.local' );

		$test_user = get_user_by( 'email', \WP_TESTS_EMAIL );
		update_user_option( $test_user->ID, 'friends_no_friend_request_notification', true );

		$me_id = $this->factory->user->create(
			array(
				'user_login' => 'me.local',
				'user_email' => 'me@me.local',
				'role'       => 'friend_request',
			)
		);

		$me = new User( $me_id );
		$me->set_role( 'friend' );
		do_action( 'notify_accepted_friend_request', $me );
	}

	/**
	 * Test notifications of a keyword in a post.
	 */
	public function test_notify_keyword() {
		$that = $this;
		add_filter(
			'notify_user_about_keyword_post',
			function( $do_send ) use ( $that ) {
				$that->assertTrue( $do_send );
				return $do_send;
			},
			10
		);
		add_filter(
			'notify_user_about_friend_post',
			function( $do_send ) use ( $that ) {
				// This should be never reached because the notification above happened.
				$that->assertTrue( false );
				return $do_send;
			},
			10
		);
		$keyword = 'private';
		add_filter(
			'friends_send_mail',
			function( $do_send, $to, $subject ) use ( $that, $keyword ) {
				// translators: %s is a keyword string specified by the user.
				$keyword_title = sprintf( __( 'Keyword matched: %s', 'friends' ), $keyword );
				// translators: %1$s is the site name, %2$s is the subject.
				$that->assertEquals( $subject, sprintf( _x( '[%1$s] %2$s', 'email subject', 'friends' ), 'friend.local', $keyword_title ) );
				$that->assertEquals( $to, \WP_TESTS_EMAIL );
				$that->assertTrue( $do_send );
				return false;
			},
			10,
			3
		);
		$friends = Friends::get_instance();
		fetch_feed( null ); // load SimplePie.
		update_option( 'home', 'http://me.local' );
		update_option(
			'friends_notification_keywords',
			array(
				array(
					'enabled' => true,
					'keyword' => $keyword,
				),
			)
		);

		$file = new \SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );
		$parser = new Feed_Parser_SimplePie;

		$user = new User( $this->friend_id );
		$term = new \WP_Term(
			(object) array(
				'term_id' => 100,
				'url'     => $user->user_url . '/feed/',
			)
		);
		$user_feed = new User_Feed( $term, $user );

		$feed = new \SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$friends   = Friends::get_instance();
		$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed, Friends::CPT );
	}
}
