<?php
/**
 * Friend User
 *
 * This wraps \WP_User and adds friend specific functions.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the User part of the Friends Plugin.
 *
 * @since 0.21
 *
 * @package Friends
 * @author Alex Kirk
 */
class User extends \WP_User {
	/**
	 * Caches the feed rules.
	 *
	 * @var array
	 */
	public static $feed_rules = array();

	/**
	 * Caches the feed catch all action.
	 *
	 * @var array
	 */
	public static $feed_catch_all = array();

	public static function get_by_username( $username ) {
		$subscription = Subscription::get_by_username( $username );
		if ( $subscription && ! is_wp_error( $subscription ) ) {
			return $subscription;
		}

		$user = get_user_by( 'login', $username );
		if ( $user && ! is_wp_error( $user ) ) {
			return new self( $user );
		}
		return $user;
	}

	public static function get_post_author( \WP_Post $post ) {
		$subscriptions = wp_get_object_terms( $post->ID, Subscription::TAXONOMY );
		if ( empty( $subscriptions ) ) {
			return new self( $post->post_author );
		}

		return new Subscription( reset( $subscriptions ) );
	}

	/**
	 * Create a User with a specific Friends-related role
	 *
	 * @param      string $user_login    The user login.
	 * @param      string $role          The role: subscription,
	 *                                   pending_friend_request,
	 *                                   or friend_request.
	 * @param      string $url           The site URL for which
	 *                                   to create the user.
	 * @param      string $display_name  The user's display name.
	 * @param      string $avatar_url      The user_avatar_url URL.
	 * @param      string $description   A description for the user.
	 * @param      string $user_registered   When the user was registered.
	 * @param      bool   $subscription_override  Whether to override the automatic creation of a subscription.
	 *
	 * @return     User|\WP_Error  The created user or an error.
	 */
	public static function create( $user_login, $role, $url, $display_name = null, $avatar_url = null, $description = null, $user_registered = null, $subscription_override = false ) {
		if ( 'subscription' === $role && ! $subscription_override ) {
			return Subscription::create( $user_login, $role, $url, $display_name, $avatar_url, $description );
		}

		$role_rank = array_flip(
			array(
				'subscription',
				'pending_friend_request',
				'friend_request',
			)
		);
		if ( ! isset( $role_rank[ $role ] ) ) {
			return new \WP_Error( 'invalid_role', 'Invalid role for creation specified' );
		}

		if ( is_multisite() ) {
			$user = get_user_by( 'login', $user_login );
			if ( $user && ! self::is_friends_plugin_user( $user ) ) {
				if ( ! is_user_member_of_blog( $user->ID, get_current_blog_id() ) ) {
					add_user_to_blog( get_current_blog_id(), $user->ID, $role );
				}
			}
		}

		$friend_user = self::get_by_username( $user_login );
		if ( $friend_user && ! is_wp_error( $friend_user ) && ! $subscription_override ) {
			if ( $friend_user instanceof Subscription ) {
				$friend_user = Subscription::convert_to_user( $friend_user );
			}

			foreach ( $role_rank as $_role => $rank ) {
				if ( $rank > $role_rank[ $role ] ) {
					break;
				}
				if ( $friend_user->has_cap( $_role ) ) {
					// Upgrade user role.
					$friend_user->set_role( $role );
					break;
				}
			}
			return $friend_user;
		}

		$userdata = array(
			'user_login'   => $user_login,
			'display_name' => $display_name,
			'first_name'   => $display_name,
			'nickname'     => $display_name,
			'description'  => $description,
			'user_url'     => $url,
			'user_pass'    => wp_generate_password( 256 ),
			'role'         => $role,
		);

		if ( $user_registered ) {
			$userdata['user_registered'] = $user_registered;
		}

		$friend_id = wp_insert_user( $userdata );

		$friend_user = new User( $friend_id );
		$friend_user->update_user_option( 'friends_new_friend', true );
		$friend_user->update_user_icon_url( $avatar_url );

		do_action( 'friends_after_create_friend_user', $friend_user );
		return $friend_user;
	}

	public static function register_wrapper_hooks() {
		add_filter( 'friends_get_user_feeds', array( 'Friends\User', 'friends_get_user_feeds' ), 10, 2 );
	}

	/**
	 * Get the feeds for a user.
	 *
	 * @param      array    $feeds  The feeds.
	 * @param      \WP_User $user   The user.
	 */
	public static function friends_get_user_feeds( $feeds, $user ) {
		$user_feeds = $user->get_feeds();
		if ( is_array( $user_feeds ) ) {
			$feeds = array_merge( $feeds, $user_feeds );
		}
		return $feeds;
	}

	/**
	 * Convert a site URL to a username
	 *
	 * @param  string $url The site URL in question.
	 * @param  bool   $multisite_shortname Whether to use the multisite shortname.
	 * @return string The corresponding username.
	 */
	public static function get_user_login_for_url( $url, $multisite_shortname = true ) {
		$pre_user_login = apply_filters( 'friends_pre_get_user_login_for_url', false, $url );
		if ( $pre_user_login ) {
			return $pre_user_login;
		}

		$multisite_user = self::get_multisite_user( $url );
		if ( $multisite_user && $multisite_shortname ) {
			return $multisite_user->user_login;
		}

		$user_login = self::sanitize_username( self::get_display_name_for_url( $url, $multisite_shortname ) );
		return $user_login;
	}

