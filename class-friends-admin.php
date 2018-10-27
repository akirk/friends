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
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_friends_menu' ), 39 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 39 );
		add_action( 'gettext_with_context', array( $this, 'translate_user_role' ), 10, 4 );
	}

	/**
	 * Registers the admin menus
	 */
	public function register_admin_menu() {
		add_menu_page( 'Friends', 'Friends', 'manage_options', 'friends-settings', null, 'dashicons-groups', 3.73 );
		add_submenu_page( 'friends-settings', __( 'Settings' ), __( 'Settings' ), 'manage_options', 'friends-settings', array( $this, 'render_admin_settings' ) );
		add_submenu_page( 'friends-settings', __( 'Send Friend Request', 'friends' ), __( 'Send Friend Request', 'friends' ), Friends::REQUIRED_ROLE, 'send-friend-request', array( $this, 'render_admin_send_friend_request' ) );
		add_action( 'load-toplevel_page_friends-settings', array( $this, 'process_admin_settings' ) );

		add_submenu_page( 'friends-settings', __( 'Friends &amp; Requests', 'friends' ), __( 'Friends &amp; Requests', 'friends' ), Friends::REQUIRED_ROLE, 'users.php' );
		add_submenu_page( 'friends-settings', __( 'Refresh', 'friends' ), __( 'Refresh', 'friends' ), 'manage_options', 'friends-refresh', array( $this, 'admin_refresh_friend_posts' ) );

		if ( isset( $_GET['page'] ) && 'edit-friend' === $_GET['page'] ) {
			add_submenu_page( 'friends-settings', __( 'Edit User', 'friends' ), __( 'Edit User', 'friends' ), Friends::REQUIRED_ROLE, 'edit-friend', array( $this, 'render_admin_edit_friend' ) );
			add_action( 'load-friends_page_edit-friend', array( $this, 'process_admin_edit_friend' ) );
		}
		if ( isset( $_GET['page'] ) && 'suggest-friends-plugin' === $_GET['page'] ) {
			add_submenu_page( 'friends-settings', __( 'Suggest Friends Plugin', 'friends' ), __( 'Suggest Friends Plugin', 'friends' ), 'manage_options', 'suggest-friends-plugin', array( $this, 'render_suggest_friends_plugin' ) );
		}
	}

	/**
	 * Reference our script for the /friends page
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script( 'friends-admin', plugins_url( 'friends-admin.js', __FILE__ ), array( 'jquery' ) );
		$variables = array();
		wp_localize_script( 'friends-admin', 'friends', $variables );
	}

	/**
	 * Admin menu to refresh the friend posts.
	 */
	public function admin_refresh_friend_posts() {
		$friend = null;
		if ( isset( $_GET['user'] ) ) {
			$friend = new WP_User( intval( $_GET['user'] ) );
			if ( ! $friend || is_wp_error( $friend ) ) {
				wp_die( esc_html__( 'Invalid user ID.' ) );
			}
		}

		?>
		<h1><?php esc_html_e( "Refreshing Your Friends' Posts", 'friends' ); ?></h1>
		<?php

		add_filter( 'notify_about_new_friend_post', '__return_false', 999 );

		add_filter(
			'friends_friend_feed_url',
			function( $feed_url, $friend_user ) {
				// translators: %s is the name of the friend.
				printf( __( 'Refreshing %s', 'friends' ) . '<br/>', '<a href="' . esc_url( $feed_url ) . '">' . esc_html( $friend_user->user_login ) . '</a>' );
				return $feed_url;
			},
			10,
			2
		);

		add_action(
			'friends_retrieved_new_posts',
			function( $new_posts, $friend_user ) {
				// translators: %s is the number of new posts found.
				printf( _n( 'Found %d new post.', 'Found %d new posts.', count( $new_posts ), 'friends' ) . '<br/>', count( $new_posts ) );
			},
			10,
			2
		);

		add_action(
			'friends_retrieve_friends_error',
			function( $error, $friend_user ) {
				esc_html_e( 'An error occurred while retrieving the posts.', 'friends' );
				print_r( $error );
			},
			10,
			2
		);

		$this->friends->feed->retrieve_friend_posts( $friend );
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
		$site_url = $feed_url;

		$feed = $this->friends->feed->fetch_feed( $feed_url );
		if ( $feed->all_discovered_feeds ) {
			$feed_url = $feed->all_discovered_feeds[0]->url;
			$feed     = $this->friends->feed->fetch_feed( $feed_url );
		}
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		if ( '/feed/' === substr( $feed_url, -6 ) ) {
			$site_url = substr( $feed_url, 0, -6 );
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
			return new WP_Error( 'invalid-url', __( 'You entererd an invalid URL.', 'friends' ) );
		}

		if ( 0 === strcasecmp( site_url(), $friend_url ) ) {
			return new WP_Error( 'friend-yourself', __( 'It seems like you sent a friend request to yourself.', 'friends' ) );
		}

		$user = $this->friends->access_control->get_user_for_site_url( $friend_url );
		if ( $user && ! is_wp_error( $user ) && $this->friends->access_control->is_valid_friend( $user ) ) {
			return new WP_Error( 'already-friend', __( 'You are already friends with this site.', 'friends' ) );
		}

		$response = wp_safe_remote_get(
			$friend_url . '/wp-json/' . Friends_REST::PREFIX . '/hello',
			array(
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

		// Sending a friend request to someone who has requested friendship is like accepting the friend request.
		$friend_user = $this->friends->access_control->get_user_for_site_url( $friend_url );
		if ( $friend_user && ! is_wp_error( $friend_user ) && $friend_user->has_cap( 'friend_request' ) ) {
			$this->update_in_token( $friend_user->ID );
			$friend_user->set_role( get_option( 'friends_default_friend_role', 'friend' ) );
			return $friend_user;
		}

		$user_login = $this->friends->access_control->get_user_login_for_site_url( $friend_url );

		$friend_request_token = sha1( wp_generate_password( 256 ) );
		update_option( 'friends_request_token_' . sha1( $friend_url ), $friend_request_token );

		$current_user = wp_get_current_user();
		$response     = wp_remote_post(
			$friend_url . '/wp-json/' . Friends_REST::PREFIX . '/friend-request',
			array(
				'body'        => array(
					'site_url'  => site_url(),
					'name'      => $current_user->display_name,
					'gravatar'  => get_avatar_url( $current_user->ID ),
					'signature' => $friend_request_token,
				),
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( $json && isset( $json->code ) && isset( $json->message ) ) {
				if ( 'rest_no_route' === $json->code ) {
					return $this->subscribe( $friend_url );
				}
				return new WP_Error( $json->code, $json->message, $json->data );
			}

			return new WP_Error( 'unexpected-rest-response', 'Unexpected server response.', $response );
		}

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

				if ( isset( $json->gravatar ) ) {
					$this->friends->access_control->update_gravatar( $user->ID, $json->gravatar );
				}

				if ( isset( $json->name ) ) {
					wp_update_user(
						array(
							'ID'           => $user->ID,
							'nickname'     => $json->name,
							'first_name'   => $json->name,
							'display_name' => $json->name,
						)
					);
				}

				$response = wp_safe_remote_post(
					$user->user_url . '/wp-json/' . Friends_REST::PREFIX . '/friend-request-accepted',
					array(
						'body'        => array(
							'token'  => $json->token,
							'friend' => $this->friends->access_control->update_in_token( $user->ID ),
							'proof'  => sha1( $json->token . $friend_request_token ),
						),
						'timeout'     => 20,
						'redirection' => 5,
					)
				);
				delete_user_option( $user->ID, 'friends_request_token' );

				$json = json_decode( wp_remote_retrieve_body( $response ) );
				if ( $json->friend ) {
					update_user_option( $user->ID, 'friends_out_token', $json->friend );
				}
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
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
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

		foreach ( array( 'ignore_incoming_friend_requests', 'ignore_recommendations' ) as $checkbox ) {
			if ( isset( $_POST[ $checkbox ] ) && $_POST[ $checkbox ] ) {
				update_option( 'friends_' . $checkbox, true );
			} else {
				delete_option( 'friends_' . $checkbox );
			}
		}

		foreach ( array( 'friend_request_notification' ) as $negative_user_checkbox ) {
			if ( isset( $_POST[ $negative_user_checkbox ] ) && $_POST[ $negative_user_checkbox ] ) {
				delete_user_option( get_current_user_id(), 'friends_no_' . $negative_user_checkbox );
			} else {
				update_user_option( get_current_user_id(), 'friends_no_' . $negative_user_checkbox, 1 );
			}
		}

		if ( isset( $_POST['main_user_id'] ) && is_numeric( $_POST['main_user_id'] ) ) {
			update_option( 'friends_main_user_id', intval( $_POST['main_user_id'] ) );
		}

		if ( isset( $_POST['default_role'] ) && in_array( $_POST['default_role'], array( 'friend', 'restricted_friend' ), true ) ) {
			update_option( 'friends_default_friend_role', $_POST['default_role'] );
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

		$current_user = wp_get_current_user();

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

		$potential_main_users = Friends::all_admin_users();
		$main_user_id         = $this->friends->get_main_friend_user_id();
		$default_role         = get_option( 'friends_default_friend_role', 'friend' );

		include apply_filters( 'friends_template_path', 'admin/settings.php' );
	}

	/**
	 * Process access for the Friends Edit User page
	 */
	private function check_admin_edit_friend() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
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
				$friend->set_role( get_option( 'friends_default_friend_role', 'friend' ) );
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
		} elseif ( isset( $_GET['change-to-restricted-friend'] ) && wp_verify_nonce( $_GET['change-to-restricted-friend'], 'change-to-restricted-friend-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'friend' ) ) {
				$friend->set_role( 'restricted_friend' );
			}
		} elseif ( isset( $_GET['change-to-friend'] ) && wp_verify_nonce( $_GET['change-to-friend'], 'change-to-friend-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'restricted_friend' ) ) {
				$friend->set_role( 'friend' );
			}
		} elseif ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'edit-friend-' . $friend->ID ) ) {
			if ( trim( $_POST['friends_display_name'] ) ) {
				$friend->display_name = trim( $_POST['friends_display_name'] );
			}
			if ( trim( $_POST['user_url'] ) && filter_var( $_POST['user_url'], FILTER_VALIDATE_URL ) ) {
				$friend->user_url = $_POST['user_url'];
			}
			wp_update_user( $friend );

			if ( ! get_user_option( 'friends_no_new_post_notification' ) ) {
				if ( isset( $_POST['friends_new_post_notification'] ) && $_POST['friends_new_post_notification'] ) {
					delete_user_option( get_current_user_id(), 'friends_no_new_post_notification_' . $friend->ID );
				} else {
					update_user_option( get_current_user_id(), 'friends_no_new_post_notification_' . $friend->ID, 1 );
				}
			}

			if ( isset( $_POST['friends_feed_url'] ) && filter_var( $_POST['friends_feed_url'], FILTER_VALIDATE_URL ) ) {
				update_user_option( $friend->ID, 'friends_feed_url', $_POST['friends_feed_url'] );
			} else {
				delete_user_option( $friend->ID, 'friends_feed_url' );
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
		$friend       = $this->check_admin_edit_friend();
		$friend_posts = new WP_Query(
			array(
				'post_type'   => Friends::FRIEND_POST_CACHE,
				'post_status' => array( 'publish', 'private' ),
				'author'      => $friend->ID,
			)
		);

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
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
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

			if ( isset( $_POST['just-subscribe'] ) ) {
				$friend_user = $this->subscribe( $friend_url );
			} else {
				$friend_user = $this->send_friend_request( $friend_url );
			}

			if ( is_wp_error( $friend_user ) ) {
				?>
				<div id="message" class="updated error is-dismissible"><p><?php echo esc_html( $friend_user->get_error_message() ); ?></p></div>
				<?php
			} elseif ( $friend_user instanceof WP_User ) {
				$friend_link = '<a href="' . esc_url( $friend_user->user_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $friend_user->user_url ) . '</a>';
				if ( $friend_user->has_cap( 'pending_friend_request' ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
						<?php
						// translators: %s is a Site URL.
						echo wp_kses( sprintf( __( 'Friendship requested for site %s.', 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
						// translators: %s is a Site URL.
						echo ' ', wp_kses( sprintf( __( 'Until they respond, we have already subscribed you to their updates.', 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
						// translators: %s is the friends page URL.
						echo ' ', wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> to view their posts.', 'friends' ), '"' . site_url( '/friends/' . sanitize_title_with_dashes( $friend_user->user_login ) . '/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
						?>
					</p></div>
					<?php
				} elseif ( $friend_user->has_cap( 'friend' ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
						<?php
						// translators: %s is a Site URL.
						echo wp_kses( sprintf( __( "You're now a friend of site %s.", 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
						// translators: %s is the friends page URL.
						echo ' ', wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> to view their posts.', 'friends' ), '"' . site_url( '/friends/' . sanitize_title_with_dashes( $friend_user->user_login ) . '/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
						?>
					</p></div>
					<?php
				} elseif ( $friend_user->has_cap( 'subscription' ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
						<?php
						if ( isset( $_POST['request-friendship'] ) ) {
							// translators: %s is a Site URL.
							echo wp_kses( sprintf( __( 'No friends plugin installed at %s.', 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
							echo ' ';
						}
						esc_html_e( 'We subscribed you to their updates.', 'friends' );
						// translators: %s is the friends page URL.
						echo ' ', wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> to view their posts.', 'friends' ), '"' . site_url( '/friends/' . sanitize_title_with_dashes( $friend_user->user_login ) . '/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
						?>
					</p></div>
					<?php
					$this->render_suggest_friends_plugin( null, $friend_user );
					return;
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

		$friend_requests = new WP_User_Query(
			array(
				'role__in' => array( 'friend', 'restricted_friend', 'pending_friend_request', 'friend_request', 'subscription' ),
				'orderby'  => 'registered',
				'order'    => 'DESC',
			)
		);
		$friend_requests = $friend_requests->get_results();

		$wp_roles = wp_roles();
		$roles    = $wp_roles->get_names();

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
	 * Enable translated user roles.
	 * props https://wordpress.stackexchange.com/a/141705/74893
	 *
	 * @param string $translations    The potentially translated text.
	 * @param string $text    Text to translate.
	 * @param string $context Context information for the translators.
	 * @param string $domain  Unique identifier for retrieving translated strings.
	 * @return string Translated text on success, original text on failure.
	 */
	public function translate_user_role( $translations, $text, $context, $domain ) {

		$roles = array(
			'Friend',
			'Friend Request',
			'Pending Friend Request',
			'Subscription',
		);

		if (
			'User role' === $context
			&& in_array( $text, $roles, true )
			&& 'friends' !== $domain
		) {
			// @codingStandardsIgnoreLine
			return translate_with_gettext_context( $text, $context, 'friends' );
		}

		return $translations;
	}

	/**
	 * Renders a form where the user can suggest the friends plugin to a friend.
	 *
	 * @param string  $string A string passed by the admin hook.
	 * @param WP_User $friend The friend to which to suggest the plugin.
	 */
	public function render_suggest_friends_plugin( $string, WP_User $friend = null ) {
		?>
		<p><?php esc_html_e( "Maybe you'll want to suggest your friend to install the friends plugin?", 'friends' ); ?> <?php esc_html_e( 'We have prepared some tools for you to give them instructions on how to do so.' ); ?></p>
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
		if ( ! isset( $_GET['tab'] ) ) {
			$_GET['tab'] = 'e-mail';
		}

		if ( ! $friend && isset( $_GET['url'] ) ) {
			$friend = $this->friends->access_control->get_user_for_site_url( $_GET['url'] );
			$url    = $_GET['url'];
			if ( 'http' !== substr( $url, 0, 4 ) ) {
				$url = 'http://' . $url;
			}
		} else {
			$url = $friend->user_url;
		}

		if ( $friend && ! is_wp_error( $friend ) ) {
			$domain = parse_url( $friend->user_url, PHP_URL_HOST );
			$to     = '@' . $domain;
		} else {
			$domain = '';
			$to     = '';
		}

		$tweet = '@' . $domain . ' ';
		// translators: %s is the URL of the blog.
		$tweet .= sprintf( __( 'Would you like to install the Friends plugin to your WordPress blog %s to stay in touch with me?', 'friends' ), $url );
		$tweet .= ' ' . Friends::PLUGIN_URL;

		$tabs = array(
			'E-Mail'   => admin_url( 'admin.php?page=suggest-friends-plugin&url=' . urlencode( $url ) ),
			'Twitter'  => 'https://twitter.com/intent/tweet?text=' . urlencode( $tweet ),
			'Facebook' => 'https://facebook.com/sharer/sharer.php?u=' . urlencode( Friends::PLUGIN_URL ),
		)

		?>
		<h2 class="nav-tab-wrapper" style="margin-bottom: 2em">
			<?php
			foreach ( $tabs as $tab_title => $tab_url ) {
				printf(
					'<a href="%s" class="nav-tab %s">%s</a>',
					esc_url( $tab_url ),
					esc_attr( 'E-Mail' === $tab_title ? 'nav-tab-active' : '' ),
					esc_html( $tab_title )
				);
			}
			?>
		</h2>
		<?php

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

		$actions['view'] = '<a href="' . esc_url( $user->user_url ) . '" target="_blank" rel="noopener noreferrer">' . __( 'Visit' ) . '</a>';

		if ( $user->has_cap( 'friend_request' ) ) {
			$link                                  = self_admin_url( wp_nonce_url( 'users.php?action=accept_friend_request&users[]=' . $user->ID ) );
			$actions['user_accept_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Accept Friend Request', 'friends' ) . '</a>';
			$actions['friend_request_date']        = '<div class="nonessential">Requested on ' . date_i18n( __( 'F j, Y g:i a' ), strtotime( $user->user_registered ) ) . '</div>';

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
			$user->set_role( get_option( 'friends_default_friend_role', 'friend' ) );
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
		$friends = Friends::all_friend_requests();
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
		$friends_url   = site_url( '/friends/' );
		$friends_title = __( 'Friends', 'friends' );
		$open_requests = 0;

		if ( current_user_can( 'friend' ) ) {
			$current_user = wp_get_current_user();
			$friends_url  = $current_user->user_url . '/friends/';
		}

		$friends_main_url = $friends_url;
		if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
			$friend_requests = Friends::all_friend_requests();
			$open_requests   = $friend_requests->get_total();
			if ( $open_requests > 0 ) {
				// translators: %s is the number of open friend requests.
				$friends_title    = sprintf( __( 'Friends (%s)', 'friends' ), $open_requests );
				$friends_main_url = self_admin_url( 'users.php?role=friend_request' );
			}
		}

		$wp_menu->add_node(
			array(
				'id'     => 'friends',
				'parent' => '',
				'title'  => '<span class="ab-icon dashicons dashicons-groups"></span> ' . esc_html( $friends_title ),
				'href'   => $friends_main_url,
			)
		);

		if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
			if ( $open_requests > 0 ) {
				$wp_menu->add_menu(
					array(
						'id'     => 'open-friend-requests',
						'parent' => 'friends',
						// translators: %s is the number of open friend requests.
						'title'  => esc_html( sprintf( _n( 'Review %s Friend Request', 'Review %s Friends Request', $open_requests, 'friends' ), $open_requests ) ),
						'href'   => self_admin_url( 'users.php?role=friend_request' ),
					)
				);
			}
			$wp_menu->add_menu(
				array(
					'id'     => 'your-feed',
					'parent' => 'friends',
					'title'  => esc_html__( 'Latest Posts', 'friends' ),
					'href'   => site_url( '/friends/' ),
				)
			);
			$wp_menu->add_menu(
				array(
					'id'     => 'your-profile',
					'parent' => 'friends',
					'title'  => esc_html__( 'Your Profile' ),
					'href'   => site_url( '/friends/?public' ),
				)
			);
			$wp_menu->add_menu(
				array(
					'id'     => 'send-friend-request',
					'parent' => 'friends',
					'title'  => esc_html__( 'Add a Friend', 'friends' ),
					'href'   => self_admin_url( 'admin.php?page=send-friend-request' ),
				)
			);
			$wp_menu->add_menu(
				array(
					'id'     => 'friends-requests',
					'parent' => 'friends',
					'title'  => esc_html__( 'Your Friends & Requests', 'friends' ),
					'href'   => self_admin_url( 'users.php' ),
				)
			);
			$wp_menu->add_menu(
				array(
					'id'     => 'friends-settings',
					'parent' => 'friends',
					'title'  => esc_html__( 'Settings' ),
					'href'   => self_admin_url( 'admin.php?page=friends-settings' ),
				)
			);
		}

		if ( current_user_can( 'friend' ) ) {
			$wp_menu->add_menu(
				array(
					'id'     => 'send-friend-request',
					'parent' => 'friends',
					'title'  => esc_html__( 'Profile' ),
					'href'   => site_url( '/friends/' ),
				)
			);
		}
	}
}
