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
		add_action( 'rest_api_init', array( $this, 'add_rest_routes' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'rest_request_before_callbacks' ), 100, 3 );
		add_action( 'wp_trash_post', array( $this, 'notify_remote_friend_post_deleted' ) );
		add_action( 'before_delete_post', array( $this, 'notify_remote_friend_post_deleted' ) );
		add_action( 'friends_user_post_reaction', array( $this, 'notify_remote_friend_post_reaction' ), 10, 2 );
		add_action( 'friends_user_post_reaction', array( $this, 'notify_friend_of_my_reaction' ) );
		add_action( 'set_user_role', array( $this, 'notify_remote_friend_request_accepted' ), 20, 3 );
	}

	/**
	 * Add the REST API to send and receive friend requests
	 */
	public function add_rest_routes() {
		register_rest_route(
			self::PREFIX,
			'friend-request',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'rest_friend_request' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'accept-friend-request',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'rest_accept_friend_request' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'hello',
			array(
				'methods'  => 'GET,POST',
				'callback' => array( $this, 'rest_hello' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'post-deleted',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'rest_friend_post_deleted' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'update-post-reactions',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'rest_update_friend_post_reactions' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'my-reactions',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'rest_update_reactions_on_my_post' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'recommendation',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'rest_receive_recommendation' ),
			)
		);
	}

	/**
	 * Limit the REST API.
	 *
	 * @param WP_HTTP_Response|WP_Error $response Result to send to the client.
	 * @param array                     $handler  Route handler used for the request.
	 * @param WP_REST_Request           $request  Request used to generate the response.
	 * @return WP_HTTP_Response|WP_Error Result to send to the client.
	 */
	public function rest_request_before_callbacks( $response, $handler, $request ) {
		// Nota bene: when directly accessing an endpoint in a browser, a user will be
		// appear authenticated if a nonce is present, see rest_cookie_check_errors().
		if ( is_wp_error( $response ) || current_user_can( Friends::REQUIRED_ROLE ) ) {
			return $response;
		}

		$route = $request->get_route();

		// Don't allow spying on the users list since it gives away the user's subscriptions,
		// friend requests and friends.
		if ( 0 === stripos( $route, '/wp/v2/users' ) ) {
			return new WP_Error(
				'rest_forbidden',
				'Sorry, you are not allowed to do that.',
				array( 'status' => 401 )
			);
		}

		// The wp/v2/posts and wp/v2/pages endpoints are safe since they respect the post status.
		// The friend_post_cache CPT is also fine since its public attribute is set to false.
		return $response;
	}

	/**
	 * Receive a notification via REST that a friend request was accepted
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_accept_friend_request( WP_REST_Request $request ) {
		$request_id     = $request->get_param( 'request' );
		$friend_user_id = get_option( 'friends_request_' . sha1( $request_id ) );
		$friend_user    = false;
		if ( $friend_user_id ) {
			$friend_user = new WP_User( $friend_user_id );
		}

		if ( ! $request_id || ! $friend_user || is_wp_error( $friend_user ) || ! $friend_user->user_url ) {
			return new WP_Error(
				'friends_invalid_parameters',
				'Not all necessary parameters were provided.',
				array(
					'status' => 403,
				)
			);
		}

		$future_in_token = get_user_option( 'friends_future_in_token_' . sha1( $request_id ), $friend_user_id );
		$proof            = $request->get_param( 'proof' );
		if ( ! $proof || sha1( $future_in_token . $request_id ) !== $proof ) {
			return new WP_Error(
				'friends_invalid_proof',
				'An invalid proof was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$friend_user_login = $this->friends->access_control->get_user_login_for_url( $friend_user->user_url );
		if ( $friend_user_login !== $friend_user->user_login ) {
			return new WP_Error(
				'friends_offer_no_longer_valid',
				'The friendship offer is no longer valid.',
				array(
					'status' => 403,
				)
			);
		}

		$future_out_token = $request->get_param( 'key' );
		if ( ! is_string( $future_out_token ) || empty( $future_out_token ) ) {
			return new WP_Error(
				'friends_invalid_key',
				'The key must be a non-empty string.',
				array(
					'status' => 403,
				)
			);
		}
		$this->friends->access_control->make_friend( $friend_user, $future_out_token, $future_in_token );

		$this->friends->access_control->update_user_icon_url( $friend_user->ID, $request->get_param( 'icon_url' ) );
		if ( $request->get_param( 'name' ) ) {
			wp_update_user(
				array(
					'ID'           => $friend_user->ID,
					'nickname'     => $request->get_param( 'name' ),
					'first_name'   => $request->get_param( 'name' ),
					'display_name' => $request->get_param( 'name' ),
				)
			);
		}

		delete_user_option( $friend_user_id, 'friends_future_in_token_' . sha1( $request_id ) );

		do_action( 'notify_accepted_friend_request', $friend_user );
		return array(
			'signature' => sha1( $future_out_token . $future_in_token ),
		);
	}

	/**
	 * Receive a friend request via REST
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_request( WP_REST_Request $request ) {
		$codeword = $request->get_param( 'codeword' );
		if ( get_option( 'friends_require_codeword' ) && get_option( 'friends_codeword', 'friends' ) !== $codeword ) {
			return new WP_Error(
				'friends_invalid_codeword',
				get_option( 'friends_wrong_codeword_message', 'An invalid codeword was provided.' ),
				array(
					'status' => 403,
				)
			);
		}

		$url = trim( $request->get_param( 'url' ) );
		if ( ! is_string( $url ) || ! wp_http_validate_url( $url ) || 0 === strcasecmp( site_url(), $url ) ) {
			return new WP_Error(
				'friends_invalid_site',
				'An invalid site was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$future_out_token = $request->get_param( 'key' );
		if ( ! is_string( $future_out_token ) || empty( $future_out_token ) ) {
			return new WP_Error(
				'friends_invalid_key',
				'The key must be a non-empty string.',
				array(
					'status' => 403,
				)
			);
		}

		$friend_user = $this->friends->access_control->create_user( $url, 'friend_request', $request->get_param( 'name' ), $request->get_param( 'icon_url' ) );
		if ( $friend_user->has_cap( 'friend' ) ) {
			if ( get_user_option( 'friends_out_token', $friend_user->ID ) && ! get_user_option( 'friends_out_token', $friend_user->ID ) ) {
				// TODO: trigger an accept friend request right away?
			}
			$friend_user->set_role( 'friend_request' );
		}
		$this->friends->access_control->update_user_icon_url( $friend_user->ID, $request->get_param( 'icon_url' ) );

		update_user_option( $friend_user->ID, 'friends_future_out_token', $request->get_param( 'key' ) );
		update_user_option( $friend_user->ID, 'friends_request_message', mb_substr( $request->get_param( 'message' ), 0, 2000 ) );

		$request_id = sha1( wp_generate_password( 256 ) );
		update_user_option( $friend_user->ID, 'friends_request_id', $request_id );

		return array(
			'request' => $request_id,
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

		$friends = Friends::all_friends();
		$friends = $friends->get_results();

		foreach ( $friends as $friend_user ) {
			$friend_rest_url = $this->friends->access_control->get_rest_url( $friend_user );

			$response = wp_safe_remote_post(
				$friend_rest_url . '/post-deleted',
				array(
					'body'        => array(
						'post_id' => $post_id,
						'friend'  => get_user_option( 'friends_out_token', $friend_user->ID ),
					),
					'timeout'     => 20,
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
		$token   = $request->get_param( 'friend' );
		$user_id = $this->friends->access_control->verify_token( $token );
		if ( ! $user_id ) {
			return new WP_Error(
				'friends_request_failed',
				'Could not respond to the request.',
				array(
					'status' => 403,
				)
			);
		}
		$friend_user     = new WP_User( $user_id );
		$remote_post_id  = $request->get_param( 'post_id' );
		$remote_post_ids = $this->friends->feed->get_remote_post_ids( $friend_user );

		if ( ! isset( $remote_post_ids[ $remote_post_id ] ) ) {
			return array(
				'deleted' => false,
			);
		}

		$post_id = $remote_post_ids[ $remote_post_id ];
		$post    = WP_Post::get_instance( $post_id );
		if ( Friends::CPT === $post->post_type ) {
			wp_delete_post( $post_id );
		}

		return array(
			'deleted' => true,
		);
	}


	/**
	 * Notify friends of a friend reaction on my local post
	 *
	 * @param  int $post_id The post id of the post that somebody reacted.
	 * @param  int $exclude_friend_user_id Don't notify this user_id.
	 */
	public function notify_remote_friend_post_reaction( $post_id, $exclude_friend_user_id = null ) {
		$post = WP_Post::get_instance( $post_id );
		if ( 'post' !== $post->post_type ) {
			return;
		}

		$friends = new WP_User_Query(
			array(
				'role'    => 'friend',
				'exclude' => array( $exclude_friend_user_id ),
			)
		);
		foreach ( $friends->get_results() as $friend_user ) {
			$reactions       = $this->friends->reactions->get_reactions( $post->ID, $friend_user->ID );
			$friend_rest_url = $this->friends->access_control->get_rest_url( $friend_user );

			$response = wp_safe_remote_post(
				$friend_rest_url . '/update-post-reactions',
				array(
					'body'        => array(
						'post_id'   => $post_id,
						'reactions' => $reactions,
						'friend'    => get_user_option( 'friends_out_token', $friend_user->ID ),
					),
					'timeout'     => 20,
					'redirection' => 5,
				)
			);
		}
	}

	/**
	 * Update the remote friend reactions for this post.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_update_friend_post_reactions( $request ) {
		$token   = $request->get_param( 'friend' );
		$user_id = $this->friends->access_control->verify_token( $token );
		if ( ! $user_id ) {
			return new WP_Error(
				'friends_request_failed',
				'Could not respond to the request.',
				array(
					'status' => 403,
				)
			);
		}
		$friend_user     = new WP_User( $user_id );
		$remote_post_id  = $request->get_param( 'post_id' );
		$remote_post_ids = $this->friends->feed->get_remote_post_ids( $friend_user );

		if ( ! isset( $remote_post_ids[ $remote_post_id ] ) ) {
			return array(
				'updated' => false,
			);
		}

		$post_id = $remote_post_ids[ $remote_post_id ];
		$post    = WP_Post::get_instance( $post_id );
		$this->friends->reactions->update_remote_reactions( $post_id, $request->get_param( 'reactions' ) );

		return array(
			'updated' => true,
		);
	}

	/**
	 * Notify the friend of our reaction on their post
	 *
	 * @param  int $post_id The post id of the post that was reacted to.
	 */
	public function notify_friend_of_my_reaction( $post_id ) {
		$post = WP_Post::get_instance( $post_id );
		if ( Friends::CPT !== $post->post_type ) {
			return;
		}

		$friend_user = new WP_User( $post->post_author );

		$reactions      = $this->friends->reactions->get_my_reactions( $post->ID );
		$remote_post_id = get_post_meta( $post->ID, 'remote_post_id', true );

		$friend_rest_url = $this->friends->access_control->get_rest_url( $friend_user );

		$response = wp_safe_remote_post(
			$friend_rest_url . '/my-reactions',
			array(
				'body'        => array(
					'post_id'   => $remote_post_id,
					'reactions' => $reactions,
					'friend'    => get_user_option( 'friends_out_token', $friend_user->ID ),
				),
				'timeout'     => 20,
				'redirection' => 5,
			)
		);
	}

	/**
	 * Update the reactions of a friend on my post.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_update_reactions_on_my_post( $request ) {
		$token   = $request->get_param( 'friend' );
		$user_id = $this->friends->access_control->verify_token( $token );
		if ( ! $user_id ) {
			return new WP_Error(
				'friends_request_failed',
				'Could not respond to the request.',
				array(
					'status' => 403,
				)
			);
		}
		$friend_user = new WP_User( $user_id );
		$post_id     = $request->get_param( 'post_id' );
		$post        = WP_Post::get_instance( $post_id );

		if ( ! $post || is_wp_error( $post ) ) {
			return array(
				'updated' => false,
			);
		}
		$reactions = $request->get_param( 'reactions' );
		if ( ! $reactions ) {
			$reactions = array();
		}
		$this->friends->reactions->update_friend_reactions( $post_id, $friend_user->ID, $reactions );

		do_action( 'friends_user_post_reaction', $post_id, $friend_user->ID );

		return array(
			'updated' => true,
		);
	}

	/**
	 * Receive a recommendation for a post
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_receive_recommendation( $request ) {
		$token   = $request->get_param( 'friend' );
		$user_id = $this->friends->access_control->verify_token( $token );
		if ( ! $user_id ) {
			return new WP_Error(
				'friends_request_failed',
				'Could not respond to the request.',
				array(
					'status' => 403,
				)
			);
		}

		$standard_response = array(
			'thank' => 'you',
		);
		$standard_response = false;

		$friend_user = new WP_User( $user_id );

		$permalink = $request->get_param( 'link' );
		$sha1_link = $request->get_param( 'sha1_link' );

		$is_public_recommendation = boolval( $permalink );

		if ( ! apply_filters( 'friends_accept_recommendation', true, $is_public_recommendation ? $permalink : $sha1_link, $friend_user ) ) {
			if ( $standard_response ) {
				return $standard_response;
			}

			return array(
				'no' => 'thanks',
			);
		}

		if ( ! $permalink ) {
			// TODO: check if we also have this friend post and highlight it.
			if ( $standard_response ) {
				return $standard_response;
			}

			return array(
				'ignored' => 'for now',
			);
		}

		$post_id = $this->friends->feed->url_to_postid( $permalink );
		if ( $post_id ) {
			if ( $standard_response ) {
				return $standard_response;
			}

			return array(
				'already' => 'knew',
			);
		}

		$post_data = array(
			'post_title'   => $request->get_param( 'title' ),
			'post_content' => $request->get_param( 'description' ),
			'post_status'  => 'publish',
			'post_author'  => $friend_user->ID,
			'guid'         => $permalink,
			'post_type'    => Friends::CPT,
			'tags_input'   => array( 'recommendation' ),
		);

		$post_id = wp_insert_post( $post_data );
		update_post_meta( $post_id, 'author', $request->get_param( 'author' ) );
		update_post_meta( $post_id, 'icon_url', $request->get_param( 'icon_url' ) );

		$message = $request->get_param( 'message' );
		if ( ! $message ) {
			$message = true;
		}
		update_post_meta( $post_id, 'recommendation', $message );

		if ( $standard_response ) {
			return $standard_response;
		}

		return array(
			'thank' => 'you',
		);
	}

	/**
	 * Discover the REST URL for a friend site
	 *
	 * @param  string $url The URL of the site.
	 * @return string|WP_Error The REST URL or an error.
	 */
	public function discover_rest_url( $url ) {
		if ( ! is_string( $url ) || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'invalid-url-given', 'An invalid URL was given.' );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$dom = new DOMDocument();
			set_error_handler( '__return_null' );
			$dom->loadHTML( wp_remote_retrieve_body( $response ) );
			restore_error_handler();

			$xpath = new DOMXpath( $dom );
			foreach ( $xpath->query( '//link[@rel and @href]' ) as $link ) {
				if ( 'friends-base-url' === $link->getAttribute( 'rel' ) ) {
					$rest_url = $link->getAttribute( 'href' );
					if ( is_string( $rest_url ) && wp_http_validate_url( $rest_url ) ) {
						return $rest_url;
					}
				}
			}
		}

		return false;
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
		if ( 'friend' !== $new_role && 'restricted_friend' !== $new_role ) {
			return;
		}

		$request_token = get_user_option( 'friends_request_id', $user_id );
		if ( ! $request_token ) {
			// We were accepted, so no need to notify the other.
			return;
		}

		$friend_user = new WP_User( $user_id );

		$friend_rest_url  = $this->friends->access_control->get_rest_url( $friend_user );
		$request_id       = get_user_option( 'friends_request_id', $friend_user->ID );
		$future_out_token = get_user_option( 'friends_future_out_token', $friend_user->ID );
		$future_in_token  = sha1( wp_generate_password( 256 ) );

		$current_user = wp_get_current_user();
		$response     = wp_safe_remote_post(
			$friend_rest_url . '/accept-friend-request',
			array(
				'body'        => array(
					'request'  => $request_id,
					'proof'    => sha1( $future_out_token . $request_id ),
					'key'      => $future_in_token,
					'name'     => $current_user->display_name,
					'icon_url' => get_avatar_url( $current_user->ID ),
				),
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// TODO find a way to message the user.
			return;
		}

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! isset( $json->signature ) || sha1( $future_in_token . $future_out_token ) !== $json->signature ) {
			$friend_user->set_role( 'friend_request' );
			// TODO find a way to message the user.
			return;
		}

		$this->friends->access_control->make_friend( $friend_user, $future_out_token, $future_in_token );
		delete_user_option( $friend_user->ID, 'friends_request_id' );
		delete_user_option( $friend_user->ID, 'friends_future_out_token' );

		/*
		TODO
		if ( isset( $json->user_icon_url ) ) {
		$this->friends->access_control->update_user_icon_url( $friend_user->ID, $json->user_icon_url );
		}
		When their friend request is no longer valid
		$friend_user->set_role( 'pending_friend_request' );
		if ( isset( $json->friend_request_pending ) ) {
		}
		}
		*/
	}
}
