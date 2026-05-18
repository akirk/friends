<?php
/**
 * WordPress Abilities API integration.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Registers Friends abilities for AI Assistant and other Abilities API clients.
 */
class Abilities {
	const CATEGORY = 'friends';

	/**
	 * A reference to the Friends object.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * Constructor.
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;

		if ( function_exists( 'did_action' ) && did_action( 'wp_abilities_api_categories_init' ) ) {
			$this->register_category();
		} else {
			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		}

		if ( function_exists( 'did_action' ) && did_action( 'wp_abilities_api_init' ) ) {
			$this->register_abilities();
		} else {
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		}

		add_filter( 'ai_assistant_ability_domains', array( $this, 'register_ability_domain' ) );
		add_filter( 'ai_assistant_ability_instructions', array( $this, 'ability_instructions' ), 10, 4 );
	}

	/**
	 * Register the Friends ability category.
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Friends', 'friends' ),
				'description' => __( 'Abilities for managing Friends subscriptions, feeds, and timeline items.', 'friends' ),
			)
		);
	}

	/**
	 * Register all Friends abilities.
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( $this->get_ability_configs() as $name => $config ) {
			wp_register_ability( $name, $config );
		}
	}

	/**
	 * Register AI Assistant domain hints.
	 *
	 * @param array $domains Registered ability domains.
	 * @return array Updated ability domains.
	 */
	public function register_ability_domain( $domains ) {
		$domains[ self::CATEGORY ] = 'Friends, friend posts, subscriptions, follows, feed reader, timeline, RSS, Atom, ActivityPub, Mastodon, Fediverse, refresh feeds, subscribe';
		return $domains;
	}

	/**
	 * Add follow-up instructions for AI Assistant after specific ability calls.
	 *
	 * @param string $instructions Existing instructions.
	 * @param string $ability_id   Ability ID that was executed.
	 * @param array  $args         Ability arguments.
	 * @param mixed  $result       Ability result.
	 * @return string Instructions for AI Assistant.
	 */
	public function ability_instructions( $instructions, $ability_id, $args, $result ) {
		unset( $args, $result );

		switch ( $ability_id ) {
			case 'friends/list-feed-items':
				return 'Summarize the returned timeline items with the author name, date, title or excerpt, and a link from local_url when available. Keep long content concise unless the user asked for full text.';

			case 'friends/add-subscription':
				return 'Tell the user which subscription was added, which feeds were activated, and whether an initial refresh created cached posts. Include local_url when present.';

			case 'friends/refresh-feed':
			case 'friends/refresh-feeds':
				return 'Report the number of feeds refreshed and cached posts created. If errors are present, list the affected feed URLs with the error messages.';
		}

		return $instructions;
	}

	/**
	 * Whether the current user may read Friends data.
	 *
	 * @return bool True if allowed.
	 */
	public function can_read() {
		return Friends::has_required_privileges();
	}

	/**
	 * Whether the current user may manage Friends data.
	 *
	 * @return bool True if allowed.
	 */
	public function can_manage() {
		return Friends::has_required_privileges();
	}

	/**
	 * List Friends subscriptions.
	 *
	 * @param array|null $input Ability input.
	 * @return array List result.
	 */
	public function list_subscriptions( $input = null ) {
		$input         = $this->normalize_input( $input );
		$limit         = $this->sanitize_limit( $input['limit'] ?? 20, 1, 100 );
		$include_feeds = $this->input_bool( $input, 'include_feeds', true );
		$search        = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';

		if ( '' !== $search ) {
			$query = User_Query::search( $search );
		} else {
			$query = User_Query::all_subscriptions();
		}

		$subscriptions = array();
		foreach ( array_slice( $query->get_results(), 0, $limit ) as $subscription ) {
			$subscriptions[] = $this->subscription_to_array( $subscription, $include_feeds );
		}

		return array(
			'count'         => count( $subscriptions ),
			'total'         => (int) $query->get_total(),
			'subscriptions' => $subscriptions,
		);
	}

	/**
	 * Get one Friends subscription.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error Subscription result or error.
	 */
	public function get_subscription( $input = null ) {
		$input = $this->normalize_input( $input );
		$subscription = $this->get_subscription_from_input( $input );

		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		return $this->subscription_to_array( $subscription, true );
	}

	/**
	 * Discover feeds for a URL.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error Discovery result or error.
	 */
	public function discover_feeds( $input = null ) {
		$input = $this->normalize_input( $input );
		$url   = $this->normalize_url( $input['url'] ?? '' );

		if ( ! $url ) {
			return new \WP_Error( 'invalid-url', __( 'A valid URL is required.', 'friends' ) );
		}

		$feeds = $this->friends->feed->discover_available_feeds( $url );
		if ( is_wp_error( $feeds ) ) {
			return $feeds;
		}

		return array(
			'url'   => $url,
			'count' => count( $feeds ),
			'feeds' => $this->discovered_feeds_to_array( $feeds ),
		);
	}