	/**
	 * Convert a site URL to a display name
	 *
	 * @param  string $url The site URL in question.
	 * @param  bool   $multisite_shortname Whether to use the multisite shortname.
	 * @return string The corresponding display name.
	 */
	public static function get_display_name_for_url( $url, $multisite_shortname = true ) {
		$multisite_user = self::get_multisite_user( $url );
		if ( $multisite_user && $multisite_shortname ) {
			return $multisite_user->display_name;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = wp_parse_url( $url, PHP_URL_PATH );

		$display_name = sanitize_text_field( preg_replace( '#^www\.#', '', preg_replace( '#[^a-z0-9.-]+#i', ' ', strtolower( $host . ' ' . $path ) ) ) );
		return $display_name;
	}

	/**
	 * Discover a user login from feeds
	 *
	 * @param  array $feeds A list of feeds.
	 * @return string The corresponding user login.
	 */
	public static function get_user_login_from_feeds( $feeds ) {
		foreach ( $feeds as $feed ) {
			if ( isset( $feed['suggested-username'] ) ) {
				return sanitize_text_field( $feed['suggested-username'] );
			}
		}
		$display_name = self::get_display_name_from_feeds( $feeds );
		if ( $display_name ) {
			return self::sanitize_username( $display_name );
		}

		return false;
	}
	/**
	 * Discover a display name from feeds
	 *
	 * @param  array $feeds A list of feeds.
	 * @return string The corresponding display name.
	 */
	public static function get_display_name_from_feeds( $feeds ) {
		foreach ( $feeds as $feed ) {
			if ( 'self' === $feed['rel'] && ! empty( $feed['title'] ) ) {
				$name = trim( preg_replace( '/(\s[–—|-]|[:,])\s.*$/u', '', $feed['title'] ) );
				return sanitize_text_field( $name );
			}
		}

		return false;
	}

	/**
	 * If the URL is on the same multisite, get the main user.
	 *
	 * @param  string $url The site URL in question.
	 * @return bool|WP_User false or the user.
	 */
	public static function get_multisite_user( $url ) {
		if ( ! is_multisite() ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			$path = '';
		}

		$site_id = get_blog_id_from_url( $host, trailingslashit( $path ) );
		if ( ! $site_id ) {
			return false;
		}

		// Let's find the user that is associated with that blog.
		switch_to_blog( $site_id );
		$friend_user_id = Friends::get_main_friend_user_id();
		restore_current_blog();

		return get_user_by( 'id', $friend_user_id );
	}

	/**
	 * Sanitize the username according to some more rules than just sanitize_user()
	 *
	 * @param      string $username  The username.
	 *
	 * @return     string  The sanitized username.
	 */
	public static function sanitize_username( $username ) {
		$username = preg_replace( '/[^a-z0-9.]+/', '-', strtolower( remove_accents( $username ) ) );
		$username = sanitize_user( $username );
		return $username;
	}

	/**
	 * Determines whether the specified user is a friends plugin user.
	 *
	 * @param      \WP_User $user   The user.
	 *
	 * @return     bool     True if the specified user is a friends plugin user, False otherwise.
	 */
	public static function is_friends_plugin_user( \WP_User $user ) {
		return $user->has_cap( 'friend' ) || $user->has_cap( 'pending_friend_request' ) || $user->has_cap( 'friend_request' ) || $user->has_cap( 'subscription' );
	}

	/**
	 * Get a friend user for a user_login.
	 *
	 * @param  string $user_login The user login.
	 * @return User|false The friend user or false.
	 */
	public static function get_user( $user_login ) {
		$user = get_user_by( 'login', $user_login );
		if ( $user ) {
			if ( self::is_friends_plugin_user( $user ) ) {
				return new self( $user );
			}

			return false;
		}
		return $user;
	}

	/**
	 * Get a friend user for a user_id.
	 *
	 * @param  string $user_id The user ID.
	 * @return User|false The friend user or false.
	 */
	public static function get_user_by_id( $user_id ) {
		if ( $user_id > 1e10 ) {
			$term = get_term( $user_id - 1e10, Subscription::TAXONOMY );
			if ( $term && ! is_wp_error( $term ) ) {
				return new Subscription( $term );
			}
		}

		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			if ( $user->has_cap( 'friend' ) || $user->has_cap( 'pending_friend_request' ) || $user->has_cap( 'friend_request' ) || $user->has_cap( 'subscription' ) ) {
				return new self( $user );
			}

			return false;
		}
		return $user;
	}

	public function __get( $key ) {
		if ( 'user_url' === $key && empty( $this->data->user_url ) && is_multisite() ) {
			$site = get_active_blog_for_user( $this->ID );
			if ( $site ) {
				// Ensure we're using the same URL protocol.
				$this->data->user_url = set_url_scheme( $site->siteurl );
				return $this->data->user_url;
			}
		}

		return parent::__get( $key );
	}


	public function insert_post( array $postarr, $wp_error = false, $fire_after_hooks = true ) {
		$current_user = wp_get_current_user();

		// Posts and revisions should be associated with this user.
		wp_set_current_user( $this->ID );

		$post = wp_insert_post( $postarr, $wp_error, $fire_after_hooks );

		if ( $current_user ) {
			wp_set_current_user( $current_user->ID );
		}

		return $post;
	}

	public function get_object_id() {
		return $this->ID;
	}

	public function save() {
		return wp_update_user( $this );
	}

