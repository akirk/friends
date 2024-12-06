<?php
/**
 * Class Friends_RestTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the REST API.
 */
class RestTest extends Friends_TestCase_Cache_HTTP {
	/**
	 * The REST server.
	 *
	 * @var object
	 */
	protected $server;

	/**
	 * Setup the unit tests.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'friends_enable_wp_friendships', true );

		User_Query::$cache = false;

		// Emulate HTTP requests to the REST API.
		add_filter(
			'pre_http_request',
			function ( $preempt, $request, $url ) {
				$p = wp_parse_url( $url );

				$home_url = home_url();

				// Pretend the url now is the requested one.
				update_option( 'home', $p['scheme'] . '://' . $p['host'] );
				$rest_prefix = home_url() . '/wp-json';
				if ( substr( $url, -6 ) === '/feed/' ) {
					// Restore the old home_url.
					update_option( 'home', $home_url );
					return apply_filters(
						'fake_http_response',
						array(
							'headers'  => array(
								'content-type' => 'application/rss+xml',
							),
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
							'body'     => file_get_contents( __DIR__ . '/data/friend-feed-1-public-post.rss' ),
							'response' => array(
								'code' => 200,
							),
						),
						$p['scheme'] . '://' . $p['host'],
						$url,
						$request
					);
				} elseif ( false === strpos( $url, $rest_prefix ) ) {
					$html = '<html><head><title>' . esc_html( ucwords( strtr( wp_parse_url( $home_url, PHP_URL_HOST ), '.', ' ' ) ) ) . '</title>' . implode( PHP_EOL, Friends::get_html_rel_links() ) . '</head></html>';

					// Restore the old home_url.
					update_option( 'home', $home_url );
					return apply_filters(
						'fake_http_response',
						array(
							'headers'  => array(
								'content-type' => 'text/html',
							),
							'body'     => $html,
							'response' => array(
								'code' => 200,
							),
						),
						$p['scheme'] . '://' . $p['host'],
						$url,
						$request
					);
				} elseif ( 0 === strpos( $url, $rest_prefix ) ) {
					$rest_path = substr( $p['path'], strlen( '/wp-json' ) );
					$rest_request = new \WP_REST_Request( $request['method'], $rest_path );

					if ( ! empty( $request['body'] ) ) {
						$rest_request->set_body_params( $request['body'] );
					}
					if ( isset( $p['query'] ) ) {
						parse_str( $p['query'], $query );
						$rest_request->set_query_params( $query );
					}
					$rest_request->set_headers( $request['headers'] );
					$response = $this->server->dispatch( $rest_request );

					// Restore the old home_url.
					update_option( 'home', $home_url );
					return apply_filters(
						'fake_http_response',
						array(
							'headers'  => array(
								'content-type' => 'application/json',
							),
							'body'     => wp_json_encode( $response->data ),
							'response' => array(
								'code' => 200,
							),
						),
						$p['scheme'] . '://' . $p['host'],
						$url,
						$request
					);
				}
				return $preempt;
			},
			10,
			3
		);
	}

	/**
	 * Check that all endpoints are reachable.
	 */
	public function test_endpoints() {
		$the_route = '/' . REST::PREFIX;
		$routes    = $this->server->get_routes();
		foreach ( $routes as $route => $route_config ) {
			if ( 0 === strpos( $the_route, $route ) ) {
				$this->assertTrue( is_array( $route_config ) );
				foreach ( $route_config as $i => $endpoint ) {
					$this->assertArrayHasKey( 'callback', $endpoint );
					$this->assertArrayHasKey( 0, $endpoint['callback'], get_class( $this ) );
					$this->assertArrayHasKey( 1, $endpoint['callback'], get_class( $this ) );
					$this->assertTrue( is_callable( array( $endpoint['callback'][0], $endpoint['callback'][1] ) ) );
				}
			}
		}
	}

	/**
	 * Test a friend request using admin functions and accepting on mobile.
	 */
	public function test_friend_request_with_admin_and_accept_on_mobile() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		update_option( 'home', $my_url );
		$friends = Friends::get_instance();
		$friend_username = User::get_user_login_for_url( $friend_url );