	/**
	 * Add a Friends subscription.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error Add result or error.
	 */
	public function add_subscription( $input = null ) {
		$input = $this->normalize_input( $input );
		$url   = $this->normalize_url( $input['url'] ?? '' );

		if ( ! $url ) {
			return new \WP_Error( 'invalid-url', __( 'A valid URL is required.', 'friends' ) );
		}

		if ( 0 === strpos( $url, home_url() ) ) {
			return new \WP_Error( 'friend-yourself', __( 'It seems like you sent a friend request to yourself.', 'friends' ) );
		}

		$discovered_feeds = array();
		$selected_urls    = $this->sanitize_url_list( $input['feed_urls'] ?? array() );
		if ( empty( $selected_urls ) ) {
			$discovered_feeds = $this->friends->feed->discover_available_feeds( $url );
			if ( is_wp_error( $discovered_feeds ) ) {
				return $discovered_feeds;
			}
			if ( empty( $discovered_feeds ) ) {
				return new \WP_Error( 'no-feed-found', __( 'No suitable feed was found at the provided address.', 'friends' ) );
			}
			$selected_urls = $this->select_discovered_feed_urls( $discovered_feeds );
		}

		if ( empty( $selected_urls ) ) {
			return new \WP_Error( 'no-subscribable-feed-found', __( 'No subscribable feed was found at the provided address.', 'friends' ) );
		}

		if ( empty( $discovered_feeds ) ) {
			$discovered_feeds = $this->feeds_from_urls( $selected_urls );
		}

		$username = isset( $input['username'] ) ? User::sanitize_username( $input['username'] ) : '';
		if ( ! $username ) {
			$username = apply_filters( 'friends_suggest_user_login', User::get_user_login_for_url( $url ), $url );
			$better_username = User::get_user_login_from_feeds( $discovered_feeds );
			if ( $better_username ) {
				$username = trim( $better_username, '-' );
			}
		}
		$username = User::sanitize_username( $username );
		if ( ! $username ) {
			return new \WP_Error( 'invalid-username', __( 'The subscription username could not be determined.', 'friends' ) );
		}

		if ( ! is_multisite() && username_exists( $username ) ) {
			return new \WP_Error( 'username-exists', __( 'This username is already registered. Please choose another one.', 'friends' ) );
		}

		$existing = User::get_user( $username );
		if ( $existing && ! is_wp_error( $existing ) ) {
			return new \WP_Error( 'already-subscribed', __( 'You are already subscribed to this site.', 'friends' ), $this->subscription_to_array( $existing, true ) );
		}

		$display_name = isset( $input['display_name'] ) ? sanitize_text_field( $input['display_name'] ) : '';
		if ( ! $display_name ) {
			$display_name = apply_filters( 'friends_suggest_display_name', User::get_display_name_for_url( $url ), $url );
			$better_display_name = User::get_display_name_from_feeds( $discovered_feeds );
			if ( $better_display_name ) {
				$display_name = $better_display_name;
			}
		}

		$feed_options = $this->build_feed_options( $selected_urls, $discovered_feeds, $display_name );
		if ( empty( $feed_options ) ) {
			return new \WP_Error( 'no-subscribable-feed-found', __( 'No subscribable feed was found at the provided address.', 'friends' ) );
		}

		$avatar      = $this->first_feed_value( $feed_options, 'avatar' );
		$description = $this->first_feed_value( $feed_options, 'description' );
		$subscription = User::create( $username, 'subscription', $url, $display_name, $avatar, $description );
		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		$saved_feeds = $subscription->save_feeds( $feed_options );
		if ( is_wp_error( $saved_feeds ) ) {
			return $saved_feeds;
		}

		$activated_feeds = array();
		foreach ( $feed_options as $feed_url => $options ) {
			$new_feed = $subscription->subscribe( $feed_url, $options );
			if ( is_wp_error( $new_feed ) ) {
				return $new_feed;
			}

			do_action( 'friends_user_feed_activated', $new_feed );
			$activated_feeds[] = $this->feed_to_array( $new_feed );
		}

		$refresh_result = null;
		if ( $this->input_bool( $input, 'refresh', true ) ) {
			$refresh_result = $this->refresh_feeds(
				array(
					'subscription_id' => $subscription->ID,
					'force'           => true,
				)
			);
		}

		$result = array(
			'subscription'    => $this->subscription_to_array( $subscription, true ),
			'activated_feeds' => $activated_feeds,
		);

		if ( $refresh_result && ! is_wp_error( $refresh_result ) ) {
			$result['refresh'] = $refresh_result;
		}

		return $result;
	}

