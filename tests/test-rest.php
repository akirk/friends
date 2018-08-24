<?php
/**
 * Class Friends_RestTest
 *
 * @package Friends
 */

/**
 * Test the REST API.
 */
class Friends_RestTest extends WP_UnitTestCase {
	/**
	 * The REST server.
	 *
	 * @var object
	 */
	protected $server;

	/**
	 * Setup the unit tests.
	 */
	public function setUp() {
		parent::setUp();

		// Manually activate the REST server.
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server;
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		// Emulate HTTP requests to the REST API.
		add_filter(
			'pre_http_request', function( $preempt, $request, $url ) {
				$p = wp_parse_url( $url );

				$site_url = site_url();

				// Pretend the site_url now is the requested one.
				update_option( 'siteurl', $p['scheme'] . '://' . $p['host'] );
				$url = substr( $url, strlen( site_url() . '/wp-json' ) );
				$r   = new WP_REST_Request( $request['method'], $url );
				if ( ! empty( $request['body'] ) ) {
					foreach ( $request['body'] as $key => $value ) {
						$r->set_param( $key, $value );
					}
				}
				global $wp_rest_server;
				$response = $wp_rest_server->dispatch( $r );

				// Restore the old site_url.
				update_option( 'siteurl', $site_url );

				return array(
					'body'     => wp_json_encode( $response->data ),
					'response' => array(
						'code' => $response->status,
					),
				);
			}, 10, 3
		);
	}

	/**
	 * Check that all endpoints are reachable.
	 */
	public function test_endpoints() {
		$the_route = '/' . Friends_REST::PREFIX;
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
	 * Test the Hello endpoint.
	 */
	public function test_hello() {
		$hello  = '/' . Friends_REST::PREFIX . '/hello';
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( $hello, $routes );

		$request  = new WP_REST_Request( 'GET', $hello );
		$response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'version', $response->data );
	}

	/**
	 * Test a friend request on the REST level.
	 */
	public function test_friend_request() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		update_option( 'siteurl', $my_url );
		$friends = Friends::get_instance();

		// Prepare a signature that $my_url generates before sending the friend request.
		$friend_request_token = sha1( wp_generate_password( 256 ) );
		update_option( 'friends_request_token_' . sha1( $friend_url ), $friend_request_token );

		// Let's send a friend request to $friend_url.
		update_option( 'siteurl', $friend_url );
		$request = new WP_REST_Request( 'POST', '/' . Friends_REST::PREFIX . '/friend-request' );
		$request->set_param( 'site_url', $my_url );
		$request->set_param( 'signature', $friend_request_token );

