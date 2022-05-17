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
	 * @param      string $icon_url      The user_icon_url URL.
	 *
	 * @return     User|\WP_Error  The created user or an error.
	 */
	public static function create( $user_login, $role, $url, $display_name = null, $icon_url = null ) {
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

		$friend_user = self::get_user( $user_login );
		if ( $friend_user && ! is_wp_error( $friend_user ) ) {

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

		$userdata  = array(
			'user_login'   => $user_login,
			'display_name' => $display_name,
			'first_name'   => $display_name,
			'nickname'     => $display_name,
			'user_url'     => $url,
			'user_pass'    => wp_generate_password( 256 ),
			'role'         => $role,
		);
		$friend_id = wp_insert_user( $userdata );
		update_user_option( $friend_id, 'friends_new_friend', true );

		$friend_user = new User( $friend_id );
		$friend_user->update_user_icon_url( $icon_url );
		return $friend_user;
	}

	/**
	 * Convert a site URL to a username
	 *
	 * @param  string $url The site URL in question.
	 * @return string The corresponding username.
	 */
	public static function get_user_login_for_url( $url ) {
		$multisite_user = self::get_multisite_user( $url );
		if ( $multisite_user ) {
			return $multisite_user->user_login;
		}

		$user_login = self::sanitize_username( self::get_display_name_for_url( $url ) );
		return $user_login;
	}

	/**
	 * Convert a site URL to a display name
	 *
	 * @param  string $url The site URL in question.
	 * @return string The corresponding display name.
	 */
	public static function get_display_name_for_url( $url ) {
		$multisite_user = self::get_multisite_user( $url );
		if ( $multisite_user ) {
			return $multisite_user->display_name;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = wp_parse_url( $url, PHP_URL_PATH );

		$display_name = sanitize_text_field( preg_replace( '#^www\.#', '', preg_replace( '#[^a-z0-9.-]+#i', ' ', strtolower( $host . ' ' . $path ) ) ) );
		return $display_name;
	}

	/**
	 * Discover a display name from feeds
	 *
	 * @param  array $feeds A list of feeds.
	 * @return string The corresponding display name.
	 */
	public static function get_display_name_from_feeds( $feeds ) {
		foreach ( $feeds as $feed ) {
			if ( 'self' === $feed['rel'] ) {
				return sanitize_text_field( $feed['title'] );
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
		$path = wp_parse_url( $url, PHP_URL_PATH );

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
		$username = preg_replace( '/[^a-z0-9.]+/', '-', strtolower( $username ) );
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
			// Ensure we're using the same URL protocol.
			$this->data->user_url = set_url_scheme( $site->siteurl );
			return $this->data->user_url;
		}

		return parent::__get( $key );
	}
	/**
	 * Sends a message to the friend..
	 *
	 * @param      string $message  The message.
	 * @param      string $subject  The subject.
	 *
	 * @return     \WP_Error|bool  True if the message was sent successfully.
	 */
	public function send_message( $message, $subject = null ) {
		$friends = Friends::get_instance();
		return $friends->messages->send_message( $this, $message, $subject );
	}

	/**
	 * Save multiple feeds for a user.
	 *
	 * @param      string $feeds  The feed URLs to subscribe to.
	 *
	 * @return     \WP_User|\WP_error  $user The new associated user or an error object.
	 */
	public function save_feeds( $feeds = array() ) {
		$errors = new \WP_Error();
		foreach ( $feeds as $feed_url => $options ) {
			if ( ! is_string( $feed_url ) || ! Friends::check_url( $feed_url ) ) {
				$errors->add( 'invalid-url', 'An invalid URL was provided' );
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

			$feed_options = array();
			foreach ( $default_options as $key => $value ) {
				if ( isset( $options[ $key ] ) ) {
					$feed_options[ $key ] = $options[ $key ];
				} else {
					$feed_options[ $key ] = $value;
				}
			}
			$feeds[ $feed_url ] = $feed_options;
		}

		$user_feeds = User_Feed::save_multiple(
			$this,
			$feeds
		);

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return $user_feeds;
	}

	/**
	 * Save a feed url for a user.
	 *
	 * @param      string $feed_url  The feed URL to subscribe to.
	 * @param      array  $options   The options.
	 *
	 * @return     \WP_User|\WP_error  $user The new associated user or an error object.
	 */
	public function save_feed( $feed_url, $options = array() ) {
		if ( ! is_string( $feed_url ) || ! Friends::check_url( $feed_url ) ) {
			return new \WP_Error( 'invalid-url', 'An invalid URL was provided' );
		}

		$default_options = array(
			'active'      => false,
			'parser'      => 'simplepie',
			'post-format' => 'standard',
			'mime-type'   => 'application/rss+xml',
			'title'       => $this->display_name . ' RSS Feed',
		);

		$feed_options = array();
		foreach ( $default_options as $key => $value ) {
			if ( isset( $options[ $key ] ) ) {
				$feed_options[ $key ] = $options[ $key ];
			} else {
				$feed_options[ $key ] = $value;
			}
		}

		$user_feed = User_Feed::save(
			$this,
			$feed_url,
			$feed_options
		);

		return $user_feed;
	}

	/**
	 * Subscribe to a friends site without becoming a friend
	 *
	 * @param      string $feed_url  The feed URL to subscribe to.
	 * @param      array  $options   The options.
	 *
	 * @return     \WP_User|\WP_error  $user The new associated user or an error object.
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
	public function retrieve_posts() {
		$friends = Friends::get_instance();
		$new_posts = array();
		foreach ( $this->get_active_feeds() as $feed ) {
			$posts = $friends->feed->retrieve_feed( $feed );
			if ( ! is_wp_error( $posts ) ) {
				$new_posts = array_merge( $new_posts, $posts );
			}
		}

		$this->delete_outdated_posts();
		return $new_posts;
	}

	/**
	 * Delete posts the user decided to automatically delete.
	 */
	public function delete_outdated_posts() {
		$count = 0;
		if ( $this->is_retention_days_enabled() ) {
			$date_before = gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $this->get_retention_days() * 24 ) . 'hours' ) );
			$args        = array(
				'post_type'  => Friends::CPT,
				'author'     => $this->ID,
				'nopaging'   => true,
				'date_query' => array(
					'before' => $date_before,
				),
			);

			$query = new \WP_Query( $args );

			while ( $query->have_posts() ) {
				$count ++;
				$query->the_post();
				if ( apply_filters( 'friends_debug', false ) ) {
					echo 'Deleting ', get_the_ID(), '<br/>';
				}
				wp_delete_post( get_the_ID(), true );
			}
		}

		if ( $this->is_retention_number_enabled() ) {
			$args = array(
				'post_type' => Friends::CPT,
				'author'    => $this->ID,
				'offset'    => $this->get_retention_number(),
			);

			$query = new \WP_Query( $args );

			while ( $query->have_posts() ) {
				$count ++;
				$query->the_post();
				if ( apply_filters( 'friends_debug', false ) ) {
					echo 'Deleting ', get_the_ID(), '<br/>';
				}
				wp_delete_post( get_the_ID(), true );
			}
		}
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
		return $days;
	}

	/**
	 * Gets the post counts by post format.
	 *
	 * @return     array  The post counts.
	 */
	public function get_post_count_by_post_format() {
		$friends = Friends::get_instance();
		return $friends->get_post_count_by_post_format( $this->ID );
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
			if ( $this->has_cap( 'friend' ) || $this->has_cap( 'pending_friend_request' ) || $this->has_cap( 'friend_request' ) || $this->has_cap( 'subscription' ) ) {
				$icon_host_parts = array_reverse( explode( '.', parse_url( strtolower( $user_icon_url ), PHP_URL_HOST ) ) );
				if ( 'gravatar.com' === $icon_host_parts[1] . '.' . $icon_host_parts[0] ) {
					update_user_option( $this->ID, 'friends_user_icon_url', $user_icon_url );
					return $user_icon_url;
				}

				$user_host_parts = array_reverse( explode( '.', parse_url( strtolower( $this->user_url ), PHP_URL_HOST ) ) );
				if ( $user_host_parts[1] . '.' . $user_host_parts[0] === $icon_host_parts[1] . '.' . $icon_host_parts[0] ) {
					update_user_option( $this->ID, 'friends_user_icon_url', $user_icon_url );
					return $user_icon_url;
				}
			} elseif ( $this->has_cap( 'subscription' ) ) {
				update_user_option( $this->ID, 'friends_user_icon_url', $user_icon_url );
				return $user_icon_url;
			}
		}

		return false;
	}

	/**
	 * Retrieve the rules for this feed.
	 *
	 * @return array The rules set by the user for this feed.
	 */
	public function get_feed_rules() {
		$friends = Friends::get_instance();
		if ( ! isset( self::$feed_rules[ $this->ID ] ) ) {
			self::$feed_rules[ $this->ID ] = $friends->feed->validate_feed_rules( get_option( 'friends_feed_rules_' . $this->ID ) );
		}
		return self::$feed_rules[ $this->ID ];
	}


	/**
	 * Retrieve the catch_all value for this feed.
	 *
	 * @return array The rules set by the user for this feed.
	 */
	public function get_feed_catch_all() {
		$friends = Friends::get_instance();
		if ( ! isset( self::$feed_catch_all[ $this->ID ] ) ) {
			self::$feed_catch_all[ $this->ID ] = $friends->feed->validate_feed_catch_all( get_option( 'friends_feed_catch_all_' . $this->ID ) );
		}
		return self::$feed_catch_all[ $this->ID ];
	}

	/**
	 * Retrieve the remote post ids.
	 *
	 * @return array A mapping of the remote post ids.
	 */
	public function get_remote_post_ids() {
		$remote_post_ids = array();
		$existing_posts  = new \WP_Query(
			array(
				'post_type'   => Friends::CPT,
				'post_status' => array( 'publish', 'private', 'trash' ),
				'author'      => $this->ID,
				'nopaging'    => true,
			)
		);

		if ( $existing_posts->have_posts() ) {
			while ( $existing_posts->have_posts() ) {
				$post           = $existing_posts->next_post();
				$remote_post_id = get_post_meta( $post->ID, 'remote_post_id', true );
				if ( $remote_post_id ) {
					$remote_post_ids[ $remote_post_id ] = $post->ID;
				}
				$permalink                     = get_permalink( $post );
				$remote_post_ids[ $permalink ] = $post->ID;
				$permalink                     = str_replace( array( '&#38;', '&#038;' ), '&', ent2ncr( $permalink ) );
				$remote_post_ids[ $permalink ] = $post->ID;
			}
		}

		do_action( 'friends_remote_post_ids', $remote_post_ids );
		return $remote_post_ids;
	}

	/**
	 * Get the user's feeds (and potentially convert old-style feed URL).
	 *
	 * @return array An array of User_Feed items.
	 */
	public function get_feeds() {
		$feeds = User_Feed::get_for_user( $this );
		if ( empty( $feeds ) ) {
			$feeds = User_Feed::convert_user( $this );
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
	 *
	 * @param  string $out_token The token to authenticate against the remote.
	 * @param  string $in_token The token the remote needs to use to authenticate to us.
	 */
	public function make_friend( $out_token, $in_token ) {
		$this->update_user_option( 'friends_out_token', $out_token );
		if ( $this->update_user_option( 'friends_in_token', $in_token ) ) {
			update_option( 'friends_in_token_' . $in_token, $this->ID );
		}
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
		$name = false;

		if ( ! $name && in_array( 'acquaintance', $this->roles ) ) {
			return _nx( 'Acquaintance', 'Acquaintances', $count, 'User role', 'friends' );
		}

		if ( ! $name && in_array( 'friend', $this->roles ) && $this->is_valid_friend() ) {
			return _nx( 'Friend', 'Friends', $count, 'User role', 'friends' );
		}

		if ( ! $name && in_array( 'subscription', $this->roles ) || ( $group_subscriptions && ( in_array( 'friend_request', $this->roles ) || in_array( 'pending_friend_request', $this->roles ) ) ) ) {
			return _nx( 'Subscription', 'Subscriptions', $count, 'User role', 'friends' );
		}

		if ( ! $name && in_array( 'friend_request', $this->roles ) ) {
			return _nx( 'Friend Request', 'Friend Requests', $count, 'User role', 'friends' );
		}

		if ( ! $name && in_array( 'pending_friend_request', $this->roles ) ) {
			return _nx( 'Pending Friend Request', 'Pending Friend Requests', $count, 'User role', 'friends' );
		}

		$name = apply_filters( 'friend_user_role_name', $name, $this );

		if ( ! $name ) {
			$name = _x( 'Unknown', 'User role', 'friends' );
		}

		return $name;
	}

	/**
	 * Gets the local friends page url.
	 *
	 * @param      integer $post_id  The post identifier.
	 *
	 * @return     string      The local friends page url.
	 */
	function get_local_friends_page_url( $post_id = null ) {
		$path = '/';
		if ( $post_id ) {
			$path = '/' . $post_id . '/';
		}
		return home_url( '/friends/' . self::get_user_login_for_url( $this->user_login ) . $path );
	}

	/**
	 * Gets the local friends page url for a post format.
	 *
	 * @param      string $post_format  The post format.
	 *
	 * @return     string      The local friends page url.
	 */
	function get_local_friends_page_post_format_url( $post_format ) {
		return home_url( '/friends/' . $this->user_login . '/type/' . $post_format . '/' );
	}

	/**
	 * Gets the local friends page url for a reaction.
	 *
	 * @param      string $slug  The reaction slug.
	 *
	 * @return     string      The local friends page url.
	 */
	function get_local_friends_page_reaction_url( $slug ) {
		return home_url( '/friends/' . $this->user_login . '/reaction' . $slug . '/' );
	}

	/**
	 * Gets the friend auth to be used as a GET parameter.
	 *
	 * @param      integer $validity  The validity in seconds.
	 *
	 * @return     string   The friend auth.
	 */
	function get_friend_auth( $validity = 3600 ) {
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
	function is_friend_url( $url ) {
		if ( ! $this->user_url ) {
			return false;
		}
		if ( false === strpos( $url, $this->user_url ) ) {
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
			if ( $rest_url ) {
				$this->update_user_option( 'friends_rest_url', $rest_url );
			}
		}
		return $rest_url;
	}

	/**
	 * Wrap get_user_option
	 *
	 * @param string $option_name User option name.
	 * @return int|bool User meta ID if the option didn't exist, true on successful update,
	 *                  false on failure.
	 */
	function get_user_option( $option_name ) {
		return get_user_option( $option_name, $this->ID );
	}

	/**
	 * Wrap update_user_option
	 *
	 * @param string $option_name User option name.
	 * @param mixed  $newvalue    User option value.
	 * @param bool   $global      Optional. Whether option name is global or blog specific.
	 *                            Default false (blog specific).
	 * @return int|bool User meta ID if the option didn't exist, true on successful update,
	 *                  false on failure.
	 */
	function update_user_option( $option_name, $newvalue, $global = false ) {
		return update_user_option( $this->ID, $option_name, $newvalue, $global );
	}

	/**
	 * Wrap delete_user_option
	 *
	 * @param string $option_name User option name.
	 * @param bool   $global      Optional. Whether option name is global or blog specific.
	 *                            Default false (blog specific).
	 * @return bool True on success, false on failure.
	 */
	function delete_user_option( $option_name, $global = false ) {
		return delete_user_option( $this->ID, $option_name, $global );
	}
}
