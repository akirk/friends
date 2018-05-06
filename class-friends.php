<?php
/**
 * Friends
 *
 * A plugin to connect WordPresses and communicate privately with your friends.
 *
 * @package Friends
 */

/**
 * This is the class for the Friends Plugin.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends {
	const VERSION = '0.5';
	const REST_NAMESPACE = 'friends/v1';
	const FRIEND_POST_CACHE = 'friend_post_cache';
	const XMLNS = 'wordpress-plugin-friends:feed-additions:1';
	/**
	 * States whether this is an authenticated feed call.
	 *
	 * @var null
	 */
	private $feed_authenticated = null;

	/**
	 * Whether we are on the /friends page.
	 *
	 * @var boolean
	 */
	private $on_friends_page = false;

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		static::get_instance();
	}

	/**
	 * Get the class singleton
	 *
	 * @return Friends A class instance.
	 */
	public static function get_instance() {
		static $instance;
		if ( ! isset( $instance ) ) {
			$self = get_called_class();
			$instance = new $self();
		}
		return $instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_hooks();
		load_plugin_textdomain( 'friends', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		// Hooks for Authentication.
		add_filter( 'determine_current_user',     array( $this, 'authenticate' ), 1 );

		// Hooks for Access control.
		add_action( 'set_user_role',              array( $this, 'update_friend_token' ), 10, 3 );
		add_action( 'set_user_role',              array( $this, 'update_friend_request_token' ), 10, 3 );
		add_action( 'set_user_role',              array( $this, 'notify_remote_friend_request_accepted' ), 20, 3 );
		add_action( 'delete_user',                array( $this, 'delete_friend_token' ) );
		add_action( 'init',                       array( $this, 'remote_login' ) );

		// Hooks for RSS.
		add_filter( 'pre_get_posts',              array( $this, 'private_feed_query' ), 1 );
		add_filter( 'private_title_format',       array( $this, 'private_title_format' ) );
		add_filter( 'pre_option_rss_use_excerpt', array( $this, 'feed_use_excerpt' ), 90 );
		add_action( 'rss_item',                   array( $this, 'feed_additional_fields' ) );
		add_action( 'rss2_item',                  array( $this, 'feed_additional_fields' ) );
		add_action( 'rss_ns',                     array( $this, 'additional_feed_namespaces' ) );
		add_action( 'rss2_ns',                    array( $this, 'additional_feed_namespaces' ) );

		// Hooks for /friends/.
		add_filter( 'pre_get_posts',              array( $this, 'friend_posts_query' ), 2 );
		add_filter( 'post_type_link',             array( $this, 'friend_post_link' ), 10, 4 );
		add_filter( 'get_edit_post_link',         array( $this, 'friend_post_edit_link' ), 10, 2 );
		add_filter( 'template_include',           array( $this, 'template_override' ) );
		add_filter( 'init',                       array( $this, 'register_custom_post_types' ) );
		add_filter( 'init',                       array( $this, 'register_friends_sidebar' ) );
		add_action( 'wp_ajax_friends_publish',    array( $this, 'frontend_publish_post' ) );
		add_action( 'admin_bar_menu',             array( $this, 'admin_bar_friends_menu' ), 100 );
		add_action( 'wp_enqueue_scripts',         array( $this, 'enqueue_scripts' ) );
		add_filter( 'body_class',                 array( $this, 'add_body_class' ) );
		add_action( 'set_user_role',              array( $this, 'retrieve_new_friends_posts' ), 999, 3 );

		// Hooks for Cron.
		add_action( 'friends_refresh_feeds',      array( $this, 'cron_friends_refresh_feeds' ) );

		// Hooks for Admin.
		add_action( 'admin_menu',                 array( $this, 'register_admin_menu' ), 10, 3 );
		add_filter( 'user_row_actions',           array( $this, 'user_row_actions' ), 10, 2 );
		add_filter( 'handle_bulk_actions-users',  array( $this, 'handle_bulk_friend_request_approval' ), 10, 3 );
		add_filter( 'handle_bulk_actions-users',  array( $this, 'handle_bulk_send_friend_request' ), 10, 3 );
		add_filter( 'bulk_actions-users',         array( $this, 'add_user_bulk_options' ) );

		// Hooks for REST.
		add_action( 'rest_api_init',              array( $this, 'add_rest_routes' ) );
		add_action( 'wp_trash_post',              array( $this, 'notify_friend_post_deleted' ) );
		add_action( 'before_delete_post',         array( $this, 'notify_friend_post_deleted' ) );

		// Hooks for Notifications.
		add_action( 'notify_new_friend_post',     array( $this, 'notify_new_friend_post' ) );
		add_action( 'notify_new_friend_request',  array( $this, 'notify_new_friend_request' ) );
		add_action( 'notify_accepted_friend_request',  array( $this, 'notify_accepted_friend_request' ) );
	}

	/**
	 * Registers the custom post types
	 */
	public function register_custom_post_types() {
		$labels = array(
			'name'               => 'Friend Post Cache',
			'singular_name'      => 'Friend Post Cache Item',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Friend Post',
			'edit_item'          => 'Edit Friend Post',
			'new_item'           => 'New Friend Post',
			'all_items'          => 'All Friend Posts',
			'view_item'          => 'View Friend Posts Item',
			'search_items'       => 'Search Friend Posts',
			'not_found'          => 'No Friend Posts Items found',
			'not_found_in_trash' => 'No Friend Posts Items found in the Trash',
			'parent_item_colon'  => '',
			'menu_name'          => 'Friend Post Cache',
		);

		$args = array(
			'labels'        => $labels,
			'description'   => "A cached friend's post",
			'public'        => apply_filters( 'friends_show_cached_posts', false ),
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-groups',
			'supports'      => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
			'has_archive'   => true,
		);
		register_post_type( self::FRIEND_POST_CACHE, $args );
	}

	/**
	 * Registers the sidebar for the /friends page.
	 */
	public function register_friends_sidebar() {
		register_sidebar(
			array(
				'name' => 'Friends Sidebar',
				'before_widget' => '<div class="friends-widget">',
				'after_widget' => '</div>',
				'before_title' => '<h3>',
				'after_title' => '</h3>',
			)
		);
	}

	/**
	 * Registers the admin menus
	 */
	public function register_admin_menu() {
		add_menu_page( 'Friends', 'Friends', 'manage_options', 'send-friend-request', null, 'dashicons-groups', 3.73);
		add_submenu_page( 'send-friend-request', 'Send Friend Request', 'Send Friend Request', 'manage_options', 'send-friend-request', array( $this, 'render_admin_send_friend_request' ) );
		add_submenu_page( 'send-friend-request', 'Feed', 'Refresh', 'manage_options', 'refresh', array( $this, 'refresh_friend_posts' ) );
	}

	/**
	 * Admin menu to refresh the friend posts.
	 */
	public function refresh_friend_posts() {
		add_filter( 'notify_about_new_friend_post', '__return_false', 999 );
		$this->retrieve_friend_posts();
	}

	/**
	 * Cron function to refresh the feeds of the friends' blogs
	 */
	public function cron_friends_refresh_feeds() {
		$this->retrieve_friend_posts();
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
		$user = $this->create_user( $site_url, 'subscription' );
		if ( ! is_wp_error( $user ) ) {
			$this->process_friend_feed( $user, $feed );
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
			$site_url . '/wp-json/' . self::REST_NAMESPACE . '/hello', array(
				'timeout' => 20,
				'redirection' => 5,
			)
		);

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
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

		$user = $this->get_user_for_site_url( $site_url );
		if ( $user && ! is_wp_error( $user ) && $user->has_cap( 'friend_request' ) ) {
			$user->set_role( 'friend' );
			return $user;
		}

		$user_login = $this->get_user_login_for_site_url( $site_url );

		$friend_request_token = sha1( wp_generate_password( 256 ) );
		update_option( 'friends_request_token_' . sha1( $site_url ), $friend_request_token );

		$response = wp_remote_post(
			$site_url . '/wp-json/' . self::REST_NAMESPACE . '/friend-request', array(
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
		$user = $this->create_user( $site_url, 'pending_friend_request' );
		if ( ! is_wp_error( $user ) ) {
			if ( isset( $json->friend_request_pending ) ) {
				$user->set_role( 'pending_friend_request' );
				update_option( 'friends_accept_token_' . $json->friend_request_pending, $user->ID );
			} elseif ( isset( $json->friend ) ) {
				$this->make_friend( $user, $json->friend );
			}
		}

		return $user;
	}

	/**
	 * Render the admin form for sending a friend request.
	 */
	public function render_admin_send_friend_request() {
		$site_url = '';
		?><h1><?php esc_html_e( 'Send Friend Request', 'friends' ); ?></h1>
		<?php
		if ( ! empty( $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'send-friend-request' ) ) {

			$site_url = $_POST['site_url'];
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
		?>
		<form method="post">
			<?php wp_nonce_field( 'send-friend-request' ); ?>
			<p><?php esc_html_e( "This will send a friend request to the WordPress site you can enter below. If the other site doesn't have friends plugin installed, you'll be subscribed to that site.", 'friends' ); ?></p>
			<label><?php esc_html_e( 'Site:' ); ?> <input type="text" autofocus name="site_url" value="<?php echo esc_attr( $site_url ); ?>" required placeholder="Enter your friend's WordPress URL" size="90" /></label>
			<button><?php echo esc_attr_x( 'Send Friend Request', 'button', 'friends' ); ?></button>
		</form>
		<?php
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
	 * Add the REST API to send and receive friend requests
	 */
	public function add_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE, 'friend-request', array(
				'methods' => 'POST',
				'callback' => array( $this, 'rest_friend_request' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE, 'friend-request-accepted', array(
				'methods' => 'POST',
				'callback' => array( $this, 'rest_friend_request_accepted' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE, 'hello', array(
				'methods' => 'GET,POST',
				'callback' => array( $this, 'rest_hello' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE, 'post-deleted', array(
				'methods' => 'POST',
				'callback' => array( $this, 'rest_friend_post_deleted' ),
			)
		);
	}

	/**
	 * Retrieve posts from a remote WordPress for a user or all friend users.
	 *
	 * @param  WP_User|null $single_user A single user or null to fetch all.
	 */
	private function retrieve_friend_posts( WP_User $single_user = null ) {
		if ( $single_user ) {
			$friends = array(
				$single_user,
			);
		} else {
			$friends = new WP_User_Query( array( 'role__in' => array( 'friend', 'pending_friend_request', 'subscription' ) ) );
			$friends = $friends->get_results();

			if ( empty( $friends ) ) {
				return;
			}
		}

		foreach ( $friends as $friend_user ) {
			$feed_url = get_user_option( 'friends_feed_url', $friend_user->ID );
			if ( ! $feed_url ) {
				$feed_url = rtrim( $friend_user->user_url, '/' ) . '/feed/';
			}

			$token = get_user_option( 'friends_out_token', $friend_user->ID );
			if ( $token ) {
				$feed_url .= '?friend=' . $token;
			}
			$feed_url = apply_filters( 'friends_friend_feed_url', $feed_url, $friend_user );

			$feed = fetch_feed( $feed_url );
			if ( is_wp_error( $feed ) ) {
				continue;
			}
			$this->process_friend_feed( $friend_user, $feed );
		}
	}

	/**
	 * Retrieve the remote post ids.
	 *
	 * @param  WP_User $friend_user The friend user.
	 * @return array A mapping of the remote post ids.
	 */
	private function get_remote_post_ids( WP_User $friend_user ) {
		$remote_post_ids = array();
		$existing_posts = new WP_Query( array(
			'post_type' => self::FRIEND_POST_CACHE,
			'post_status' => array( 'publish', 'private' ),
			'author' => $friend_user->ID,
		));

		if ( $existing_posts->have_posts() ) {
			while ( $existing_posts->have_posts() ) {
				$existing_posts->the_post();
				$remote_post_id = get_post_meta( get_the_ID(), 'remote_post_id', true );
				$remote_post_ids[ $remote_post_id ] = get_the_ID();
				$remote_post_ids[ get_the_permalink() ] = get_the_ID();
			}
			wp_reset_postdata();
		}

		do_action( 'friends_remote_post_ids', $remote_post_ids );
		return $remote_post_ids;
	}

	/**
	 * Process the feed of a friend user.
	 *
	 * @param  WP_User   $friend_user The friend user.
	 * @param  SimplePie $feed        The RSS feed object of the friend user.
	 */
	private function process_friend_feed( WP_User $friend_user, SimplePie $feed ) {
		$new_friend = get_user_option( 'friends_new_friend', $friend_user->ID );

		$remote_post_ids = $this->get_remote_post_ids( $friend_user );

		foreach ( $feed->get_items() as $item ) {
			$permalink = $item->get_permalink();
			// Fallback, when no friends plugin is installed.
			$item->{'post-id'} = $permalink;
			$item->{'post-status'} = 'publish';

			foreach ( array( 'gravatar', 'comments', 'post-status', 'post-id' ) as $key ) {
				foreach ( array( self::XMLNS, 'com-wordpress:feed-additions:1' ) as $xmlns ) {
					if ( isset( $item->data['child'][ $xmlns ][ $key ][0]['data'] ) ) {
						$item->{$key} = $item->data['child'][ $xmlns ][ $key ][0]['data'];
						continue;
					}
				}
			}

			$item->comments_count = isset( $item->data['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'] ) ? $item->data['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'] : 0;

			$post_id = null;
			if ( isset( $remote_post_ids[ $item->{'post-id'} ] ) ) {
				$post_id = $remote_post_ids[ $item->{'post-id'} ];
			}
			if ( is_null( $post_id ) && isset( $remote_post_ids[ $permalink ] ) ) {
				$post_id = $remote_post_ids[ $permalink ];
			}
			if ( ! is_null( $post_id ) ) {
				$new_post = false;
				wp_update_post(
					array(
						'ID'                => $post_id,
						'post_title'        => $item->get_title(),
						'post_content'      => $item->get_content(),
						'post_modified_gmt' => $item->get_updated_gmdate( 'Y-m-d H:i:s' ),
						'post_status'       => $item->{'post-status'},
						'guid'              => $permalink,
					)
				);
			} else {
				$new_post = true;
				$post_id = wp_insert_post(
					array(
						'post_author'       => $friend_user->ID,
						'post_type'         => self::FRIEND_POST_CACHE,
						'post_title'        => $item->get_title(),
						'post_date_gmt'     => $item->get_gmdate( 'Y-m-d H:i:s' ),
						'post_content'      => $item->get_content(),
						'post_status'       => $item->{'post-status'},
						'post_modified_gmt' => $item->get_updated_gmdate( 'Y-m-d H:i:s' ),
						'guid'              => $permalink,
						'comment_count'     => $item->comment_count,
					)
				);
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
			}
			$author = $item->get_author();
			update_post_meta( $post_id, 'author', $author->name );
			update_post_meta( $post_id, 'gravatar', $item->gravatar );

			update_post_meta( $post_id, 'remote_post_id', $item->{'post-id'} );
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'comment_count' => $item->comment_count ), array( 'ID' => $post_id ) );

			$notify_users = apply_filters( 'notify_about_new_friend_post', $new_post && ! $new_friend, $friend_user, $post_id );
			if ( $notify_users ) {
				do_action( 'notify_new_friend_post', WP_Post::get_instance( $post_id ) );
			}
		}

		if ( $new_friend ) {
			delete_user_option( $friend_user->ID, 'friends_new_friend' );
		}
	}

	/**
	 * Notify the users of this site about a new friend request
	 *
	 * @param  WP_Post $post The new post by a friend.
	 */
	public function notify_new_friend_post( WP_Post $post ) {
		$users = new WP_User_Query( array( 'role' => 'administrator' ) );
		$users = $users->get_results();

		$message_headers = 'Content-Type: text/plain; charset="' . get_option( 'blog_charset' ) . "\"\n";

		foreach ( $users as $user ) {
			if ( ! $user->user_email ) {
				continue;
			}

			if ( ! apply_filters( 'notify_user_about_friend_post', true, $user, $post ) ) {
				continue;
			}

			// TODO: Opt out of these e-mails.
			$author = new WP_User( $post->post_author );
			// translators: %s is a username.
			$message = sprintf( __( 'Howdy, %s' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			// translators: %1$s is a username, %2$s is a URL.
			$message .= sprintf( __( 'Your friend %1$s has posted something new: %2$s', 'friends' ), $author->display_name, site_url( '/friends/' . $post->ID . '/' ) ) . PHP_EOL . PHP_EOL;
			$message .= __( 'Best, the Friends plugin', 'friends' ) . PHP_EOL;

			// translators: %1$s is the blog name, %2$s is a post title.
			wp_mail( $user->user_email, sprintf( __( '[%1$s] New Friend Post: %2$s', 'friends' ), wp_specialchars_decode( get_site_option( 'blogname' ), ENT_QUOTES ), wp_specialchars_decode( $post->post_title, ENT_QUOTES ) ), $message, $message_headers );
		}
	}

	/**
	 * Notify the users of this site about a new friend request
	 *
	 * @param  WP_User $friend_user The user requesting friendship.
	 */
	public function notify_new_friend_request( WP_User $friend_user ) {
		$users = new WP_User_Query( array( 'role' => 'administrator' ) );
		$users = $users->get_results();

		$message_headers = 'Content-Type: text/plain; charset="' . get_option( 'blog_charset' ) . "\"\n";

		foreach ( $users as $user ) {
			if ( ! $user->user_email ) {
				continue;
			}

			if ( ! apply_filters( 'notify_user_about_friend_request', true, $user, $friend_user ) ) {
				continue;
			}

			// TODO: Opt out of these e-mails.
			// translators: %s is a username.
			$message = sprintf( __( 'Howdy, %s' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			// translators: %s is a username.
			$message .= sprintf( __( 'You have received a new friend request from %s.', 'friends' ), $friend_user->display_name ) . PHP_EOL . PHP_EOL;
			$message .= sprintf( __( 'Go to your admin page to review the request and approve or delete it.', 'friends' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			$message .= self_admin_url( 'users.php?role=friend_request' ) . PHP_EOL . PHP_EOL;
			$message .= __( 'Best, the Friends plugin', 'friends' ) . PHP_EOL;

			// translators: %1$s is the blog name, %2$s is a username.
			wp_mail( $user->user_email, sprintf( __( '[%1$s] New Friend Request from %2$s', 'friends' ), wp_specialchars_decode( get_site_option( 'blogname' ), ENT_QUOTES ), wp_specialchars_decode( $friend_user->display_name , ENT_QUOTES ) ), $message, $message_headers );
		}

	}

	/**
	 * Notify the users of this site about an accepted friend request
	 *
	 * @param  WP_User $friend_user The user who accepted friendship.
	 */
	public function notify_accepted_friend_request( WP_User $friend_user ) {
		$users = new WP_User_Query( array( 'role' => 'administrator' ) );
		$users = $users->get_results();

		$message_headers = 'Content-Type: text/plain; charset="' . get_option( 'blog_charset' ) . "\"\n";

		foreach ( $users as $user ) {
			if ( ! $user->user_email ) {
				continue;
			}

			if ( ! apply_filters( 'notify_user_about_accepted_friend_request', true, $user, $friend_user ) ) {
				continue;
			}

			// TODO: Opt out of these e-mails.
			// translators: %s is a username.
			$message = sprintf( __( 'Howdy, %s' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			// translators: %s is a username.
			$message .= sprintf( __( '%s has accepted your friend request.', 'friends' ), $friend_user->display_name ) . PHP_EOL . PHP_EOL;
			$message .= sprintf( __( 'Go to your friends page and look at their posts.', 'friends' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			$message .= site_url( '/friends/' ) . PHP_EOL . PHP_EOL;
			$message .= __( 'Best, the Friends plugin', 'friends' ) . PHP_EOL;

			// translators: %1$s is the blog name, %2$s is a username.
			wp_mail( $user->user_email, sprintf( __( '[%1$s] %2$s accepted your Friend Request', 'friends' ), wp_specialchars_decode( get_site_option( 'blogname' ), ENT_QUOTES ), wp_specialchars_decode( $friend_user->display_name , ENT_QUOTES ) ), $message, $message_headers );
		}

	}

	/**
	 * Acknowledge via REST that the friends plugin had called.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_hello( WP_REST_Request $request ) {
		if ( 'GET' === $request->get_method() ) {
			return array(
				'version' => self::VERSION,
				'site_url' => site_url(),
			);
		}

		$signature = get_option( 'friends_request_token_' . sha1( $request->get_param( 'site_url' ) ) );

		if ( ! $signature ) {
			return new WP_Error(
				'friends_unknown_request',
				'The other party is unknown.',
				array(
					'status' => 403,
				)
			);
		}

		return array(
			'version' => self::VERSION,
			'response' => sha1( $signature . $request->get_param( 'challenge' ) ),
		);
	}

	/**
	 * Receive a notification via REST that a friend request was accepted
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_request_accepted( WP_REST_Request $request ) {
		$accept_token = $request->get_param( 'token' );
		$out_token = $request->get_param( 'friend' );
		$proof = $request->get_param( 'proof' );
		$friend_user_id = get_option( 'friends_accept_token_' . $accept_token );
		$friend_user = false;
		if ( $friend_user_id ) {
			$friend_user = new WP_User( $friend_user_id );
		}

		if ( ! $accept_token || ! $out_token || ! $proof || ! $friend_user || is_wp_error( $friend_user ) || ! $friend_user->user_url ) {
			return new WP_Error(
				'friends_invalid_parameters',
				'Not all necessary parameters were provided.',
				array(
					'status' => 403,
				)
			);
		}

		$signature = get_user_option( 'friends_accept_signature', $friend_user_id );
		if ( sha1( $accept_token . $signature ) !== $proof ) {
			return new WP_Error(
				'friends_invalid_proof',
				'An invalid proof was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$friend_user_login = $this->get_user_login_for_site_url( $friend_user->user_url );
		if ( $friend_user_login !== $friend_user->user_login ) {
			return new WP_Error(
				'friends_offer_no_longer_valid',
				'The friendship offer is no longer valid.',
				array(
					'status' => 403,
				)
			);
		}

		delete_option( 'friends_accept_token_' . $accept_token );

		$this->make_friend( $friend_user, $out_token );
		do_action( 'notify_accepted_friend_request', $friend_user );
		return array(
			'friend' => get_user_option( 'friends_in_token', $friend_user->ID ),
		);
	}

	/**
	 * Receive a friend request via REST
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_request( WP_REST_Request $request ) {
		// TODO: rate limit.
		$site_url = $request->get_param( 'site_url' );
		if ( ! is_string( $site_url ) || ! wp_http_validate_url( $site_url ) || strtolower( site_url() ) === $site_url ) {
			return new WP_Error(
				'friends_invalid_site',
				'An invalid site was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$challenge = sha1( wp_generate_password( 256 ) );
		$response = wp_safe_remote_post(
			$site_url . '/wp-json/' . self::REST_NAMESPACE . '/hello', array(
				'body' => array(
					'challenge' => $challenge,
					'site_url' => site_url(),
				),
				'timeout' => 5,
				'redirection' => 1,
			)
		);

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $json || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( $json && isset( $json->code ) && isset( $json->message ) ) {
				return new WP_Error( $json->code, $json->message, $json->data );
			}

			return new WP_Error(
				'friends_unsupported_site',
				'An unsupported site was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$signature = $request->get_param( 'signature' );
		if ( sha1( $signature . $challenge ) !== $json->response ) {
			return new WP_Error(
				'friends_invalid_response',
				'An invalid response was provided.',
				array(
					'status' => 403,
				)
			);
		}

		$user = $this->get_user_for_site_url( $site_url );
		if ( $user && ! is_wp_error( $user ) ) {
			if ( $user->has_cap( 'friend_request' ) && get_user_option( 'friends_request_token', $user->ID ) ) {
				// Exit early and don't notify.
				return array(
					'friend_request_pending' => get_user_option( 'friends_request_token', $user->ID ),
				);
			}

			if ( $user->has_cap( 'friend' ) ) {
				// Already a friend, was it deleted?
				return array(
					'friend' => get_user_option( 'friends_in_token', $user->ID ),
				);
			}
		}

		$user_id = $this->create_user( $site_url, 'friend_request', $request->get_param( 'name' ), $request->get_param( 'email' ) );
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error(
				'friends_friend_request_failed',
				'Could not respond to the friend request.',
				array(
					'status' => 403,
				)
			);
		}

		$user = new WP_User( $user_id );

		if ( $user->has_cap( 'pending_friend_request' ) ) {
			// Friend request was deleted on the other side and then re-initated.
			$user->set_role( 'friend_request' );
		}

		update_user_option( $user_id, 'friends_accept_signature', $signature );

		do_action( 'notify_new_friend_request', $user );
		return array(
			'friend_request_pending' => get_user_option( 'friends_request_token', $user->ID ),
		);
	}

	/**
	 * Notify friends of a deleted post
	 *
	 * @param  int $post_id The post id of the post that is deleted.
	 */
	public function notify_friend_post_deleted( $post_id ) {
		$post = WP_Post::get_instance( $post_id );
		if ( 'post' !== $post->post_type ) {
			return;
		}

		$friends = new WP_User_Query( array( 'role' => 'friend' ) );
		$friends = $friends->get_results();

		foreach ( $friends as $friend_user ) {
			$response = wp_safe_remote_post(
				$friend_user->user_url . '/wp-json/' . self::REST_NAMESPACE . '/post-deleted', array(
					'body' => array(
						'post_id' => $post_id,
						'friend' => get_user_option( 'friends_out_token', $friend_user->ID ),
					),
					'timeout' => 20,
					'redirection' => 5,
				)
			);
		}
	}

	/**
	 * Receive a REST message that a post was deleted.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_friend_post_deleted( $request ) {
		$token = $request->get_param( 'friend' );
		$user_id = $this->verify_token( $token );
		if ( ! $user_id ) {
			return new WP_Error(
				'friends_friend_request_failed',
				'Could not respond to the friend request.',
				array(
					'status' => 403,
				)
			);
		}
		$friend_user = new WP_User( $user_id );
		$remote_post_id = $request->get_param( 'post_id' );
		$remote_post_ids = $this->get_remote_post_ids( $friend_user );

		$post_id = false;
		if ( isset( $remote_post_ids[ $remote_post_id ] ) ) {
			$post_id = $remote_post_ids[ $remote_post_id ];
		}

		if ( ! $post_id ) {
			return array(
				'deleted' => false,
			);
		}
		$post = WP_Post::get_instance( $post_id );
		if ( self::FRIEND_POST_CACHE === $post->post_type ) {
			wp_delete_post( $post_id );
		}

		return array(
			'deleted' => true,
		);
	}

	/**
	 * Convert a site URL to a username
	 *
	 * @param  string $site_url The site URL in question.
	 * @return string The corresponding username.
	 */
	private function get_user_login_for_site_url( $site_url ) {
		$host = wp_parse_url( $site_url, PHP_URL_HOST );
		$path = wp_parse_url( $site_url, PHP_URL_PATH );

		$user_login = trim( preg_replace( '#[^a-z0-9.-]+#', '_', strtolower( $host . '_' . $path ) ), '_' );
		return $user_login;
	}

	/**
	 * Checks whether a user already exists for a site URL.
	 *
	 * @param  string $site_url The site URL for which to create the user.
	 * @return WP_User|false Whether the user already exists
	 */
	private function get_user_for_site_url( $site_url ) {
		$user_login = $this->get_user_login_for_site_url( $site_url );
		return get_user_by( 'login', $user_login );
	}

	/**
	 * Create a WP_User with a specific Friends-related role
	 *
	 * @param  string $site_url The site URL for which to create the user.
	 * @param  string $role     The role: subscription, pending_friend_request, friend_request, or friend.
	 * @param  string $name     The user's nickname.
	 * @param  string $email    The user e-mail address.
	 * @return WP_User|WP_Error The created user or an error.
	 */
	private function create_user( $site_url, $role, $name = null, $email = null ) {
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

		$user = $this->get_user_for_site_url( $site_url );
		if ( $user && ! is_wp_error( $user ) ) {
			foreach ( $role_rank as $_role => $rank ) {
				if ( $rank > $role_rank[ $role ] ) {
					break;
				}
				if ( $user->has_cap( $_role ) ) {
					// Upgrade user role.
					$user->set_role( $role );
					break;
				}
			}
			return $user;
		}

		$userdata = array(
			'user_login' => $this->get_user_login_for_site_url( $site_url ),
			'nickname'   => $name,
			'user_email' => $email,
			'user_url'   => $site_url,
			'user_pass'  => wp_generate_password( 256 ),
			'role'       => $role,
		);

		$user_id = wp_insert_user( $userdata );

		update_user_option( $user_id, 'friends_new_friend', true );

		return new WP_User( $user_id );
	}

	/**
	 * Remove the Private: when sending a private feed.
	 *
	 * @param  string $title_format The title format for a private post title.
	 * @return string The modified title format for a private post title.
	 */
	public function private_title_format( $title_format ) {
		if ( $this->feed_authenticated ) {
			return '%s';
		}
		return $title_format;
	}

	/**
	 * Disable excerpted feeds for friend feeds
	 *
	 * @param  boolean $feed_use_excerpt Whether to only have excerpts in feeds.
	 * @return boolean The modified flag whether to have excerpts in feeds.
	 */
	public function feed_use_excerpt( $feed_use_excerpt ) {
		if ( $this->feed_authenticated ) {
			return 0;
		}

		return $feed_use_excerpt;
	}

	/**
	 * Output an additional XMLNS for the feed.
	 */
	public function additional_feed_namespaces() {
		if ( $this->feed_authenticated ) {
			echo 'xmlns:friends="' . esc_attr( self::XMLNS ) . '"';
		}
	}

	/**
	 * Additional fields for the friends feed.
	 */
	public function feed_additional_fields() {
		if ( $this->feed_authenticated ) {
			global $post;
			echo '<friends:gravatar>' . esc_html( get_avatar_url( $post->post_author ) ) . '</friends:gravatar>';
			echo '<friends:post-status>' . esc_html( $post->post_status ) . '</friends:post-status>';
			echo '<friends:post-id>' . esc_html( $post->ID ) . '</friends:post-id>';
		}
	}

	/**
	 * Modify the main query for the friends feed
	 *
	 * @param  WP_Query $query The main query.
	 * @return WP_Query The modified main query.
	 */
	public function private_feed_query( WP_Query $query ) {
		if ( ! $this->feed_authenticated ) {
			return $query;
		}

		if ( ! $query->is_admin && $query->is_feed ) {
			$query->set( 'post_status', array( 'publish', 'private' ) );
		}

		return $query;
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

	/**
	 * Reference our script for the /friends page
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'friends-js', plugin_dir_url( __FILE__ ) . 'friends.js', 'jquery' );
		wp_enqueue_style( 'friends-css', plugin_dir_url( __FILE__ ) . 'friends.css' );
	}

	/**
	 * Add a CSS class to the body
	 *
	 * @param array $classes The existing CSS classes.
	 * @return array The modified CSS classes.
	 */
	public function add_body_class( $classes ) {
		if ( $this->on_friends_page ) {
			$classes[] = 'friends-page';
		}

		return $classes;
	}

	/**
	 * The Ajax function to be called upon posting from /friends
	 */
	public function frontend_publish_post() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'friends_publish' ) ) {
			$post_id = wp_insert_post(
				array(
					'post_type'         => 'post',
					'post_title'        => $_POST['title'],
					'post_content'      => $_POST['content'],
					'post_status'       => $_POST['status'],
				)
			);
			$result = is_wp_error( $post_id ) ? 'error' : 'success';
			if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' ) {
				echo $result;
			} else {
				wp_safe_redirect( $_SERVER['HTTP_REFERER'] );
				exit;
			}
		}
	}

	/**
	 * Load the template for /friends
	 * @param  string $template The original template intended to load.
	 * @return string The new template to be loaded.
	 */
	public function template_override( $template ) {
		global $wp_query;

		if ( 'friends' === $wp_query->query['pagename'] ) {
			if ( current_user_can( 'edit_posts' ) ) {
				if ( isset( $_GET['refresh'] ) ) {
					add_filter( 'notify_about_new_friend_post', '__return_false', 999 );
					add_filter( 'wp_feed_options', function( $feed ) {
						$feed->enable_cache( false );
					} );
					$this->retrieve_friend_posts( null, true );
				}

				$friends = new WP_User_Query( array( 'role__in' => array( 'friend', 'pending_friend_request' ) ) );

				if ( ! have_posts() ) {
					return __DIR__ . '/templates/friends/no-posts.php';
				}
				return __DIR__ . '/templates/friends/posts.php';
			}

			if ( $wp_query->is_404 ) {
				$wp_query->is_404 = false;
				if ( current_user_can( 'friend' ) ) {
					$user = wp_get_current_user();
					wp_safe_redirect( $user->user_url . '/friends/' );
					exit;
				}

				return __DIR__ . '/templates/friends/logged-out.php';

			}
		}
		return $template;
	}

	/**
	 * Don't show the edit link for friend posts.
	 *
	 * @param  string $link    The edit link.
	 * @param  int    $post_id The post id.
	 * @return string|bool The edit link or false.
	 */
	public function friend_post_edit_link( $link, $post_id ) {
		global $post;

		if ( self::FRIEND_POST_CACHE === $post->post_type ) {
			$link = false;
		}
		return $link;
	}

	/**
	 * Link friend posts to the remote site.
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post in question.
	 * @param bool    $leavename Whether to keep the post name.
	 * @param bool    $sample    Is it a sample permalink.
	 * @reeturn string The overriden post link.
	 */
	public function friend_post_link( $post_link, WP_Post $post, $leavename, $sample ) {
		if ( self::FRIEND_POST_CACHE === $post->post_type ) {
			return get_the_guid( $post );
		}
	}

	/**
	 * Modify the main query for the /friends page
	 *
	 * @param  WP_Query $query The main query.
	 * @return WP_Query The modified main query.
	 */
	public function friend_posts_query( $query ) {
		if ( 'friends' !== get_query_var( 'pagename' ) ) {
			return $query;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return $query;
		}
		$this->on_friends_page = true;

		$page_id = get_query_var( 'page' );

		$query->set( 'post_status', array( 'publish', 'private' ) );
		$query->set( 'post_type', array( self::FRIEND_POST_CACHE, 'post' ) );
		$query->is_page = false;
		$query->set( 'page', null );
		$query->set( 'pagename', null );

		if ( $page_id ) {
			$query->set( 'page_id', $page_id );
			$query->is_singular = true;
		} else {
			$query->is_singular = false;
		}

		return $query;
	}

	/**
	 * Verify a friend token
	 *
	 * @param  string $token The token to verify.
	 * @return int|bool The user id or false.
	 */
	protected function verify_token( $token ) {
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
		if ( ! isset( $_GET['friend'] ) ) {
			return $incoming_user_id;
		}

		$user_id = $this->verify_token( $_GET['friend'] );
		if ( ! $user_id ) {
			return $incoming_user_id;
		}

		$user = new WP_User( $user_id );
		if ( ! $user->has_cap( 'friend' ) ) {
			return $incoming_user_id;
		}

		$this->feed_authenticated = $user_id;
		return $user_id;
	}

	/**
	 * Notify the friend's site via REST about the accepted friend request.
	 *
	 * Accepting a friend request is simply setting the role to "friend".
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  string $old_roles The old roles.
	 */
	public function notify_remote_friend_request_accepted( $user_id, $new_role, $old_roles ) {
		if ( 'friend' !== $new_role ) {
			return;
		}

		$request_token = get_user_option( 'friends_request_token', $user_id );
		if ( ! $request_token ) {
			// We were accepted, so no need to notify the other.
			return;
		}

		$user = new WP_User( $user_id );

		$friend_request_token = get_option( 'friends_request_token_' . sha1( $user->user_url ) );
		$in_token = $this->update_in_token( $user->ID );

		$response = wp_safe_remote_post(
			$user->user_url . '/wp-json/' . self::REST_NAMESPACE . '/friend-request-accepted', array(
				'body' => array(
					'token' => $request_token,
					'friend' => $in_token,
					'proof' => sha1( $request_token . $friend_request_token ),
				),
				'timeout' => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		delete_user_option( $user_id, 'friends_request_token' );
		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( isset( $json->friend ) ) {
			$this->make_friend( $user, $json->friend );
		} else {
			$user->set_role( 'pending_friend_request' );
			if ( isset( $json->friend_request_pending ) ) {
				update_option( 'friends_accept_token_' . $json->friend_request_pending, $user_id );
			}
		}
	}

	/**
	 * Convert a user to a friend
	 *
	 * @param  WP_User $user  The user to become a friend of the blog.
	 * @param  string  $out_token The token to talk to the remote.
	 * @return WP_User|WP_Error The user or an error.
	 */
	private function make_friend( WP_User $user, $out_token ) {
		if ( ! $user || is_wp_error( $user ) ) {
			return $user;
		}

		update_user_option( $user->ID, 'friends_out_token', $out_token );
		$user->set_role( 'friend' );

		return $user;
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
			delete_option( 'friends_accept_token_' . $current_secret );
		}

		$user = new WP_User( $user_id );
		delete_option( 'friends_request_token_' . sha1( $user->user_url ) );

		// No need to delete user options as the user will be deleted.

		return $current_secret;
	}

	/**
	 * Update the friend_in_token
	 *
	 * @param  int $user_id The user_id to give an friend_in_token.
	 * @return string The new friend_in_token.
	 */
	public function update_in_token( $user_id ) {
		$in_token = sha1( wp_generate_password( 256 ) );
		if ( update_user_option( $user_id, 'friends_in_token', $in_token ) ) {
			update_option( 'friends_in_token_' . $in_token, $user_id );
		}

		return $in_token;
	}

	/**
	 * Update a friend request token
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  string $old_roles The old roles.
	 */
	public function update_friend_request_token( $user_id, $new_role, $old_roles ) {
		if ( 'friend_request' !== $new_role || in_array( $new_role, $old_roles, true ) ) {
			return;
		}

		$token = sha1( wp_generate_password( 256 ) );
		update_user_option( $user_id, 'friends_request_token', $token );
	}

	/**
	 * Update the friend_token after changing roles
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  string $old_roles The old roles.
	 */
	public function update_friend_token( $user_id, $new_role, $old_roles ) {
		if ( 'friend' === $new_role && in_array( $new_role, $old_roles, true ) ) {
			return;
		}
		$current_secret = $this->delete_friend_token( $user_id );

		if ( 'friend' !== $new_role ) {
			return;
		}
		$this->update_in_token( $user_id );
	}

	/**
	 * Retrieve new friend's posts after changing roles
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  string $old_roles The old roles.
	 */
	public function retrieve_new_friends_posts( $user_id, $new_role, $old_roles ) {
		if ( 'friend' === $new_role ) {
			update_user_option( $user_id, 'friends_new_friend', true );
			$this->retrieve_friend_posts( new WP_User( $user_id ) );
		}
	}

	/**
	 * Create the Friend user roles
	 */
	private static function setup_roles() {
		$friend = get_role( 'friend' );
		if ( ! $friend ) {
			$friend = add_role( 'friend', 'Friend' );
		}
		$friend->add_cap( 'read_private_posts' );
		$friend->add_cap( 'read' );
		$friend->add_cap( 'friend' );
		$friend->add_cap( 'level_0' );

		$friend_request = get_role( 'friend_request' );
		if ( ! $friend_request ) {
			$friend_request = add_role( 'friend_request', 'Friend Request' );
		}
		$friend_request->add_cap( 'friend_request' );
		$friend_request->add_cap( 'level_0' );

		$pending_friend_request = get_role( 'pending_friend_request' );
		if ( ! $pending_friend_request ) {
			$pending_friend_request = add_role( 'pending_friend_request', 'Pending Friend Request' );
		}
		$pending_friend_request->add_cap( 'pending_friend_request' );
		$pending_friend_request->add_cap( 'level_0' );

		$subscription = get_role( 'subscription' );
		if ( ! $subscription ) {
			$subscription = add_role( 'subscription', 'Subscription' );
		}
		$subscription->add_cap( 'subscription' );
		$subscription->add_cap( 'level_0' );
	}

	/**
	 * Actions to take upon plugin activation.
	 */
	public static function activate_plugin() {
		self::setup_roles();

		if ( ! wp_next_scheduled( 'cron_friends_refresh_feeds' ) ) {
			wp_schedule_event( time(), 'hourly', 'cron_friends_refresh_feeds' );
		}
	}

	/**
	 * Actions to take upon plugin deactivation.
	 */
	public static function deactivate_plugin() {
		$timestamp = wp_next_scheduled( 'cron_friends_refresh_feeds' );
		wp_unschedule_event( $timestamp, 'cron_friends_refresh_feeds' );
	}

	/**
	 * Delete all the data the plugin has stored in WordPress
	 */
	public static function uninstall_plugin() {
		$affected_users = new WP_User_Query( array( 'role__in' => array( 'friend', 'friend_request', 'pending_friend_request', 'subscription' ) ) );
		foreach ( $affected_users as $user ) {
			$in_token = get_user_option( 'friends_in_token', $user->ID );
			delete_option( 'friends_in_token_' . $in_token );
			delete_option( 'friends_accept_token_' . $in_token );
			delete_option( 'friends_request_token_' . sha1( $user->user_url ) );
			delete_user_option( $user->ID, 'friends_out_token' );
			delete_user_option( $user->ID, 'friends_in_token' );
			delete_user_option( $user->ID, 'friends_new_friend' );
			delete_user_option( $user->ID, 'friends_accept_signature' );
			delete_user_option( $user->ID, 'friends_request_token' );
		}

		remove_role( 'friend' );
		remove_role( 'friend_request' );
		remove_role( 'pending_friend_request' );
		remove_role( 'subscription' );

		$friend_posts = new WP_Query(
			array(
				'post_type' => self::FRIEND_POST_CACHE,
				'post_status' => array( 'publish', 'private' ),
			)
		);

		while ( $friend_posts->have_posts() ) {
			wp_delete_post( $friend_posts->post->ID, true );
		}
	}
}
