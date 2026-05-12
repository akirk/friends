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

		register_rest_route(
			self::PREFIX,
			'extension/action',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_extension_action' ),
				'permission_callback' => array( $this, 'browser_extension_action_permission_callback' ),
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'required' => true,
					),
					'key'    => array(
						'type'     => 'string',
						'required' => true,
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
			'friends_invalid_parameters' => __( 'Not all necessary parameters were provided.', 'friends' ),
			'friends_invalid_url'        => __( 'An invalid URL was provided.', 'friends' ),
			'friends_no_request'         => __( 'No request was found.', 'friends' ),
			'friends_invalid_site'       => __( 'An invalid site was provided.', 'friends' ),
			'unknown'                    => __( 'An unknown error occurred.', 'friends' ),
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
			$current_user = self::get_browser_extension_user( $request->get_param( 'key' ) );
			if ( ! is_wp_error( $current_user ) ) {
				$context = $this->get_browser_extension_request_context( $request, $current_user );

				/**
				 * Allows plugins to register actions for the Friends browser extension.
				 *
				 * Each action is an associative array with:
				 * - `name` (string, required) — label shown in the extension popup.
				 * - `url` (string, required) — target URL; may contain `{current_url}` which the extension substitutes with the current page URL (URL-encoded).
				 * - `method` (string, optional) — if `"POST"`, the extension submits a form instead of opening a link.
				 * - `fields` (object, optional) — for POST actions, key/value pairs of form fields; values may contain `{current_url}` (raw) and `{page_html}` placeholders.
				 * - `run` (string, optional) — if `"inline"`, the extension handles the response in place instead of opening a new tab.
				 * - `inputs` (array, optional) — user-editable fields for inline actions.
				 * - `category` (string, optional) — groups actions under a named header; actions without a category appear under the default "Actions" header.
				 *
				 * Example:
				 * ```php
				 * add_filter( 'friends_browser_extension_actions', function ( $actions, $current_user, $context ) {
				 *     $actions[] = array(
				 *         'name' => 'Save to Collection',
				 *         'url'  => home_url( '/collect/?url={current_url}' ),
				 *     );
				 *     return $actions;
				 * }, 10, 3 );
				 * ```
				 *
				 * @param array    $actions      The array of actions.
				 * @param \WP_User $current_user The current user.
				 * @param array    $context      Browser extension request context: key, version, user, and request.
				 * @return array The modified array of actions.
				 */
				$previous_user_id = get_current_user_id();
				wp_set_current_user( $current_user->ID );
				try {
					$actions = apply_filters( 'friends_browser_extension_actions', array(), $current_user, $context );
				} finally {
					wp_set_current_user( $previous_user_id );
				}

				$return['actions'] = array_values(
					array_filter(
						$actions,
						function ( $action ) {
							return is_array( $action )
								&& ! empty( $action['name'] )
								&& is_string( $action['name'] )
								&& ! empty( $action['url'] )
								&& is_string( $action['url'] );
						}
					)
				);
			} else {
				$return['error'] = 'Invalid API key';
			}
		}

		return $return;
	}

	/**
	 * Validate a browser extension inline action request.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return true|\WP_Error True if the request is allowed, otherwise an error.
	 */
	public function browser_extension_action_permission_callback( $request ) {
		$current_user = self::get_browser_extension_user( $request->get_param( 'key' ) );
		if ( is_wp_error( $current_user ) ) {
			return new \WP_Error(
				$current_user->get_error_code(),
				$current_user->get_error_message(),
				array( 'status' => 401 )
			);
		}

		$attributes                                      = $request->get_attributes();
		$attributes['friends_browser_extension_user']    = $current_user;
		$attributes['friends_browser_extension_context'] = $this->get_browser_extension_request_context( $request, $current_user );
		$request->set_attributes( $attributes );

		return true;
	}

	/**
	 * Handle a browser extension inline action.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response The REST response.
	 */
	public function rest_extension_action( $request ) {
		$url_params = $request->get_url_params();
		$action     = isset( $url_params['action'] ) ? $url_params['action'] : $request->get_param( 'action' );
		$action     = sanitize_key( (string) wp_unslash( $action ) );
		if ( ! $action ) {
			return self::browser_extension_action_error(
				new \WP_Error( 'friends_missing_browser_extension_action', __( 'No browser extension action was provided.', 'friends' ) ),
				400
			);
		}

		$attributes   = $request->get_attributes();
		$current_user = isset( $attributes['friends_browser_extension_user'] ) ? $attributes['friends_browser_extension_user'] : self::get_browser_extension_user( $request->get_param( 'key' ) );
		if ( is_wp_error( $current_user ) ) {
			return self::browser_extension_action_error( $current_user, 401 );
		}

		$context           = isset( $attributes['friends_browser_extension_context'] ) ? $attributes['friends_browser_extension_context'] : $this->get_browser_extension_request_context( $request, $current_user );
		$context['action'] = $action;

		$previous_user_id = get_current_user_id();
		wp_set_current_user( $current_user->ID );

		try {
			/**
			 * Handles a browser extension inline action.
			 *
			 * Return a \WP_REST_Response, \WP_Error, array, or scalar value. Returning null means the action
			 * was not handled.
			 *
			 * @param mixed            $response     The action response.
			 * @param string           $action       The browser extension action name.
			 * @param \WP_REST_Request $request      The REST request.
			 * @param \WP_User         $current_user The user authenticated by the browser extension key.
			 * @param array            $context      Browser extension request context.
			 */
			$response = apply_filters( 'friends_browser_extension_action', null, $action, $request, $current_user, $context );

			/**
			 * Handles a specific browser extension inline action.
			 *
			 * The dynamic portion of the hook name, `$action`, is the sanitized action name from the
			 * request's `action` parameter.
			 *
			 * @param mixed            $response     The action response.
			 * @param \WP_REST_Request $request      The REST request.
			 * @param \WP_User         $current_user The user authenticated by the browser extension key.
			 * @param array            $context      Browser extension request context.
			 */
			$response = apply_filters( "friends_browser_extension_action_{$action}", $response, $request, $current_user, $context );
		} finally {
			wp_set_current_user( $previous_user_id );
		}

		if ( null === $response ) {
			return self::browser_extension_action_error(
				new \WP_Error( 'friends_unknown_browser_extension_action', __( 'Unknown browser extension action.', 'friends' ) ),
				404
			);
		}

		return self::prepare_browser_extension_action_response( $response );
	}

	/**
	 * Get the user authenticated by a browser extension key.
	 *
	 * @param string $key The browser extension API key.
	 * @return \WP_User|\WP_Error The authenticated user or an error.
	 */
	private static function get_browser_extension_user( $key ) {
		$key = sanitize_text_field( (string) wp_unslash( $key ) );
		if ( ! $key ) {
			return new \WP_Error( 'friends_invalid_browser_extension_key', __( 'Invalid API key', 'friends' ) );
		}

		$user = Admin::get_browser_api_key_user( $key );
		if ( ! $user ) {
			return new \WP_Error( 'friends_invalid_browser_extension_key', __( 'Invalid API key', 'friends' ) );
		}

		return $user;
	}

	/**
	 * Build browser extension request context for plugin filters.
	 *
	 * @param \WP_REST_Request $request      The REST request.
	 * @param \WP_User         $current_user The user authenticated by the browser extension key.
	 * @return array Browser extension request context.
	 */
	private function get_browser_extension_request_context( $request, $current_user ) {
		$key     = sanitize_text_field( (string) wp_unslash( $request->get_param( 'key' ) ) );
		$version = sanitize_text_field( (string) wp_unslash( $request->get_param( 'version' ) ) );

		if ( ! $version ) {
			$version = sanitize_text_field( (string) wp_unslash( $request->get_param( 'extension_version' ) ) );
		}

		return array(
			'key'                   => $key,
			'browser_extension_key' => $key,
			'version'               => $version,
			'extension_version'     => $version,
			'user'                  => $current_user,
			'request'               => $request,
		);
	}

	/**
	 * Prepare a browser extension action response.
	 *
	 * @param mixed $response The handler response.
	 * @return \WP_REST_Response The REST response.
	 */
	private static function prepare_browser_extension_action_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return self::browser_extension_action_error( $response, 400 );
		}

		if ( $response instanceof \WP_REST_Response ) {
			return $response;
		}

		if ( true === $response ) {
			$response = array(
				'success' => true,
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Format a browser extension action error response.
	 *
	 * @param \WP_Error $error  The error.
	 * @param int       $status The default HTTP status.
	 * @return \WP_REST_Response The REST response.
	 */
	private static function browser_extension_action_error( \WP_Error $error, $status ) {
		$error_data = $error->get_error_data();
		if ( is_array( $error_data ) && ! empty( $error_data['status'] ) ) {
			$status = absint( $error_data['status'] );
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
			$status
		);
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
}
