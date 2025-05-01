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
		update_option( 'friends_enable_wp_friendships', true );
		$test_user = get_user_by( 'email', \WP_TESTS_EMAIL );
		update_option( 'friends_main_user_id', $test_user->ID );

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
				'email'      => 'friend@friend.local',
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
			function ( $do_send ) use ( $that ) {
				$that->assertTrue( $do_send );
				return $do_send;
			},
			10
		);
		add_filter(
			'friends_send_mail',
			function ( $do_send, $to, $subject ) use ( $that ) {
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
		$friends   = Friends::get_instance();
		$parser = new Feed_Parser_SimplePie( $friends->feed );

		$user = new User( $this->friend_id );
		$user_feed = User_Feed::save( $user, 'http://me.local/feed/', array( 'parser' => $parser::SLUG )  );

		$feed = new \SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed );
	}

	/**
	 * Test notifications of a new post.
	 */
	public function test_no_notify_new_post_single_setting() {
		$that = $this;
		add_filter(
			'notify_user_about_friend_post',
			function ( $do_send ) use ( $that ) {
				$that->assertFalse( $do_send );
				return $do_send;
			},
			10
		);

		if ( ! class_exists( 'SimplePie', false ) ) {
			require_once ABSPATH . WPINC . '/class-simplepie.php';
		}
		update_option( 'home', 'http://me.local' );

		$file = new \SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );
		$friends   = Friends::get_instance();
		$parser = new Feed_Parser_SimplePie( $friends->feed );

		$user = new User( $this->friend_id );
		add_filter( 'friends_pre_check_url', '__return_true' );
		$user_feed = User_Feed::save( $user, 'http://friend.local/feed/', array( 'parser' => $parser::SLUG ) );
		remove_filter( 'friends_pre_check_url', '__return_true' );

		$test_user = get_user_by( 'email', \WP_TESTS_EMAIL );
		$this->assertInstanceOf( 'WP_User', $test_user );
		update_user_option( $test_user->ID, 'friends_no_new_post_notification_' . $user->user_login, true );

		$feed = new \SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed );
	}

	/**
	 * Test notifications of a new post with specific feed parsers.
	 */
	public function test_no_notify_new_post_feed_parser_setting() {
		$that = $this;
		$assert = null;
		add_filter(
			'notify_user_about_friend_post',
			function ( $do_send ) use ( $that, &$assert ) {
				$that->assertEquals( $assert, $do_send );
				return $do_send;
			},
			10
		);

		if ( ! class_exists( 'SimplePie', false ) ) {
			require_once ABSPATH . WPINC . '/class-simplepie.php';
		}
		update_option( 'home', 'http://me.local' );

		$file = new \SimplePie_File( __DIR__ . '/data/friend-feed-1-private-post.rss' );
		$friends   = Friends::get_instance();
		$simple_pie_parser = new Feed_Parser_SimplePie( $friends->feed );
		$local_parser = new Feed_Parser_Local_File( $friends->feed );
		$friends->feed->register_parser( $local_parser::SLUG, $local_parser );

		$user = new User( $this->friend_id );
		add_filter( 'friends_pre_check_url', '__return_true' );
		$user_feed = User_Feed::save( $user, 'http://friend.local/feed/', array( 'parser' => $simple_pie_parser::SLUG ) );
		$user_local_feed = User_Feed::save( $user, __DIR__ . '/data/friend-feed-post-formats.rss', array( 'parser' => $local_parser::SLUG ) );
		remove_filter( 'friends_pre_check_url', '__return_true' );

		$test_user = get_user_by( 'email', \WP_TESTS_EMAIL );
		$this->assertInstanceOf( 'WP_User', $test_user );
		update_user_option( $test_user->ID, 'friends_no_new_post_by_parser_notification_local', true );

		$feed = new \SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$assert = true;
		$new_items = $friends->feed->process_incoming_feed_items( $simple_pie_parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed );

		$assert = false;
		$new_items = $user->retrieve_posts_from_feeds( array( $user_local_feed ) );
	}

	public function notification_post_format_provider() {
		return array(
			array(
				array( 'aside' ),
				9
			),
			array(
				array( 'status', 'gallery' ),
				8
			),
		);
	}

	/**
	 * Test notifications of a new post.
	 *
	 * @dataProvider notification_post_format_provider
	 */
	public function test_no_notify_new_post_post_format_setting( $disabled_post_formats, $expected_notifications ) {
		$that = $this;
		$notifications_count = 0;
		add_filter(
			'notify_user_about_friend_post',
			function ( $do_send ) use ( $that, &$notifications_count ) {
				if ( $do_send ) {
					$notifications_count++;
				}
				return $do_send;
			},
			10
		);

		if ( ! class_exists( 'SimplePie', false ) ) {
			require_once ABSPATH . WPINC . '/class-simplepie.php';
		}
		update_option( 'home', 'http://me.local' );

		$file = new \SimplePie_File( __DIR__ . '/data/friend-feed-post-formats.rss' );
		$friends   = Friends::get_instance();
		$parser = new Feed_Parser_SimplePie( $friends->feed );

		$user = new User( $this->friend_id );
		add_filter( 'friends_pre_check_url', '__return_true' );
		$user_feed = User_Feed::save( $user, 'http://friend.local/feed/', array( 'post-format' => 'autodetect', 'parser' => $parser::SLUG ) );
		remove_filter( 'friends_pre_check_url', '__return_true' );

		$test_user = get_user_by( 'email', \WP_TESTS_EMAIL );
		$this->assertInstanceOf( 'WP_User', $test_user );
		foreach ( $disabled_post_formats as $post_format ) {
			update_user_option( $test_user->ID, 'friends_no_new_post_format_notification_' . $post_format, true );
		}

		$feed = new \SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed );

		$this->assertEquals( $expected_notifications, $notifications_count );
	}

	public function keyword_match_tests() {
		return array(
			array(
				'user_options' => array(),
				'keyword' => 'private',
				'notify_user_about_keyword_post' => 'true',
				'notify_user_about_friend_post' => 'never',
			),
			array(
				'user_options' => array(
					'friends_no_new_post_notification' => true,
					'friends_keyword_notification_override_disabled' => true,
				),
				'keyword' => 'private',
				'notify_user_about_keyword_post' => 'never',
				'notify_user_about_friend_post' => 'false',
			),
			array(
				'user_options' => array(
					'friends_no_new_post_notification' => true,
				),
				'keyword' => 'private',
				'notify_user_about_keyword_post' => 'true',
				'notify_user_about_friend_post' => 'false',
			),
		);
	}

	/**
	 * Test notifications of a keyword in a post.
	 *
	 * @dataProvider keyword_match_tests

	 */
	public function test_notify_keyword( $user_options, $keyword, $notify_user_about_keyword_post, $notify_user_about_friend_post ) {
		$that = $this;
		$asserts = array(
			'never' => function ( $do_send ) use ( $that ) {
				$that->assertTrue( false, current_filter() );
				return $do_send;
			},
			'true' => function ( $do_send ) use ( $that ) {
				$that->assertTrue( $do_send, current_filter() );
				return $do_send;
			},
			'false' => function ( $do_send ) use ( $that ) {
				$that->assertFalse( $do_send, current_filter() );
				return $do_send;
			},
		);
		add_filter(
			'notify_user_about_keyword_post',
			$asserts[ $notify_user_about_keyword_post ],
			10
		);
		add_filter(
			'notify_user_about_friend_post',
			$asserts[ $notify_user_about_friend_post ],
			10
		);

		add_filter(
			'friends_send_mail',
			function ( $do_send, $to, $subject ) use ( $that, $keyword ) {
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
		$friends   = Friends::get_instance();
		$parser = new Feed_Parser_SimplePie( $friends->feed );

		$test_user = get_user_by( 'email', \WP_TESTS_EMAIL );
		$this->assertInstanceOf( 'WP_User', $test_user );
		foreach ( $user_options as $option => $value ) {
			update_user_option( $test_user->ID, $option, $value );
		}

		$user = new User( $this->friend_id );
		add_filter( 'friends_pre_check_url', '__return_true' );
		$user_feed = User_Feed::save( $user, 'http://friend.local/feed/', array( 'parser' => $parser::SLUG )  );
		remove_filter( 'friends_pre_check_url', '__return_true' );

		$feed = new \SimplePie();
		$feed->set_file( $file );
		$feed->init();

		$new_items = $friends->feed->process_incoming_feed_items( $parser->process_items( $feed->get_items(), $user_feed->get_url() ), $user_feed, Friends::CPT );
	}
}