		$friend_request_response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'friend_request_pending', $friend_request_response->data );

		// Verify that the user case created at remote.
		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );

		$this->assertInstanceOf( 'WP_User', $my_user_at_friend );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		$this->assertEquals( get_user_option( 'friends_request_token', $my_user_at_friend->ID ), $friend_request_response->data['friend_request_pending'] );

		// We're just testing the REST api, so we need to create the user ourselves.
		$friend_user = $friends->access_control->create_user( $friend_url, 'pending_friend_request' );
		$this->assertInstanceOf( 'WP_User', $friend_user );

		// And set the appropriate options.
		update_option( 'friends_accept_token_' . $friend_request_response->data['friend_request_pending'], $friend_user->ID );
		update_user_option( $friend_user->ID, 'friends_accept_signature', $friend_request_token );
		$my_token_at_friend = $friends->access_control->update_in_token( $friend_user->ID );
		update_user_option( $my_user_at_friend->ID, 'friends_in_token', $my_token_at_friend );

		// Now let's accept the friend request.
		update_option( 'siteurl', $my_url );
		$request       = new WP_REST_Request( 'POST', '/' . Friends_REST::PREFIX . '/friend-request-accepted' );
		$request_token = $friend_request_response->data['friend_request_pending'];
		$request->set_param( 'token', $request_token );
		$request->set_param( 'friend', $my_token_at_friend );
		$request->set_param( 'proof', sha1( $request_token . $friend_request_token ) );

		$friend_accept_response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'friend', $friend_accept_response->data );
		delete_user_option( $my_user_at_friend->ID, 'friends_request_token' );

		$friends->access_control->make_friend( $my_user_at_friend, $friend_accept_response->data['friend'] );

		// Check the token.
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), $friend_accept_response->data['friend'] );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), $my_token_at_friend );
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $my_user_at_friend->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $my_user_at_friend->ID ) ) );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), get_user_option( 'friends_in_token', $my_user_at_friend->ID ) );
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), get_user_option( 'friends_out_token', $my_user_at_friend->ID ) );

		// Check cleanup.
		$this->assertFalse( get_option( 'friends_accept_token_' . $friend_request_response->data['friend_request_pending'] ) );
	}

	/**
	 * Test a friend request using admin functions and accepting on mobile.
	 */
	public function test_friend_request_with_admin_and_accept_on_mobile() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		update_option( 'siteurl', $my_url );
		$friends = Friends::get_instance();

		$friend_user = $friends->admin->send_friend_request( $friend_url );
		$this->assertInstanceOf( 'WP_User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );

		$this->assertInstanceOf( 'WP_User', $my_user_at_friend );
		$this->assertEquals( $my_user_at_friend->user_url, $my_url );
		$this->assertFalse( $my_user_at_friend->has_cap( 'pending_friend_request' ) );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		// Remote approves friend request = sets user to friend.
		update_option( 'siteurl', $friend_url );
		$my_user_at_friend->set_role( 'friend' );

		// Refresh the users before querying them again.
		$friend_user = new WP_User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = new WP_User( $my_user_at_friend->ID );
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
		update_option( 'siteurl', $my_url );
		$friends = Friends::get_instance();

		$friend_user = $friends->admin->send_friend_request( $friend_url );
		$this->assertInstanceOf( 'WP_User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );

		$this->assertInstanceOf( 'WP_User', $my_user_at_friend );
		$this->assertEquals( $my_user_at_friend->user_url, $my_url );
		$this->assertFalse( $my_user_at_friend->has_cap( 'pending_friend_request' ) );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		// Remote approves friend request through admin.
		update_option( 'siteurl', $friend_url );
		$friends->admin->handle_bulk_friend_request_approval( false, 'accept_friend_request', array( $my_user_at_friend->ID ) );

		// Refresh the users before querying them again.
		$friend_user = new WP_User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = new WP_User( $my_user_at_friend->ID );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend' ) );

		// We could now access the remote feed with this token.
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $friend_user->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $friend_user->ID ) ) );
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), get_user_option( 'friends_out_token', $my_user_at_friend->ID ) );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), get_user_option( 'friends_in_token', $my_user_at_friend->ID ) );
	}

	/**
	 * Test a friend request with both sides having incoming requests disabled.
	 */
	public function test_friend_request_with_incoming_requests_disabled() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		update_option( 'friends_ignore_incoming_friend_requests', 1 );
		update_option( 'siteurl', $my_url );
		$friends = Friends::get_instance();

		$friend_user = $friends->admin->send_friend_request( $friend_url );
		$this->assertInstanceOf( 'WP_User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user not was created at remote (request is ignored!).
		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );

		$this->assertFalse( $my_user_at_friend );

		// Remote also sends a friend request.
		update_option( 'siteurl', $friend_url );
		$friends->admin->send_friend_request( $my_url );

		// Refresh the users before querying them again.
		$friend_user = new WP_User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );

		$this->assertTrue( $my_user_at_friend->has_cap( 'friend' ) );

		// We could now access the remote feed with this token.
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $friend_user->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $friend_user->ID ) ) );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), get_user_option( 'friends_in_token', $my_user_at_friend->ID ) );
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), get_user_option( 'friends_out_token', $my_user_at_friend->ID ) );
	}

	/**
	 * Test friend requests with just the remote having incoming requests disabled.
	 */
	public function test_friend_request_with_remote_incoming_requests_disabled() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		add_filter(
			'pre_option_friends_ignore_incoming_friend_requests', function( $value ) use ( $my_url ) {
				return $my_url !== $value;
			}
		);
		update_option( 'siteurl', $my_url );
		$friends = Friends::get_instance();

		$friend_user = $friends->admin->send_friend_request( $friend_url );
		$this->assertInstanceOf( 'WP_User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user not was created at remote (request is ignored!).
		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );

		$this->assertFalse( $my_user_at_friend );

		// Remote also sends a friend request.
		update_option( 'siteurl', $friend_url );
		$friends->admin->send_friend_request( $my_url );

		// Refresh the users before querying them again.
		$friend_user = new WP_User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );

		$this->assertTrue( $my_user_at_friend->has_cap( 'friend' ) );

		// We could now access the remote feed with this token.
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $friend_user->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $friend_user->ID ) ) );
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), get_user_option( 'friends_out_token', $my_user_at_friend->ID ) );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), get_user_option( 'friends_in_token', $my_user_at_friend->ID ) );
	}

	/**
	 * Test a friend request with incoming requests disabled locally.
	 */
	public function test_friend_request_with_local_incoming_requests_disabled() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		add_filter(
			'pre_option_friends_ignore_incoming_friend_requests', function( $value ) use ( $my_url ) {
				return $my_url === $value;
			}
		);
		update_option( 'siteurl', $my_url );
		$friends = Friends::get_instance();

		$friend_user = $friends->admin->send_friend_request( $friend_url );
		$this->assertInstanceOf( 'WP_User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );

		$this->assertInstanceOf( 'WP_User', $my_user_at_friend );
		$this->assertEquals( $my_user_at_friend->user_url, $my_url );
		$this->assertFalse( $my_user_at_friend->has_cap( 'pending_friend_request' ) );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		// Remote approves friend request through admin.
		update_option( 'siteurl', $friend_url );
		$friends->admin->handle_bulk_friend_request_approval( false, 'accept_friend_request', array( $my_user_at_friend->ID ) );

		// Refresh the users before querying them again.
		$friend_user = new WP_User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = new WP_User( $my_user_at_friend->ID );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend' ) );

		// We could now access the remote feed with this token.
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $friend_user->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $friend_user->ID ) ) );
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), get_user_option( 'friends_out_token', $my_user_at_friend->ID ) );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), get_user_option( 'friends_in_token', $my_user_at_friend->ID ) );
	}

	/**
	 * Test a friend request with incoming requests disabled locally and accepting with set_role().
	 */
	public function test_friend_request_with_local_incoming_requests_disabled_set_role() {
		$my_url     = 'http://me.local';
		$friend_url = 'http://friend.local';
		add_filter(
			'pre_option_friends_ignore_incoming_friend_requests', function( $value ) use ( $my_url ) {
				return $my_url === $value;
			}
		);
		update_option( 'siteurl', $my_url );
		$friends = Friends::get_instance();

		$friend_user = $friends->admin->send_friend_request( $friend_url );
		$this->assertInstanceOf( 'WP_User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );

		$this->assertInstanceOf( 'WP_User', $my_user_at_friend );
		$this->assertEquals( $my_user_at_friend->user_url, $my_url );
		$this->assertFalse( $my_user_at_friend->has_cap( 'pending_friend_request' ) );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		// Remote approves friend request through admin.
		update_option( 'siteurl', $friend_url );
		$my_user_at_friend->set_role( 'friend' );

		// Refresh the users before querying them again.
		$friend_user = new WP_User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = new WP_User( $my_user_at_friend->ID );
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
		update_option( 'siteurl', $my_url );
		$friends = Friends::get_instance();

		// Friend user already exists with no metadata.
		$this->factory->user->create(
			array(
				'user_login' => 'me.local',
				'user_email' => 'me@me.local',
				'role'       => 'friend',
			)
		);

		$friend_user = $friends->admin->send_friend_request( $friend_url );
		$this->assertInstanceOf( 'WP_User', $friend_user );
		$this->assertEquals( $friend_user->user_url, $friend_url );
		$this->assertTrue( $friend_user->has_cap( 'pending_friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend_request' ) );
		$this->assertFalse( $friend_user->has_cap( 'friend' ) );

		// Verify that the user was created at remote.
		$my_user_at_friend = $friends->access_control->get_user_for_site_url( $my_url );
		$this->assertInstanceOf( 'WP_User', $my_user_at_friend );
		$this->assertEquals( $my_user_at_friend->user_url, $my_url );
		$this->assertFalse( $my_user_at_friend->has_cap( 'pending_friend_request' ) );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend_request' ) );
		$this->assertFalse( $my_user_at_friend->has_cap( 'friend' ) );

		// Remote approves friend request through admin.
		update_option( 'siteurl', $friend_url );
		$friends->admin->handle_bulk_friend_request_approval( false, 'accept_friend_request', array( $my_user_at_friend->ID ) );

		// Refresh the users before querying them again.
		$friend_user = new WP_User( $friend_user->ID );
		$this->assertTrue( $friend_user->has_cap( 'friend' ) );

		$my_user_at_friend = new WP_User( $my_user_at_friend->ID );
		$this->assertTrue( $my_user_at_friend->has_cap( 'friend' ) );

		// We could now access the remote feed with this token.
		$this->assertTrue( boolval( get_user_option( 'friends_in_token', $friend_user->ID ) ) );
		$this->assertTrue( boolval( get_user_option( 'friends_out_token', $friend_user->ID ) ) );
		$this->assertEquals( get_user_option( 'friends_in_token', $friend_user->ID ), get_user_option( 'friends_out_token', $my_user_at_friend->ID ) );
		$this->assertEquals( get_user_option( 'friends_out_token', $friend_user->ID ), get_user_option( 'friends_in_token', $my_user_at_friend->ID ) );
	}
}
