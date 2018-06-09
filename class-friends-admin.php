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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 10, 3 );
		add_filter( 'user_row_actions', array( $this, 'user_row_actions' ), 10, 2 );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_friend_request_approval' ), 10, 3 );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_send_friend_request' ), 10, 3 );
		add_filter( 'bulk_actions-users', array( $this, 'add_user_bulk_options' ) );
		add_filter( 'get_edit_user_link', array( $this, 'admin_edit_user_link' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_friends_menu' ), 100 );
	}

	/**
	 * Registers the admin menus
	 */
	public function register_admin_menu() {
		add_menu_page( 'Friends', 'Friends', 'manage_options', 'friends-settings', null, 'dashicons-groups', 3.73 );
		add_submenu_page( 'friends-settings', 'Settings', 'Settings', 'manage_options', 'friends-settings', array( $this, 'render_admin_settings' ) );
		add_submenu_page( 'friends-settings', 'Send Friend Request', 'Send Friend Request', 'edit_users', 'send-friend-request', array( $this, 'render_admin_send_friend_request' ) );
		add_action( 'load-toplevel_page_friends-settings', array( $this, 'process_admin_settings' ) );

		add_submenu_page( 'friends-settings', 'Feed', 'Friends &amp; Requests', 'edit_users', 'users.php' );
		add_submenu_page( 'friends-settings', 'Feed', 'Refresh', 'manage_options', 'friends-refresh', array( $this, 'admin_refresh_friend_posts' ) );

		if ( isset( $_GET['page'] ) && 'edit-friend' === $_GET['page'] ) {
			add_submenu_page( 'friends-settings', 'Edit User', 'Edit User', 'edit_users', 'edit-friend', array( $this, 'render_admin_edit_friend' ) );
			add_action( 'load-friends_page_edit-friend', array( $this, 'process_admin_edit_friend' ) );
		}
		if ( isset( $_GET['page'] ) && 'suggest-friends-plugin' === $_GET['page'] ) {
			add_submenu_page( 'friends-settings', 'Suggest Friends Plugin', 'Suggest Friends Plugin', 'manage_options', 'suggest-friends-plugin', array( $this, 'render_suggest_friends_plugin' ) );
		}
	}

	/**
	 * Admin menu to refresh the friend posts.
	 */
	public function admin_refresh_friend_posts() {
		?>
		<h1><?php esc_html_e( "Refreshing Your Friends' Posts", 'friends' ); ?></h1>
		<?php
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
	 * @param  string $friend_url The site URL of the friend's WordPress.
	 * @return WP_User|WP_error $user The new associated user or an error object.
	 */
	public function send_friend_request( $friend_url ) {
		if ( ! is_string( $friend_url ) || ! wp_http_validate_url( $friend_url ) ) {
			return new WP_Error( 'invalid-url', __( 'An invalid URL was provided.', 'friends' ) );
		}

		if ( 0 === strcasecmp( site_url(), $friend_url ) ) {
			return new WP_Error( 'friend-yourself', __( 'It seems like you sent a friend request to yourself.', 'friends' ) );
		}

		$user = $this->friends->access_control->get_user_for_site_url( $friend_url );
		if ( $user && ! is_wp_error( $user ) && $user->has_cap( 'friend' ) ) {
			return new WP_Error( 'already-friend', __( 'You are already friends with this site.', 'friends' ) );
		}

		$response = wp_safe_remote_get(
			$friend_url . '/wp-json/' . Friends_REST::PREFIX . '/hello', array(
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( 200 === wp_remote_retrieve_response_code( $response ) && $json ) {
			$friend_url = rtrim( $json->site_url, '/' );
			if ( ! is_string( $friend_url ) || ! wp_http_validate_url( $friend_url ) ) {
				return new WP_Error( 'invalid-url-returned', 'An invalid URL was returned.' );
			}
		} else {
			if ( $json && isset( $json->code ) && isset( $json->message ) ) {
				if ( 'rest_no_route' !== $json->code ) {
					return new WP_Error( $json->code, $json->message, $json->data );
				}
			}

			return $this->subscribe( $friend_url );
		}

		// Refetch the user in case the site actually reports a different URL.
		$user = $this->friends->access_control->get_user_for_site_url( $friend_url );
		if ( $user && ! is_wp_error( $user ) && $user->has_cap( 'friend_request' ) ) {
			$this->update_in_token( $user->ID );
			$user->set_role( 'friend' );
			return $user;
		}

		$user_login = $this->friends->access_control->get_user_login_for_site_url( $friend_url );

		$friend_request_token = sha1( wp_generate_password( 256 ) );
		update_option( 'friends_request_token_' . sha1( $friend_url ), $friend_request_token );

		$response = wp_remote_post(
			$friend_url . '/wp-json/' . Friends_REST::PREFIX . '/friend-request', array(
				'body'        => array(
					'site_url'  => site_url(),
					'signature' => $friend_request_token,
				),
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$json = json_decode( wp_remote_retrieve_body( $response ) );
			if ( $json && isset( $json->code ) && isset( $json->message ) ) {
				if ( 'rest_no_route' === $json->code ) {
					return $this->subscribe( $friend_url . '/feed/' );
				}
				return new WP_Error( $json->code, $json->message, $json->data );
			}

			return new WP_Error( 'unexpected-rest-response', 'Unexpected server response.', $response );
		}

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $json || ! is_object( $json ) ) {
			return new WP_Error( 'unexpected-rest-response', 'Unexpected server response.', $response );
		}

		$user = $this->friends->access_control->create_user( $friend_url, 'pending_friend_request' );
		if ( ! is_wp_error( $user ) ) {
			if ( isset( $json->friend_request_pending ) ) {
				update_option( 'friends_accept_token_' . $json->friend_request_pending, $user->ID );
				$user->set_role( 'pending_friend_request' );
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
	 * Check access for the Friends Admin settings page
	 */
	public function check_admin_settings() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the settings.', 'friends' ) );
		}
	}

	/**
	 * Process the Friends Admin settings page
	 */
	public function process_admin_settings() {
		$this->check_admin_settings();

		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'friends-settings' ) ) {
			return;
		}

		foreach ( array( 'ignore_incoming_friend_requests' ) as $checkbox ) {
			if ( isset( $_POST[ $checkbox ] ) && $_POST[ $checkbox ] ) {
				update_option( 'friends_' . $checkbox, true );
			} else {
				delete_option( 'friends_' . $checkbox );
			}
		}

		if ( isset( $_POST['friend_request_notification'] ) && $_POST['friend_request_notification'] ) {
			delete_user_option( get_current_user_id(), 'friends_no_friend_request_notification' );
		} else {
			update_user_option( get_current_user_id(), 'friends_no_friend_request_notification', 1 );
		}
		if ( isset( $_POST['new_post_notification'] ) && $_POST['new_post_notification'] ) {
			delete_user_option( get_current_user_id(), 'friends_no_new_post_notification' );
		} else {
			update_user_option( get_current_user_id(), 'friends_no_new_post_notification', 1 );
		}

		if ( isset( $_GET['wp_http_referer'] ) ) {
			wp_safe_redirect( $_GET['wp_http_referer'] );
		} else {
			wp_safe_redirect( add_query_arg( 'updated', '1', remove_query_arg( array( 'wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
		}
		exit;
	}

	/**
	 * Render the Friends Admin settings page
	 */
	public function render_admin_settings() {
		$this->check_admin_settings();

		$user = wp_get_current_user();

		?>
		<h1><?php esc_html_e( 'Friend Settings', 'friends' ); ?></h1>
		<?php
		if ( isset( $_GET['updated'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
				<?php
				esc_html_e( 'Your settings were updated.', 'friends' );
				?>
			</p></div>
			<?php
		}
		include apply_filters( 'friends_template_path', 'admin/settings.php' );
	}

	/**
	 * Process access for the Friends Edit User page
	 */
	private function check_admin_edit_friend() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this user.' ) );
		}

		if ( ! isset( $_GET['user'] ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) );
		}

		$friend = new WP_User( intval( $_GET['user'] ) );
		if ( ! $friend || is_wp_error( $friend ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) );
		}

		if (
			! $friend->has_cap( 'friend_request' ) &&
			! $friend->has_cap( 'pending_friend_request' ) &&
			! $friend->has_cap( 'friend' ) &&
			! $friend->has_cap( 'subscription' )
		) {
			wp_die( esc_html__( 'This is not a user related to this plugin.', 'friends' ) );
		}

		return $friend;
	}

	/**
	 * Process the Friends Edit User page
	 */
	public function process_admin_edit_friend() {
		$friend    = $this->check_admin_edit_friend();
		$arg       = 'updated';
		$arg_value = 1;

		if ( isset( $_GET['accept-friend-request'] ) && wp_verify_nonce( $_GET['accept-friend-request'], 'accept-friend-request-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'friend_request' ) ) {
				$this->friends->access_control->update_in_token( $friend->ID );
				$friend->set_role( 'friend' );
				$arg = 'friend';
			}
		} elseif ( isset( $_GET['send-friend-request'] ) && wp_verify_nonce( $_GET['send-friend-request'], 'send-friend-request-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'pending_friend_request' ) || $friend->has_cap( 'subscription' ) ) {
				$response = $this->send_friend_request( $friend->user_url );
				if ( is_wp_error( $response ) ) {
					$arg = 'error';
				} elseif ( $response instanceof WP_User ) {
					if ( $response->has_cap( 'pending_friend_request' ) ) {
						$arg = 'sent-request';
						// translators: %s is a Site URL.
						$arg_value = wp_kses( sprintf( __( 'Friendship requested for site %s.', 'friends' ), $user_link ), array( 'a' => array( 'href' => array() ) ) );
					} elseif ( $response->has_cap( 'friend' ) ) {
						$arg       = 'friend';
						$arg_value = 1;
					} elseif ( $response->has_cap( 'subscription' ) ) {
						$arg       = 'subscribed';
						$arg_value = 1;
					}
				}
			}
		} elseif ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'edit-friend-' . $friend->ID ) ) {
			if ( trim( $_POST['friends_display_name'] ) ) {
				$friend->display_name = trim( $_POST['friends_display_name'] );
			}
			wp_update_user( $friend );

			if ( ! get_user_option( 'friends_no_new_post_notification' ) ) {
				if ( isset( $_POST['friends_new_post_notification'] ) && $_POST['friends_new_post_notification'] ) {
					delete_user_option( get_current_user_id(), 'friends_no_new_post_notification_' . $friend->ID );
				} else {
					update_user_option( get_current_user_id(), 'friends_no_new_post_notification_' . $friend->ID, 1 );
				}
			}
		} else {
			return;
		}

		if ( isset( $_GET['wp_http_referer'] ) ) {
			wp_safe_redirect( $_GET['wp_http_referer'] );
		} else {
			wp_safe_redirect( add_query_arg( $arg, $arg_value, remove_query_arg( array( 'wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
		}
		exit;
	}

	/**
	 * Render the Friends Edit User page
	 */
	public function render_admin_edit_friend() {
		$friend = $this->check_admin_edit_friend();

		?>
		<h1><?php echo esc_html( $friend->display_name ); ?></h1>
		<?php

		if ( isset( $_GET['updated'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'User was updated.', 'friends' ); ?></p></div>
			<?php
		} elseif ( isset( $_GET['friend'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'You are now friends.', 'friends' ); ?></p></div>
			<?php
		} elseif ( isset( $_GET['error'] ) ) {
						?>
			<div id="message" class="updated error is-dismissible"><p><?php esc_html_e( 'An error occurred.', 'friends' ); ?></p></div>
			<?php

		} elseif ( isset( $_GET['sent-request'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'Your request was sent.', 'friends' ); ?></p></div>
			<?php

		} elseif ( isset( $_GET['subscribed'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'Subscription activated.', 'friends' ); ?></p></div>
			<?php
		}

		include apply_filters( 'friends_template_path', 'admin/edit-friend.php' );
	}

	/**
	 * Render the admin form for sending a friend request.
	 */
	public function render_admin_send_friend_request() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to send friend requests.', 'friends' ) );
		}

		$friend_url = '';
		?>
		<h1><?php esc_html_e( 'Send Friend Request', 'friends' ); ?></h1>
		<?php
		if ( ! empty( $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'send-friend-request' ) ) {
			$friend_url = trim( $_POST['friend_url'] );
			$protocol   = wp_parse_url( $friend_url, PHP_URL_SCHEME );
			if ( ! $protocol ) {
				$friend_url = 'http://' . $friend_url;
			}

			$friend = $this->send_friend_request( $friend_url );
			if ( is_wp_error( $friend ) ) {
				?>
				<div id="message" class="updated error is-dismissible"><p><?php echo esc_html( $friend->get_error_message() ); ?></p></div>
				<?php
			} elseif ( $friend instanceof WP_User ) {
				$friend_link = '<a href="' . esc_url( $friend->user_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $friend->user_url ) . '</a>';
				if ( $friend->has_cap( 'pending_friend_request' ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
						<?php
						// translators: %s is a Site URL.
						echo wp_kses( sprintf( __( 'Friendship requested for site %s.', 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
						?>
					</p></div>
					<?php
				} elseif ( $friend->has_cap( 'friend' ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
						<?php
						// translators: %s is a Site URL.
						echo wp_kses( sprintf( __( "You're now a friend of site %s.", 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
						?>
					</p></div>
					<?php
				} elseif ( $friend->has_cap( 'subscription' ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
						<?php
						// translators: %s is a Site URL.
						echo wp_kses( sprintf( __( 'No friends plugin installed at %s. We subscribed you to their updates.', 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
						?>
					</p></div>
					<?php
					$this->render_suggest_friends_plugin( null, $friend );
				} else {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
						<?php
						// translators: %s is a username.
						echo esc_html( sprintf( __( 'User %s could not be assigned the appropriate role.', 'friends' ), $user->display_name ) );
						?>
					</p></div>
					<?php
				}
			}
		}
		if ( ! empty( $_GET['url'] ) ) {
			$friend_url = $_GET['url'];
		}
		include apply_filters( 'friends_template_path', 'admin/send-friend-request.php' );
	}

	/**
	 * Handles the submitted form when suggesting the friends plugin to a friend.
	 */
	public function process_suggest_friends_plugin() {
		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'suggest-friends-plugin' ) ) {
			return;
		}

		$to = wp_unslash( $_POST['to'] );
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'invalid-email', "You didn't specify a valid e-mail address of your friend." );
		}

		$subject = trim( wp_unslash( $_POST['subject'] ) );
		if ( ! $subject ) {
			return new WP_Error( 'empty-subject', 'You specified no subject to send to your friend.' );
		}

		$message = trim( wp_unslash( $_POST['message'] ) );
		if ( ! $message ) {
			return new WP_Error( 'empty-message', 'You specified an empty message to send to your friend.' );
		}

		return $this->friends->notifications->send_mail( $to, $subject, $message );
	}

	/**
	 * Renders a form where the user can suggest the friends plugin to a friend.
	 *
	 * @param string  $string A string passed by the admin hook.
	 * @param WP_User $friend The friend to which to suggest the plugin.
	 */
	public function render_suggest_friends_plugin( $string, WP_User $friend = null ) {
		?>
		<h1><?php esc_html_e( 'Recommend the Friends plugin to a friend' ); ?></h1>
		<?php

		$error = $this->process_suggest_friends_plugin();
		if ( is_wp_error( $error ) ) {
			?>
			<div id="message" class="updated error is-dismissible"><p><?php echo $error->get_error_message(); ?></p></div>
			<?php
		} elseif ( $error ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'The message to your friend was sent.', 'friends' ); ?></p></div>
			<?php
		}

		if ( ! $friend && isset( $_GET['url'] ) ) {
			$friend = $this->friends->access_control->get_user_for_site_url( $_GET['url'] );
		}

		if ( $friend && ! is_wp_error( $friend ) ) {
			$domain = parse_url( $friend->user_url, PHP_URL_HOST );
			$to     = '@' . $domain;
		} else {
			$domain = '';
			$to     = '';
		}

		if ( isset( $_POST['to'] ) ) {
			$to = wp_unslash( $_POST['to'] );
		}

		if ( isset( $_POST['subject'] ) ) {
			$subject = wp_unslash( $_POST['subject'] );
		} else {
			// translators: %s is the domain of the friend's WordPress.
			$subject = sprintf( __( 'Install the Friends plugin on %s' ), $domain );
		}

		if ( isset( $_POST['message'] ) ) {
			$message = wp_unslash( $_POST['message'] );
		} else {
			ob_start();
			include apply_filters( 'friends_template_path', 'email/suggest-friends-plugin.text.php' );
			$message = ob_get_contents();
			ob_end_clean();
		}

		include apply_filters( 'friends_template_path', 'admin/suggest-friends-plugin.php' );
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

		$actions['view'] = '<a href="' . esc_url( $user->user_url ) . '" target="_blank" rel="noopener noreferrer">' . __( 'View' ) . '</a>';

		if ( $user->has_cap( 'friend_request' ) ) {
			$link                                  = self_admin_url( wp_nonce_url( 'users.php?action=accept_friend_request&users[]=' . $user->ID ) );
			$actions['user_accept_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Accept Friend Request', 'friends' ) . '</a>';
		}

		if ( $user->has_cap( 'pending_friend_request' ) ) {
			$link                           = self_admin_url( wp_nonce_url( 'users.php?action=friend_request&users[]=' . $user->ID ) );
			$actions['user_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Resend Friend Request', 'friends' ) . '</a>';
		}

		if ( $user->has_cap( 'subscription' ) ) {
			$link                           = self_admin_url( wp_nonce_url( 'users.php?action=friend_request&users[]=' . $user->ID ) );
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

			if ( $user->has_cap( 'friend' ) ) {
				continue;
			}

			$this->friends->access_control->update_in_token( $user->ID );
			$user->set_role( 'friend' );
			$accepted++;
		}

		if ( ! $sendback ) {
			return array(
				'accepted' => $accepted,
			);
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
		$friends->get_results();

		if ( ! empty( $friends ) ) {
			$actions['accept_friend_request'] = 'Accept Friend Request';
		}

		$friends = new WP_User_Query( array( 'role' => 'subscription' ) );
		$friends->get_results();

		if ( ! empty( $friends ) ) {
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
				'href'   => site_url( '/friends/' ),
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