		$friend_user = $friends->admin->send_friend_request( $friend_url . '/wp-json/friends/v1', $friend_username, $friend_url, $friend_username );
		$this->assertInstanceOf( __NAMESPACE__ . '\User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_username_at_friend = User::get_user_login_for_url( $my_url );
		$my_user_at_friend = User::get_user( $my_username_at_friend );

		$this->assertInstanceOf( __NAMESPACE__ . '\User', $my_user_at_friend );
		$this->assertEquals( $my_user_at_friend->user_url, $my_url );
		$this->assertFalse( $my_user_at_friend->has_cap( 'pending_friend_request' ) );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		// Remote approves friend request = sets user to friend.
		update_option( 'home', $friend_url );
		$my_user_at_friend->set_role( 'friend' );

		// Refresh the users before querying them again.
		$friend_user = new User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = new User( $my_user_at_friend->ID );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend' ) );

		// We could now access the remote feed with this token.
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $friend_user->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $friend_user->ID ) ) );
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), get_user_option( 'friends_out_token', $my_user_at_friend->ID ) );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), get_user_option( 'friends_in_token', $my_user_at_friend->ID ) );
	}

	/**
	 * Test a friend request using the admin.
	 */
	public function test_friend_request_with_admin() {
		$my_url     = 'http://me.local';
		$my_username_at_friend = 'me-at-friend';
		$friend_url = 'http://friend.local';
		update_option( 'home', $my_url );
		$friends = Friends::get_instance();
		$friend_username = User::get_user_login_for_url( $friend_url );

		$me_username = function ( $false, $url )  use ( $my_url, $my_username_at_friend ) {
			if ( $url === $my_url ) {
				return $my_username_at_friend;
			}
			return $false;
		};

		add_filter( 'friends_pre_get_user_login_for_url', $me_username, 10, 2 );
		$friend_user = $friends->admin->send_friend_request( $friend_url . '/wp-json/friends/v1', 'my-friend', $friend_url, $friend_username );
		remove_filter( 'friends_pre_get_user_login_for_url', $me_username, 10, 2 );

		$this->assertInstanceOf( __NAMESPACE__ . '\User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		update_option( 'home', $friend_url );
		$request = new \WP_REST_Request( 'GET', '/' . REST::PREFIX . '/friendship-requested' );
		$request->set_param( 'url', $my_url );
		$friend_request_response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'date', $friend_request_response->data );

		// Verify that the user was created at remote.
		$my_user_at_friend = User::get_user( $my_username_at_friend );

		$this->assertInstanceOf( __NAMESPACE__ . '\User', $my_user_at_friend );
		$this->assertEquals( $my_user_at_friend->user_url, $my_url );
		$this->assertFalse( $my_user_at_friend->has_cap( 'pending_friend_request' ) );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		// Remote approves friend request through admin.
		update_option( 'home', $friend_url );
		$friends->admin->handle_bulk_friend_request_approval( false, 'accept_friend_request', array( $my_user_at_friend->ID ) );

		// Refresh the users before querying them again.
		$friend_user = new User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = new User( $my_user_at_friend->ID );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend' ) );

		// We could now access the remote feed with this token.
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $friend_user->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $friend_user->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $my_user_at_friend->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $my_user_at_friend->ID ) ) );
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), get_user_option( 'friends_out_token', $my_user_at_friend->ID ) );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), get_user_option( 'friends_in_token', $my_user_at_friend->ID ) );
	}

	/**
	 * Test a friend request with the local user not having metadata for whatever reason.
	 */
	public function test_friend_request_with_local_user_without_metadata() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		update_option( 'home', $my_url );
		$friends = Friends::get_instance();
		$friend_username = User::get_user_login_for_url( $friend_url );

		// Friend user already exists with no metadata.
		$this->factory->user->create(
			array(
				'user_login' => 'me.local',
				'user_email' => 'me@me.local',
				'role'       => 'friend',
				'user_url'   => $my_url,
			)
		);

		$friend_user = $friends->admin->send_friend_request( $friend_url . '/wp-json/friends/v1', $friend_username, $friend_url, $friend_username );
		$this->assertInstanceOf( __NAMESPACE__ . '\User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_username_at_friend = User::get_user_login_for_url( $my_url );
		$my_user_at_friend = User::get_user( $my_username_at_friend );
		$this->assertInstanceOf( __NAMESPACE__ . '\User', $my_user_at_friend );
		$this->assertEquals( $my_user_at_friend->user_url, $my_url );
		$this->assertFalse( $my_user_at_friend->has_cap( 'pending_friend_request' ) );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		// Remote approves friend request through admin.
		update_option( 'home', $friend_url );
		$friends->admin->handle_bulk_friend_request_approval( false, 'accept_friend_request', array( $my_user_at_friend->ID ) );

		// Refresh the users before querying them again.
		$friend_user = new User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = new User( $my_user_at_friend->ID );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend' ) );

		// We could now access the remote feed with this token.
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $friend_user->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $friend_user->ID ) ) );
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), get_user_option( 'friends_out_token', $my_user_at_friend->ID ) );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), get_user_option( 'friends_in_token', $my_user_at_friend->ID ) );
	}

	/**
	 * The friend doesn't have the plugin installed, so we should subscribe.
	 */
	public function test_friend_request_with_no_plugin_on_other_side() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';

		add_filter(
			'fake_http_response',
			function ( $response, $home_url, $url, $request ) use ( $my_url, $friend_url ) {
				if ( $home_url === $my_url ) {
					return $response;
				}
				if ( rtrim( $url, '/' ) === $friend_url ) {
					return array(
						'headers'  => array(
							'content-type' => 'text/html',
						),
						'body'     => '<html><link rel="alternate" type="application/rss+xml" title="akirk.blog &raquo; Feed" href="' . $friend_url . '/feed/" />',
						'response' => array(
							'code' => 200,
						),
					);
				}
				if ( $friend_url . '/feed/' === $url ) {
					return array(
						'headers'  => array(
							'content-type' => 'application/rss+xml',
						),
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						'body'     => file_get_contents( __DIR__ . '/data/friend-feed-1-private-post.rss' ),
						'response' => array(
							'code' => 200,
						),
					);
				}
				return new \WP_Error(
					'rest_no_route',
					'No route was found matching the URL and request method',
					array(
						'status' => 404,
					)
				);
			},
			10,
			4
		);

		update_option( 'home', $my_url );
		$friends = Friends::get_instance();
		$friend_username = User::get_user_login_for_url( $friend_url );

		$friend_user = $friends->admin->send_friend_request( $friend_url . '/wp-json/UNKNOWN-friends/v1', $friend_username, $friend_url, $friend_username );
		$this->assertInstanceOf( '\WP_Error', $friend_user );

		$friend_user = User::create( $friend_username, 'subscription', $friend_url, $friend_username );
		$friend_user->subscribe( $friend_url );

		$this->assertInstanceOf( __NAMESPACE__ . '\User', $friend_user );
		$this->assertEquals( rtrim( $friend_user->user_url, '/' ), $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'subscription' ) );

		// Verify that the user was not created at remote.
		$my_username_at_friend = User::get_user_login_for_url( $my_url );
		$my_user_at_friend = User::get_user( $my_username_at_friend );
		$this->assertFalse( $my_user_at_friend );

		// No tokens were generated.
		$this->assertFalse( boolval( get_user_option( 'friends_in_token', $friend_user->ID ) ) );
		$this->assertFalse( boolval( get_user_option( 'friends_out_token', $friend_user->ID ) ) );
	}

	/**
	 * We can't connect to the friend (for example because of incompatibel SSL configurations).
	 */
	public function test_friend_request_unable_to_connect() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';

		add_filter(
			'fake_http_response',
			function ( $response, $home_url, $url, $request ) use ( $my_url, $friend_url ) {
				if ( $home_url === $my_url ) {
					return $response;
				}
				return new \WP_Error(
					'http_request_failed',
					'cURL error 35: error:14077410:SSL routines:SSL23_GET_SERVER_HELLO:sslv3 alert handshake failure'
				);
			},
			10,
			4
		);

		update_option( 'home', $my_url );
		$friends = Friends::get_instance();
		$friend_username = User::get_user_login_for_url( $friend_url );

		$friend_user = $friends->admin->send_friend_request( $friend_url . '/wp-json/friends/v1', $friend_username, $friend_url, $friend_username );
		$this->assertInstanceOf( '\WP_Error', $friend_user );
		$this->assertEquals( 'cURL error 35: error:14077410:SSL routines:SSL23_GET_SERVER_HELLO:sslv3 alert handshake failure', $friend_user->get_error_message() );
	}

	/**
	 * Test a friend request when they are disabled on the other side.
	 */
	public function test_friend_request_with_disabled_other_side() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		update_option( 'friends_enable_wp_friendships', false );
		update_option( 'home', $my_url );
		$friends = Friends::get_instance();
		$friend_username = User::get_user_login_for_url( $friend_url );

		$friend_user = $friends->admin->send_friend_request( $friend_url . '/wp-json/friends/v1', $friend_username, $friend_url, $friend_username );
		$this->assertInstanceOf( 'WP_Error', $friend_user );

		// Verify that user was not created on remote
		$my_username_at_friend = User::get_user_login_for_url( $my_url );
		$my_user_at_friend = User::get_user( $my_username_at_friend );
		$this->assertFalse( $my_user_at_friend );
	}

	/**
	 * Test a friend request when they are disabled on the other side.
	 */
	public function test_friend_request_and_send_message() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		update_option( 'home', $my_url );
		$friends = Friends::get_instance();
		$friend_username = User::get_user_login_for_url( $friend_url );

		$friend_user = $friends->admin->send_friend_request( $friend_url . '/wp-json/friends/v1', $friend_username, $friend_url, $friend_username );
		$this->assertInstanceOf( __NAMESPACE__ . '\User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_username_at_friend = User::get_user_login_for_url( $my_url );
		$my_user_at_friend = User::get_user( $my_username_at_friend );

		$this->assertInstanceOf( __NAMESPACE__ . '\User', $my_user_at_friend );
		$this->assertEquals( $my_user_at_friend->user_url, $my_url );
		$this->assertFalse( $my_user_at_friend->has_cap( 'pending_friend_request' ) );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		$message_id = $friend_user->send_message( 'test' );
		$this->assertInstanceOf( 'WP_Error', $message_id );

		$message_id = $my_user_at_friend->send_message( 'test' );
		$this->assertInstanceOf( 'WP_Error', $message_id );
	}
}
