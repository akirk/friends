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
	 * @return Friend_User|null The authentication status of the feed.
	 */
	public function get_authenticated_feed_user() {
		if ( is_null( $this->feed_authenticated ) ) {
			$this->authenticate( 0 );
		}

		if ( is_null( $this->feed_authenticated ) ) {
			return null;
		}

		return new Friend_User( $this->feed_authenticated );
	}

	/**
	 * Whether the private RSS feed is authenticated. This is the feed for the admins of the site that will contain the friends posts.
	 *
	 * @return bool The authentication status of the feed.
	 */
	public static function private_rss_is_authenticated() {
		if ( isset( $_GET['auth'] ) && get_option( 'friends_private_rss_key' ) === $_GET['auth'] ) {
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
		$user_id = get_option( 'friends_in_token_' . $token );
		if ( ! $user_id ) {
			$me = Friend_User::get_user( Friend_User::get_user_login_for_url( $token ) );
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

		// Allow for some grace period by skipping the auth check for older versions.
		if ( ! is_null( $until ) && ! is_null( $auth ) ) {
			if ( ! password_verify( $until . get_user_option( 'friends_out_token', $user_id ), $auth ) ) {
				return false;
			}

			if ( time() > $until ) {
				return false;
			}
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
		$tokens = explode( '-', $_GET['friend_auth'] );
		if ( 3 === count( $tokens ) ) {
			$user_id = $this->verify_token( $tokens[0], $tokens[1], $tokens[2] );
		} elseif ( 2 === count( $tokens ) && isset( $_GET['me'] ) ) {
			$user_id = $this->verify_token( $_GET['me'], $tokens[0], $tokens[1] );
		} else {
			return;
		}

		if ( ! $user_id ) {
			return;
		}
		$user = new Friend_User( $user_id );
		if ( ! $user->has_cap( 'friend' ) ) {
			return;
		}

		wp_set_auth_cookie( $user_id );
		wp_safe_redirect( str_replace( array( '?friend_auth=' . $_GET['friend_auth'], '&friend_auth=' . $_GET['friend_auth'], '?me=' . $_GET['me'], '&me=' . $_GET['me'] ), '', $_SERVER['REQUEST_URI'] ) );
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

		$user_id = false;
		if ( isset( $_GET['friend'] ) && isset( $_GET['until'] ) && isset( $_GET['auth'] ) ) {
			$user_id = $this->verify_token( $_GET['friend'], $_GET['until'], $_GET['auth'] );
		} elseif ( isset( $_GET['me'] ) && isset( $_GET['until'] ) && isset( $_GET['auth'] ) ) {
			$user_id = $this->verify_token( $_GET['me'], $_GET['until'], $_GET['auth'] );
		}

		if ( $user_id ) {
			$user = new Friend_User( $user_id );
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
	 * @param      Friend_User $friend_user  The friend user.
	 * @param      integer     $validity     The validity.
	 *
	 * @return     string       The friend auth.
	 */
	public function get_friend_auth( Friend_User $friend_user, $validity = 3600 ) {
		static $tokens = array();

		if ( ! isset( $tokens[ $friend_user->ID ] ) ) {
			$tokens[ $friend_user->ID ] = array();
			$out_token = $friend_user->get_user_option( 'friends_out_token' );
			$in_token = $friend_user->get_user_option( 'friends_in_token' );

			if ( $in_token && $out_token ) {
				$until = time() + $validity;
				$auth = password_hash( $until . $in_token, PASSWORD_DEFAULT );

				$tokens[ $friend_user->ID ] = array(
					'me'    => Friend_User::get_user_login_for_url( home_url() ),
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
	 * @param      string      $url          The url.
	 * @param      Friend_User $friend_user  The friend user.
	 * @param      integer     $validity     The validity in seconds.
	 *
	 * @return     string       The url with an appended auth.
	 */
	public function append_auth( $url, Friend_User $friend_user, $validity = 3600 ) {
		if ( $validity < 0 ) {
			return $url;
		}
		$friend_auth = $this->get_friend_auth( $friend_user, $validity );
		if ( ! empty( $friend_auth ) ) {
			$sep = false === strpos( $url, '?' ) ? '?' : '&';

			$url .= $sep . 'me=' . urlencode( $friend_auth['me'] );
			$url .= '&until=' . urlencode( $friend_auth['until'] );
			$url .= '&auth=' . urlencode( $friend_auth['auth'] );
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

		$user = new Friend_User( $user_id );

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

		do_action( 'notify_new_friend_request', new Friend_User( $user_id ) );
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
}
