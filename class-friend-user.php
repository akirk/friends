<?php
/**
 * Friend User
 *
 * This wraps WP_User and adds friend specific functions.
 *
 * @package Friends
 */

/**
 * This is the class for the User part of the Friends Plugin.
 *
 * @since 0.21
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friend_User extends WP_User {
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
	 * Create a Friend_User with a specific Friends-related role
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
	 * @return     Friend_User|WP_Error  The created user or an error.
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
			return new WP_Error( 'invalid_role', 'Invalid role for creation specified' );
		}

		$friend_user = self::get_user( $user_login );
		if ( $friend_user && ! is_wp_error( $friend_user ) ) {
			if ( is_multisite() ) {
				$current_site = get_current_site();
				if ( ! is_user_member_of_blog( $friend_user->ID, $current_site->ID ) ) {
					add_user_to_blog( $current_site->ID, $friend_user->ID, $role );
				}
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

		$friend_user = new Friend_User( $friend_id );
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
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = wp_parse_url( $url, PHP_URL_PATH );

		$user_login = sanitize_title( preg_replace( '#^www\.#', '', preg_replace( '#[^a-z0-9.-]#i', ' ', strtolower( $host . ' ' . $path ) ) ) );
		return $user_login;
	}

	/**
	 * Convert a site URL to a display name
	 *
	 * @param  string $url The site URL in question.
	 * @return string The corresponding display name.
	 */
	public static function get_display_name_for_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = wp_parse_url( $url, PHP_URL_PATH );

		$display_name = trim( preg_replace( '#^www\.#', '', preg_replace( '#[^a-z0-9.-]#i', ' ', strtolower( $host . ' ' . $path ) ) ) );
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
	 * Get a friend user for a user_login.
	 *
	 * @param  string $user_login The user login.
	 * @return Friend_User|false The friend user or false.
	 */
	public static function get_user( $user_login ) {
		$user = get_user_by( 'login', $user_login );
		if ( $user && ! $user->data->user_url ) {
			wp_update_user(
				array(
					'ID'       => $user->ID,
					'user_url' => $url,
				)
			);
			$user = get_user_by( 'login', $user_login );
		}
		if ( $user ) {
			if ( $user->has_cap( 'friend' ) || $user->has_cap( 'pending_friend_request' ) || $user->has_cap( 'friend_request' ) || $user->has_cap( 'subscription' ) ) {
				return new self( $user );
			}

			return false;
		}
		return $user;
	}

	/**
	 * Save multiple feeds for a user.
	 *
	 * @param      string $feeds  The feed URLs to subscribe to.
	 *
	 * @return     WP_User|WP_error  $user The new associated user or an error object.
	 */
	public function save_feeds( $feeds = array() ) {
		$errors = new WP_Error();
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
				'post-type'   => 'post',
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

		$user_feeds = Friend_User_Feed::save_multiple(
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
	 * @return     WP_User|WP_error  $user The new associated user or an error object.
	 */
	public function save_feed( $feed_url, $options = array() ) {
		if ( ! is_string( $feed_url ) || ! Friends::check_url( $feed_url ) ) {
			return new WP_Error( 'invalid-url', 'An invalid URL was provided' );
		}

		$default_options = array(
			'active'      => false,
			'parser'      => 'simplepie',
			'post-format' => 'standard',
			'post-type'   => 'post',
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

		$user_feed = Friend_User_Feed::save(
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
	 * @return     WP_User|WP_error  $user The new associated user or an error object.
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
		return $friends->feed->retrieve_single_friend_posts( $this );
	}

	/**
	 * Update a friend's avatar URL
	 *
	 * @param  string $user_icon_url  The user icon URL.
	 * @return string|false The URL that was set or false.
	 */
	public function update_user_icon_url( $user_icon_url ) {
		if ( $user_icon_url && wp_http_validate_url( $user_icon_url ) ) {
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
				update_user_option( $this->ID, 'friends_user_icon_url', $gravatar );
				return $gravatar;
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
		$friends = Friends::get_instance();
		$remote_post_ids = array();
		$existing_posts  = new WP_Query(
			array(
				'post_type'   => $friends->post_types->get_all_cached(),
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
	 * @return array An array of Friend_User_Feed items.
	 */
	public function get_feeds() {
		$feeds = Friend_User_Feed::get_for_user( $this );
		if ( empty( $feeds ) ) {
			$feeds = Friend_User_Feed::convert_user( $this );
		}

		return $feeds;
	}

	/**
	 * Get just the user's active feeds.
	 *
	 * @return array An array of active Friend_User_Feed items.
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
	 * Gets the local friends page url.
	 *
	 * @return     string  The local friends page url.
	 */
	function get_local_friends_page_url() {
		return site_url( '/friends/' . $this->user_login . '/' );
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
		return $friend_auth['friend'] . '-' . $friend_auth['until'] . '-' . $friend_auth['auth'];
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
		if ( 0 === strpos( $url, $this->user_url ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get the REST URL for the friend
	 *
	 * @return string        The REST URL.
	 */
	public function get_rest_url() {
		$friends = Friends::get_instance();
		$rest_url = $this->get_user_option( 'friends_rest_url' );
		if ( ! $rest_url || false === strpos( $rest_url, Friends_REST::PREFIX ) ) {
			$rest_url = $friends->rest->discover_rest_url( $this->user_url );
			if ( $rest_url ) {
				$this->update_user_option( 'friends_rest_url', $rest_url );
			}
		}
		return $rest_url;
	}

	/**
	 * Is this a new user?
	 *
	 * @return boolean [description]
	 */
	public function is_new() {
		return $this->get_user_option( 'friends_new_friend' );
	}

	/**
	 * Flag the user as no lopnger new
	 */
	public function set_not_new() {
		$this->delete_user_option( 'friends_new_friend' );
	}

	/**
	 * Get the whitelisted post types for this user
	 *
	 * @return array All the post types.
	 */
	public function get_post_types() {
		$post_types = $this->get_user_option( 'friends_post_types' );

		if ( false === $post_types ) {
			$post_types = get_option( 'friends_default_post_types' );
			if ( false === $post_types ) {
				$post_types = 'all';
			}
		}

		if ( 'all' === $post_types ) {
			$friends = Friends::get_instance();
			return $friends->post_types->get_all_registered();
		}

		return explode( ',', $post_types );
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
