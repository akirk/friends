<?php
/**
 * Friends REST
 *
 * This contains the functions for REST.
 *
 * @package Friends
 */

/**
 * This is the class for the REST part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_REST {
	const PREFIX = 'friends/v1';
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'rest_api_init',      array( $this, 'add_rest_routes' ) );
		add_action( 'wp_trash_post',      array( $this, 'notify_remote_friend_post_deleted' ) );
		add_action( 'before_delete_post', array( $this, 'notify_remote_friend_post_deleted' ) );
		add_action( 'set_user_role',      array( $this, 'notify_remote_friend_request_accepted' ), 20, 3 );
	}

	/**
	 * Add the REST API to send and receive friend requests
	 */
	public function add_rest_routes() {
		register_rest_route(
			self::PREFIX, 'friend-request', array(
				'methods' => 'POST',
				'callback' => array( $this, 'rest_friend_request' ),
			)
		);
		register_rest_route(
			self::PREFIX, 'friend-request-accepted', array(
				'methods' => 'POST',
				'callback' => array( $this, 'rest_friend_request_accepted' ),
			)
		);
		register_rest_route(
			self::PREFIX, 'hello', array(
				'methods' => 'GET,POST',
				'callback' => array( $this, 'rest_hello' ),
			)
		);
		register_rest_route(
			self::PREFIX, 'post-deleted', array(
				'methods' => 'POST',
				'callback' => array( $this, 'rest_friend_post_deleted' ),
			)
		);
	}

	/**
	 * Acknowledge via REST that the friends plugin had called.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_hello( WP_REST_Request $request ) {
		if ( 'GET' === $request->get_method() ) {
			return array(
				'version' => Friends::VERSION,
				'site_url' => site_url(),
			);
		}

		$signature = get_option( 'friends_request_token_' . sha1( $request->get_param( 'site_url' ) ) );

		if ( ! $signature ) {
			return new WP_Error(
				'friends_unknown_request',
				'The other party is unknown.',
				array(
					'status' => 403,
				)
			);
		}

		return array(
			'version' => Friends::VERSION,
			'response' => sha1( $signature . $request->get_param( 'challenge' ) ),
		);
	}

	/**
	 * Receive a notification via REST that a friend request was accepted
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_request_accepted( WP_REST_Request $request ) {
		$accept_token = $request->get_param( 'token' );
		$out_token = $request->get_param( 'friend' );
		$proof = $request->get_param( 'proof' );
		$friend_user_id = get_option( 'friends_accept_token_' . $accept_token );
		$friend_user = false;
		if ( $friend_user_id ) {
			$friend_user = new WP_User( $friend_user_id );
		}

		if ( ! $accept_token || ! $out_token || ! $proof || ! $friend_user || is_wp_error( $friend_user ) || ! $friend_user->user_url ) {
			return new WP_Error(
				'friends_invalid_parameters',
				'Not all necessary parameters were provided.',
				array(
					'status' => 403,
				)
			);
		}

		$signature = get_user_option( 'friends_accept_signature', $friend_user_id );
		if ( sha1( $accept_token . $signature ) !== $proof ) {
			return new WP_Error(
				'friends_invalid_proof',
				'An invalid proof was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$friend_user_login = $this->friends->access_control->get_user_login_for_site_url( $friend_user->user_url );
		if ( $friend_user_login !== $friend_user->user_login ) {
			return new WP_Error(
				'friends_offer_no_longer_valid',
				'The friendship offer is no longer valid.',
				array(
					'status' => 403,
				)
			);
		}

		delete_option( 'friends_accept_token_' . $accept_token );
		$this->friends->access_control->make_friend( $friend_user, $out_token );
		$in_token = $this->friends->access_control->update_in_token( $friend_user->ID );

		do_action( 'notify_accepted_friend_request', $friend_user );
		return array(
			'friend' => $in_token,
		);
	}

	/**
	 * Limits the requests from an ip address
	 *
	 * @param  string $name             A unique identifier of the page loader.
	 * @param  int    $allowed_requests The number of allowed requests in time-frame.
	 * @param  int    $minutes          The time-frame in minutes.
	 * @return bool Whether the user should be limited or not.
	 */
	public function limit_requests_in_minutes( $name, $allowed_requests, $minutes ) {
		$requests = 0;
		$now = time();

		for ( $time = $now - $minutes * 60; $time <= $now; $time += 60 ) {
			$key = $name . date( 'dHi', $time );

			$requests_in_current_minute = wp_cache_get( $key, 'friends' );

			if ( false === $requests_in_current_minute ) {
				wp_cache_set( $key, 1, 'friends', $minutes * 60 + 1 );
			} else {
				wp_cache_incr( $key, 1, 'friends' );
			}
		}

		if ( $requests > $allowed_requests ) {
			return false;
		}
		return true;
	}

	/**
	 * Receive a friend request via REST
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_request( WP_REST_Request $request ) {
		if ( ! $this->limit_requests_in_minutes( 'friend-request' . $_SERVER['REMOTE_ADDR'], 5, 5 ) ) {
			return new WP_Error(
				'too_many_request',
				'Too many requests were sent.',
				array(
					'status' => 529,
				)
			);

		}

		$site_url = trim( $request->get_param( 'site_url' ) );
		if ( ! is_string( $site_url ) || ! wp_http_validate_url( $site_url ) || 0 === strcasecmp( site_url(), $site_url ) ) {
			return new WP_Error(
				'friends_invalid_site',
				'An invalid site was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$challenge = sha1( wp_generate_password( 256 ) );
		$response = wp_safe_remote_post(
			$site_url . '/wp-json/' . self::PREFIX . '/hello', array(
				'body' => array(
					'challenge' => $challenge,
					'site_url' => site_url(),
				),
				'timeout' => 5,
				'redirection' => 1,
			)
		);

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $json || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( $json && isset( $json->code ) && isset( $json->message ) ) {
				return new WP_Error( $json->code, $json->message, $json->data );
			}
			return new WP_Error(
				'friends_unsupported_site',
				'An unsupported site was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$signature = $request->get_param( 'signature' );
		if ( sha1( $signature . $challenge ) !== $json->response ) {
			return new WP_Error(
				'friends_invalid_response',
				'An invalid response was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$user = $this->friends->access_control->get_user_for_site_url( $site_url );
		if ( $user && ! is_wp_error( $user ) ) {
			$request_token = false;
			if ( $user->has_cap( 'friend_request' ) && get_user_option( 'friends_request_token', $user->ID ) ) {
				// Exit early and don't notify.
				return array(
					'friend_request_pending' => $request_token,
				);
			}

			if ( $user->has_cap( 'pending_friend_request' ) && get_option( 'friends_request_token_' . sha1( $site_url ) ) ) {
				// We already requested friendship, so let's become friends right away.
				$in_token = $this->friends->access_control->update_in_token( $user->ID );
				$user->set_role( 'friend' );
				return array(
					'friend' => $in_token,
				);
			}

			if ( $user->has_cap( 'friend' ) ) {
				// Already a friend, was it deleted?
				return array(
					'friend' => get_user_option( 'friends_in_token', $user->ID ),
				);
			}
		} elseif ( get_option( 'friends_ignore_incoming_friend_requests' ) ) {
			$token = sha1( wp_generate_password( 256 ) );
			update_option( 'friends_request_token_' . sha1( $site_url ), $token );
			return array(
				'friend_request_pending' => $token,
			);
		}

		$user_id = $this->friends->access_control->create_user( $site_url, 'friend_request', $request->get_param( 'name' ), $request->get_param( 'email' ) );
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error(
				'friends_friend_request_failed',
				'Could not respond to the friend request.',
				array(
					'status' => 403,
				)
			);
		}

		$user = new WP_User( $user_id );

		if ( $user->has_cap( 'pending_friend_request' ) ) {
			// Friend request was deleted on the other side and then re-initated.
			$user->set_role( 'friend_request' );
		}

		update_user_option( $user_id, 'friends_accept_signature', $signature );

		do_action( 'notify_new_friend_request', $user );
		return array(
			'friend_request_pending' => get_user_option( 'friends_request_token', $user->ID ),
		);
	}

	/**
	 * Notify friends of a deleted post
	 *
	 * @param  int $post_id The post id of the post that is deleted.
	 */
	public function notify_remote_friend_post_deleted( $post_id ) {
		$post = WP_Post::get_instance( $post_id );
		if ( 'post' !== $post->post_type ) {
			return;
		}

		$friends = new WP_User_Query( array( 'role' => 'friend' ) );
		$friends = $friends->get_results();

		foreach ( $friends as $friend_user ) {
			$response = wp_safe_remote_post(
				$friend_user->user_url . '/wp-json/' . self::PREFIX . '/post-deleted', array(
					'body' => array(
						'post_id' => $post_id,
						'friend' => get_user_option( 'friends_out_token', $friend_user->ID ),
					),
					'timeout' => 20,
					'redirection' => 5,
				)
			);
		}
	}

	/**
	 * Receive a REST message that a post was deleted.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_post_deleted( $request ) {
		$token = $request->get_param( 'friend' );
		$user_id = $this->friends->access_control->verify_token( $token );
		if ( ! $user_id ) {
			return new WP_Error(
				'friends_friend_request_failed',
				'Could not respond to the friend request.',
				array(
					'status' => 403,
				)
			);
		}
		$friend_user = new WP_User( $user_id );
		$remote_post_id = $request->get_param( 'post_id' );
		$remote_post_ids = $this->friends->feed->get_remote_post_ids( $friend_user );

		$post_id = false;
		if ( isset( $remote_post_ids[ $remote_post_id ] ) ) {
			$post_id = $remote_post_ids[ $remote_post_id ];
		}

		if ( ! $post_id ) {
			return array(
				'deleted' => false,
			);
		}
		$post = WP_Post::get_instance( $post_id );
		if ( Friends::FRIEND_POST_CACHE === $post->post_type ) {
			wp_delete_post( $post_id );
		}

		return array(
			'deleted' => true,
		);
	}

	/**
	 * Notify the friend's site via REST about the accepted friend request.
	 *
	 * Accepting a friend request is simply setting the role to "friend".
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  string $old_roles The old roles.
	 */
	public function notify_remote_friend_request_accepted( $user_id, $new_role, $old_roles ) {
		if ( 'friend' !== $new_role ) {
			return;
		}

		$request_token = get_user_option( 'friends_request_token', $user_id );
		if ( ! $request_token ) {
			// We were accepted, so no need to notify the other.
			return;
		}

		$user = new WP_User( $user_id );

		$friend_request_token = get_option( 'friends_request_token_' . sha1( $user->user_url ) );
		$in_token = $this->friends->access_control->update_in_token( $user->ID );

		$response = wp_safe_remote_post(
			$user->user_url . '/wp-json/' . self::PREFIX . '/friend-request-accepted', array(
				'body' => array(
					'token' => $request_token,
					'friend' => $in_token,
					'proof' => sha1( $request_token . $friend_request_token ),
				),
				'timeout' => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		delete_user_option( $user_id, 'friends_request_token' );
		$json = json_decode( wp_remote_retrieve_body( $response ) );
		$u = get_user_by( 'login', 'friend.local' );
		if ( isset( $json->friend ) ) {
			$this->friends->access_control->make_friend( $user, $json->friend );
		} else {
			$user->set_role( 'pending_friend_request' );
			if ( isset( $json->friend_request_pending ) ) {
				update_option( 'friends_accept_token_' . $json->friend_request_pending, $user_id );
			}
		}
	}
}
