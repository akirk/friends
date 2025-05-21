<?php
/**
 * Friends Access Control
 *
 * This contains the functions for access control.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Friends Plugin Access Control.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Access_Control {
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
		add_action( 'map_meta_cap', array( $this, 'strict_friend_checking_for_super_admin' ), 10, 3 );
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
	 * @return User|null The authentication status of the feed.
	 */
	public function get_authenticated_feed_user() {
		if ( is_null( $this->feed_authenticated ) ) {
			$this->authenticate( 0 );
		}

		if ( is_null( $this->feed_authenticated ) ) {
			return null;
		}

		return new User( $this->feed_authenticated );
	}

	/**
	 * Whether the private RSS feed is authenticated. This is the feed for the admins of the site that will contain the friends posts.
	 *
	 * @return bool The authentication status of the feed.
	 */
	public static function private_rss_is_authenticated() {
		if ( filter_input( INPUT_GET, 'auth' ) === get_option( 'friends_private_rss_key' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}

	/**
	 * Verify a friend token
	 *
	 * @param      string  $token  The token to verify.
	 * @param      integer $until  Valid until timestamp.
	 * @param      string  $auth   The auth code.
	 *
	 * @return     int|bool  The user id or false.
	 */
	public function verify_token( $token, $until, $auth ) {
		if ( ! get_option( 'friends_enable_wp_friendships' ) ) {
			return false;
		}
		$user_id = get_option( 'friends_in_token_' . $token );
		if ( ! $user_id ) {
			$me = User::get_user( User::get_user_login_for_url( $token ) );
			if ( ! $me || is_wp_error( $me ) ) {
				return false;
			}
			$user_id = $me->ID;
		} else {
			settype( $user_id, 'int' );

			if ( get_user_option( 'friends_in_token', $user_id ) !== $token ) {
				return false;
			}
		}

		if ( ! $auth || ! password_verify( $until . get_user_option( 'friends_out_token', $user_id ), $auth ) ) {
			return false;
		}

		if ( time() > $until ) {
			return false;
		}

		return $user_id;
	}

	/**
	 * Log in a friend via URL parameter
	 */
	public function remote_login() {
		if ( ! get_option( 'friends_enable_wp_friendships' ) ) {
			return false;
		}

		// The request comes from a rmeote WordPress. so no nonce verification needed.
		// Also, verifying a token doesn't need unslashing. See https://github.com/WordPress/WordPress-Coding-Standards/issues/869.

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_GET['friend_auth'] ) ) {
			return;
		}
		$tokens = explode( '-', $_GET['friend_auth'] );
		if ( 3 === count( $tokens ) ) {
			$user_id = $this->verify_token( $tokens[0], $tokens[1], $tokens[2] );
		} elseif ( 2 === count( $tokens ) && isset( $_GET['me'] ) ) {
			$user_id = $this->verify_token( $_GET['me'], $tokens[0], $tokens[1] );
		} else {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! $user_id ) {
			return;
		}
		$user = new User( $user_id );
		if ( ! $user->has_cap( 'friend' ) ) {
			return;
		}

		wp_set_auth_cookie( $user_id );
		wp_safe_redirect( remove_query_arg( array( 'friend_auth', 'me' ) ) );
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

		// The request comes from a rmeote WordPress. so no nonce verification needed.
		// Also, verifying a token doesn't need unslashing. See https://github.com/WordPress/WordPress-Coding-Standards/issues/869.

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_id = false;
		if ( isset( $_GET['friend'] ) && isset( $_GET['until'] ) && isset( $_GET['auth'] ) ) {
			$user_id = $this->verify_token( $_GET['friend'], $_GET['until'], $_GET['auth'] );
		} elseif ( isset( $_GET['me'] ) && isset( $_GET['until'] ) && isset( $_GET['auth'] ) ) {
			$user_id = $this->verify_token( $_GET['me'], $_GET['until'], $_GET['auth'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $user_id ) {
			$user = new User( $user_id );
			if ( $user->has_cap( 'friend' ) ) {
				$this->feed_authenticated = $user_id;
				return $this->feed_authenticated;
			}
		}

		return $incoming_user_id;
	}

	/**
	 * Gets the friend auth.
	 *
	 * @param      User    $friend_user  The friend user.
	 * @param      integer $validity     The validity.
	 *
	 * @return     string       The friend auth.
	 */
	public function get_friend_auth( User $friend_user, $validity = 3600 ) {
		static $tokens = array();

		if ( ! isset( $tokens[ $friend_user->ID ] ) ) {
			$tokens[ $friend_user->ID ] = array();
			$out_token = $friend_user->get_user_option( 'friends_out_token' );
			$in_token = $friend_user->get_user_option( 'friends_in_token' );

			if ( $in_token && $out_token ) {
				$until = time() + $validity;
				$auth = password_hash( $until . $in_token, PASSWORD_DEFAULT );

				$tokens[ $friend_user->ID ] = array(
					'me'    => User::get_user_login_for_url( home_url(), false ),
					'until' => $until,
					'auth'  => $auth,
				);
			}
		}

		return $tokens[ $friend_user->ID ];
	}

	/**
	 * Appends an auth to an URL.
	 *
	 * @param      string  $url          The url.
	 * @param      User    $friend_user  The friend user.
	 * @param      integer $validity     The validity in seconds.
	 *
	 * @return     string       The url with an appended auth.
	 */
	public function append_auth( $url, User $friend_user, $validity = 3600 ) {
		if ( $validity < 0 ) {
			return $url;
		}
		$friend_auth = $this->get_friend_auth( $friend_user, $validity );
		if ( ! empty( $friend_auth ) ) {
			$url = add_query_arg( 'me', $friend_auth['me'], $url );
			$url = add_query_arg( 'until', $friend_auth['until'], $url );
			$url = add_query_arg( 'auth', $friend_auth['auth'], $url );
		}

		return $url;
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

		$user = new User( $user_id );

		// No need to delete user options as the user will be deleted.
		return $current_secret;
	}

	/**
	 * Update a friend request token
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  array  $old_roles The old roles.
	 *
	 * @return string The new token.
	 */
	public function notify_new_friend_request( $user_id, $new_role, $old_roles ) {
		if ( 'friend_request' !== $new_role || in_array( $new_role, $old_roles, true ) ) {
			return;
		}

		do_action( 'notify_new_friend_request', new User( $user_id ) );
	}

	/**
	 * Demotes the super admin for the friend roles so that they can interact in the Friends system like a normal user.
	 *
	 * @param string[] $caps    Primitive capabilities required of the user.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id The user ID.
	 *
	 * @return     array
	 */
	public function strict_friend_checking_for_super_admin( $caps, $cap, $user_id ) {
		static $user_roles = array(), $is_superadmin = array();

		if ( ! in_array( $cap, array( 'friend', 'acquaintance', 'pending_friend_request', 'friend_request', 'subscription' ) ) ) {
			return $caps;
		}

		if ( isset( $is_superadmin[ $user_id ] ) ) {
			if ( ! $is_superadmin[ $user_id ] ) {
				return $caps;
			}
		} elseif ( ! is_multisite() || ! is_super_admin( $user_id ) ) {
			$is_superadmin[ $user_id ] = false;
			return $caps;
		}
		$is_superadmin[ $user_id ] = true;

		if ( ! isset( $user_roles[ $user_id ] ) ) {
			$user_roles[ $user_id ] = get_user_meta( $user_id, 'wp_capabilities', true );
		}

		if ( is_array( $user_roles[ $user_id ] ) ) {
			foreach ( $user_roles[ $user_id ] as $role ) {
				// If they have the role we are checking for, we'll respond with unmapped caps.
				if ( $cap === $role ) {
					return $caps;
				}
			}
		}

		// If the super admin doesn't have the role, respond with do_not_allow so that they don't qualify
		// for the capapbility, despite being a super admin (which automatically has any capability).
		return array( 'do_not_allow' );
	}

	/**
	 * Prevent a friend's first comment ending up in moderation.
	 *
	 * @param  string $value The value retrieved from the Database.
	 * @return string The filtered value.
	 */
	public function option_comment_whitelist( $value ) {
		// Don't moderate the first comment by a friend.
		if ( current_user_can( 'friend' ) || current_user_can( 'acquaintance' ) ) {
			return '0';
		}
		return $value;
	}

	public static function check_browser_api_key( $key ) {
		$parts = explode( '-', $key, 3 );
		if ( 3 !== count( $parts ) ) {
			return false;
		}

		$user_id = (int) $parts[1];
		if ( ! $user_id ) {
			return false;
		}

		$desired_key = self::get_browser_api_key( $user_id );
		if ( ! $desired_key ) {
			return false;
		}

		return $key === $desired_key;
	}

	public static function revoke_browser_api_key( $user_id = false ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		delete_user_option( $user_id, 'friends_browser_api_key' );
	}

	public static function get_browser_api_key( $user_id = false ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$key = get_user_option( 'friends_browser_api_key', $user_id );
		if ( ! $key ) {
			$key = 'friends-' . $user_id . '-' . wp_generate_password( 32, false );
			update_user_option( $user_id, 'friends_browser_api_key', $key );
		}

		return $key;
	}
}
