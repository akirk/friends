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
			if ( Admin::check_browser_api_key( $request->get_param( 'key' ) ) ) {
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
}