	/**
	 * List cached feed items.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error Feed item result or error.
	 */
	public function list_feed_items( $input = null ) {
		$input = $this->normalize_input( $input );
		$limit = $this->sanitize_limit( $input['limit'] ?? 20, 1, 50 );

		$args = array(
			'post_type'           => Friends::CPT,
			'post_status'         => array( 'publish', 'private' ),
			'posts_per_page'      => $limit,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		if ( isset( $input['search'] ) && '' !== trim( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( isset( $input['post_format'] ) && '' !== trim( $input['post_format'] ) ) {
			$args['tax_query'] = $this->friends->wp_query_get_post_format_tax_query( array(), sanitize_key( $input['post_format'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$subscription = null;
		if ( ! empty( $input['subscription_id'] ) || ! empty( $input['username'] ) ) {
			$subscription = $this->get_subscription_from_input( $input );
			if ( is_wp_error( $subscription ) ) {
				return $subscription;
			}
			$args = $subscription->modify_get_posts_args_by_author( $args );
		}

		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->post_to_array( $post );
		}

		return array(
			'count'        => count( $items ),
			'subscription' => $subscription ? $this->subscription_to_array( $subscription, false ) : null,
			'items'        => $items,
		);
	}

	/**
	 * Refresh cached feeds.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error Refresh result or error.
	 */
	public function refresh_feeds( $input = null ) {
		$input = $this->normalize_input( $input );
		$force = $this->input_bool( $input, 'force', false );
		$subscription = null;

		if ( ! empty( $input['subscription_id'] ) || ! empty( $input['username'] ) ) {
			$subscription = $this->get_subscription_from_input( $input );
			if ( is_wp_error( $subscription ) ) {
				return $subscription;
			}
			$feeds = $force ? $subscription->get_active_feeds() : $subscription->get_due_feeds();
		} else {
			$feeds = User_Feed::get_all_due( $force );
		}

		$result = $this->new_refresh_result( $subscription );

		add_filter( 'notify_about_new_friend_post', '__return_false', 999 );
		foreach ( $feeds as $feed ) {
			$this->refresh_feed_into_result( $result, $feed, $force );
		}
		remove_filter( 'notify_about_new_friend_post', '__return_false', 999 );

		return $result;
	}

	/**
	 * Refresh one cached feed.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error Refresh result or error.
	 */
	public function refresh_feed( $input = null ) {
		$input = $this->normalize_input( $input );
		$feed  = $this->get_feed_from_input( $input );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		if ( ! $feed->is_active() ) {
			return new \WP_Error( 'feed-inactive', __( 'The requested feed is not active.', 'friends' ) );
		}

		$friend_user = $feed->get_friend_user();
		if ( ! $friend_user ) {
			return new \WP_Error( 'subscription-not-found', __( 'The requested feed is not linked to a subscription.', 'friends' ) );
		}

		$result = $this->new_refresh_result( $friend_user );
		$force  = $this->input_bool( $input, 'force', true );

		add_filter( 'notify_about_new_friend_post', '__return_false', 999 );
		$this->refresh_feed_into_result( $result, $feed, $force );
		remove_filter( 'notify_about_new_friend_post', '__return_false', 999 );

		return $result;
	}

	/**
	 * Build ability registrations.
	 *
	 * @return array Ability configs keyed by ability ID.
	 */
	private function get_ability_configs() {
		return array(
			'friends/list-subscriptions' => array(
				'label'               => __( 'List Friends subscriptions', 'friends' ),
				'description'         => __( 'Returns Friends subscriptions with profile details and optionally their configured feeds.', 'friends' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->list_subscriptions_input_schema(),
				'output_schema'       => $this->list_subscriptions_output_schema(),
				'execute_callback'    => array( $this, 'list_subscriptions' ),
				'permission_callback' => array( $this, 'can_read' ),
				'meta'                => $this->ability_meta( true, false, 'Use this to find subscription IDs, usernames, and feed IDs before calling get-subscription, list-feed-items, refresh-feed, refresh-feeds, or add-subscription follow-up actions.' ),
			),
			'friends/get-subscription'   => array(
				'label'               => __( 'Get Friends subscription', 'friends' ),
				'description'         => __( 'Returns full details for one Friends subscription by subscription_id or username, including configured feeds.', 'friends' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->subscription_lookup_input_schema(),
				'output_schema'       => $this->subscription_output_schema(),
				'execute_callback'    => array( $this, 'get_subscription' ),
				'permission_callback' => array( $this, 'can_read' ),
				'meta'                => $this->ability_meta( true, false, 'Call list-subscriptions first when the user gives an ambiguous name. The returned id can be passed as subscription_id to other Friends abilities.' ),
			),
			'friends/discover-feeds'     => array(
				'label'               => __( 'Discover feeds', 'friends' ),
				'description'         => __( 'Discovers RSS, Atom, ActivityPub, and other supported feeds for a URL without subscribing to them.', 'friends' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->url_input_schema(),
				'output_schema'       => $this->discover_feeds_output_schema(),
				'execute_callback'    => array( $this, 'discover_feeds' ),
				'permission_callback' => array( $this, 'can_read' ),
				'meta'                => $this->ability_meta( true, false, 'Use this before add-subscription when the user wants to review available feeds. It performs a remote fetch but does not change WordPress data.' ),
			),
			'friends/add-subscription'   => array(
				'label'               => __( 'Add Friends subscription', 'friends' ),
				'description'         => __( 'Creates a Friends subscription for a URL, activates selected or auto-discovered feeds, and optionally refreshes them immediately.', 'friends' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->add_subscription_input_schema(),
				'output_schema'       => $this->add_subscription_output_schema(),
				'execute_callback'    => array( $this, 'add_subscription' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'meta'                => $this->ability_meta( false, false, 'This changes Friends subscription data and may fetch remote feeds. If feed_urls is omitted, it selects discovered feeds marked autoselect, otherwise the first supported discovered feed.' ),
			),
			'friends/list-feed-items'    => array(
				'label'               => __( 'List Friends feed items', 'friends' ),
				'description'         => __( 'Returns cached Friends timeline items, optionally filtered by subscription, search text, or post format.', 'friends' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->list_feed_items_input_schema(),
				'output_schema'       => $this->list_feed_items_output_schema(),
				'execute_callback'    => array( $this, 'list_feed_items' ),
				'permission_callback' => array( $this, 'can_read' ),
				'meta'                => $this->ability_meta( true, false, 'Use this for timeline questions and summaries. Prefer subscription_id when filtering to a specific subscription.' ),
			),
			'friends/refresh-feed'       => array(
				'label'               => __( 'Refresh Friends feed', 'friends' ),
				'description'         => __( 'Fetches one configured Friends feed by feed_id and stores new cached posts.', 'friends' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->refresh_feed_input_schema(),
				'output_schema'       => $this->refresh_feeds_output_schema(),
				'execute_callback'    => array( $this, 'refresh_feed' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'meta'                => $this->ability_meta( false, false, 'Use this when the user asks to refresh one specific feed. Get feed_id from get-subscription or list-subscriptions. The ability refreshes immediately by default.' ),
			),
			'friends/refresh-feeds'      => array(
				'label'               => __( 'Refresh Friends feeds', 'friends' ),
				'description'         => __( 'Fetches due or forced Friends feeds and stores new cached posts.', 'friends' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->refresh_feeds_input_schema(),
				'output_schema'       => $this->refresh_feeds_output_schema(),
				'execute_callback'    => array( $this, 'refresh_feeds' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'meta'                => $this->ability_meta( false, false, 'This fetches remote feeds and can create cached friend posts. Set force to true only when the user asks to refresh now rather than just due feeds.' ),
			),
		);
	}

	/**
	 * Build common ability meta.
	 *
	 * @param bool   $is_readonly  Whether the ability is read-only.
	 * @param bool   $destructive  Whether the ability can be destructive.
	 * @param string $instructions AI Assistant instructions.
	 * @return array Ability meta.
	 */
	private function ability_meta( $is_readonly, $destructive, $instructions ) {
		return array(
			'annotations'  => array(
				'readonly'     => (bool) $is_readonly,
				'destructive'  => (bool) $destructive,
				'instructions' => $instructions,
			),
			'show_in_rest' => true,
		);
	}

	/**
	 * Normalize ability input.
	 *
	 * @param mixed $input Raw input.
	 * @return array Normalized input.
	 */
	private function normalize_input( $input ) {
		return is_array( $input ) ? $input : array();
	}

	/**
	 * Read a boolean input value.
	 *
	 * @param array  $input   Input data.
	 * @param string $key     Input key.
	 * @param bool   $default_value Default value.
	 * @return bool Boolean input.
	 */
	private function input_bool( $input, $key, $default_value = false ) {
		if ( ! array_key_exists( $key, $input ) ) {
			return $default_value;
		}
		return rest_sanitize_boolean( $input[ $key ] );
	}

	/**
	 * Sanitize a limit.
	 *
	 * @param mixed $limit Limit input.
	 * @param int   $min   Minimum limit.
	 * @param int   $max   Maximum limit.
	 * @return int Sanitized limit.
	 */
	private function sanitize_limit( $limit, $min, $max ) {
		$limit = absint( $limit );
		if ( $limit < $min ) {
			return $min;
		}
		if ( $limit > $max ) {
			return $max;
		}
		return $limit;
	}

	/**
	 * Normalize a URL-like input.
	 *
	 * @param string $url URL input.
	 * @return string|false Valid URL or false.
	 */
	private function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}

		if ( ! wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			$url = apply_filters( 'friends_rewrite_incoming_url', 'https://' . $url, $url );
		}

		return Friends::check_url( $url ) ? esc_url_raw( $url ) : false;
	}

	/**
	 * Sanitize a list of URLs.
	 *
	 * @param mixed $urls URL list.
	 * @return array Sanitized URLs.
	 */
	private function sanitize_url_list( $urls ) {
		if ( ! is_array( $urls ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $urls as $url ) {
			$url = $this->normalize_url( $url );
			if ( $url ) {
				$sanitized[] = $url;
			}
		}
		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Get a subscription from lookup input.
	 *
	 * @param array $input Ability input.
	 * @return User|\WP_Error Subscription or error.
	 */
	private function get_subscription_from_input( $input ) {
		if ( ! empty( $input['subscription_id'] ) ) {
			$subscription = User::get_user_by_id( absint( $input['subscription_id'] ) );
		} elseif ( ! empty( $input['username'] ) ) {
			$subscription = User::get_by_username( sanitize_text_field( $input['username'] ) );
		} else {
			return new \WP_Error( 'missing-subscription', __( 'A subscription_id or username is required.', 'friends' ) );
		}

		if ( is_wp_error( $subscription ) ) {
			return new \WP_Error( 'subscription-not-found', __( 'The requested subscription was not found.', 'friends' ) );
		}

		if ( ! $subscription || ! $subscription instanceof User || ! $subscription->has_cap( 'subscription' ) ) {
			return new \WP_Error( 'subscription-not-found', __( 'The requested subscription was not found.', 'friends' ) );
		}

		return $subscription;
	}

	/**
	 * Get a feed from lookup input.
	 *
	 * @param array $input Ability input.
	 * @return User_Feed|\WP_Error Feed or error.
	 */
	private function get_feed_from_input( $input ) {
		if ( empty( $input['feed_id'] ) ) {
			return new \WP_Error( 'missing-feed', __( 'A feed_id is required.', 'friends' ) );
		}

		$term = get_term( absint( $input['feed_id'] ), User_Feed::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return new \WP_Error( 'feed-not-found', __( 'The requested feed was not found.', 'friends' ) );
		}

		return new User_Feed( $term );
	}

	/**
	 * Create an empty refresh result structure.
	 *
	 * @param User|null $subscription Optional subscription context.
	 * @return array Refresh result.
	 */
	private function new_refresh_result( ?User $subscription = null ) {
		return array(
			'feed_count'      => 0,
			'new_post_count'  => 0,
			'new_post_ids'    => array(),
			'errors'          => array(),
			'subscription'    => $subscription ? $this->subscription_to_array( $subscription, false ) : null,
			'refreshed_feeds' => array(),
		);
	}

	/**
	 * Refresh a feed and merge its result into a refresh result structure.
	 *
	 * @param array     $result Refresh result.
	 * @param User_Feed $feed   Feed to refresh.
	 * @param bool      $force  Whether to ignore due date and polling locks.
	 */
	private function refresh_feed_into_result( &$result, User_Feed $feed, $force ) {
		if ( ! $force && ! $feed->can_be_polled_now() ) {
			return;
		}

		$feed->set_polling_now();
		$posts = $this->friends->feed->retrieve_feed( $feed );
		$feed->was_polled();

		++$result['feed_count'];
		$feed_result = $this->feed_to_array( $feed );
		if ( is_wp_error( $posts ) ) {
			$error = array(
				'feed_id' => (int) $feed->get_id(),
				'url'     => $feed->get_url(),
				'code'    => $posts->get_error_code(),
				'message' => $posts->get_error_message(),
			);
			$result['errors'][] = $error;
			$feed_result['error'] = $error;
		} else {
			$post_ids = array_map( 'intval', array_keys( $posts ) );
			$result['new_post_ids'] = array_merge( $result['new_post_ids'], $post_ids );
			$result['new_post_count'] += count( $post_ids );
			$feed_result['new_post_ids'] = $post_ids;
		}
		$result['refreshed_feeds'][] = $feed_result;
	}

	/**
	 * Serialize a subscription.
	 *
	 * @param User $subscription Subscription user.
	 * @param bool $include_feeds Whether to include feeds.
	 * @return array Serialized subscription.
	 */
	private function subscription_to_array( User $subscription, $include_feeds = true ) {
		$feeds = $subscription->get_feeds();
		$active_feed_count = 0;
		foreach ( $feeds as $feed ) {
			if ( $feed->is_active() ) {
				++$active_feed_count;
			}
		}

		$data = array(
			'id'                => (int) $subscription->ID,
			'username'          => (string) $subscription->user_login,
			'name'              => (string) ( $subscription->display_name ? $subscription->display_name : $subscription->user_login ),
			'url'               => (string) $subscription->user_url,
			'description'       => $this->clean_text( (string) $subscription->description, 600 ),
			'avatar_url'        => (string) $subscription->get_avatar_url(),
			'local_url'         => (string) $subscription->get_local_friends_page_url(),
			'starred'           => (bool) $subscription->is_starred(),
			'feed_count'        => count( $feeds ),
			'active_feed_count' => $active_feed_count,
		);

		if ( $subscription instanceof Subscription ) {
			$data['term_id'] = (int) $subscription->get_term_id();
		}

		if ( $include_feeds ) {
			$data['feeds'] = array();
			foreach ( $feeds as $feed ) {
				$data['feeds'][] = $this->feed_to_array( $feed );
			}
		}

		return $data;
	}

	/**
	 * Serialize a feed.
	 *
	 * @param User_Feed $feed User feed.
	 * @return array Serialized feed.
	 */
	private function feed_to_array( User_Feed $feed ) {
		$friend_user = $feed->get_friend_user();

		return array(
			'id'             => (int) $feed->get_id(),
			'url'            => (string) $feed->get_url(),
			'title'          => (string) $feed->get_title(),
			'active'         => (bool) $feed->is_active(),
			'parser'         => (string) $feed->get_parser(),
			'post_format'    => (string) $feed->get_post_format(),
			'mime_type'      => (string) $feed->get_mime_type(),
			'next_poll'      => (string) $feed->get_next_poll(),
			'last_log'       => (string) $feed->get_last_log(),
			'local_html_url' => $friend_user ? (string) $friend_user->get_local_friends_page_url() : '',
		);
	}

	/**
	 * Serialize a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Serialized post.
	 */
	private function post_to_array( \WP_Post $post ) {
		$author = User::get_post_author( $post );
		$post_format = get_post_format( $post );
		if ( ! $post_format ) {
			$post_format = 'standard';
		}

		return array(
			'id'           => (int) $post->ID,
			'title'        => (string) $post->post_title,
			'excerpt'      => $this->clean_text( $post->post_excerpt ? $post->post_excerpt : $post->post_content, 500 ),
			'content_text' => $this->clean_text( $post->post_content, 1200 ),
			'date'         => mysql2date( DATE_ATOM, $post->post_date_gmt, false ),
			'status'       => (string) $post->post_status,
			'post_format'  => (string) $post_format,
			'local_url'    => (string) get_permalink( $post ),
			'external_url' => (string) $post->guid,
			'reblog'       => (bool) get_post_meta( $post->ID, 'reblog', true ),
			'author'       => $author ? $this->subscription_to_array( $author, false ) : null,
		);
	}

	/**
	 * Clean and truncate text for ability output.
	 *
	 * @param string $text Text input.
	 * @param int    $max_length Maximum length.
	 * @return string Clean text.
	 */
	private function clean_text( $text, $max_length ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text ) > $max_length ) {
				return rtrim( mb_substr( $text, 0, $max_length - 3 ) ) . '...';
			}
			return $text;
		}

		if ( strlen( $text ) > $max_length ) {
			return rtrim( substr( $text, 0, $max_length - 3 ) ) . '...';
		}

		return $text;
	}

	/**
	 * Convert discovered feeds to serializable arrays.
	 *
	 * @param array $feeds Discovered feeds.
	 * @return array Serialized feeds.
	 */
	private function discovered_feeds_to_array( $feeds ) {
		$serialized = array();
		foreach ( $feeds as $url => $feed ) {
			$feed = array_merge(
				array(
					'url'               => $url,
					'title'             => '',
					'type'              => '',
					'rel'               => '',
					'parser'            => '',
					'parser_confidence' => 0,
					'autoselect'        => false,
					'post-format'       => '',
					'avatar'            => '',
					'description'       => '',
				),
				(array) $feed
			);

			$serialized[] = array(
				'url'               => (string) $feed['url'],
				'title'             => (string) $feed['title'],
				'type'              => (string) $feed['type'],
				'rel'               => (string) $feed['rel'],
				'parser'            => (string) $feed['parser'],
				'parser_confidence' => (int) $feed['parser_confidence'],
				'autoselect'        => (bool) $feed['autoselect'],
				'post_format'       => (string) $feed['post-format'],
				'avatar'            => (string) $feed['avatar'],
				'description'       => $this->clean_text( (string) $feed['description'], 600 ),
			);
		}

		return $serialized;
	}

	/**
	 * Select feed URLs from discovered feeds.
	 *
	 * @param array $feeds Discovered feeds.
	 * @return array Selected feed URLs.
	 */
	private function select_discovered_feed_urls( $feeds ) {
		$selected = array();
		foreach ( $feeds as $url => $feed ) {
			if ( ! empty( $feed['autoselect'] ) && ! empty( $feed['parser'] ) && 'unsupported' !== $feed['parser'] ) {
				$selected[] = $url;
			}
		}

		if ( ! empty( $selected ) ) {
			return $selected;
		}

		foreach ( $feeds as $url => $feed ) {
			if ( ! empty( $feed['parser'] ) && 'unsupported' !== $feed['parser'] ) {
				return array( $url );
			}
		}

		return array();
	}

	/**
	 * Create discovered-feed-shaped records from URLs.
	 *
	 * @param array $urls Feed URLs.
	 * @return array Feed records.
	 */
	private function feeds_from_urls( $urls ) {
		$feeds = array();
		foreach ( $urls as $url ) {
			$feeds[ $url ] = array(
				'url'               => $url,
				'title'             => Friends::url_truncate( $url, 100 ),
				'type'              => 'application/rss+xml',
				'rel'               => 'alternate',
				'parser'            => 'simplepie',
				'parser_confidence' => 0,
			);
		}
		return $feeds;
	}

	/**
	 * Build feed options for subscription storage.
	 *
	 * @param array  $selected_urls Selected URLs.
	 * @param array  $discovered_feeds Discovered feeds.
	 * @param string $display_name Subscription display name.
	 * @return array Feed options.
	 */
	private function build_feed_options( $selected_urls, $discovered_feeds, $display_name ) {
		$options = array();
		foreach ( $selected_urls as $feed_url ) {
			if ( empty( $discovered_feeds[ $feed_url ] ) ) {
				$discovered_feeds[ $feed_url ] = $this->feeds_from_urls( array( $feed_url ) )[ $feed_url ];
			}

			$feed = $discovered_feeds[ $feed_url ];
			if ( empty( $feed['parser'] ) || 'unsupported' === $feed['parser'] ) {
				continue;
			}

			if ( isset( $feed['type'] ) ) {
				$feed['mime-type'] = $feed['type'];
				unset( $feed['type'] );
			}

			$options[ $feed_url ] = array(
				'active'      => true,
				'parser'      => sanitize_key( $feed['parser'] ),
				'post-format' => isset( $feed['post-format'] ) ? sanitize_key( $feed['post-format'] ) : 'standard',
				'mime-type'   => isset( $feed['mime-type'] ) ? sanitize_text_field( $feed['mime-type'] ) : 'application/rss+xml',
				'title'       => isset( $feed['title'] ) && $feed['title'] ? sanitize_text_field( $feed['title'] ) : sprintf(
					// translators: %s is a subscription display name.
					__( '%s RSS Feed', 'friends' ),
					$display_name
				),
			);

			foreach ( array( 'avatar', 'description' ) as $key ) {
				if ( ! empty( $feed[ $key ] ) ) {
					$options[ $feed_url ][ $key ] = sanitize_text_field( $feed[ $key ] );
				}
			}
		}

		return $options;
	}

	/**
	 * Return the first non-empty value from feed options.
	 *
	 * @param array  $feed_options Feed options.
	 * @param string $key          Option key.
	 * @return string|null First value.
	 */
	private function first_feed_value( $feed_options, $key ) {
		foreach ( $feed_options as $feed ) {
			if ( ! empty( $feed[ $key ] ) ) {
				return $feed[ $key ];
			}
		}
		return null;
	}

	/**
	 * Schema for a URL input.
	 *
	 * @return array Schema.
	 */
	private function url_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'url' => array(
					'type'        => 'string',
					'description' => __( 'Website, profile, or feed URL to inspect.', 'friends' ),
					'format'      => 'uri',
				),
			),
			'required'             => array( 'url' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Schema for subscription lookup input.
	 *
	 * @return array Schema.
	 */
	private function subscription_lookup_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'subscription_id' => array(
					'type'        => 'integer',
					'description' => __( 'Subscription ID returned by friends/list-subscriptions.', 'friends' ),
				),
				'username'        => array(
					'type'        => 'string',
					'description' => __( 'Subscription username, for example example.com.', 'friends' ),
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Schema for listing subscriptions.
	 *
	 * @return array Schema.
	 */
	private function list_subscriptions_input_schema() {
		return array(
			'type'                 => array( 'object', 'null' ),
			'properties'           => array(
				'search'        => array(
					'type'        => 'string',
					'description' => __( 'Optional search text for subscription username or display name.', 'friends' ),
				),
				'limit'         => array(
					'type'        => 'integer',
					'description' => __( 'Maximum number of subscriptions to return, from 1 to 100.', 'friends' ),
					'default'     => 20,
				),
				'include_feeds' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether to include each subscription\'s configured feeds.', 'friends' ),
					'default'     => true,
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Schema for adding a subscription.
	 *
	 * @return array Schema.
	 */
	private function add_subscription_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'url'          => array(
					'type'        => 'string',
					'description' => __( 'Website, profile, or feed URL to subscribe to.', 'friends' ),
					'format'      => 'uri',
				),
				'feed_urls'    => array(
					'type'        => 'array',
					'description' => __( 'Optional exact feed URLs to activate. If omitted, Friends auto-selects discovered feeds.', 'friends' ),
					'items'       => array(
						'type'   => 'string',
						'format' => 'uri',
					),
				),
				'username'     => array(
					'type'        => 'string',
					'description' => __( 'Optional Friends username. Defaults to a value inferred from the URL or discovered feed metadata.', 'friends' ),
				),
				'display_name' => array(
					'type'        => 'string',
					'description' => __( 'Optional display name. Defaults to a value inferred from the URL or discovered feed metadata.', 'friends' ),
				),
				'refresh'      => array(
					'type'        => 'boolean',
					'description' => __( 'Whether to fetch the activated feeds immediately after subscribing.', 'friends' ),
					'default'     => true,
				),
			),
			'required'             => array( 'url' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Schema for listing feed items.
	 *
	 * @return array Schema.
	 */
	private function list_feed_items_input_schema() {
		return array(
			'type'                 => array( 'object', 'null' ),
			'properties'           => array(
				'subscription_id' => array(
					'type'        => 'integer',
					'description' => __( 'Optional subscription ID to filter by.', 'friends' ),
				),
				'username'        => array(
					'type'        => 'string',
					'description' => __( 'Optional subscription username to filter by.', 'friends' ),
				),
				'search'          => array(
					'type'        => 'string',
					'description' => __( 'Optional search text for cached feed items.', 'friends' ),
				),
				'post_format'     => array(
					'type'        => 'string',
					'description' => __( 'Optional WordPress post format slug, such as status, image, link, or standard.', 'friends' ),
				),
				'limit'           => array(
					'type'        => 'integer',
					'description' => __( 'Maximum number of feed items to return, from 1 to 50.', 'friends' ),
					'default'     => 20,
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Schema for refreshing feeds.
	 *
	 * @return array Schema.
	 */
	private function refresh_feeds_input_schema() {
		return array(
			'type'                 => array( 'object', 'null' ),
			'properties'           => array(
				'subscription_id' => array(
					'type'        => 'integer',
					'description' => __( 'Optional subscription ID to refresh. Omit to refresh all due feeds.', 'friends' ),
				),
				'username'        => array(
					'type'        => 'string',
					'description' => __( 'Optional subscription username to refresh. Omit to refresh all due feeds.', 'friends' ),
				),
				'force'           => array(
					'type'        => 'boolean',
					'description' => __( 'Whether to refresh active feeds immediately even if they are not due.', 'friends' ),
					'default'     => false,
				),
			),
			'additionalProperties' => false,
		);
	}

	/**
	 * Schema for refreshing one feed.
	 *
	 * @return array Schema.
	 */
	private function refresh_feed_input_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'feed_id' => array(
					'type'        => 'integer',
					'description' => __( 'Feed ID returned by friends/get-subscription or friends/list-subscriptions.', 'friends' ),
				),
				'force'   => array(
					'type'        => 'boolean',
					'description' => __( 'Whether to refresh immediately even if the feed is not due.', 'friends' ),
					'default'     => true,
				),
			),
			'required'             => array( 'feed_id' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Output schema for subscription list.
	 *
	 * @return array Schema.
	 */
	private function list_subscriptions_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'count'         => array( 'type' => 'integer' ),
				'total'         => array( 'type' => 'integer' ),
				'subscriptions' => array(
					'type'  => 'array',
					'items' => $this->subscription_output_schema(),
				),
			),
		);
	}

	/**
	 * Output schema for a subscription.
	 *
	 * @return array Schema.
	 */
	private function subscription_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'type'        => 'integer',
					'description' => __( 'Use as subscription_id in related Friends abilities.', 'friends' ),
				),
				'term_id'           => array( 'type' => 'integer' ),
				'username'          => array( 'type' => 'string' ),
				'name'              => array( 'type' => 'string' ),
				'url'               => array( 'type' => 'string' ),
				'description'       => array( 'type' => 'string' ),
				'avatar_url'        => array( 'type' => 'string' ),
				'local_url'         => array( 'type' => 'string' ),
				'starred'           => array( 'type' => 'boolean' ),
				'feed_count'        => array( 'type' => 'integer' ),
				'active_feed_count' => array( 'type' => 'integer' ),
				'feeds'             => array(
					'type'  => 'array',
					'items' => $this->feed_output_schema(),
				),
			),
		);
	}

	/**
	 * Output schema for a feed.
	 *
	 * @return array Schema.
	 */
	private function feed_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'             => array( 'type' => 'integer' ),
				'url'            => array( 'type' => 'string' ),
				'title'          => array( 'type' => 'string' ),
				'active'         => array( 'type' => 'boolean' ),
				'parser'         => array( 'type' => 'string' ),
				'post_format'    => array( 'type' => 'string' ),
				'mime_type'      => array( 'type' => 'string' ),
				'next_poll'      => array( 'type' => 'string' ),
				'last_log'       => array( 'type' => 'string' ),
				'local_html_url' => array( 'type' => 'string' ),
				'new_post_ids'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'error'          => array(
					'type'       => 'object',
					'properties' => array(
						'feed_id' => array( 'type' => 'integer' ),
						'url'     => array( 'type' => 'string' ),
						'code'    => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
			),
		);
	}

	/**
	 * Mark a schema as nullable.
	 *
	 * @param array $schema Schema.
	 * @return array Nullable schema.
	 */
	private function nullable_schema( $schema ) {
		$type = isset( $schema['type'] ) ? $schema['type'] : 'object';
		if ( ! is_array( $type ) ) {
			$type = array( $type );
		}
		if ( ! in_array( 'null', $type, true ) ) {
			$type[] = 'null';
		}
		$schema['type'] = $type;
		return $schema;
	}

	/**
	 * Output schema for discovered feeds.
	 *
	 * @return array Schema.
	 */
	private function discover_feeds_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'url'   => array( 'type' => 'string' ),
				'count' => array( 'type' => 'integer' ),
				'feeds' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'url'               => array( 'type' => 'string' ),
							'title'             => array( 'type' => 'string' ),
							'type'              => array( 'type' => 'string' ),
							'rel'               => array( 'type' => 'string' ),
							'parser'            => array( 'type' => 'string' ),
							'parser_confidence' => array( 'type' => 'integer' ),
							'autoselect'        => array( 'type' => 'boolean' ),
							'post_format'       => array( 'type' => 'string' ),
							'avatar'            => array( 'type' => 'string' ),
							'description'       => array( 'type' => 'string' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Output schema for add subscription.
	 *
	 * @return array Schema.
	 */
	private function add_subscription_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'subscription'    => $this->subscription_output_schema(),
				'activated_feeds' => array(
					'type'  => 'array',
					'items' => $this->feed_output_schema(),
				),
				'refresh'         => $this->refresh_feeds_output_schema(),
			),
		);
	}

	/**
	 * Output schema for feed item list.
	 *
	 * @return array Schema.
	 */
	private function list_feed_items_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'count'        => array( 'type' => 'integer' ),
				'subscription' => $this->nullable_schema( $this->subscription_output_schema() ),
				'items'        => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'           => array( 'type' => 'integer' ),
							'title'        => array( 'type' => 'string' ),
							'excerpt'      => array( 'type' => 'string' ),
							'content_text' => array( 'type' => 'string' ),
							'date'         => array( 'type' => 'string' ),
							'status'       => array( 'type' => 'string' ),
							'post_format'  => array( 'type' => 'string' ),
							'local_url'    => array( 'type' => 'string' ),
							'external_url' => array( 'type' => 'string' ),
							'reblog'       => array( 'type' => 'boolean' ),
							'author'       => $this->nullable_schema( $this->subscription_output_schema() ),
						),
					),
				),
			),
		);
	}

	/**
	 * Output schema for refresh feeds.
	 *
	 * @return array Schema.
	 */
	private function refresh_feeds_output_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'feed_count'      => array( 'type' => 'integer' ),
				'new_post_count'  => array( 'type' => 'integer' ),
				'new_post_ids'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'errors'          => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'feed_id' => array( 'type' => 'integer' ),
							'url'     => array( 'type' => 'string' ),
							'code'    => array( 'type' => 'string' ),
							'message' => array( 'type' => 'string' ),
						),
					),
				),
				'subscription'    => $this->nullable_schema( $this->subscription_output_schema() ),
				'refreshed_feeds' => array(
					'type'  => 'array',
					'items' => $this->feed_output_schema(),
				),
			),
		);
	}
}
