<?php
/**
 * Friends REST
 *
 * This contains the functions for REST.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the REST part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class REST {
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
		add_action( 'wp_trash_post', array( $this, 'notify_remote_friend_post_deleted' ) );
		add_action( 'before_delete_post', array( $this, 'notify_remote_friend_post_deleted' ) );
		add_action( 'set_user_role', array( $this, 'notify_remote_friend_request_accepted' ), 20, 3 );
		add_action( 'rest_pre_serve_request', array( $this, 'send_rest_origin' ), 20, 3 );
	}

	public function send_rest_origin( $ret, $response, $request ) {
		if ( strpos( $request->get_route(), '/' . self::PREFIX . '/extension' ) !== 0 ) {
			return $ret;
		}

		if ( $request->get_header( 'origin' ) ) {
			$scheme = wp_parse_url( $request->get_header( 'origin' ), PHP_URL_SCHEME );
			if ( 'moz-extension' === $scheme ) {
				header( 'access-control-allow-origin: ' . $request->get_header( 'origin' ) );
			}
		}
		return $ret;
	}

	/**
	 * Add the REST API to send and receive friend requests
	 */
	public function add_rest_routes() {
		register_rest_route(
			self::PREFIX,
			'friend-request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_friend_request' ),
				'permission_callback' => array( $this, 'permission_check_friend_request' ),
				'args'                => array(
					'url'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'key'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'name'     => array(
						'type'     => 'string',
						'required' => false,
					),
					'icon_url' => array(
						'type'     => 'string',
						'required' => false,
					),
					'message'  => array(
						'type'     => 'string',
						'required' => false,
					),
					'codeword' => array(
						'type'     => 'string',
						'required' => false,
					),
					'version'  => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::PREFIX,
			'friendship-requested',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_friendship_requested' ),
				'permission_callback' => '__return_true', // Unauthenticated is ok.
				'args'                => array(
					'url' => array(
						'type'     => 'string',
						'required' => true,
					),
				),

			)
		);
		register_rest_route(
			self::PREFIX,
			'accept-friend-request',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_accept_friend_request' ),
				'permission_callback' => array( $this, 'permssion_check_accept_friend_request' ),
				'args'                => array(
					'url'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'key'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'your_key' => array(
						'type'     => 'string',
						'required' => true,
					),
					'name'     => array(
						'type'     => 'string',
						'required' => false,
					),
					'icon_url' => array(
						'type'     => 'string',
						'required' => false,
					),
				),

			)
		);
		register_rest_route(
			self::PREFIX,
			'post-deleted',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_friend_post_deleted' ),
				'permission_callback' => array( $this, 'permission_check_friends_only' ),
			)
		);
		register_rest_route(
			self::PREFIX,
			'embed',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_embed_friend_post' ),
				'permission_callback' => function () {
					return current_user_can( Friends::REQUIRED_ROLE );
				},
			)
		);

		register_rest_route(
			self::PREFIX,
			'get-feeds',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_feeds' ),
				'permission_callback' => function () {
					return current_user_can( Friends::REQUIRED_ROLE );
				},
			)
		);

		register_rest_route(
			self::PREFIX,
			'refresh-feed',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_refresh_feed' ),
				'params'              => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'permission_callback' => function () {
					return current_user_can( Friends::REQUIRED_ROLE );
				},
			)
		);

		register_rest_route(
			self::PREFIX,
			'extension',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'rest_extension' ),
				'permission_callback' => '__return_true', // Public.
				'params'              => array(
					'key' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Translate a REST error message
	 *
	 * @param  string $message The message to translate.
	 * @return string The translated message.
	 */
	public static function translate_error_message( $message ) {
		$messages = self::get_error_messages( true );
		if ( isset( $messages[ $message ] ) ) {
			return $messages[ $message ];
		}
		return $message;
	}

	/**
	 * Get the error messages for REST
	 *
	 * @return array The error messages.
	 */
	public static function get_error_messages() {
		$english = function () {
			return 'en_US';
		};

		// In the first pass never translate these messages.
		add_filter( 'locale', $english );

		$messages = array(
			'friends_invalid_parameters'           => __( 'Not all necessary parameters were provided.', 'friends' ),
			'friends_invalid_url'                  => __( 'An invalid URL was provided.', 'friends' ),
			'friends_no_request'                   => __( 'No request was found.', 'friends' ),
			'friends_unsupported_protocol_version' => __( 'Incompatible Friends protocol version.', 'friends' ),
			'friends_invalid_codeword'             => __( 'An invalid codeword was provided.', 'friends' ),
			'friends_invalid_site'                 => __( 'An invalid site was provided.', 'friends' ),
			'friends_disabled_friendship'          => __( 'This site doesn\'t accept friend requests.', 'friends' ),
			'friends_not_requested'                => __( 'No friendship request was made.', 'friends' ),
			'friends_request_failed'               => __( 'Could not respond to the request.', 'friends' ),
			'unknown'                              => __( 'An unknown error occurred.', 'friends' ),
		);

		remove_filter( 'locale', $english );

		// Add mapping for English text to translations.
		foreach ( $messages as $key => $message ) {
			$messages[ $message ] = __( $message, 'friends' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		}

		return $messages;
	}

	/**
	 * Standardize the error message texts
	 *
	 * @param string $code    The error code.
	 * @param string $message The message to return, if not provided the default message will be used.
	 * @param int    $status  The status code to return.
	 *
	 * @return \WP_Error The error object.
	 */
	public static function error( $code, $message = '', $status = 403 ) {
		if ( ! $message ) {
			// Return English error messages.
			$messages = self::get_error_messages();
			if ( isset( $messages[ $code ] ) ) {
				$message = $messages[ $code ];
			} else {
				$message = $messages['unknown'];
			}
		}

		return new \WP_Error(
			$code,
			$message,
			array(
				'status' => $status,
			)
		);
	}

	public function permssion_check_accept_friend_request( \WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );

		$friend_user = false;
		$pending_friend_requests = User_Query::all_pending_friend_requests();
		foreach ( $pending_friend_requests->get_results() as $pending_friend_request ) {
			if ( $pending_friend_request->user_url === $url ) {
				$friend_user = $pending_friend_request;
				break;
			}
		}

		if ( ! $friend_user ) {
			// Maybe they are already friends from the other side.
			$potential_friends = User_Query::all_friends();
			foreach ( $potential_friends->get_results() as $potential_friend ) {
				if ( $potential_friend->user_url === $url ) {
					$friend_user = $potential_friend;
					break;
				}
			}

			if ( ! $friend_user ) {
				return self::error( 'friends_invalid_parameters' );
			}
		}

		$their_key = $request->get_param( 'key' );
		$our_key = $request->get_param( 'your_key' );

		if (
			$friend_user->get_user_option( 'friends_out_token' ) !== $our_key
			|| $friend_user->get_user_option( 'friends_in_token' ) !== $their_key
		) {
			return self::error( 'friends_invalid_parameters' );
		}
		return true;
	}


	/**
	 * Receive a notification via REST that a friend request was accepted
	 *
	 * @param  \WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_accept_friend_request( \WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );

		$friend_user = false;
		$pending_friend_requests = User_Query::all_pending_friend_requests();
		foreach ( $pending_friend_requests->get_results() as $pending_friend_request ) {
			if ( $pending_friend_request->user_url === $url ) {
				$friend_user = $pending_friend_request;
				break;
			}
		}

		if ( ! $friend_user ) {
			// Maybe they are already friends from the other side.
			$potential_friends = User_Query::all_friends();
			foreach ( $potential_friends->get_results() as $potential_friend ) {
				if ( $potential_friend->user_url === $url ) {
					$friend_user = $potential_friend;
					break;
				}
			}

			if ( ! $friend_user ) {
				return self::error( 'friends_invalid_parameters' );
			}
		}

		$their_key = $request->get_param( 'key' );
		$our_key = $request->get_param( 'your_key' );

		if (
			$friend_user->get_user_option( 'friends_out_token' ) !== $our_key
			|| $friend_user->get_user_option( 'friends_in_token' ) !== $their_key
		) {
			return self::error( 'friends_invalid_parameters' );
		}

		$friend_user->make_friend();

		$friend_user->update_user_icon_url( $request->get_param( 'icon_url' ) );
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

		do_action( 'notify_accepted_friend_request', $friend_user );

		return array(
			'ok' => true,
		);
	}

	public function check_remote_did_send_request( $url ) {
		$rest_url = $this->discover_rest_url( $url );
		if ( ! $rest_url || is_wp_error( $rest_url ) ) {
			return false;
		}

		$rest_url .= '/friendship-requested';
		$check_url = add_query_arg( 'url', home_url(), $rest_url );
		$response = wp_safe_remote_get(
			$check_url,
			array(
				'timeout'     => 20,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );
		if ( ! isset( $body['date'] ) ) {
			return false;
		}

		try {
			$friendship_requested = new \DateTime( $body['date'] );
		} catch ( \Exception $e ) {
			// Invalid date.
			return false;
		}

		// It can't be older than 1 year but not newer than now.
		$year_ago = new \DateTime( '1 year ago' );
		if ( $friendship_requested < $year_ago || $friendship_requested > new \DateTime() ) {
			return false;
		}

		return true;
	}

	public function rest_friendship_requested( \WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );

		if ( ! Friends::check_url( $url ) ) {
			return self::error( 'friends_invalid_url' );
		}

		$pending_friend_requests = User_Query::all_pending_friend_requests();
		foreach ( $pending_friend_requests->get_results() as $pending_friend_request ) {
			if ( $pending_friend_request->user_url === $url ) {
				return array(
					'date' => gmdate( 'c', strtotime( $pending_friend_request->user_registered ) ),
				);
			}
		}

		return self::error( 'friends_no_request' );
	}

	public function permission_check_friend_request( \WP_REST_Request $request ) {
		$version = $request->get_param( 'version' );
		if ( 3 !== intval( $version ) ) {
			return self::error( 'friends_unsupported_protocol_version' );
		}

		$codeword = $request->get_param( 'codeword' );
		if ( get_option( 'friends_require_codeword' ) && get_option( 'friends_codeword', 'friends' ) !== $codeword ) {
			return self::error( 'friends_invalid_codeword', get_option( 'friends_wrong_codeword_message' ) );
		}

		$url = trim( $request->get_param( 'url' ) );
		if ( ! is_string( $url ) || ! Friends::check_url( $url ) || 0 === strcasecmp( home_url(), $url ) ) {
			return self::error( 'friends_invalid_site' );
		}

		if ( ! get_option( 'friends_enable_wp_friendships' ) ) {
			return self::error( 'friends_disabled_friendship' );
		}

		if ( ! $this->check_remote_did_send_request( $url ) ) {
			return self::error( 'friends_not_requested' );
		}

		return true;
	}

	/**
	 * Receive a friend request via REST
	 *
	 * @param  \WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_request( \WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );

		$user_login = User::get_user_login_for_url( $url );
		$friend_user = User::create( $user_login, 'friend_request', $url, $request->get_param( 'name' ), $request->get_param( 'icon_url' ) );
		if ( $friend_user->has_cap( 'friend' ) ) {
			// TODO: trigger an accept friend request right away?
			$friend_user->set_role( 'friend_request' );
		}
		$friend_user->update_user_icon_url( $request->get_param( 'icon_url' ) );
		$friend_user->update_user_option( 'friends_out_token', $request->get_param( 'key' ) );
		$our_key = wp_generate_password( 128, false );
		$friend_user->update_user_option( 'friends_in_token', $our_key );

		$message = $request->get_param( 'message' );
		if ( is_string( $message ) ) {
			$friend_user->update_user_option( 'friends_request_message', mb_substr( $message, 0, 2000 ) );
		}

		return array(
			'key' => $our_key,
		);
	}

	public function permission_check_friends_only( \WP_REST_Request $request ) {
		if ( Friends::authenticated_for_posts() ) {
			return true;
		}

		$tokens = explode( '-', $request->get_param( 'auth' ) );
		$user_id = $this->friends->access_control->verify_token( $tokens[0], isset( $tokens[1] ) ? $tokens[1] : null, isset( $tokens[2] ) ? $tokens[2] : null );
		if ( ! $user_id ) {
			return self::error( 'friends_request_failed' );
		}

		$friend_user = new User( $user_id );
		if ( ! $friend_user->has_cap( 'friend' ) ) {
			return self::error( 'friends_request_failed' );
		}

		return true;
	}

	/**
	 * Notify friends of a deleted post
	 *
	 * @param  int $post_id The post id of the post that is deleted.
	 */
	public function notify_remote_friend_post_deleted( $post_id ) {
		$post = \WP_Post::get_instance( $post_id );
		if ( 'post' !== $post->post_type ) {
			return;
		}

		$friends = User_Query::all_friends();
		$friends = $friends->get_results();

		foreach ( $friends as $friend_user ) {
			$friend_rest_url = $friend_user->get_rest_url();

			$response = wp_safe_remote_post(
				$friend_rest_url . '/post-deleted',
				array(
					'body'        => array(
						'post_id' => $post_id,
						'auth'    => $friend_user->get_friend_auth(),
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
	 * @param  \WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_post_deleted( $request ) {
		$tokens = explode( '-', $request->get_param( 'auth' ) );
		$user_id = $this->friends->access_control->verify_token( $tokens[0], isset( $tokens[1] ) ? $tokens[1] : null, isset( $tokens[2] ) ? $tokens[2] : null );
		if ( ! $user_id ) {
			return self::error( 'friends_request_failed' );
		}
		$friend_user     = new User( $user_id );
		$remote_post_id  = $request->get_param( 'post_id' );
		$remote_post_ids = $friend_user->get_remote_post_ids();

		if ( ! isset( $remote_post_ids[ $remote_post_id ] ) ) {
			return array(
				'deleted' => false,
			);
		}

		$post_id = $remote_post_ids[ $remote_post_id ];
		$post    = \WP_Post::get_instance( $post_id );
		if ( Friends::CPT === $post->post_type ) {
			wp_delete_post( $post_id );
		}

		return array(
			'deleted' => true,
		);
	}

	public function rest_embed_friend_post( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['url'] ) ) {
			return false;
		}
		$post_id = $this->friends->feed->url_to_postid( sanitize_text_field( wp_unslash( $_GET['url'] ) ) );
		if ( empty( $post_id ) ) {
			return false;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( get_post_type( $post_id ), apply_filters( 'friends_frontend_post_types', array() ) ) ) {
			return false;
		}

		enqueue_embed_scripts();
		$post = get_post( $post_id );
		$args = compact( 'post' );
		setup_postdata( $post );

		header( 'Content-type: text/html' );
		Friends::template_loader()->get_template_part( 'embed/header-embed', null, $args );
		Friends::template_loader()->get_template_part( 'embed/embed-content', null, $args );
		exit;
	}

	public function rest_get_feeds( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$feeds = User_Feed::get_all_due( true );
		$feeds = array_map(
			function ( $feed ) {
				return array(
					'id'        => $feed->get_id(),
					'url'       => $feed->get_url(),
					'parser'    => $feed->get_parser(),
					'last_log'  => $feed->get_last_log(),
					'next_poll' => $feed->get_next_poll(),
				);
			},
			$feeds
		);

		return $feeds;
	}

	public function rest_refresh_feed( $request ) {
		$feed_id = $request->get_param( 'id' );
		$feed = new User_Feed( get_term( intval( $feed_id ) ) );
		add_filter( 'notify_about_new_friend_post', '__return_false', 999 );
		add_action(
			'wp_feed_options',
			function ( &$feed ) {
				$feed->enable_cache( false );
			}
		);
		$new_posts = array();

		$friend_user = $feed->get_friend_user();
		$was_polled = false;
		if ( $friend_user && $feed->can_be_polled_now() ) {
			$feed->set_polling_now();
			$new_posts = $this->friends->feed->retrieve_feed( $feed );
			$feed->was_polled();
			if ( is_wp_error( $new_posts ) ) {
				return $new_posts;
			}
			$was_polled = true;
			$friend_user->delete_outdated_posts();
		}

		return array(
			'new_posts'  => count( $new_posts ),
			'url'        => $feed->get_url(),
			'was_polled' => $was_polled,
		);
	}

	public function rest_extension( $request ) {
		$return = array(
			'version'      => Friends::VERSION,
			'friends_url'  => home_url( '/friends/' ),
			'settings_url' => admin_url( 'admin.php?page=friends-browser-extension' ),
		);

		if ( 'POST' === $request->get_method() && $request->get_param( 'key' ) ) {
			if ( Access_Control::check_browser_api_key( $request->get_param( 'key' ) ) ) {
				$return = apply_filters( 'friends_browser_extension_rest_info', $return );
			} else {
				$return['error'] = 'Invalid API key';
			}
		}

		return $return;
	}


	/**
	 * Discover the REST URL for a friend site
	 *
	 * @param  array $feeds The URL of the site.
	 * @return string|\WP_Error The REST URL or an error.
	 */
	public function get_friends_rest_url( $feeds ) {
		foreach ( $feeds as $feed_url => $feed ) {
			if ( isset( $feed['parser'] ) && 'friends' === $feed['parser'] ) {
				return $feed_url;
			}
		}

		return false;
	}

	/**
	 * Discover the REST URL for a friend site
	 *
	 * @param  string $url The URL of the site.
	 * @return string|\WP_Error The REST URL or an error.
	 */
	public function discover_rest_url( $url ) {
		if ( ! is_string( $url ) || ! Friends::check_url( $url ) ) {
			return self::error( 'friends_invalid_url' );
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
			$dom = new \DOMDocument();
			set_error_handler( '__return_null' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			$dom->loadHTML( wp_remote_retrieve_body( $response ) );
			restore_error_handler();

			$xpath = new \DOMXpath( $dom );
			foreach ( $xpath->query( '//link[@rel and @href]' ) as $link ) {
				if ( 'friends-base-url' === $link->getAttribute( 'rel' ) ) {
					$rest_url = $link->getAttribute( 'href' );
					if ( is_string( $rest_url ) && Friends::check_url( $rest_url ) ) {
						return $rest_url;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Notify the friend's site via REST about the accepted friend request.
	 *
	 * Accepting a friend request is simply setting the role to "friend".
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  string $old_role  The old role.
	 */
	public function notify_remote_friend_request_accepted( $user_id, $new_role, $old_role ) {
		if ( 'friend' !== $new_role && 'acquaintance' !== $new_role ) {
			return;
		}

		if ( ! in_array( 'friend_request', $old_role, true ) ) {
			return;
		}

		$friend_user = new User( $user_id );

		$friend_rest_url  = $friend_user->get_rest_url();
		$current_user = wp_get_current_user();
		$body = array(
			'url'      => home_url(),
			'key'      => $friend_user->get_user_option( 'friends_out_token' ),
			'your_key' => $friend_user->get_user_option( 'friends_in_token' ),
			'name'     => $current_user->display_name,
			'icon_url' => get_avatar_url( $current_user->ID ),
		);
		$response     = wp_safe_remote_post(
			$friend_rest_url . '/accept-friend-request',
			array(
				'body'        => $body,
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// TODO find a way to message the user.
			return;
		}

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! isset( $json->ok ) ) {
			$friend_user->set_role( 'friend_request' );
			// TODO find a way to message the user.
			return;
		}

		$friend_user->make_friend();
	}
}
