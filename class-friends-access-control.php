<?php
/**
 * Friends Access Control
 *
 * This contains the functions for access control.
 *
 * @package Friends
 */

/**
 * This is the class for the Friends Plugin Access Control.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Access_Control {
	/**
	 * States whether this is an authenticated feed call.
	 *
	 * @var boolean
	 */
	private $feed_authenticated = null;

	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends = null;

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
		add_filter( 'determine_current_user', array( $this, 'authenticate' ), 1 );
		add_filter( 'option_comment_whitelist', array( $this, 'option_comment_whitelist' ) );
		add_action( 'set_user_role', array( $this, 'notify_new_friend_request' ), 10, 3 );
		add_action( 'delete_user', array( $this, 'delete_friend_token' ) );
		add_action( 'init', array( $this, 'remote_login' ) );
	}

	/**
	 * Whether the feed is authenticated
	 *
	 * @return bool The authentication status of the feed.
	 */
	public function feed_is_authenticated() {
		return (bool) $this->get_authenticated_feed_user();
	}

	/**
	 * Get authenticated feed user
	 *
	 * @return WP_User|null The authentication status of the feed.
	 */
	public function get_authenticated_feed_user() {
		if ( is_null( $this->feed_authenticated ) ) {
			$this->authenticate( 0 );
		}

		if ( is_null( $this->feed_authenticated ) ) {
			return null;
		}

		return new WP_User( $this->feed_authenticated );
	}

	/**
	 * Whether the private RSS feed is authenticated. This is the feed for the admins of the site that will contain the friends posts.
	 *
	 * @return bool The authentication status of the feed.
	 */
	public function private_rss_is_authenticated() {
		if ( isset( $_GET['auth'] ) && get_option( 'friends_private_rss_key' ) === $_GET['auth'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Convert a site URL to a username
	 *
	 * @param  string $url The site URL in question.
	 * @return string The corresponding username.
	 */
	public function get_user_login_for_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = wp_parse_url( $url, PHP_URL_PATH );

		$user_login = trim( preg_replace( '#^www\.#', '', preg_replace( '#[^a-z0-9.-]+#', ' ', strtolower( $host . ' ' . $path ) ) ) );
		return $user_login;
	}

	/**
	 * Checks whether a user already exists for a site URL.
	 *
	 * @param  string $url The site URL for which to create the user.
	 * @return WP_User|false Whether the user already exists
	 */
	public function get_user_for_url( $url ) {
		$user_login = $this->get_user_login_for_url( $url );
		$user       = get_user_by( 'login', $user_login );
		if ( $user && ! $user->data->user_url ) {
			wp_update_user(
				array(
					'ID'       => $user->ID,
					'user_url' => $url,
				)
			);
			$user = get_user_by( 'login', $user_login );
		}
		return $user;
	}

	/**
	 * Create a WP_User with a specific Friends-related role
	 *
	 * @param  string $url     The site URL for which to create the user.
	 * @param  string $role         The role: subscription, pending_friend_request, or friend_request.
	 * @param  string $display_name The user's display name.
	 * @param  string $icon_url     The user_icon_url URL.
	 * @return WP_User|WP_Error The created user or an error.
	 */
	public function create_user( $url, $role, $display_name = null, $icon_url = null ) {
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

		$friend_user = $this->get_user_for_url( $url );
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
			'user_login'   => $this->get_user_login_for_url( $url ),
			'display_name' => $display_name,
			'first_name'   => $display_name,
			'nickname'     => $display_name,
			'user_url'     => $url,
			'user_pass'    => wp_generate_password( 256 ),
			'role'         => $role,
		);
		$friend_id = wp_insert_user( $userdata );
		update_user_option( $friend_id, 'friends_new_friend', true );
		$this->update_user_icon_url( $friend_id, $icon_url, $url );

		return new WP_User( $friend_id );
	}

	/**
	 * Update a friend's avatar URL
	 *
	 * @param  int    $user_id    The user id.
	 * @param  string $user_icon_url  The user icon URL.
	 * @return string|false The URL that was set or false.
	 */
	public function update_user_icon_url( $user_id, $user_icon_url ) {
		if ( $user_icon_url && wp_http_validate_url( $user_icon_url ) ) {
			$user = new WP_User( $user_id );
			if ( $user->has_cap( 'friend' ) || $user->has_cap( 'pending_friend_request' ) || $user->has_cap( 'friend_request' ) || $user->has_cap( 'subscription' ) ) {
				$icon_host_parts = array_reverse( explode( '.', parse_url( strtolower( $user_icon_url ), PHP_URL_HOST ) ) );
				if ( 'gravatar.com' === $icon_host_parts[1] . '.' . $icon_host_parts[0] ) {
					update_user_option( $user_id, 'friends_user_icon_url', $user_icon_url );
					return $user_icon_url;
				}

				$user_host_parts = array_reverse( explode( '.', parse_url( strtolower( $user->user_url ), PHP_URL_HOST ) ) );
				if ( $user_host_parts[1] . '.' . $user_host_parts[0] === $icon_host_parts[1] . '.' . $icon_host_parts[0] ) {
					update_user_option( $user_id, 'friends_user_icon_url', $user_icon_url );
					return $user_icon_url;
				}
			} elseif ( $user->has_cap( 'subscription' ) ) {
				update_user_option( $user_id, 'friends_gravatar', $gravatar );
				return $gravatar;
			}
		}

		return false;
	}

	/**
	 * Verify a friend token
	 *
	 * @param  string $token The token to verify.
	 * @return int|bool The user id or false.
	 */
	public function verify_token( $token ) {
		$user_id = get_option( 'friends_in_token_' . $token );
		if ( ! $user_id ) {
			return false;
		}
		settype( $user_id, 'int' );
		if ( get_user_option( 'friends_in_token', $user_id ) !== $token ) {
			return false;
		}

		return $user_id;
	}

	/**
	 * Log in a friend via URL parameter
	 */
	public function remote_login() {
		if ( ! isset( $_GET['friend_auth'] ) ) {
			return;
		}

		$user_id = $this->verify_token( $_GET['friend_auth'] );
		if ( ! $user_id ) {
			return;
		}
		$user = new WP_User( $user_id );
		if ( ! $user->has_cap( 'friend' ) ) {
			return;
		}

		wp_set_auth_cookie( $user_id );
		wp_safe_redirect( str_replace( array( '?friend_auth=' . $_GET['friend_auth'], '&friend_auth=' . $_GET['friend_auth'] ), '', $_SERVER['REQUEST_URI'] ) );
		exit;
	}

	/**
	 * Authenticate a user for a feed.
	 *
	 * @param  int $incoming_user_id An already authenticated user.
	 * @return int The new authenticated user.
	 */
	public function authenticate( $incoming_user_id ) {
		if ( false === $incoming_user_id ) {
			return false;
		}

		if ( isset( $_GET['friend'] ) ) {
			$user_id = $this->verify_token( $_GET['friend'] );
			if ( $user_id ) {
				$user = new WP_User( $user_id );
				if ( $user->has_cap( 'friend' ) ) {
					$this->feed_authenticated = $user_id;
					return $this->feed_authenticated;
				}
			}
		}

		return $incoming_user_id;
	}

	/**
	 * Delete options associated with a user
	 *
	 * @param  int $user_id The user id.
	 * @return The old token.
	 */
	public function delete_friend_token( $user_id ) {
		$current_secret = get_user_option( 'friends_in_token', $user_id );

		if ( $current_secret ) {
			delete_option( 'friends_in_token_' . $current_secret );
		}

		$user = new WP_User( $user_id );

		// No need to delete user options as the user will be deleted.
		return $current_secret;
	}

	/**
	 * Update a friend request token
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  string $old_roles The old roles.
	 *
	 * @return string The new token.
	 */
	public function notify_new_friend_request( $user_id, $new_role, $old_roles ) {
		if ( 'friend_request' !== $new_role || in_array( $new_role, $old_roles, true ) ) {
			return;
		}

		do_action( 'notify_new_friend_request', new WP_User( $user_id ) );
		return $token;
	}

	/**
	 * Convert a user to a friend
	 *
	 * @param  WP_User $user  The user to become a friend of the blog.
	 * @param  string  $out_token The token to authenticate against the remote.
	 * @param  string  $in_token The token the remote needs to use to authenticate to us.
	 * @return WP_User|WP_Error The user or an error.
	 */
	public function make_friend( WP_User $user, $out_token, $in_token ) {
		if ( ! $user || is_wp_error( $user ) ) {
			return $user;
		}
		update_user_option( $user->ID, 'friends_out_token', $out_token );
		if ( update_user_option( $user->ID, 'friends_in_token', $in_token ) ) {
			update_option( 'friends_in_token_' . $in_token, $user->ID );
		}
		$user->set_role( get_option( 'friends_default_friend_role', 'friend' ) );

		return $user;
	}

	/**
	 * Check whether a user is a valid friend
	 *
	 * @param  WP_User $user The potential friend user.
	 * @return boolean       Whether the user has valid friend data.
	 */
	public function is_valid_friend( WP_User $user ) {
		if ( ! $user->has_cap( 'friend' ) ) {
			return false;
		}

		if ( ! $user->data->user_url ) {
			return false;
		}

		if ( ! get_user_option( 'friends_in_token', $user->ID ) ) {
			return false;
		}

		if ( ! get_user_option( 'friends_out_token', $user->ID ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the REST URL for the friend
	 *
	 * @param  WP_User $user A friend user.
	 * @return string        The REST URL.
	 */
	public function get_rest_url( WP_User $user ) {
		$rest_url = get_user_option( 'friends_rest_url', $user->ID );
		if ( ! $rest_url || false === strpos( $rest_url, Friends_REST::PREFIX ) ) {
			$rest_url = $this->friends->rest->discover_rest_url( $user->user_url );
			if ( $rest_url ) {
				update_user_option( $user->ID, 'friends_rest_url', $rest_url );
			}
		}
		return $rest_url;
	}

	/**
	 * Prevent a friend's first comment ending up in moderation.
	 *
	 * @param  string $value The value retrieved from the Database.
	 * @return string The filtered value.
	 */
	public function option_comment_whitelist( $value ) {
		// Don't moderate the first comment by a friend.
		if ( current_user_can( 'friend' ) || current_user_can( 'restricted_friend' ) ) {
			return '0';
		}
		return $value;
	}
}