	/**
	 * Save multiple feeds for a user.
	 *
	 * @param      array $feeds  The feed URLs to subscribe to.
	 *
	 * @return     array(\WP_Term)|\WP_error  $user The new associated user or an error object.
	 */
	public function save_feeds( $feeds = array() ) {
		$errors = new \WP_Error();
		foreach ( $feeds as $feed_url => $options ) {
			if ( ! is_string( $feed_url ) || ! Friends::check_url( $feed_url ) ) {
				$errors->add( 'invalid-url', 'An invalid URL was provided', $feed_url );
				unset( $feeds[ $feed_url ] );
				continue;
			}

			$default_options = array(
				'active'      => false,
				'parser'      => 'simplepie',
				'post-format' => 'standard',
				'mime-type'   => 'application/rss+xml',
				'title'       => $this->display_name . ' RSS Feed',
			);

			$feeds[ $feed_url ] = array_merge( $default_options, $options );
		}

		$all_urls = array();
		foreach ( wp_get_object_terms( $this->get_object_id(), User_Feed::TAXONOMY ) as $term ) {
			$url = str_replace( '&amp;', '&', $term->name );
			$all_urls[ $url ] = $term->term_id;
		}

		$user_feeds = wp_set_object_terms( $this->get_object_id(), array_keys( array_merge( $all_urls, $feeds ) ), User_Feed::TAXONOMY );
		if ( is_wp_error( $user_feeds ) ) {
			return $user_feeds;
		}

		foreach ( wp_get_object_terms( $this->get_object_id(), User_Feed::TAXONOMY ) as $term ) {
			$url = str_replace( '&amp;', '&', $term->name );
			$all_urls[ $url ] = $term->term_id;
		}

		foreach ( $feeds as $url => $feed_options ) {
			if ( ! isset( $all_urls[ $url ] ) ) {
				continue;
			}
			$term_id = $all_urls[ $url ];
			foreach ( $feed_options as $key => $value ) {
				if ( in_array( $key, array( 'active', 'parser', 'post-format', 'mime-type', 'title', 'interval', 'modifier' ) ) ) {

					if ( metadata_exists( 'term', $term_id, $key ) ) {
						update_metadata( 'term', $term_id, $key, $value );
					} else {
						add_metadata( 'term', $term_id, $key, $value, true );
					}
				}
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return $all_urls;
	}

	/**
	 * Save a feed url for a user.
	 *
	 * @param      string $feed_url  The feed URL to subscribe to.
	 * @param      array  $options   The options.
	 *
	 * @return     User_Feed|\WP_Error  $user The new feed or an error object.
	 */
	public function save_feed( $feed_url, $options = array() ) {
		$all_urls = $this->save_feeds( array( $feed_url => $options ) );

		if ( is_wp_error( $all_urls ) ) {
			return $all_urls;
		}

		if ( ! isset( $all_urls[ $feed_url ] ) ) {
			return new \WP_Error(
				'error-saving-feed',
				sprintf(
					// translators: %s is a URL.
					__( 'The feed %s could not be saved', 'friends' ),
					$feed_url
				)
			);
		}

		$term = get_term( $all_urls[ $feed_url ], User_Feed::TAXONOMY );

		return new User_Feed( $term );
	}

	/**
	 * Subscribe to a friends site without becoming a friend
	 *
	 * @param      string $feed_url  The feed URL to subscribe to.
	 * @param      array  $options   The options.
	 *
	 * @return     User_Feed|\WP_error  $user The new associated user or an error object.
	 */
	public function subscribe( $feed_url, $options = array() ) {
		$options['active'] = true;
		return $this->save_feed( $feed_url, $options );
	}

	/**
	 * Retrieve the posts for this user
	 *
	 * @return array The new posts.
	 */
	public function retrieve_posts_from_active_feeds() {
		return $this->retrieve_posts_from_feeds( $this->get_active_feeds() );
	}

	/**
	 * Retrieve the posts for these user feeds
	 *
	 * @param      array $feeds  The feeds to retrieve from.
	 *
	 * @return array The new posts.
	 */
	public function retrieve_posts_from_feeds( array $feeds ) {
		$friends = Friends::get_instance();
		$new_posts = array();
		foreach ( $feeds as $feed ) {
			$posts = $friends->feed->retrieve_feed( $feed );
			if ( ! is_wp_error( $posts ) ) {
				foreach ( $posts as $post_id => $item ) {
					$new_posts[ $post_id ] = $item;
				}
			}
		}
		return $new_posts;
	}

	public function modify_query_by_author( \WP_Query $query ) {
		$query->set( 'author', $this->ID );
		if ( ! user_can( $this->ID, 'friends_plugin' ) || user_can( $this->ID, 'manage_options' ) ) {
			// If the user doesn't belong to the friends plugin, only show their local posts so that subcriptions don't spill in.
			$query->set( 'post_type', 'post' );
		}
		return $query;
	}

	public function modify_get_posts_args_by_author( $args ) {
		$args['author'] = $this->ID;
		return $args;
	}

	public function delete() {
		if ( is_multisite() ) {
			remove_user_from_blog( $this->ID, get_current_blog_id() );
		} else {
			wp_delete_user( $this->ID );
		}
		// The content (posts and feeds) will be deleted with the 'delete_user' hook.
	}

	/**
	 * Delete posts the user decided to automatically delete.
	 */
	public function delete_outdated_posts() {
		$deleted_posts = array();

		$args = array(
			'post_type'      => Friends::CPT,
			'post_status'    => array( 'publish', 'trash' ),
			'fields'         => 'ids',
			'posts_per_page' => 10,
		);

		if ( $this->is_retention_days_enabled() ) {
			$args['date_query'] = array(
				'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $this->get_retention_days() * 24 ) . 'hours' ) ),
			);

			$query = new \WP_Query();
			foreach ( $args as $key => $value ) {
				$query->set( $key, $value );
			}
			$query = $this->modify_query_by_author( $query );

			foreach ( $query->get_posts() as $post_id ) {
				$post_id = Friends::maybe_delete_outdated_post( $post_id, 'date overflow' );
				if ( $post_id ) {
					$deleted_posts[] = $post_id;
				}
			}
		}

		unset( $args['date_query'] );
		$args['orderby'] = 'date';
		$args['order'] = 'desc';
		if ( $this->is_retention_number_enabled() ) {
			$args['offset'] = $this->get_retention_number();
			$query = new \WP_Query();
			foreach ( $args as $key => $value ) {
				$query->set( $key, $value );
			}
			$query = $this->modify_query_by_author( $query );
			foreach ( $query->get_posts() as $post_id ) {
				$post_id = Friends::maybe_delete_outdated_post( $post_id, 'local number overflow' );
				if ( $post_id ) {
					$deleted_posts[] = $post_id;
				}
			}
		}

		return $deleted_posts;
	}

	/**
	 * Check whether the retention by number of posts is enabled.
	 *
	 * @return boolean Whether the retention by number of posts is enabled.
	 */
	public function is_retention_number_enabled() {
		return $this->get_user_option( 'friends_enable_retention_number' );
	}

	/**
	 * Enable or disable the retention by number of posts.
	 *
	 * @param boolean $enabled Whether the retention by number of posts should be enabled.
	 * @return boolean Whether the retention by number of posts is enabled.
	 */
	public function set_retention_number_enabled( $enabled ) {
		$this->update_user_option( 'friends_enable_retention_number', boolval( $enabled ) );
		return boolval( $enabled );
	}

	/**
	 * Get the retention by number of posts.
	 *
	 * @return int The retention by number of posts.
	 */
	public function get_retention_number() {
		$number = $this->get_user_option( 'friends_retention_number' );
		if ( $number <= 0 ) {
			return 200;
		}

		return $number;
	}

	/**
	 * Set the retention by number of posts.
	 *
	 * @param  int $number   The retention by number of posts.
	 * @return int The retention by number of posts.
	 */
	public function set_retention_number( $number ) {
		$number = max( 1, $number );
		$this->update_user_option( 'friends_retention_number', $number );
		return $number;
	}
	/**
	 * Check whether the retention by days of posts is enabled.
	 *
	 * @return boolean Whether the retention by days of posts is enabled.
	 */
	public function is_retention_days_enabled() {
		return $this->get_user_option( 'friends_enable_retention_days' );
	}

	/**
	 * Enable or disable the retention by days of posts.
	 *
	 * @param boolean $enabled Whether the retention by days of posts should be enabled.
	 * @return boolean Whether the retention by days of posts is enabled.
	 */
	public function set_retention_days_enabled( $enabled ) {
		$this->update_user_option( 'friends_enable_retention_days', boolval( $enabled ) );
		return boolval( $enabled );
	}

	/**
	 * Get the retention by days of posts.
	 *
	 * @return int The retention by days of posts.
	 */
	public function get_retention_days() {
		$days = $this->get_user_option( 'friends_retention_days' );
		if ( $days <= 0 ) {
			return 14;
		}

		return $days;
	}

	/**
	 * Set the retention by days of posts.
	 *
	 * @param  int $days   The retention by days of posts.
	 * @return int The retention by days of posts.
	 */
	public function set_retention_days( $days ) {
		$days = max( 1, intval( $days ) );
		return $this->update_user_option( 'friends_retention_days', $days );
	}

	/**
	 * Gets the post counts by post format.
	 *
	 * @return     int  The post count.
	 */
	public function get_post_in_trash_count() {
		global $wpdb;
		$post_types = apply_filters( 'friends_frontend_post_types', array() );

		$cache_key = 'get_post_in_trash_count_' . $this->get_term_id() . '_' . implode( '_', $post_types );
		if ( false !== wp_cache_get( $cache_key, 'friends' ) ) {
			return wp_cache_get( $cache_key, 'friends' );
		}

		$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				sprintf(
					'SELECT COUNT(*)
					FROM %s
					WHERE post_author = %%d
					AND post_type IN ( %s )
					AND post_status = "trash"',
					$wpdb->posts,
					implode( ',', array_fill( 0, count( $post_types ), '%s' ) )
				),
				array_merge(
					array( $this->ID ),
					$post_types
				)
			)
		);

		wp_cache_set( $cache_key, intval( $count ), 'friends', HOUR_IN_SECONDS - 60 );
		return intval( $count );
	}

