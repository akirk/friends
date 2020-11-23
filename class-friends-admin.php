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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_head-users.php', array( $this, 'keep_friends_open_on_users_screen' ) );
		add_filter( 'user_row_actions', array( $this, 'user_row_actions' ), 10, 2 );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_friend_request_approval' ), 10, 3 );
		add_filter( 'bulk_actions-users', array( $this, 'add_user_bulk_options' ) );
		add_filter( 'get_edit_user_link', array( $this, 'admin_edit_user_link' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_friends_menu' ), 39 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_friends_new_content' ), 71 );
		add_action( 'current_screen', array( $this, 'register_help' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 39 );
		add_action( 'gettext_with_context', array( $this->friends, 'translate_user_role' ), 10, 4 );
		add_action( 'wp_ajax_friends_preview_rules', array( $this, 'ajax_preview_friend_rules' ) );
		add_action( 'delete_user_form', array( $this, 'delete_user_form' ), 10, 2 );
		add_action( 'tool_box', array( $this, 'toolbox_bookmarklets' ) );
		add_action( 'dashboard_glance_items', array( $this, 'dashboard_glance_items' ) );

		if ( ! get_option( 'permalink_structure' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_unsupported_permalink_structure' ) );
		}
		add_action( 'admin_notices', array( $this, 'admin_notice_no_friends' ) );
	}

	/**
	 * Display an admin notice.
	 *
	 * @param string $short_notice The message to display on the first line of the notice beside "Friends".
	 * @param string $long_notice The message to display below the "Friends" line.
	 * @param string $type The type of the message (error, warning, or success).
	 */
	private function show_admin_notice( $short_notice, $long_notice, $type = 'error' ) {

		?>
		<div class="friends-notice notice notice-<?php echo $type; ?>">
			<p style="max-width:800px;"><?php echo $short_notice; // WPCS: xss ok. ?></p>
			<p style="max-width:800px;"><?php echo $long_notice; // WPCS: xss ok. ?></p>
		</div>
		<?php
	}

	/**
	 * Display admin notice about an unsupported permalink structure
	 */
	public function admin_notice_unsupported_permalink_structure() {
		$screen = get_current_screen();

		if ( 'plugins' !== $screen->id ) {
			return;
		}
		$short_notice = '<b>' . __( 'Friends', 'friends' ) . '</b> ';
		$short_notice .= __( '&#151; You are running an unsupported permalink structure.', 'friends' );
		// translators: 1: URL to permalink settings, 2: the name of the Permalink Settings page.
		$long_notice = __( 'In order to be able to view the Friends page, you need to enable a custom permalink structure. Please go to <a href="%1$s">%2$s</a> and enable an option other than Plain.', 'friends' );
		$long_notice = sprintf( $long_notice, admin_url( 'options-permalink.php' ), __( 'Permalink Settings' ) );

		$this->show_admin_notice( $short_notice, $long_notice );
	}

	/**
	 * Display admin notice when user doesn't have friends or subscriptions
	 */
	public function admin_notice_no_friends() {
		$screen = get_current_screen();

		if ( 'dashboard' !== $screen->id ) {
			return;
		}

		$friends_subscriptions = Friend_User_Query::all_friends_subscriptions();
		if ( $friends_subscriptions->get_total() ) {
			return;
		}

		$short_notice = '<b class="headline">' . __( 'Welcome to the Friends Plugin!', 'friends' ) . '</b>';
		$long_notice = '<p>' . __( "You're seeing this message because you haven't connected with friends or made any subscriptions yet. Here is how to get started:", 'friends' ) . '</p>';
		$long_notice .= '<p>' . __( 'The Friends plugin is all about connecting with friends and news.', 'friends' );
		// translators: 1: URL to add-friend, 2: the name of the Send Friend Request page.
		$long_notice .= ' ' . sprintf( __( "First, you'll want to go to <a href=%1\$s>%2\$s</a> and add a new friend or enter the URL of a website or blog you'd like to subscribe to.", 'friends' ), '"' . admin_url( 'admin.php?page=add-friend' ) . '"', __( 'Send Friend Request', 'friends' ) );
		// translators: %s is the URL of the user's friends page.
		$long_notice .= ' ' . sprintf( __( "As soon as you have done this, you'll be able see all the compiled posts of your friends (and subscriptions) on your <a href=%s>Friends page</a>.", 'friends' ), site_url( '/friends/' ) ) . '</p>';

		$long_notice .= '<p>' . __( 'Furthermore, your friends will be able to see your private posts. This means you can submit posts on your own blog that only they will be able to see and vica versa.', 'friends' ) . ' ';
		$long_notice .= __( 'This allows building your own decentralized social network, no third party involved.', 'friends' ) . '</p>';

		$this->show_admin_notice( $short_notice, $long_notice, '' );
	}

	/**
	 * Registers the admin menus
	 */
	public function register_admin_menu() {
		$friend_requests = Friend_User_Query::all_friend_requests();
		$friend_request_count = $friend_requests->get_total();
		$unread_badge = $this->get_unread_badge( $friend_request_count );

		$menu_title = __( 'Friends', 'friends' ) . $unread_badge;
		$page_type = sanitize_title( $menu_title );
		add_menu_page( 'friends', $menu_title, Friends::REQUIRED_ROLE, 'friends-settings', null, 'dashicons-groups', 3.73 );
		add_submenu_page( 'friends-settings', __( 'Settings' ), __( 'Settings' ), Friends::REQUIRED_ROLE, 'friends-settings', array( $this, 'render_admin_settings' ) );
		add_action( 'load-' . $page_type . '_page_friends-page', array( $this, 'redirect_to_friends_page' ) );
		add_submenu_page( 'friends-settings', __( 'Latest Posts', 'friends' ), __( 'Latest Posts', 'friends' ), Friends::REQUIRED_ROLE, 'friends-page', array( $this, 'redirect_to_friends_page' ) );
		add_submenu_page( 'friends-settings', __( 'Add New Friend', 'friends' ), __( 'Add New Friend', 'friends' ), Friends::REQUIRED_ROLE, 'add-friend', array( $this, 'render_admin_add_friend' ) );
		add_action( 'load-toplevel_page_friends-settings', array( $this, 'process_admin_settings' ) );

		if ( $friend_request_count > 0 ) {
			add_submenu_page( 'friends-settings', __( 'Friend Requests', 'friends' ), __( 'Friend Requests', 'friends' ) . $unread_badge, Friends::REQUIRED_ROLE, 'users.php?role=friend_request' );
		}

		add_submenu_page( 'friends-settings', __( 'Friends &amp; Requests', 'friends' ), __( 'Friends &amp; Requests', 'friends' ), Friends::REQUIRED_ROLE, 'users.php' );

		add_submenu_page( 'friends-settings', __( 'Refresh', 'friends' ), __( 'Refresh', 'friends' ), Friends::REQUIRED_ROLE, 'friends-refresh', array( $this, 'admin_refresh_friend_posts' ) );

		if ( isset( $_GET['page'] ) && 0 === strpos( $_GET['page'], 'edit-friend' ) ) {
			add_submenu_page( 'friends-settings', __( 'Edit User', 'friends' ), __( 'Edit User', 'friends' ), Friends::REQUIRED_ROLE, 'edit-friend' . ( 'edit-friend' !== $_GET['page'] && isset( $_GET['user'] ) ? '&user=' . $_GET['user'] : '' ), array( $this, 'render_admin_edit_friend' ) );
			add_submenu_page( 'friends-settings', __( 'Edit Rules', 'friends' ), __( 'Edit Rules', 'friends' ), Friends::REQUIRED_ROLE, 'edit-friend-rules' . ( 'edit-friend-rules' !== $_GET['page'] && isset( $_GET['user'] ) ? '&user=' . $_GET['user'] : '' ), array( $this, 'render_admin_edit_friend_rules' ) );
			add_action( 'load-' . $page_type . '_page_edit-friend', array( $this, 'process_admin_edit_friend' ) );
			add_action( 'load-' . $page_type . '_page_edit-friend-rules', array( $this, 'process_admin_edit_friend_rules' ) );
		}
		if ( isset( $_GET['page'] ) && 'suggest-friends-plugin' === $_GET['page'] ) {
			add_submenu_page( 'friends-settings', __( 'Suggest Friends Plugin', 'friends' ), __( 'Suggest Friends Plugin', 'friends' ), Friends::REQUIRED_ROLE, 'suggest-friends-plugin', array( $this, 'render_suggest_friends_plugin' ) );
		}
	}

	/**
	 * Use JavaScript to keep the Friends menu open when responding to a Friend Request.
	 */
	public function keep_friends_open_on_users_screen() {
		if ( 'friend_request' === $_GET['role'] ) {
			?>
			<script type="text/javascript">
				jQuery(document).ready( function($)
				{
					$("#toplevel_page_friends-settings, #toplevel_page_friends-settings > a").addClass('wp-has-current-submenu');
				});
			</script>
			<?php
		}
	}

	/**
	 * Add our help information
	 *
	 * @param  WP_Screen $screen The current wp-admin screen.
	 */
	public function register_help( $screen ) {
		if ( ! ( $screen instanceof WP_Screen ) ) {
			return;
		}
		global $friends_debug;

		switch ( $screen->id ) {
			case 'toplevel_page_friends-settings':
				$screen->add_help_tab(
					array(
						'id'      => 'overview',
						'title'   => __( 'Overview' ),
						'content' => '<p>' . __( 'Welcome to the Friends Settings! You can configure the Friends plugin here to your liking.', 'friends' ) . '</p>' .
							// translators: %1$s is a URL, %2$s is the name of a wp-admin screen.
							'<p>' . sprintf( __( 'There are more settings available for each friend or subscription individually. To get there, click on the user on the <a href=%1$s>%2$s</a> screen.', 'friends' ), '"' . esc_attr( self_admin_url( 'users.php' ) ) . '"', __( 'Friends &amp; Requests', 'friends' ) ) . '</p>',
					)
				);
				break;
			case 'users':
				$screen->add_help_tab(
					array(
						'id'      => 'friends',
						'title'   => __( 'Friends', 'friends' ),
						'content' => '<p>' . __( 'Here you can find your friends and subscriptions.', 'friends' ) . '</p><p>' . __( 'If you no longer want to be friends with someone or stop a subscription, you can simply delete that user.', 'friends' ) . '</p>',
					)
				);
				break;
			default:
				if ( strpos( $screen->id, 'friends' ) && isset( $friends_debug ) && $friends_debug ) {
					$screen->add_help_tab(
						array(
							'id'      => 'friends_' . $screen->id,
							'title'   => $screen->id,
							'content' => '<p>' . $screen->id . '</p>',
						)
					);
				}
		}
	}

	/**
	 * Reference our script for the /friends page
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script( 'friends-admin', plugins_url( 'friends-admin.js', __FILE__ ), array( 'jquery' ), Friends::VERSION );
		$variables = array(
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'add_friend_url'  => self_admin_url( 'admin.php?page=add-friend' ),
			'add_friend_text' => __( 'Add a Friend', 'friends' ),
		);
		wp_localize_script( 'friends-admin', 'friends', $variables );
		wp_enqueue_style( 'friends-admin', plugins_url( 'friends-admin.css', __FILE__ ), array(), Friends::VERSION );

	}

	/**
	 * Admin menu to refresh the friend posts.
	 */
	public function admin_refresh_friend_posts() {
		?>
		<h1><?php esc_html_e( "Refreshing Your Friends' Posts", 'friends' ); ?></h1>
		<?php

		add_filter( 'notify_about_new_friend_post', '__return_false', 999 );

		add_filter(
			'friends_friend_private_feed_url',
			function( $feed_url, $friend_user ) {
				// translators: %1s is the name of the friend, %2$s is the feed URL.
				printf( __( 'Refreshing %1$s at %2$s', 'friends' ) . '<br/>', '<b>' . esc_html( $friend_user->user_login ) . '</b>', '<a href="' . esc_url( $feed_url ) . '">' . esc_html( $feed_url ) . '</a>' );
				return $feed_url;
			},
			10,
			2
		);

		add_action(
			'friends_retrieved_new_posts',
			function( $new_posts, $friend_user ) {
				$count = 0;
				foreach ( $new_posts as $post_type => $posts ) {
					$count += count( $posts );
				}
				// translators: %s is the number of new posts found.
				printf( _n( 'Found %d new post.', 'Found %d new posts.', $count, 'friends' ) . '<br/>', $count );
			},
			10,
			2
		);

		add_action(
			'friends_incoming_feed_items',
			function( $items ) {
				// translators: %s is the number of posts found.
				printf( _n( 'Found %d item in the feed.', 'Found %d items in the feed.', count( $items ), 'friends' ) . ' ', count( $items ) );
			}
		);

		add_action(
			'friends_retrieve_friends_error',
			function( $feed_url, $error, $friend_user ) {
				esc_html_e( 'An error occurred while retrieving the posts.', 'friends' );
				echo $error->get_error_message(), '<br/>';
			},
			10,
			3
		);

		if ( isset( $_GET['user'] ) ) {
			$friend_user = new Friend_User( intval( $_GET['user'] ) );
			if ( ! $friend_user || is_wp_error( $friend_user ) ) {
				wp_die( esc_html__( 'Invalid user ID.' ) );
			}
			$friend_user->retrieve_posts();
		} else {
			$this->friends->feed->retrieve_friend_posts();
		}
	}

	/**
	 * Subscribe to a friends site without becoming a friend
	 *
	 * @param      string $feed_url  The feed URL to subscribe to.
	 * @param      array  $args      The arguments for the feed.
	 *
	 * @return     WP_User|WP_error  $user The new associated user or an error object.
	 */
	public function subscribe( $feed_url, $args = array() ) {
		if ( ! is_string( $feed_url ) || ! Friends::check_url( $feed_url ) ) {
			return new WP_Error( 'invalid-url', 'An invalid URL was provided' );
		}

		$friend_user = Friend_User::get_user_for_url( $feed_url );
		if ( $friend_user && ! is_wp_error( $friend_user ) && $friend_user->is_valid_friend() ) {
			return new WP_Error( 'already-friend', __( 'You are already subscribed to this site.', 'friends' ) );
		}

		$favicon  = null;
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 5,
			)
		);
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$dom = new DOMDocument();

			set_error_handler( '__return_null' );
			$dom->loadHTML( wp_remote_retrieve_body( $response ) );
			restore_error_handler();

			$xpath = new DOMXpath( $dom );
			foreach ( $xpath->query( '//link[@rel and @href]' ) as $link ) {
				if ( in_array( $link->getAttribute( 'rel' ), array( 'shortcut icon', 'icon' ) ) ) {
					$favicon = $link->getAttribute( 'href' );

					$domain = wp_parse_url( $favicon, PHP_URL_HOST );

					if ( ! $domain ) {
						$parsed_url = wp_parse_url( $url );
						// TODO relative urls.
						$favicon = $parsed_url['scheme'] . '://' . $parsed_url['host'] . '/' . ltrim( $favicon, '/' );
					}
					break;
				}
			}
		}
		$feed_title = trim( str_replace( '&raquo; Feed', '', $feed->get_title() ) );
		$friend_user = Friend_User::create( $url, 'subscription', $feed_title, $favicon );
		if ( ! is_wp_error( $friend_user ) ) {
			$friend_user->subscribe( $feed_url, $feed_title );
		}

		return $friend_user;
	}

	/**
	 * Send a friend request to another WordPress with the Friends plugin
	 *
	 * @param      string $rest_url    The site URL of the friend's
	 *                                 WordPress.
	 * @param      string $user_login  The user login.
	 * @param      string $user_url    The user url.
	 * @param      string $codeword    A codeword to send along.
	 * @param      string $message     A message to send along.
	 *
	 * @return     WP_User|WP_error  $user The new associated user or an error object.
	 */
	public function send_friend_request( $rest_url, $user_login, $user_url, $codeword = 'friends', $message = '' ) {
		if ( ! is_string( $rest_url ) || ! Friends::check_url( $rest_url ) ) {
			return new WP_Error( 'invalid-url', __( 'You entered an invalid URL.', 'friends' ) );
		}

		$future_in_token = sha1( wp_generate_password( 256 ) );

		$current_user = wp_get_current_user();
		$response     = wp_safe_remote_post(
			$rest_url . '/friend-request',
			array(
				'body'        => array(
					'version'  => 2,
					'codeword' => $codeword,
					'name'     => $current_user->display_name,
					'url'      => site_url(),
					'icon_url' => get_avatar_url( $current_user->ID ),
					'message'  => $message,
					'key'      => $future_in_token,
				),
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( $json && isset( $json->code ) && isset( $json->message ) ) {
				// translators: %s is the message from the other server.
				return new WP_Error( $json->code, sprintf( __( 'The other side responded: %s', 'friends' ), $json->message ), $json->data );
			}
		}

		if ( ! $json || ! is_object( $json ) ) {
			return new WP_Error( 'unexpected-rest-response', 'Unexpected remote response.', $response );
		}

		$friend_user = Friend_User::create( $user_login, 'pending_friend_request', $user_url );
		if ( is_wp_error( $friend_user ) ) {
			return $friend_user;
		}
		$friend_user->update_user_option( 'friends_rest_url', $rest_url );

		if ( isset( $json->request ) ) {
			update_option( 'friends_request_' . sha1( $json->request ), $friend_user->ID );
			$friend_user->update_user_option( 'friends_future_in_token_' . sha1( $json->request ), $future_in_token );
			$friend_user->set_role( 'pending_friend_request' );
			// } elseif ( isset( $json->friend ) ) {
			// $this->friends->access_control->make_friend( $user, $json->friend );
			// if ( isset( $json->gravatar ) ) {
			// $this->friends->access_control->update_gravatar( $user->ID, $json->gravatar );
			// }
			// if ( isset( $json->name ) ) {
			// wp_update_user(
			// array(
			// 'ID'           => $user->ID,
			// 'nickname'     => $json->name,
			// 'first_name'   => $json->name,
			// 'display_name' => $json->name,
			// )
			// );
			// }
			// $response = wp_safe_remote_post(
			// $friend_rest_url . '/friend-request-accepted',
			// array(
			// 'body'        => array(
			// 'token'  => $json->token,
			// 'friend' => $this->friends->access_control->update_in_token( $user->ID ),
			// 'proof'  => sha1( $json->token . $friend_request_token ),
			// ),
			// 'timeout'     => 20,
			// 'redirection' => 5,
			// )
			// );
			// delete_user_option( $user->ID, 'friends_request_token' );
			// $json = json_decode( wp_remote_retrieve_body( $response ) );
			// if ( $json->friend ) {
			// update_user_option( $user->ID, 'friends_out_token', $json->friend );
			// }
		}
		$friend_user->retrieve_posts();

		return $friend_user;
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
	 * Redirect to the Friends page
	 */
	public function redirect_to_friends_page() {
		wp_safe_redirect( site_url( '/friends/' ) );
		exit;
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

		if ( isset( $_POST['require_codeword'] ) && $_POST['require_codeword'] ) {
			update_option( 'friends_require_codeword', true );
		} else {
			delete_option( 'friends_require_codeword' );
		}

		if ( isset( $_POST['codeword'] ) && $_POST['codeword'] ) {
			update_option( 'friends_codeword', $_POST['codeword'] );
		} else {
			delete_option( 'friends_codeword' );
		}

		if ( isset( $_POST['wrong_codeword_message'] ) && $_POST['wrong_codeword_message'] ) {
			update_option( 'friends_wrong_codeword_message', $_POST['wrong_codeword_message'] );
		} else {
			delete_option( 'friends_wrong_codeword_message' );
		}

		if ( isset( $_POST['default_role'] ) && in_array( $_POST['default_role'], array( 'friend', 'acquaintance' ), true ) ) {
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
		<h1><?php esc_html_e( 'Friends Settings', 'friends' ); ?></h1>
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

		$potential_main_users = Friend_User_Query::all_admin_users();
		$main_user_id = $this->friends->get_main_friend_user_id();
		$friend_roles = $this->get_friend_roles();
		$default_role = get_option( 'friends_default_friend_role', 'friend' );

		include apply_filters( 'friends_template_path', 'admin/settings.php' );
	}

	/**
	 * Process access for the Friends Edit Rules page
	 */
	private function check_admin_edit_friend_rules() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit the rules.' ) );
		}

		if ( ! isset( $_GET['user'] ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) );
		}

		$friend = new Friend_User( intval( $_GET['user'] ) );
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
	 * Process the Friends Edit Rules page
	 */
	public function process_admin_edit_friend_rules() {
		$friend    = $this->check_admin_edit_friend_rules();
		$arg       = 'updated';
		$arg_value = 1;
		if ( isset( $_POST['friend-rules-raw'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'friend-rules-raw-' . $friend->ID ) ) {
			$rules = $this->friends->feed->validate_feed_rules( json_decode( stripslashes( $_POST['rules'] ), true ) );
			if ( false === $rules ) {
				$arg = 'error';
			} else {
				update_option( 'friends_feed_rules_' . $friend->ID, $rules );
			}
		} elseif ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'edit-friend-rules-' . $friend->ID ) ) {
			update_option( 'friends_feed_catch_all_' . $friend->ID, $this->friends->feed->validate_feed_catch_all( $_POST['catch_all'] ) );
			update_option( 'friends_feed_rules_' . $friend->ID, $this->friends->feed->validate_feed_rules( $_POST['rules'] ) );
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
	 * Render the Friends Edit Rules page
	 */
	public function render_admin_edit_friend_rules() {
		$friend    = $this->check_admin_edit_friend_rules();
		$catch_all = $friend->get_feed_catch_all();
		$rules     = $friend->get_feed_rules();
		?>
		<h1>
		<?php
		// translators: %s is the name of a friend.
		printf( __( 'Rules for %s', 'friends' ), esc_html( $friend->display_name ) );
		?>
		</h1>
		<?php

		if ( isset( $_GET['updated'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'Rules were updated.', 'friends' ); ?></p></div>
			<?php
		} elseif ( isset( $_GET['error'] ) ) {
			?>
			<div id="message" class="updated error is-dismissible"><p><?php esc_html_e( 'An error occurred.', 'friends' ); ?></p></div>
			<?php
		}

		$rules   = array_values( $rules );
		$rules[] = array(
			'field' => 'title',
			'regex' => '',
		);
		include apply_filters( 'friends_template_path', 'admin/edit-rules.php' );

		include apply_filters( 'friends_template_path', 'admin/rules-examples.php' );
		echo '<div id="preview-rules">';
		$this->render_preview_friend_rules( $rules, $catch_all );
		echo '</div>';

		array_pop( $rules );
		include apply_filters( 'friends_template_path', 'admin/edit-raw-rules.php' );
	}

	/**
	 * Respond to the Ajax request to the Friend rules preview
	 */
	public function ajax_preview_friend_rules() {
		$this->render_preview_friend_rules( $_POST['rules'], $_POST['catch_all'] );
		exit;
	}

	/**
	 * Render the Friend rules preview
	 *
	 * @param  array  $rules     The rules to apply.
	 * @param  string $catch_all The catch all behavior.
	 */
	public function render_preview_friend_rules( $rules, $catch_all ) {
		$friend       = $this->check_admin_edit_friend_rules();
		$friend_posts = new WP_Query(
			array(
				'post_type'      => $this->friends->post_types->get_all_cached(),
				'post_status'    => array( 'publish', 'private', 'trash' ),
				'author'         => $friend->ID,
				'posts_per_page' => 25,
			)
		);

		$feed = $this->friends->feed;

		Friend_User::$feed_rules[ $friend->ID ]     = $feed->validate_feed_rules( $rules );
		Friend_User::$feed_catch_all[ $friend->ID ] = $feed->validate_feed_catch_all( $catch_all );

		include apply_filters( 'friends_template_path', 'admin/preview-rules.php' );
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

		$friend = new Friend_User( intval( $_GET['user'] ) );
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
				$friend->set_role( get_option( 'friends_default_friend_role', 'friend' ) );
				$arg = 'friend';
			}
		} elseif ( isset( $_GET['add-friend'] ) && wp_verify_nonce( $_GET['add-friend'], 'add-friend-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'pending_friend_request' ) || $friend->has_cap( 'subscription' ) ) {
				$response = $this->send_friend_request( $friend->user_url );
				if ( is_wp_error( $response ) ) {
					$arg = 'error';
				} elseif ( $response instanceof Friends_Multiple_Feeds ) {

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
				$friend->set_role( 'acquaintance' );
			}
		} elseif ( isset( $_GET['change-to-friend'] ) && wp_verify_nonce( $_GET['change-to-friend'], 'change-to-friend-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'acquaintance' ) ) {
				$friend->set_role( 'friend' );
			}
		} elseif ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'edit-friend-' . $friend->ID ) ) {
			if ( trim( $_POST['friends_display_name'] ) ) {
				$friend->first_name   = trim( $_POST['friends_display_name'] );
				$friend->display_name = trim( $_POST['friends_display_name'] );
			}
			if ( trim( $_POST['user_url'] ) && filter_var( $_POST['user_url'], FILTER_VALIDATE_URL ) ) {
				$friend->user_url = $_POST['user_url'];
			}
			wp_update_user( $friend );

			$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
			if ( ! $hide_from_friends_page ) {
				$hide_from_friends_page = array();
			}
			if ( ! isset( $_POST['show_on_friends_page'] ) || ! $_POST['show_on_friends_page'] ) {
				if ( ! in_array( $friend->ID, $hide_from_friends_page ) ) {
					$hide_from_friends_page[] = $friend->ID;
					update_user_option( get_current_user_id(), 'friends_hide_from_friends_page', $hide_from_friends_page );
				}
			} else {
				if ( in_array( $friend->ID, $hide_from_friends_page ) ) {
					$hide_from_friends_page = array_values( array_diff( $hide_from_friends_page, array( $friend->ID ) ) );
					update_user_option( get_current_user_id(), 'friends_hide_from_friends_page', $hide_from_friends_page );
				}
			}

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
		$friend = $this->check_admin_edit_friend();
		$friend_posts = new WP_Query(
			array(
				'post_type'   => $this->friends->post_types->get_all_cached(),
				'post_status' => array( 'publish', 'private' ),
				'author'      => $friend->ID,
			)
		);
		$rules = $friend->get_feed_rules();
		$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
		if ( ! $hide_from_friends_page ) {
			$hide_from_friends_page = array();
		}

		?>
		<h1><?php echo esc_html( $friend->user_login ); ?></h1>
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
	 * Previous process the Add Friend form. Todo: re-integrate.
	 *
	 * @param      Friend_User $friend_user  The Friend user.
	 *
	 * @return     boolean  ( description_of_the_return_value )
	 */
	function process_admin_add_friend_response( $friend_user ) {
		if ( is_wp_error( $friend_user ) ) {
			?>
			<div id="message" class="updated error is-dismissible"><p><?php echo esc_html( $friend_user->get_error_message() ); ?></p>
			<?php
			$error_data = $friend_user->get_error_data();
			if ( isset( $error_data->error ) ) {
				$error = unserialize( $error_data->error );
				if ( is_wp_error( $error ) ) {
					?>
					<pre>
					<?php
					print_r( $error );
					?>
					</pre>
					<?php
				} elseif ( is_array( $error ) && isset( $error['body'] ) ) {
					?>
					<textarea>
					<?php
					echo esc_html( $error['body'] );
					?>
					</textarea>
					<?php
				}
			}
			?>
			</div>
			<?php
			return false;
		}

		if ( ! $friend_user instanceof Friend_User ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
			<?php
			// translators: %s is a username.
			esc_html_e( 'Unknown error', 'friends' );
			?>
			</p></div>
			<?php
			return false;
		}

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
			echo ' ', wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> to view their posts.', 'friends' ), '"' . site_url( '/friends/' . $friend_user->user_login . '/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
			?>
			</p></div>
			<?php
			return true;
		}

		if ( $friend_user->has_cap( 'friend' ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
			<?php
			// translators: %s is a Site URL.
			echo wp_kses( sprintf( __( "You're now a friend of site %s.", 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
			// translators: %s is the friends page URL.
			echo ' ', wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> to view their posts.', 'friends' ), '"' . site_url( '/friends/' . $friend_user->user_login . '/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
			?>
			</p></div>
			<?php
			return true;
		}

		if ( $friend_user->has_cap( 'subscription' ) ) {
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
			echo ' ', wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> to view their posts.', 'friends' ), '"' . site_url( '/friends/' . $friend_user->user_login . '/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
			?>
			</p></div>
			<?php
			return true;
		}
		?>
		<div id="message" class="updated notice is-dismissible"><p>
		<?php
		// translators: %s is a username.
		echo esc_html( sprintf( __( 'User %s could not be assigned the appropriate role.', 'friends' ), $friend_user->display_name ) );
		?>
		</p></div>
		<?php
		return false;
	}

	/**
	 * Process the Add Friend form.
	 *
	 * @param      array $vars   The POST or GET variables.
	 *
	 * @return     boolean A WP_Error or void.
	 */
	public function process_admin_add_friend( $vars ) {
		$friends_plugin = false;
		$friend_url = trim( $vars['friend_url'] );
		$friend_user_login = Friend_User::get_user_login_for_url( $friend_url );
		$friend_roles = $this->get_friend_roles();
		$default_role = get_option( 'friends_default_friend_role', 'friend' );
		$registered_parsers = Friends::get_instance()->feed->get_registered_parsers();

		$errors = new WP_Error();

		if ( ( isset( $vars['step2'] ) && isset( $vars['feeds'] ) && is_array( $vars['feeds'] ) ) || isset( $vars['step3'] ) ) {
			$friend_user_login = sanitize_user( $vars['user_login'] );
			if ( ! $friend_user_login ) {
				$errors->add( 'user_login', __( '<strong>Error</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
			} elseif ( username_exists( $friend_user_login ) ) {
				$errors->add( 'user_login', __( '<strong>Error</strong>: This username is already registered. Please choose another one.' ) );
			}
			$codeword = trim( $vars['codeword'] );
			$message = trim( $vars['message'] );
			$feeds = $vars['feeds'];

			if ( ! $errors->has_errors() ) {
				if ( isset( $vars['friendship'] ) ) {
					$friend_user = $this->send_friend_request( $vars['friendship'], $friend_user_login, $friend_url, $codeword, $message );
				} else {
					$friend_user = Friend_User::create( $friend_user_login, 'pending_friend_request' );
					if ( is_wp_error( $friend_user ) ) {
						$errors = $friend_user;
					}
				}
				var_dump( $vars );
				exit;
			}
		} else {
			$protocol = wp_parse_url( $friend_url, PHP_URL_SCHEME );
			if ( ! $protocol ) {
				$friend_url = 'https://' . $friend_url;
			}

			if ( 0 === strcasecmp( site_url(), $friend_url ) ) {
				return new WP_Error( 'friend-yourself', __( 'It seems like you sent a friend request to yourself.', 'friends' ) );
			}

			$friend_user = Friend_User::get_user( $friend_user_login );
			if ( $friend_user && ! is_wp_error( $friend_user ) && $friend_user->is_valid_friend() ) {
				return new WP_Error( 'already-friend', __( 'You are already friends with this site.', 'friends' ) );
			}

			$feeds = $this->friends->feed->discover_available_feeds( $friend_url );
			if ( ! $feeds ) {
				return new WP_Error( 'no-feed-found', __( 'No suitable feed was found at the provided address.', 'friends' ) );
			}

			$rest_url = $this->friends->rest->get_rest_url( $feeds );
		}

		if ( $rest_url ) {
			$friends_plugin = $rest_url;
			unset( $feeds[ $rest_url ] );
		}

		if ( 1 === count( $feeds ) && isset( $vars['quick-subscribe'] ) ) {
			if ( ! $rest_url ) {
				return $this->subscribe( $friend_url );
			}

			return $this->send_friend_request( $rest_url, $friend_user_login, $friend_url, $codeword, $message );
		}

		include apply_filters( 'friends_template_path', 'admin/select-feeds.php' );
		return;
	}

	/**
	 * Render the admin form for sending a friend request.
	 */
	public function render_admin_add_friend() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to send friend requests.', 'friends' ) );
		}

		$friend_url = '';
		if ( apply_filters( 'friends_debug', false ) && isset( $_GET['next'] ) ) {
			$_POST = $_REQUEST;
			$_POST['_wpnonce'] = wp_create_nonce( 'add-friend' );
			if ( ! empty( $_POST['url'] ) && ! isset( $_POST['friend_url'] ) ) {
				$_POST['friend_url'] = $_POST['url'];
				$parsed_url = parse_url( $_POST['friend_url'] );
				if ( isset( $parsed_url['host'] ) ) {
					if ( ! isset( $parsed_url['scheme'] ) ) {
						$_POST['friend_url'] = 'https://' . ltrim( $_POST['friend_url'], '/' );
					}
				}
			}
		}

		?>
		<h1><?php esc_html_e( 'Add New Friend', 'friends' ); ?></h1>
		<?php

		if ( ! empty( $_POST ) ) {
			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'add-friend' ) ) {
				$response = new WP_Error( 'invalid-nonce', __( 'For security reasons, please verify the URL and click next if you want to proceed.', 'friends' ) );
			} else {
				$response = $this->process_admin_add_friend( $_POST );
			}

			if ( ! is_wp_error( $response ) ) {
				return;
			}
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php echo esc_html( $response->get_error_message() ); ?></p>
			</div>
			<?php
		}

		if ( ! empty( $_GET['url'] ) || ! empty( $_POST['url'] ) ) {
			$friend_url = isset( $_GET['url'] ) ? $_GET['url'] : $_POST['url'];
			$parsed_url = parse_url( $friend_url );
			if ( isset( $parsed_url['host'] ) ) {
				if ( ! isset( $parsed_url['scheme'] ) ) {
					$friend_url = 'https://' . ltrim( $friend_url, '/' );
				}
			} else {
				$friend_url = '';
			}
		}

		include apply_filters( 'friends_template_path', 'admin/add-friend.php' );
		include apply_filters( 'friends_template_path', 'admin/latest-friends.php' );
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
		if ( ! isset( $_GET['tab'] ) ) {
			$_GET['tab'] = 'e-mail';
		}

		if ( ! $friend && isset( $_GET['url'] ) ) {
			$friend = Friend_User::get_user_for_url( $_GET['url'] );
			$url    = $_GET['url'];
			if ( 'http' !== substr( $url, 0, 4 ) ) {
				$url = 'http://' . $url;
			}
		} else {
			$url = $friend->user_url;
		}

		if ( $friend && ! is_wp_error( $friend ) ) {
			$domain = wp_parse_url( $friend->user_url, PHP_URL_HOST );
			$to     = '@' . $domain;
		} else {
			$domain = '';
			$to     = '';
		}

		?>
		<h1><?php echo esc_html( $friend->display_name ); ?></h1>
		<p><?php esc_html_e( "Maybe you'll want to suggest your friend to install the friends plugin?", 'friends' ); ?> <?php esc_html_e( 'We have prepared some tools for you to give them instructions on how to do so.', 'friends' ); ?></p>
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
	 * Gets the friend roles.
	 *
	 * @return     array  The friend roles.
	 */
	public function get_friend_roles() {
		return array(
			'friend'       => _x( 'Friend', 'User role', 'friends' ),
			'acquaintance' => _x( 'Acquaintance', 'User role', 'friends' ),
		);
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
			! current_user_can( Friends::REQUIRED_ROLE ) ||
			(
				! $user->has_cap( 'friend_request' ) &&
				! $user->has_cap( 'pending_friend_request' ) &&
				! $user->has_cap( 'friend' ) &&
				! $user->has_cap( 'subscription' )
			)
		) {
			return $actions;
		}

		if ( is_multisite() ) {
			$actions = array_merge( array( 'edit' => '<a href="' . esc_url( self_admin_url( 'admin.php?page=edit-friend&user=' . $user->ID ) ) . '">' . __( 'Edit' ) . '</a>' ), $actions );
		}

		$actions['view'] = '<a href="' . esc_url( $user->user_url ) . '" target="_blank" rel="noopener noreferrer">' . __( 'Visit' ) . '</a>';

		if ( $user->has_cap( 'friend_request' ) ) {
			$link = self_admin_url( wp_nonce_url( 'users.php?action=accept_friend_request&users[]=' . $user->ID ) );

			$actions['user_accept_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Accept Friend Request', 'friends' ) . '</a>';
			$message = get_user_option( 'friends_request_message', $user->ID );
			// translators: %s is a date.
			$actions['friends friends_request_date'] = '<br/><span class="nonessential">' . esc_html( sprintf( __( 'Requested on %s', 'friends' ), date_i18n( __( 'F j, Y g:i a' ), strtotime( $user->user_registered ) ) ) ) . '</span>';
			if ( $message ) {
				// translators: %s is a message text.
				$actions['friends friend_request_message'] = '<br/><span class="nonessential">' . esc_html( sprintf( __( 'Message: %s', 'friends' ), $message ) ) . '</span>';
			}
		}

		if ( $user->has_cap( 'pending_friend_request' ) || $user->has_cap( 'subscription' ) ) {
			$link = self_admin_url( wp_nonce_url( 'admin.php?page=add-friend&url=' . $user->user_url ) );
			if ( $user->has_cap( 'pending_friend_request' ) ) {
				$actions['user_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Resend Friend Request', 'friends' ) . '</a>';
			} elseif ( $user->has_cap( 'subscription' ) ) {
				$actions['user_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Send Friend Request', 'friends' ) . '</a>';
			}
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
			$user = new Friend_User( $user_id );
			if ( ! $user || is_wp_error( $user ) ) {
				continue;
			}

			if ( ! $user->has_cap( 'friend_request' ) ) {
				continue;
			}

			if ( $user->has_cap( 'friend' ) ) {
				continue;
			}

			$user->set_role( get_option( 'friends_default_friend_role', 'friend' ) );
			$accepted++;
		}

		if ( ! $sendback ) {
			return array(
				'accepted' => $accepted,
			);
		}

		$sendback = add_query_arg( 'accepted', $accepted, $sendback );
		$sendback = remove_query_arg( 'role', $sendback );
		wp_safe_redirect( $sendback );
	}

	/**
	 * Add options to the Bulk dropdown on the users page
	 *
	 * @param array $actions The existing bulk options.
	 * @return array The extended bulk options.
	 */
	public function add_user_bulk_options( $actions ) {
		$friends = Friend_User_Query::all_friend_requests();
		$friends->get_results();

		if ( ! empty( $friends ) ) {
			$actions['accept_friend_request'] = __( 'Accept Friend Request', 'friends' );
		}

		$friends = Friend_User_Query::all_subscriptions();
		$friends->get_results();

		if ( ! empty( $friends ) ) {
			$actions['friend_request'] = __( 'Send Friend Request', 'friends' );
		}

		return $actions;
	}

	/**
	 * Get the unread badge HTML
	 *
	 * @param  int $friend_request_count The numbger of friend requests.
	 * @return string The unread badge HTML.
	 */
	private function get_unread_badge( $friend_request_count ) {
		if ( 0 === $friend_request_count ) {
			return '';
		}

		if ( get_user_option( 'friends_unobtrusive_badge' ) ) {
			return ' (' . $friend_request_count . ')';
		}

		$unread_badge = ' <div class="wp-core-ui wp-ui-notification friends-open-requests" style="display: inline; font-size: 90%; padding: .1em .5em .1em .4em; border-radius: 50%;background-color: #d54e21; color: #fff;"><span aria-hidden="true">' . $friend_request_count . '</span><span class="screen-reader-text">';
		// translators: %s is the number of friend requests pending.
		$unread_badge .= sprintf( _n( '%s friend request', '%s friend requests', $friend_request_count, 'friends' ), $friend_request_count );
		$unread_badge .= '</span></div>';
		return $unread_badge;
	}

	/**
	 * Add a Friends menu to the admin bar
	 *
	 * @param  WP_Admin_Bar $wp_menu The admin bar to modify.
	 */
	public function admin_bar_friends_menu( WP_Admin_Bar $wp_menu ) {
		$friends_url = site_url( '/friends/' );

		if ( current_user_can( 'friend' ) ) {
			$current_user = wp_get_current_user();
			$friends_url  = $current_user->user_url . '/friends/';
		}

		$friends_main_url = $friends_url;
		if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
			$friend_requests = Friend_User_Query::all_friend_requests();
			$friend_request_count = $friend_requests->get_total();
			if ( $friend_request_count > 0 ) {
				$friends_main_url = self_admin_url( 'users.php?role=friend_request' );
			}

			$wp_menu->add_node(
				array(
					'id'     => 'friends',
					'parent' => '',
					'title'  => '<span class="ab-icon dashicons dashicons-groups"></span> ' . esc_html( __( 'Friends', 'friends' ) ) . $this->get_unread_badge( $friend_requests->get_total() ),
					'href'   => $friends_main_url,
				)
			);

			if ( $friend_request_count > 0 ) {
				$wp_menu->add_menu(
					array(
						'id'     => 'open-friend-requests',
						'parent' => 'friends',
						// translators: %s is the number of open friend requests.
						'title'  => esc_html( sprintf( _n( 'Review %s Friend Request', 'Review %s Friends Request', $friend_request_count, 'friends' ), $friend_request_count ) ),
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
					'id'     => 'add-friend',
					'parent' => 'friends',
					'title'  => esc_html__( 'Add a Friend', 'friends' ),
					'href'   => self_admin_url( 'admin.php?page=add-friend' ),
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

		} elseif ( current_user_can( 'friend' ) ) {
			$wp_menu->add_node(
				array(
					'id'     => 'friends',
					'parent' => '',
					'title'  => '<span class="ab-icon dashicons dashicons-groups"></span> ' . esc_html( __( 'Friends', 'friends' ) ),
					'href'   => $friends_main_url,
				)
			);

			$wp_menu->add_menu(
				array(
					'id'     => 'profile',
					'parent' => 'friends',
					'title'  => esc_html__( 'Profile' ),
					'href'   => site_url( '/friends/' ),
				)
			);
		}
	}

	/**
	 * Add a Friends menu to the New Content admin section
	 *
	 * @param  WP_Admin_Bar $wp_menu The admin bar to modify.
	 */
	public function admin_bar_friends_new_content( WP_Admin_Bar $wp_menu ) {
		if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
			$wp_menu->add_menu(
				array(
					'id'     => 'new-friend-request',
					'parent' => 'new-content',
					'title'  => esc_html__( 'Friend', 'friends' ),
					'href'   => self_admin_url( 'admin.php?page=add-friend' ),
				)
			);
			$wp_menu->add_menu(
				array(
					'id'     => 'new-subscription',
					'parent' => 'new-content',
					'title'  => esc_html__( 'Subscription', 'friends' ),
					'href'   => self_admin_url( 'admin.php?page=add-friend' ),
				)
			);
		}
	}

	/**
	 * Fires at the end of the delete users form prior to the confirm button.
	 *
	 * @param WP_User $current_user WP_User object for the current user.
	 * @param array   $userids      Array of IDs for users being deleted.
	 */
	public function delete_user_form( $current_user, $userids ) {
		$only_friends_affiliated = true;
		foreach ( $userids as $user_id ) {
			$user = new WP_User( $user_id );
			if (
				! $user->has_cap( 'friend_request' ) &&
				! $user->has_cap( 'pending_friend_request' ) &&
				! $user->has_cap( 'friend' ) &&
				! $user->has_cap( 'subscription' )
			) {
				$only_friends_affiliated = false;
				break;
			}
		}

		if ( $only_friends_affiliated ) {
			?>
			<script type="text/javascript">
				jQuery( function() {
					jQuery( '#delete_option1' ).closest( 'li' ).hide();
				} );
			</script>
			<?php
		}
	}

	/**
	 * Display the Bookmarklets at the Tools section of wp-admin
	 */
	public function toolbox_bookmarklets() {
		?>
		<div class="card">
			<h2 class="title"><?php _e( 'Friends', 'friends' ); ?></h2>

			<p><?php _e( "Drag one of these bookmarklets to your bookmarks bar and click it when you're on a site around the web for the appropriate action.", 'friends' ); ?></p>
			<p>
				<a href="javascript:void(location.href='<?php echo esc_attr( self_admin_url( 'admin.php?page=add-friend&url=' ) ); ?>'+encodeURIComponent(location.href))" style="display: inline-block; padding: .5em; border: 1px solid #999; border-radius: 4px; background-color: #ddd;text-decoration: none; margin-right: 3em"><?php echo esc_html_e( 'Add friend', 'friends' ); ?></a>
				<a href="javascript:void(location.href='<?php echo esc_attr( self_admin_url( 'admin.php?page=add-friend&url=' ) ); ?>'+encodeURIComponent(location.href))" style="display: inline-block; padding: .5em; border: 1px solid #999; border-radius: 4px; background-color: #ddd; text-decoration: none; margin-right: 3em"><?php echo esc_html_e( 'Subscribe', 'friends' ); ?></a>
			</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Add more "at a glance" items

	 * @param  array $items Items inserted by another plugin.
	 * @return array        Items + our items.
	 */
	public function dashboard_glance_items( $items ) {
		$users = count_users();
		$friend_count = $users['avail_roles']['friend'] + $users['avail_roles']['acquaintance'];
		$friend_request_count = $users['avail_roles']['friend_request'];
		$subscription_count = $users['avail_roles']['subscription'];
		$friend_post_count = wp_count_posts( Friends::CPT );
		$friend_post_count = $friend_post_count->publish + $friend_post_count->private;

		// translators: %s is the number of friends.
		$items[] = '<a class="friends" href="' . self_admin_url( 'users.php?role=friend' ) . '">' . sprintf( _n( '%s Friend', '%s Friends', $friend_count, 'friends' ), $friend_count ) . '</a>';
		if ( $friend_request_count ) {
			// translators: %s is the number of friend requests.
			$items[] = '<a class="friend-requests" href="' . self_admin_url( 'users.php?role=friend_request' ) . '">' . sprintf( _n( '%s Friend Request', '%s Friend Requests', $friend_request_count, 'friends' ), $friend_request_count ) . '</a>';
		}
		if ( $subscription_count ) {
			// translators: %s is the number of subscriptions.
			$items[] = '<a class="subscriptions" href="' . self_admin_url( 'users.php?role=subscription' ) . '">' . sprintf( _n( '%s Subscription', '%s Subscriptions', $subscription_count, 'friends' ), $subscription_count ) . '</a>';
		}

		if ( $friend_post_count ) {
			// translators: %s is the number of friend posts.
			$items[] = '<a class="friend-posts" href="' . site_url( '/friends/' ) . '">' . sprintf( _n( '%s Post by Friends', '%s Posts by Friends', $friend_post_count, 'friends' ), $friend_post_count ) . '</a>';
		}
		return $items;
	}
}
