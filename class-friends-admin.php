<?php
/**
 * Friends Admin
 *
 * This contains the functions for the admin section.
 *
 * @package Friends
 */

/**
 * This is the class for the Friends Plugin Admin section.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Admin {
	/**
	 * Contains a reference to the Friends class.
	 */
	private $friends;

	/**
	 * Constructor
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'admin_menu',                array( $this, 'register_admin_menu' ), 10, 3 );
		add_filter( 'user_row_actions',          array( $this, 'user_row_actions' ), 10, 2 );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_friend_request_approval' ), 10, 3 );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_send_friend_request' ), 10, 3 );
		add_filter( 'bulk_actions-users',        array( $this, 'add_user_bulk_options' ) );
		add_filter( 'get_edit_user_link',        array( $this, 'admin_edit_user_link' ), 10, 2 );
		add_action( 'admin_bar_menu',            array( $this, 'admin_bar_friends_menu' ), 100 );
	}

	/**
	 * Registers the admin menus
	 */
	public function register_admin_menu() {
		add_menu_page( 'Friends', 'Friends', 'manage_options', 'friends-settings', null, 'dashicons-groups', 3.73);
		add_submenu_page( 'friends-settings', 'Settings', 'Settings', 'manage_options', 'friends-settings', array( $this, 'render_admin_settings' ) );
		add_submenu_page( 'friends-settings', 'Send Friend Request', 'Send Friend Request', 'edit_users', 'send-friend-request', array( $this, 'render_admin_send_friend_request' ) );
		add_submenu_page( 'friends-settings', 'Feed', 'Refresh', 'manage_options', 'refresh', array( $this, 'admin_refresh_friend_posts' ) );
		if ( isset( $_GET['page'] ) && 'edit-friend' === $_GET['page'] ) {
			add_submenu_page( 'friends-settings', 'Edit User', 'Edit User', 'edit_users', 'edit-friend', array( $this, 'render_admin_edit_user' ) );
		}
	}

	/**
	 * Admin menu to refresh the friend posts.
	 */
	public function admin_refresh_friend_posts() {
		add_filter( 'notify_about_new_friend_post', '__return_false', 999 );
		$this->friends->feed->retrieve_friend_posts();
	}

	/**
	 * Subscribe to a friends site without becoming a friend
	 *
	 * @param  string $feed_url The feed URL to subscribe to.
	 * @return WP_User|WP_error $user The new associated user or an error object.
	 */
	public function subscribe( $feed_url ) {
		if ( ! is_string( $feed_url ) || ! wp_http_validate_url( $feed_url ) ) {
			return new WP_Error( 'invalid-url', 'An invalid URL was provided' );
		}

		$feed = fetch_feed( $feed_url );
		if ( is_wp_error( $feed ) ) {
			if ( '/feed/' === substr( $feed_url, -6 ) ) {
				// Retry with the entered URL, maybe an RSS feed was entered.
				$feed_url = substr( $feed_url, 0, -6 );
			}
			$feed = fetch_feed( $feed_url );
			if ( is_wp_error( $feed ) ) {
				return $feed;
			}
		}

		if ( '/feed/' === substr( $feed_url, -6 ) ) {
			$site_url = substr( $feed_url, 0, -6 );
		} else {
			$site_url = $feed_url;
		}
		$user = $this->friends->access_control->create_user( $site_url, 'subscription' );
		if ( ! is_wp_error( $user ) ) {
			$this->friends->feed->process_friend_feed( $user, $feed );
			update_user_option( $user->ID, 'friends_feed_url', $feed_url );
		}
		return $user;
	}

	/**
	 * Send a friend request to another WordPress with the Friends plugin
	 *
	 * @param  string $site_url The site URL of the friend's WordPress.
	 * @return WP_User|WP_error $user The new associated user or an error object.
	 */
	public function send_friend_request( $site_url ) {
		if ( ! is_string( $site_url ) || ! wp_http_validate_url( $site_url ) ) {
			return new WP_Error( 'invalid-url', 'An invalid URL was provided.' );
		}

		$response = wp_safe_remote_get(
			$site_url . '/wp-json/' . $this->friends->rest::NAMESPACE . '/hello', array(
				'timeout' => 20,
				'redirection' => 5,
			)
		);

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( 200 === wp_remote_retrieve_response_code( $response ) && $json ) {
			$site_url = rtrim( $json->site_url, '/' );
			if ( ! is_string( $site_url ) || ! wp_http_validate_url( $site_url ) ) {
				return new WP_Error( 'invalid-url-returned', 'An invalid URL was returned.' );
			}
		} else {
			if ( $json && isset( $json->code ) && isset( $json->message ) ) {
				if ( 'rest_no_route' !== $json->code ) {
					return new WP_Error( $json->code, $json->message, $json->data );
				}
			}

			return $this->subscribe( $site_url );
		}

		$user = $this->friends->access_control->get_user_for_site_url( $site_url );
		if ( $user && ! is_wp_error( $user ) && $user->has_cap( 'friend_request' ) ) {
			$user->set_role( 'friend' );
			return $user;
		}

		$user_login = $this->friends->access_control->get_user_login_for_site_url( $site_url );

		$friend_request_token = sha1( wp_generate_password( 256 ) );
		update_option( 'friends_request_token_' . sha1( $site_url ), $friend_request_token );

		$response = wp_remote_post(
			$site_url . '/wp-json/' . $this->friends->rest::NAMESPACE . '/friend-request', array(
				'body' => array(
					'site_url' => site_url(),
					'signature' => $friend_request_token,
				),
				'timeout' => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$json = json_decode( wp_remote_retrieve_body( $response ) );
			if ( $json && isset( $json->code ) && isset( $json->message ) ) {
				if ( 'rest_no_route' === $json->code ) {
					return $this->subscribe( $site_url . '/feed/' );
				}
				return new WP_Error( $json->code, $json->message, $json->data );
			}

			return new WP_Error( 'unexpected-rest-response', 'Unexpected server response.', $response );
		}

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		$user = $this->friends->access_control->create_user( $site_url, 'pending_friend_request' );
		if ( ! is_wp_error( $user ) ) {
			if ( isset( $json->friend_request_pending ) ) {
				$user->set_role( 'pending_friend_request' );
				update_option( 'friends_accept_token_' . $json->friend_request_pending, $user->ID );
			} elseif ( isset( $json->friend ) ) {
				$this->friends->access_control->make_friend( $user, $json->friend );
			}
		}

		return $user;
	}

	/**
	 * Don't show the edit link for friend posts
	 *
	 * @param  string $link    The edit link.
	 * @param  int    $user_id The user id.
	 * @return string|bool The edit link or false.
	 */
	public function admin_edit_user_link( $link, $user_id ) {
		$user = new WP_User( $user_id );
		if (
			! $user->has_cap( 'friend_request' ) &&
			! $user->has_cap( 'pending_friend_request' ) &&
			! $user->has_cap( 'friend' ) &&
			! $user->has_cap( 'subscription' )
		) {
			return $link;
		}

		return self_admin_url( 'admin.php?page=edit-friend&user=' . $user->ID );
	}

	/**
	 * Render the Friends Admin settings page
	 */
	public function render_admin_settings() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( __( 'Sorry, you are not allowed to change the settings.', 'friends' ) );
		}

		?><h1><?php esc_html_e( 'Friend Settings', 'friends' ); ?></h1>
		<?php
		if ( ! empty( $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'friends-settings' ) ) {
			foreach ( array( 'friends_ignore_incoming_friend_requests' ) as $checkbox ) {
				if ( isset( $_POST[ $checkbox ] ) && $_POST[ $checkbox ] ) {
					update_option( $checkbox, true );
				} else {
					delete_option( $checkbox );
				}
			}
			?>
			<div id="message" class="updated notice is-dismissible"><p>
			<?php
			esc_html_e( 'Your settings were updated.', 'friends' );
			?>
			</p></div>
			<?php
		}
		include __DIR__ . '/templates/admin/settings.php';
	}
	/**
	 * Render the Friends Edit User page
	 */
	public function render_admin_edit_user() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( __( 'Sorry, you are not allowed to edit this user.' ) );
		}

		if ( ! isset( $_GET['user'] ) ) {
			wp_die( __( 'Invalid user ID.' ) );
		}

		$user = new WP_User( intval( $_GET['user'] ) );
		if ( ! $user || is_wp_error( $user ) ) {
			wp_die( __( 'Invalid user ID.' ) );
		}

		if (
			! $user->has_cap( 'friend_request' ) &&
			! $user->has_cap( 'pending_friend_request' ) &&
			! $user->has_cap( 'friend' ) &&
			! $user->has_cap( 'subscription' )
		) {
			wp_die( __( 'This is not a user related to this plugin.', 'friends' ) );
		}

		?><h1><?php echo esc_html( $user->display_name ) ?></h1>
		<?php
		include __DIR__ . '/templates/admin/edit-friend.php';
	}

	/**
	 * Render the admin form for sending a friend request.
	 */
	public function render_admin_send_friend_request() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( __( 'Sorry, you are not allowed to send friend requests.', 'friends' ) );
		}

		$site_url = '';
		?><h1><?php esc_html_e( 'Send Friend Request', 'friends' ); ?></h1>
		<?php
		if ( ! empty( $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'send-friend-request' ) ) {

			$site_url = trim( $_POST['site_url'] );
			$protocol = wp_parse_url( $site_url, PHP_URL_SCHEME );
			if ( ! $protocol ) {
				$site_url = 'http://' . $site_url;
			}

			$response = $this->send_friend_request( $site_url );
			if ( is_wp_error( $response ) ) {
				?>
				<div id="message" class="updated error is-dismissible"><p><?php echo esc_html( $response->get_error_message() ); ?></p></div>
				<?php
			} elseif ( $response instanceof WP_User ) {
				$user_link = '<a href="' . esc_url( $response->user_url ) . '">' . esc_html( $response->user_url ) . '</a>';
				if ( $response->has_cap( 'pending_friend_request' ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
					<?php
					// translators: %s is a Site URL.
					echo wp_kses( sprintf( __( 'Friendship requested for site %s.', 'friends' ), $user_link ), array( 'a' => array( 'href' => array() ) ) );
					?>
					</p></div>
					<?php
				} elseif ( $response->has_cap( 'friend' ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
					<?php
					// translators: %s is a Site URL.
					echo wp_kses( sprintf( __( "You're now a friend of site %s.", 'friends' ), $user_link ), array( 'a' => array( 'href' => array() ) ) );
					?>
					</p></div>
					<?php
				} elseif ( $response->has_cap( 'subscription' ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
					<?php
					// translators: %s is a Site URL.
					echo wp_kses( sprintf( __( 'No friends plugin installed at %s. We subscribed you to their updates..', 'friends' ), $user_link ), array( 'a' => array( 'href' => array() ) ) );
					?>
					</p></div>
					<?php
				} else {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
					<?php
					// translators: %s is a username.
					echo esc_html( sprintf( __( 'User %s could not be assigned the appropriate role.', 'friends' ), $response->display_name ) );
					?>
					</p></div>
					<?php
				}
			}
		}
		if ( ! empty( $_GET['url'] ) ) {
			$site_url = $_GET['url'];
		}
		include __DIR__ . '/templates/admin/send-friend-request.php';
	}

	/**
	 * Add actions to the user rows
	 *
	 * @param  array   $actions The existing actions.
	 * @param  WP_User $user    The user in question.
	 * @return array The extended actions.
	 */
	public function user_row_actions( array $actions, WP_User $user ) {
		if (
			! $user->has_cap( 'friend_request' ) &&
			! $user->has_cap( 'pending_friend_request' ) &&
			! $user->has_cap( 'friend' ) &&
			! $user->has_cap( 'subscription' )
		) {
			return $actions;
		}
		unset( $actions['edit'] );
		$actions['view'] = '<a href="' . esc_url( $user->user_url ) . '">' . __( 'View' ) . '</a>';

		if ( $user->has_cap( 'friend_request' ) ) {
			$link = self_admin_url( wp_nonce_url( 'users.php?action=accept_friend_request&users[]=' . $user->ID ) );
			$actions['user_accept_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Accept Friend Request', 'friends' ) . '</a>';
		}

		if ( $user->has_cap( 'pending_friend_request' ) ) {
			$link = self_admin_url( wp_nonce_url( 'users.php?action=friend_request&users[]=' . $user->ID ) );
			$actions['user_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Resend Friend Request', 'friends' ) . '</a>';
		}

		if ( $user->has_cap( 'subscription' ) ) {
			$link = self_admin_url( wp_nonce_url( 'users.php?action=friend_request&users[]=' . $user->ID ) );
			$actions['user_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Send Friend Request', 'friends' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Handle bulk friend request approvals on the user page
	 *
	 * @param  string $sendback The URL to send the user back to.
	 * @param  string $action The requested action.
	 * @param  array  $users The selected users.
	 */
	public function handle_bulk_friend_request_approval( $sendback, $action, $users ) {
		if ( 'accept_friend_request' !== $action ) {
			return $sendback;
		}

		$accepted = 0;
		foreach ( $users as $user_id ) {
			$user = new WP_User( $user_id );
			if ( ! $user || is_wp_error( $user ) ) {
				continue;
			}

			if ( ! $user->has_cap( 'friend_request' ) ) {
				continue;
			}

			if ( $user->set_role( 'friend' ) ) {
				$accepted++;
			}
		}

		$sendback = add_query_arg( 'accepted', $accepted, $sendback );
		wp_safe_redirect( $sendback );
	}

	/**
	 * Handle bulk sending of friend requests on the user page
	 *
	 * @param  string $sendback The URL to send the user back to.
	 * @param  string $action The requested action.
	 * @param  array  $users The selected users.
	 */
	public function handle_bulk_send_friend_request( $sendback, $action, $users ) {
		if ( 'friend_request' !== $action ) {
			return $sendback;
		}

		$sent = 0;
		foreach ( $users as $user_id ) {
			$user = new WP_User( $user_id );
			if ( ! $user || is_wp_error( $user ) ) {
				continue;
			}

			if ( ! $user->has_cap( 'subscription' ) && $user->has_cap( 'friend_request' ) ) {
				continue;
			}

			if ( ! is_wp_error( $this->send_friend_request( $user->user_url ) ) ) {
				$sent++;
			}
		}

		$sendback = add_query_arg( 'sent', $sent, $sendback );
		wp_safe_redirect( $sendback );
	}

	/**
	 * Add options to the Bulk dropdown on the users page
	 *
	 * @param array $actions The existing bulk options.
	 * @return array The extended bulk options.
	 */
	public function add_user_bulk_options( $actions ) {
		$friends = new WP_User_Query( array( 'role' => 'friend_request' ) );

		if ( ! empty( $friends->get_results() ) ) {
			$actions['accept_friend_request'] = 'Accept Friend Request';
		}

		$friends = new WP_User_Query( array( 'role' => 'subscription' ) );

		if ( ! empty( $friends->get_results() ) ) {
			$actions['friend_request'] = 'Send Friend Request';
		}

		return $actions;
	}

	/**
	 * Add a Friends menu to the admin bar
	 *
	 * @param  WP_Admin_Bar $wp_menu The admin bar to modify.
	 */
	public function admin_bar_friends_menu( WP_Admin_Bar $wp_menu ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$wp_menu->add_menu(
			array(
				'id'     => 'friends',
				'parent' => 'site-name',
				'title'  => esc_html__( 'Friends', 'friends' ),
				'href'   => '/friends/',
			)
		);
		$wp_menu->add_menu(
			array(
				'id'     => 'send-friend-request',
				'parent' => 'friends',
				'title'  => esc_html__( 'Send Friend Request', 'friends' ),
				'href'   => self_admin_url( 'admin.php?page=send-friend-request' ),
			)
		);
		$wp_menu->add_menu(
			array(
				'id'     => 'friends-requests',
				'parent' => 'friends',
				'title'  => esc_html__( 'Friends & Requests', 'friends' ),
				'href'   => self_admin_url( 'users.php' ),
			)
		);
	}
}