	/**
	 * Gets the post counts by post format.
	 *
	 * @return     array  The post counts.
	 */
	public function get_post_count_by_post_format() {
		$cache_key = 'get_post_count_by_post_format_' . $this->ID;

		$counts = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $counts ) {
			return $counts;
		}
		$counts = get_transient( $cache_key );
		if ( false !== $counts ) {
			return $counts;
		}
		$counts = array();
		$post_types = apply_filters( 'friends_frontend_post_types', array() );
		$post_formats_term_ids = array();
		foreach ( get_post_format_slugs() as $post_format ) {
			$term = get_term_by( 'slug', 'post-format-' . $post_format, 'post_format' );
			if ( $term ) {
				$post_formats_term_ids[ $term->term_taxonomy_id ] = $post_format;
			}
		}

		global $wpdb;

		$counts = array();
		$counts['standard'] = $wpdb->get_var(// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				sprintf(
					"SELECT COUNT(DISTINCT posts.ID)
					FROM %s AS posts
					JOIN %s AS relationships_post_format

					WHERE posts.post_author = %%d
					AND posts.post_status IN ( 'publish', 'private' )
					AND posts.post_type IN ( %s )
					AND relationships_post_format.object_id = posts.ID",
					$wpdb->posts,
					$wpdb->term_relationships,
					implode( ',', array_fill( 0, count( $post_types ), '%s' ) )
				),
				array_merge(
					array( $this->ID ),
					$post_types
				)
			)
		);

		if ( ! empty( $post_formats_term_ids ) ) {
			$post_format_counts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					sprintf(
						"SELECT relationships_post_format.term_taxonomy_id AS post_format_id, COUNT(relationships_post_format.term_taxonomy_id) AS count
						FROM %s AS posts
						JOIN %s AS relationships_post_format

						WHERE posts.post_author = %%d
						AND posts.post_status IN ( 'publish', 'private' )
						AND posts.post_type IN ( %s )
						AND relationships_post_format.object_id = posts.ID
						AND relationships_post_format.term_taxonomy_id IN ( %s )
						GROUP BY relationships_post_format.term_taxonomy_id",
						$wpdb->posts,
						$wpdb->term_relationships,
						implode( ',', array_fill( 0, count( $post_types ), '%s' ) ),
						implode( ',', array_fill( 0, count( $post_formats_term_ids ), '%d' ) )
					),
					array_merge(
						array( $this->ID ),
						$post_types,
						array_keys( $post_formats_term_ids )
					)
				)
			);

			foreach ( $post_format_counts as $row ) {
				$counts[ $post_formats_term_ids[ $row->post_format_id ] ] = $row->count;
				$counts['standard'] -= $row->count;
			}
		}

		$counts = array_filter( $counts );

		set_transient( $cache_key, $counts, HOUR_IN_SECONDS - 60 );
		wp_cache_set( $cache_key, $counts, 'friends', HOUR_IN_SECONDS - 60 );
		return $counts;
	}

	/**
	 * Gets the post stats.
	 *
	 * @return     object  The post stats.
	 */
	public function get_post_stats() {
		global $wpdb;
		$cache_key = 'post_stats_author_' . $this->ID;
		$post_stats = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $post_stats ) {
			return $post_stats;
		}
		$post_types = apply_filters( 'friends_frontend_post_types', array() );
		$post_stats = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				sprintf(
					'SELECT SUM(
					LENGTH( ID ) +
					LENGTH( post_author ) +
					LENGTH( post_date ) +
					LENGTH( post_date_gmt ) +
					LENGTH( post_content ) +
					LENGTH( post_title ) +
					LENGTH( post_excerpt ) +
					LENGTH( post_status ) +
					LENGTH( comment_status ) +
					LENGTH( ping_status ) +
					LENGTH( post_password ) +
					LENGTH( post_name ) +
					LENGTH( to_ping ) +
					LENGTH( pinged ) +
					LENGTH( post_modified ) +
					LENGTH( post_modified_gmt ) +
					LENGTH( post_content_filtered ) +
					LENGTH( post_parent ) +
					LENGTH( guid ) +
					LENGTH( menu_order ) +
					LENGTH( post_type ) +
					LENGTH( post_mime_type ) +
					LENGTH( comment_count )
					) AS total_size,
					COUNT(*) as post_count
				FROM %s WHERE post_author = %%d AND post_type IN ( %s )',
					$wpdb->posts,
					implode( ',', array_fill( 0, count( $post_types ), '%s' ) )
				),
				array_merge( array( $this->ID ), $post_types )
			),
			ARRAY_A
		);
		$post_stats['earliest_post_date'] = mysql2date(
			'U',
			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					sprintf(
						'SELECT MIN(post_date) FROM %s WHERE post_author = %%d AND post_status = "publish" AND post_type IN ( %s )',
						$wpdb->posts,
						implode( ',', array_fill( 0, count( $post_types ), '%s' ) )
					),
					array_merge( array( $this->ID ), $post_types )
				)
			)
		);

		wp_cache_set( $cache_key, $post_stats, 'friends', HOUR_IN_SECONDS );
		return $post_stats;
	}

	public function get_all_post_ids() {
		global $wpdb;
		$post_types = apply_filters( 'friends_frontend_post_types', array() );

		$cache_key = 'get_all_post_ids_' . $this->ID . '_' . implode( '_', $post_types );
		$post_ids = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $post_ids ) {
			return $post_ids;
		}

		$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				sprintf(
					'SELECT ID FROM %s WHERE post_author = %%d AND post_type IN ( %s )',
					$wpdb->posts,
					implode( ',', array_fill( 0, count( $post_types ), '%s' ) )
				),
				array_merge(
					array( $this->ID ),
					$post_types
				)
			)
		);

		wp_cache_set( $cache_key, $post_ids, 'friends', HOUR_IN_SECONDS - 60 );
		return $post_ids;
	}

	/**
	 * Update a friend's avatar URL
	 *
	 * @param  string $user_icon_url  The user icon URL.
	 * @return string|false The URL that was set or false.
	 */
	public function update_user_icon_url( $user_icon_url ) {
		if ( ! $user_icon_url ) {
			$user_icon_url = Mf2\resolveUrl( $this->user_url, '/favicon.ico' );
		}

		if ( $user_icon_url && Friends::check_url( $user_icon_url ) ) {
			$this->update_user_option( 'friends_user_icon_url', $user_icon_url );
			return $user_icon_url;
		}

		return false;
	}

	/**
	 * Retrieve the rules for this feed.
	 *
	 * @return array The rules set by the user for this feed.
	 */
	public function get_feed_rules() {
		if ( ! isset( self::$feed_rules[ $this->ID ] ) ) {
			$this->set_feed_rules( $this->get_user_option( 'friends_feed_rules' ) );
		}
		return self::$feed_rules[ $this->ID ];
	}

	/**
	 * Set the rules for this feed.
	 *
	 * @param      array $rules  The rules to set for this feed.
	 * @return array The rules set by the user for this feed.
	 */
	public function set_feed_rules( $rules ) {
		self::$feed_rules[ $this->ID ] = Feed::validate_feed_rules( $rules );
		return self::$feed_rules[ $this->ID ];
	}


	/**
	 * Retrieve the catch_all value for this feed.
	 *
	 * @return array The rules set by the user for this feed.
	 */
	public function get_feed_catch_all() {
		if ( ! isset( self::$feed_catch_all[ $this->ID ] ) ) {
			$this->set_feed_catch_all( $this->get_user_option( 'friends_feed_catch_all' ) );
		}
		return self::$feed_catch_all[ $this->ID ];
	}


	/**
	 * Set the catch_all value for this feed.
	 *
	 * @param      array $catchall  The catchall rule to set for this feed.
	 * @return array The catchall rukle set by the user for this feed.
	 */
	public function set_feed_catch_all( $catchall ) {
		self::$feed_catch_all[ $this->ID ] = Feed::validate_feed_catch_all( $catchall );
		return self::$feed_catch_all[ $this->ID ];
	}

	/**
	 * Retrieve the remote post ids.
	 *
	 * @return array A mapping of the remote post ids.
	 */
	public function get_remote_post_ids() {
		$remote_post_ids = array();
		$existing_posts = new \WP_Query();
		foreach ( array(
			'post_type'   => Friends::CPT,
			'post_status' => array( 'publish', 'private', 'trash' ),
			'nopaging'    => true,
			'fields'      => 'ids',
		) as $key => $value ) {
			$existing_posts->set( $key, $value );
		}
		$existing_posts = $this->modify_query_by_author( $existing_posts );
		foreach ( $existing_posts->get_posts() as $post_id ) {
			$post_id = $existing_posts->next_post();
			$remote_post_id = get_post_meta( $post_id, 'remote_post_id', true );
			if ( $remote_post_id ) {
				$remote_post_ids[ $remote_post_id ] = $post_id;
			}
			$permalink                     = get_permalink( $post_id );
			$remote_post_ids[ $permalink ] = $post_id;
			$permalink                     = str_replace( array( '&#38;', '&#038;' ), '&', ent2ncr( $permalink ) );
			$remote_post_ids[ $permalink ] = $post_id;
		}

		unset( $existing_posts );
		do_action( 'friends_remote_post_ids', $remote_post_ids );
		return $remote_post_ids;
	}

	/**
	 * Get the user's feeds (and potentially convert old-style feed URL).
	 *
	 * @return array An array of User_Feed items.
	 */
	public function get_feeds() {
		$term_query = new \WP_Term_Query(
			array(
				'taxonomy'   => User_Feed::TAXONOMY,
				'object_ids' => $this->get_object_id(),
			)
		);

		$feeds = array();
		foreach ( $term_query->get_terms() as $term ) {
			$feeds[ $term->term_id ] = new User_Feed( $term, $this );
		}

		return $feeds;
	}

	/**
	 * Get just the user's active feeds.
	 *
	 * @return array An array of active User_Feed items.
	 */
	public function get_active_feeds() {
		$active_feeds = array();
		foreach ( $this->get_feeds() as $feed ) {
			if ( $feed->is_active() ) {
				$active_feeds[] = $feed;
			}
		}
		return $active_feeds;
	}

	/**
	 * Get just the user's active feeds.
	 *
	 * @return array An array of active User_Feed items.
	 */
	public function get_due_feeds() {
		$due_feeds = array();
		foreach ( $this->get_active_feeds() as $feed ) {
			// Explicitly use time() to allow mocking it inside the namespace.
			if ( gmdate( 'Y-m-d H:i:s', time() ) >= $feed->get_next_poll() ) {
				$due_feeds[] = $feed;
			}
		}
		return $due_feeds;
	}

	/**
	 * Determines whether the user can have feeds refreshed.
	 *
	 * @return     bool  True if able to refresh feeds, False otherwise.
	 */
	public function can_refresh_feeds() {
		return $this->has_cap( 'subscription' ) ||
			$this->has_cap( 'acquaintance' ) ||
			$this->has_cap( 'friend' ) ||
			$this->has_cap( 'pending_friend_request' );
	}

	/**
	 * Convert a user to a friend
	 */
	public function make_friend() {
		$this->set_role( get_option( 'friends_default_friend_role', 'friend' ) );
	}

	/**
	 * Check whether this is a valid friend
	 *
	 * @return boolean Whether the user has valid friend data.
	 */
	public function is_valid_friend() {
		if ( ! $this->has_cap( 'friend' ) ) {
			return false;
		}

		if ( ! $this->data->user_url ) {
			return false;
		}

		if ( ! $this->get_user_option( 'friends_in_token' ) ) {
			return false;
		}

		if ( ! $this->get_user_option( 'friends_out_token' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets the role name (for a specific count).
	 *
	 * @param      bool $group_subscriptions  Whether to group all types of subscriptions into the name "Subscriptions".
	 * @param      int  $count                The count if more than one.
	 *
	 * @return     string  The role name.
	 */
	public function get_role_name( $group_subscriptions = false, $count = 1 ) {
		if ( in_array( 'acquaintance', $this->roles ) ) {
			return _nx( 'Acquaintance', 'Acquaintances', $count, 'User role', 'friends' );
		}

		if ( in_array( 'friend', $this->roles ) && $this->is_valid_friend() ) {
			return _nx( 'Friend', 'Friends', $count, 'User role', 'friends' );
		}

		if ( in_array( 'subscription', $this->roles ) ) {
			return _nx( 'Subscription', 'Subscriptions', $count, 'User role', 'friends' );
		}

		if ( $group_subscriptions && ( in_array( 'friend_request', $this->roles ) || in_array( 'pending_friend_request', $this->roles ) ) ) {
			return _nx( 'Subscription', 'Subscriptions', $count, 'User role', 'friends' );
		}

		if ( in_array( 'friend_request', $this->roles ) ) {
			return _nx( 'Friend Request', 'Friend Requests', $count, 'User role', 'friends' );
		}

		if ( in_array( 'pending_friend_request', $this->roles ) ) {
			return _nx( 'Pending Friend Request', 'Pending Friend Requests', $count, 'User role', 'friends' );
		}

		$name = apply_filters( 'friend_user_role_name', false, $this );

		if ( empty( $name ) ) {
			$name = _x( 'Unknown', 'User role', 'friends' );
		}

		return $name;
	}

	/**
	 * Determines if starred.
	 *
	 * @return     bool  True if starred, False otherwise.
	 */
	public function is_starred() {
		return $this->get_user_option( 'friends_starred' );
	}

	/**
	 * Marks a friend as starred or unstarred.
	 *
	 * @param      bool $starred  Whether to star the friend.
	 *
	 * @return     bool    The new star status.
	 */
	public function set_starred( $starred ) {
		if ( $starred ) {
			$this->update_user_option( 'friends_starred', true );
			return true;
		}

		$this->delete_user_option( 'friends_starred' );
		return false;
	}

	/**
	 * Gets the local friends page url.
	 *
	 * @param      integer $post_id  The post identifier.
	 *
	 * @return     string      The local friends page url.
	 */
	public function get_local_friends_page_url( $post_id = null ) {
		$path = '/';
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			$path = '/' . $post_id . '/';
		}

		$user_login = self::get_user_login_for_url( $this->user_login );
		if ( ! $user_login || is_wp_error( $user_login ) ) {
			return home_url( '/friends/' . $path );
		}

		return home_url( '/friends/' . $user_login . $path );
	}

	/**
	 * Gets the local friends page url for a post format.
	 *
	 * @param      string|array $post_format  The post format.
	 *
	 * @return     string      The local friends page url.
	 */
	public function get_local_friends_page_post_format_url( $post_format ) {
		if ( is_array( $post_format ) ) {
			$post_format = implode( ',', $post_format );
		}
		return home_url( '/friends/' . $this->user_login . '/type/' . $post_format . '/' );
	}

	/**
	 * Gets the local friends page url for a reaction.
	 *
	 * @param      string $slug  The reaction slug.
	 *
	 * @return     string      The local friends page url.
	 */
	public function get_local_friends_page_reaction_url( $slug ) {
		return home_url( '/friends/' . $this->user_login . '/reaction' . $slug . '/' );
	}

	/**
	 * Gets the friend auth to be used as a GET parameter.
	 *
	 * @param      integer $validity  The validity in seconds.
	 *
	 * @return     string   The friend auth.
	 */
	public function get_friend_auth( $validity = 3600 ) {
		$friends = Friends::get_instance();
		$friend_auth = $friends->access_control->get_friend_auth( $this, $validity );
		if ( empty( $friend_auth ) ) {
			return '';
		}
		return $friend_auth['me'] . '-' . $friend_auth['until'] . '-' . $friend_auth['auth'];
	}

	/**
	 * Determines whether the specified url is friend url.
	 *
	 * @param      string $url    The url.
	 *
	 * @return     bool    True if the specified url is friend url, False otherwise.
	 */
	public function is_friend_url( $url ) {
		if ( ! $this->user_url ) {
			return false;
		}
		if ( 0 !== strpos( $url, $this->user_url ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Get the REST URL for the friend
	 *
	 * @return string        The REST URL.
	 */
	public function get_rest_url() {
		$friends = Friends::get_instance();
		$rest_url = $this->get_user_option( 'friends_rest_url' );
		if ( ! $rest_url || false === strpos( $rest_url, REST::PREFIX ) ) {
			$rest_url = $friends->rest->discover_rest_url( $this->user_url );
			if ( is_wp_error( $rest_url ) ) {
				return null;
			}

			if ( $rest_url ) {
				$this->update_user_option( 'friends_rest_url', $rest_url );
			}
		}
		return $rest_url;
	}

	public function get_avatar_url() {
		return $this->get_user_option( 'friends_user_icon_url' );
	}

	/**
	 * Wrap get_user_option
	 *
	 * @param string $option_name User option name.
	 * @return int|bool User meta ID if the option didn't exist, true on successful update,
	 *                  false on failure.
	 */
	public function get_user_option( $option_name ) {
		return get_user_option( $option_name, $this->ID );
	}

	/**
	 * Wrap update_user_option
	 *
	 * @param string $option_name    User option name.
	 * @param mixed  $new_value      User option value.
	 * @param bool   $is_global      Optional. Whether option name is global or blog specific.
	 *                               efault false (blog specific).
	 * @return int|bool User meta ID if the option didn't exist, true on successful update,
	 *                  false on failure.
	 */
	public function update_user_option( $option_name, $new_value, $is_global = false ) {
		return update_user_option( $this->ID, $option_name, $new_value, $is_global );
	}

	/**
	 * Wrap delete_user_option
	 *
	 * @param string $option_name    User option name.
	 * @param bool   $is_global      Optional. Whether option name is global or blog specific.
	 *                               Default false (blog specific).
	 * @return bool True on success, false on failure.
	 */
	public function delete_user_option( $option_name, $is_global = false ) {
		return delete_user_option( $this->ID, $option_name, $is_global );
	}

	public static function mastodon_api_account( $account, $user_id, $request = null, $post = null ) {
		if ( $account instanceof \Enable_Mastodon_Apps\Entity\Account && $user_id ) {
			return $account;
		}

		$user = false;
		if ( $user_id && class_exists( Feed_Parser_ActivityPub::class ) ) {
			$user = Feed_Parser_ActivityPub::determine_mastodon_api_user( $user_id );
		}

		if ( ! $user ) {
			if ( ! $post instanceof \WP_Post ) {
				return $account;
			}
			$user = self::get_post_author( $post );
		}
		if ( $user instanceof self ) {
			if ( ! self::is_friends_plugin_user( $user ) ) {
				return $account;
			}
			if ( ! $account instanceof \Enable_Mastodon_Apps\Entity\Account ) {
				$account = new \Enable_Mastodon_Apps\Entity\Account();
			}
			$note = $user->description;
			if ( ! $note ) {
				$note = '';
			}
			$account->id             = apply_filters( 'friends_mastodon_api_username', $user->ID );
			$account->username       = is_string( $account->id ) ? $account->id : $user->user_login;
			$account->display_name   = $user->display_name;
			$account->avatar         = $user->get_avatar_url();
			$account->avatar_static  = $user->get_avatar_url();
			$account->acct           = $account->username;
			$account->note           = wpautop( $note );
			$account->created_at     = new \DateTime( $user->user_registered );
			$account->statuses_count = $user->get_post_stats()['post_count'];
			if ( ! $post instanceof \WP_Post ) {
				$account->last_status_at = new \DateTime( 'now' );
			} else {
				$account->last_status_at = new \DateTime( $post->post_date_gmt );
			}
			$account->url = $user->get_local_friends_page_url();

			$account->source = array(
				'privacy'   => 'public',
				'sensitive' => false,
				'language'  => get_user_locale( $user->ID ),
				'note'      => $note,
				'fields'    => array(),
			);
		}

		return $account;
	}

	public static function mastodon_api_account_id( $user_id, $post_id ) {
		if ( $user_id ) {
			return $user_id;
		}
		$user = Feed_Parser_ActivityPub::determine_mastodon_api_user( $user_id );
		if ( ! $user ) {
			$user = self::get_post_author( get_post( $post_id ) );
		}
		if ( $user instanceof self ) {
			return $user->ID;
		}

		return $user_id;
	}

	public static function mastodon_api_get_posts_query_args( $args ) {
		if ( ! isset( $args['author'] ) ) {
			return $args;
		}
		$author = false;
		if ( is_string( $args['author'] ) ) {
			$author = self::get_by_username( $args['author'] );
		} elseif ( is_numeric( $args['author'] ) ) {
			$author = self::get_user_by_id( $args['author'] );
		}

		if ( $author instanceof User ) {
			$args['post_type'][] = Friends::CPT;
			return $author->modify_get_posts_args_by_author( $args );
		}

		return $args;
	}

	public static function mastodon_entity_relationship( $relationship, $user_id ) {
		if ( ! class_exists( 'Friends\Feed_Parser_ActivityPub' ) ) {
			if ( ! is_wp_error( $user_id ) ) {
				$user = User::get_user_by_id( $user_id );
			}
		} else {
			$user = Feed_Parser_ActivityPub::determine_mastodon_api_user( $user_id );
		}
		if ( $user instanceof self ) {
			if ( ! $relationship instanceof \Enable_Mastodon_Apps\Entity\Relationship ) {
				$relationship = new \Enable_Mastodon_Apps\Entity\Relationship();
			}
			foreach ( $user->get_active_feeds() as $feed ) {
				if ( Feed_Parser_ActivityPub::SLUG === $feed->get_parser() ) {
					$relationship->following = true;
					break;
				}
			}

			if ( $user->has_cap( 'friend' ) ) {
				$relationship->followed_by = true;
			}
		}
		return $relationship;
	}
}
