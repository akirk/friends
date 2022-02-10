<?php
/**
 * Class Friends_APITest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Notifications
 */
class MessagesTest extends \WP_UnitTestCase {
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
	public function set_up() {
		parent::set_up();

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
		update_option( 'home', 'http://me.local' );

		$this->friends_in_token = wp_generate_password( 128, false );
		update_user_option( $this->user_id, 'friends_out_token', $this->friends_in_token );
		if ( update_user_option( $this->friend_id, 'friends_in_token', $this->friends_in_token ) ) {
			update_option( 'friends_in_token_' . $this->friends_in_token, $this->friend_id );
		}

		$this->friends_out_token = wp_generate_password( 128, false );
		update_user_option( $this->user_id, 'friends_in_token', $this->friends_out_token );
		if ( update_user_option( $this->friend_id, 'friends_out_token', $this->friends_out_token ) ) {
			update_option( 'friends_in_token_' . $this->friends_out_token, $this->user_id );
		}

		add_filter(
			'user_has_cap',
			function( $allcaps, $caps, $args, $user ) {
				if ( $this->user_id === $user->ID && in_array( 'friend', $caps, true ) ) {
					$allcaps['friend'] = true;
				}
				return $allcaps;
			},
			10,
			4
		);

		add_filter(
			'get_user_option_friends_rest_url',
			function() {
				return get_option( 'home' ) . '/wp-json/' . REST::PREFIX;
			}
		);

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

		// Emulate HTTP requests to the REST API.
		add_filter(
			'pre_http_request',
			function( $preempt, $request, $url ) {
				$p = wp_parse_url( $url );

				$home_url = home_url();

				// Pretend the url now is the requested one.
				update_option( 'home', $p['scheme'] . '://' . $p['host'] );
				$rest_prefix = home_url() . '/wp-json';

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
	 * Test sending messages.
	 */
	public function test_send_messages() {
		wp_set_current_user( $this->user_id );
		$friend_user = new User( $this->friend_id );
		$message_id = $friend_user->send_message( 'test' );
		$this->assertNotInstanceOf( '\WP_Error', $message_id );
		$post = get_post( $message_id );
		$this->assertContains( '"sender":' . $this->user_id, $post->post_content );
		$this->assertContains( '<p>test</p>', $post->post_content );
	}
}
