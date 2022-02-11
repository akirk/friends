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
class RestTest extends \WP_UnitTestCase {
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

		// Manually activate the REST server.
		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server;
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		add_filter(
			'rest_url',
			function() {
				return get_option( 'home' ) . '/wp-json/';
			}
		);

		add_filter(
			'friends_host_is_valid',
			function( $return, $host ) {
				if ( 'me.local' === $host || 'friend.local' === $host || 'example.org' === $host ) {
					// Hosts used for test cases.
					return $host;
				}
				return $return;
			},
			10,
			2
		);

		// Emulate HTTP requests to the REST API.
		add_filter(
			'pre_http_request',
			function( $preempt, $request, $url ) {
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
				}

				$url = substr( $url, strlen( $rest_prefix ) );
				$r   = new \WP_REST_Request( $request['method'], $url );
				if ( ! empty( $request['body'] ) ) {
					foreach ( $request['body'] as $key => $value ) {
						$r->set_param( $key, $value );
					}
				}
				global $wp_rest_server;
				$response = $wp_rest_server->dispatch( $r );

				// Restore the old url.
				update_option( 'home', $url );

				return apply_filters(
					'fake_http_response',
					array(
						'headers'  => array(
							'content-type' => 'text/json',
						),
						'body'     => wp_json_encode( $response->data ),
						'response' => array(
							'code' => $response->status,
						),
					),
					$p['scheme'] . '://' . $p['host'],
					$url,
					$request
				);
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
	 * Test a friend request on the REST level.
	 */
	public function test_friend_request() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		update_option( 'home', $my_url );
		$friends = Friends::get_instance();
		$future_in_token = 'future_in_token';
		$future_out_token = 'future_out_token';

		// Prepare a signature that $my_url generates before sending the friend request.
		$friend_request_token = wp_generate_password( 128, false );
		update_option( 'friends_request_token_' . sha1( $friend_url . '/wp-json/' . REST::PREFIX ), $friend_request_token );

		// Let's send a friend request to $friend_url.
		update_option( 'home', $friend_url );
		$request = new \WP_REST_Request( 'POST', '/' . REST::PREFIX . '/friend-request' );
		$request->set_param( 'url', $my_url );
		$request->set_param( 'key', $future_in_token );
		$request->set_param( 'version', 2 );

		$friend_request_response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'request', $friend_request_response->data );

		// Verify that the user case created at remote.
		$my_username_at_friend = User::get_user_login_for_url( $my_url );
		$my_user_at_friend = User::get_user( $my_username_at_friend );

		$this->assertInstanceOf( 'Friends\User', $my_user_at_friend );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		$this->assertEquals( get_user_option( 'friends_request_id', $my_user_at_friend->ID ), $friend_request_response->data['request'] );

		// We're just testing the REST api, so we need to create the user ourselves.
		$friend_username = User::get_user_login_for_url( $friend_url );
		$friend_user = User::create( $friend_username, 'pending_friend_request', $friend_url );
		$this->assertInstanceOf( 'Friends\User', $friend_user );
		$friend_user = User::create( $friend_username, 'pending_friend_request', $friend_url );

		update_option( 'friends_request_' . sha1( $friend_request_response->data['request'] ), $friend_user->ID );
		$friend_user->update_user_option( 'friends_future_in_token_' . sha1( $friend_request_response->data['request'] ), $future_in_token );

		// Now let's accept the friend request.
		update_option( 'home', $my_url );
		$request = new \WP_REST_Request( 'POST', '/' . REST::PREFIX . '/accept-friend-request' );
		$request->set_param( 'request', $friend_request_response->data['request'] );
		$request->set_param( 'key', $future_out_token );
		$request->set_param( 'proof', sha1( $future_in_token . $friend_request_response->data['request'] ) );

		$friend_accept_response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'signature', $friend_accept_response->data );
		delete_user_option( $my_user_at_friend->ID, 'friends_request_token' );

		$my_user_at_friend->make_friend( $future_in_token, $future_out_token );

		// Check the token.
		$this->assertEquals( $friend_user->get_user_option( 'friends_in_token' ), $future_in_token );
		$this->assertEquals( $friend_user->get_user_option( 'friends_out_token' ), $future_out_token );
		$this->assertTrue( boolval( $my_user_at_friend->get_user_option( 'friends_in_token' ) ) );
		$this->assertTrue( boolval( $my_user_at_friend->get_user_option( 'friends_out_token' ) ) );
		$this->assertEquals( $friend_user->get_user_option( 'friends_out_token' ), $my_user_at_friend->get_user_option( 'friends_in_token' ) );
		$this->assertEquals( $friend_user->get_user_option( 'friends_in_token' ), $my_user_at_friend->get_user_option( 'friends_out_token' ) );
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
		$this->assertInstanceOf( 'Friends\User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_username_at_friend = User::get_user_login_for_url( $my_url );
		$my_user_at_friend = User::get_user( $my_username_at_friend );

		$this->assertInstanceOf( 'Friends\User', $my_user_at_friend );
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
		$friend_url = 'http://friend.local';
		update_option( 'home', $my_url );
		$friends = Friends::get_instance();
		$friend_username = User::get_user_login_for_url( $friend_url );

		$friend_user = $friends->admin->send_friend_request( $friend_url . '/wp-json/friends/v1', $friend_username, $friend_url, $friend_username );
		$this->assertInstanceOf( 'Friends\User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_username_at_friend = User::get_user_login_for_url( $my_url );
		$my_user_at_friend = User::get_user( $my_username_at_friend );

		$this->assertInstanceOf( 'Friends\User', $my_user_at_friend );
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
		$this->assertInstanceOf( 'Friends\User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_username_at_friend = User::get_user_login_for_url( $my_url );
		$my_user_at_friend = User::get_user( $my_username_at_friend );
		$this->assertInstanceOf( 'Friends\User', $my_user_at_friend );
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
			function( $response, $home_url, $url, $request ) use ( $my_url, $friend_url ) {
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

		$this->assertInstanceOf( 'Friends\User', $friend_user );
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
			function( $response, $home_url, $url, $request ) use ( $my_url, $friend_url ) {
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
}
