<?php
/**
 * Plugin name: Friends
 * Plugin author: Alex Kirk
 * Version: 0.1
 *
 * Description: Connect WordPresses through friendships and RSS.
 */

class Friends {
	const VERSION = '0.1';
	const REST_NAMESPACE = 'friends/v1';
	private $authenticated = null;

	public static function init() {
		static::get_instance();
	}

	public static function get_instance() {
		static $instance;
		if ( ! isset( $instance ) ) {
			$self = get_called_class();
			$instance = new $self;
		}
		return $instance;
	}

	public function __construct() {
		$this->add_hooks();
	}

	private function add_hooks() {
		// Authentication
		add_filter( 'determine_current_user',     array( $this, 'authenticate' ), 1 );

		// Access control
		add_action( 'set_user_role',              array( $this, 'update_friend_token' ), 10, 3 );
		add_action( 'set_user_role',              array( $this, 'notify_friend_request_accepted' ), 10, 3 );
		add_action( 'delete_user',                array( $this, 'delete_friend_token' ) );
		add_action( 'init',                       array( $this, 'remote_login' ) );

		// RSS
		add_filter( 'pre_get_posts',              array( $this, 'private_feed' ), 1 );
		add_filter( 'the_content_feed',           array( $this, 'feed_content' ), 90 );
		add_filter( 'the_excerpt_rss',            array( $this, 'feed_content' ), 90 );
		add_filter( 'comment_text_rss',           array( $this, 'feed_content' ), 90 );
		add_filter( 'rss_use_excerpt',            array( $this, 'feed_use_excerpt' ), 90 );
		add_filter( 'wp_feed_options',            array( $this, 'wp_feed_options' ), 10, 2 );
		add_action( 'rss_item',                   array( $this, 'feed_gravatar' ) );
		add_action( 'rss2_item',                  array( $this, 'feed_gravatar' ) );
		add_action( 'rss_ns',                     array( $this, 'additional_feed_namespaces' ) );
		add_action( 'rss2_ns',                    array( $this, 'additional_feed_namespaces' ) );

		// /friends/
		add_filter( 'pre_get_posts',              array( $this, 'friend_posts_query' ), 2 );
		add_filter( 'post_type_link',             array( $this, 'friend_post_link' ), 10, 4 );
		add_filter( 'get_edit_post_link',         array( $this, 'friend_post_edit_link' ), 10, 2 );
		add_filter( 'template_include',           array( $this, 'template_override' ) );
		add_filter( 'init',                       array( $this, 'register_custom_post_types' ) );

		// Admin
		add_action( 'admin_menu',                 array( $this, 'register_admin_menu' ), 10, 3 );
		add_filter( 'user_row_actions',           array( $this, 'user_row_actions' ), 10, 2 );
		add_filter( 'handle_bulk_actions-users',  array( $this, 'handle_friend_request_approval' ), 10, 3 );
		add_filter( 'bulk_actions-users',         array( $this, 'add_bulk_option_accept_friend_request' ) );

		// REST
		add_action( 'rest_api_init',              array( $this, 'add_api_routes' ) );
	}

	public function register_custom_post_types() {
		$labels = array(
			'name'               => 'Friend Post',
			'singular_name'      => 'Friend Post',
			'add_new'            => 'Add New', 'book',
			'add_new_item'       => 'Add New Friend Post',
			'edit_item'          => 'Edit Friend Post',
			'new_item'           => 'New Friend Post',
			'all_items'          => 'All Friend Posts',
			'view_item'          => 'View Friend Post',
			'search_items'       => 'Search Friend Posts',
			'not_found'          => 'No Friend Posts found',
			'not_found_in_trash' => 'No Friend Posts found in the Trash',
			'parent_item_colon'  => '',
			'menu_name'          => 'Friend Post'
		);

		$args = array(
			'labels'        => $labels,
			'description'   => "A friend's post",
			'public'        => true,
			'menu_position' => 5,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' ),
			'has_archive'   => true,
		);
		register_post_type( 'friend_post', $args );
	}

	public function register_admin_menu() {
		add_menu_page( 'Friends', 'Friends', 'manage_options', 'friends', null, '', 3.731 );
		add_submenu_page( 'friends', 'Feed', 'Refresh', 'manage_options', 'friends', array( $this, 'refresh_friends_feed' ) );
		add_submenu_page( 'friends', 'Send Friend Request', 'Send Friend Request', 'manage_options', 'send-friend-request', array( $this, 'render_admin_send_friend_request' ) );
	}

	public function refresh_friends_feed() {
		$this->retrieve_friend_posts( true ); // TODO make cron
	}

	public function wp_feed_options( $feed, $url ) {
		// TODO: do allow caching again, this is just while testing.
		if ( false !== strpos( $url, '?friend=' ) ) {
			$feed->enable_cache( false );
		}
	}

	public function render_admin_send_friend_request() {
		?><h1>Send Friend Request</h1><?php
		if ( ! empty( $_POST ) ) {
			$site_url = $_POST['site_url'];
			$protocol = parse_url( $site_url, PHP_URL_SCHEME );
			if ( ! $protocol ) {
				$site_url = 'http://' . $site_url;
			}

			if ( is_string( $site_url ) && wp_http_validate_url( $site_url ) ) {
				$response = wp_remote_post( $site_url . '/wp-json/' . self::REST_NAMESPACE . '/friend-request', array(
					'timeout' => 45,
					'redirection' => 5,
					'body' => array( 'site_url' => site_url() ),
				) );

				$link = wp_remote_retrieve_header( $response, 'link' );
				$wp_json = strpos( $link, 'wp-json/' );
				if ( false !== $wp_json ) {
					$site_url = substr( $link, 1, $wp_json - 1 );
				}
				$site_url = rtrim( $site_url, '/' );

				if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
					$json = json_decode( wp_remote_retrieve_body( $response ) );
					if ( 'rest_no_route' === $json->code ) {
						?><div id="message" class="updated error is-dismissible"><p>Site <?php echo esc_html( $site_url ); ?> doesn't have a compatible Friends plugin installed.</p></button></div><?php
					} else {
						?><div id="message" class="updated error is-dismissible"><p><?php var_dump( $json ); ?></p></button></div><?php
					}
				} else {
					$json = json_decode( wp_remote_retrieve_body( $response ) );
					$user_id = $this->create_user( $site_url, 'pending_friend_request' );
					if ( is_wp_error( $user_id ) ) {
						var_dump( $user_id );
					} elseif ( isset( $json->friend_request_pending ) ) {
						// TODO Improve message
						?><div id="message" class="updated notice is-dismissible"><p>Friendship requested for site <?php echo esc_html( $site_url ); ?>, Token: <?php echo $json->friend_request_pending; ?></p></button></div><?php
						update_option( 'friends_request_token_' . $json->friend_request_pending, $user_id );
					} elseif ( isset( $json->friend ) ) {
						$this->make_friend( $user_id, $json->friend );
						// TODO Improve message
						?><div id="message" class="updated notice is-dismissible"><p>You're now a friend of site <?php echo esc_html( $site_url ); ?></p></button></div><?php
					}
				}
			}

		}
		?><form method="post">
			<p>This will send a friend request to the WordPress site you can enter below. The other site also needs to have the friends plugin installed.</p>
			<label>Site: <input type="text" name="site_url" value="<?php if ( ! empty( $_GET['url'] ) ) echo esc_attr( $_GET['url'] ); ?>" required placeholder="Enter your friend's WordPress URL" size="90" /></label>
			<button>Initiate Friend Request</button>
		</form>
		<?php
	}

	public function user_row_actions( array $actions, WP_User $user ) {
		if ( ! $user->has_cap( 'friend_request' ) && ! $user->has_cap( 'pending_friend_request' ) && ! $user->has_cap( 'friend' ) ) {
			return $actions;
		}
		unset( $actions['edit' ] );
		$actions['view'] = '<a href="' . esc_url( $user->user_url ) . '">' . __( 'View' ) . '</a>';

		if ( $user->has_cap( 'friend_request' ) ) {
			$link = self_admin_url( wp_nonce_url( 'users.php?action=accept_friend_request&users[]=' . $user->ID ) );
			$actions['user_accept_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Accept Friend Request', 'friends' ) . '</a>';
		}

		return $actions;
	}

	public function handle_friend_request_approval( $sendback, $action, $users ) {
		if ( $action !== 'accept_friend_request' ) {
			return false;
		}
		$accepted = 0;
		foreach ( $users as $user_id ) {
			$user = new WP_User( $user_id );
			if ( ! is_wp_error( $user ) ) {
				if ( $user->set_role( 'friend' ) ) {
					$accepted += 1;
				}
			}
		}

		$sendback = add_query_arg( 'accepted', $accepted, $sendback );
		wp_redirect( $sendback );
	}

	public function add_bulk_option_accept_friend_request( $actions ) {
		$actions['accept_friend_request'] = 'Accept Friend Request';
		return $actions;
	}

	public function add_api_routes() {
		register_rest_route( self::REST_NAMESPACE, 'friend-request', array(
			'methods' => 'POST',
			'callback' => array( $this, 'friend_request' ),
		) );
		register_rest_route( self::REST_NAMESPACE, 'friend-request-accepted', array(
			'methods' => 'POST',
			'callback' => array( $this, 'friend_request_accepted' ),
		) );
		register_rest_route( self::REST_NAMESPACE, 'hello', array(
			'methods' => 'GET',
			'callback' => array( $this, 'verify_installation' ),
		) );
	}

	private function retrieve_friend_posts( $debug = false ) {
		$friends = new WP_User_Query( array( 'role' => 'friend' ) );

		if ( empty( $friends->get_results() ) ) {
			return;
		}
		foreach ( $friends->get_results() as $friend_user ) {
			$feed_url = rtrim( $friend_user->user_url, '/' ) . '/feed/';

			$token = get_user_meta( $friend_user->ID, 'friends_token', true );
			if ( $token ) {
				$feed_url .= '?friend=' . $token;
			}
			if ( $debug ) {
				echo nl2br( "Refreshing <a href=\"{$feed_url}\">{$friend_user->user_login}</span>\n" );
			}
			$feed = fetch_feed( $feed_url );
			if ( is_wp_error( $feed ) ) {
				continue;
			}

			$permalinks = array();
			$existing_posts = new WP_Query( array(
				'post_type' => 'friend_post',
				'post_author' => $user->ID,
			));

			if ( $existing_posts->have_posts() ) {
				while ( $existing_posts->have_posts() ) {
					$existing_posts->the_post();
					$permalink = get_the_guid();
					$permalinks[ $permalink ] = $existing_posts->post->ID;
				}
				wp_reset_postdata();
			}

			foreach ( $feed->get_items() as $item ) {
				$permalink = $item->get_permalink();
				$gravatar = isset( $item->data['child']['https://gravatar.com/rss/1.0/modules/gravatar']['url'][0]['data'] ) ? $item->data['child']['https://gravatar.com/rss/1.0/modules/gravatar']['url'][0]['data'] : false;

				$comment_count = isset( $item->data['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'] ) ? $item->data['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'] : 0;

				$author = $item->get_author();
				if ( isset( $permalinks[ $permalink ] ) ) {
					$post_id = $permalinks[ $permalink ];
					wp_update_post( array(
						'ID'                => $post_id,
						'post_title'        => $item->get_title(),
						'post_content'      => $item->get_content(),
						'post_modified_gmt' => $item->get_updated_gmdate( 'Y-m-d H:i:s' ),
					) );
				} else {
					$post_id = wp_insert_post( array(
						'post_author'       => $friend_user->ID,
						'post_type'	        => 'friend_post',
						'post_title'        => $item->get_title(),
						'post_date_gmt'     => $item->get_gmdate( 'Y-m-d H:i:s' ),
						'post_content'      => $item->get_content(),
						'post_status'       => 'private',
						'post_modified_gmt' => $item->get_updated_gmdate( 'Y-m-d H:i:s' ),
						'guid'              => $permalink,
						'comment_count'     => $comment_count,
					) );
					if ( is_wp_error( $post_id ) ) {
						continue;
					}
				}
				update_post_meta( $post_id, 'gravatar', $gravatar );
				update_post_meta( $post_id, 'author', $author->name );
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'comment_count' => $comment_count ), array( 'ID' => $post_id ) );
			}
		}
	}

	public function verify_installation( $request ) {
		return array(
			'version' => self::VERSION,
		);
	}

	public function friend_request_accepted( $request ) {
		$token = $request->get_param( 'token' );
		$user_id = get_option( 'friends_request_token_' . $token );
		$user = false;
		if ( $user_id ) {
			$user = new WP_User( $user_id );
		}

		if ( ! $token || ! $user || is_wp_error( $user ) || ! $user->user_url ) {
			return new WP_Error(
				'friends_invalid_parameters',
				'Not all necessary parameters were given.',
				array(
					'status' => 403,
				)
			);
		}

		$user_login = $this->get_user_login_for_site_url( $user->user_url );
		if ( $user_login !== $user->user_login ) {
			return new WP_Error(
				'friends_offer_no_longer_valid',
				'The friendship offer is no longer valid.',
				array(
					'status' => 403,
				)
			);
		}

		$user->set_role( 'friend' );
		$token = get_user_meta( $user->ID, 'friends_token', true );

		return array(
			'friend' => $token,
		);
	}

	public function friend_request( $request ) {
		if ( ! $request->get_param( 'name' ) || ! $request->get_param( 'email' ) ) {
			if ( false ) return new WP_Error(
				'friends_invalid_parameters',
				'Not all necessary parameters were given.',
				array(
					'status' => 403,
				)
			);
		}
		$site_url = $request->get_param( 'site_url' );
		if ( ! is_string( $site_url ) || ! wp_http_validate_url( $site_url ) || $site_url === strtolower( site_url() ) ) {
			return new WP_Error(
				'friends_invalid_site',
				'An invalid site was given.',
				array(
					'status' => 403,
				)
			);
		}
		// TODO: rate limit
		$response = wp_safe_remote_get( $site_url . '/wp-json/' . self::REST_NAMESPACE . '/hello' );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'friends_unsupported_site',
				'An unsupported site was given.',
				array(
					'status' => 403,
				)
			);
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
		if ( $user->has_cap( 'friend' ) ) {
			// Already a friend, was it deleted?
			$token = get_user_meta( $user_id, 'friends_token', true );
			return array(
				'friend' => $token,
			);
		}

		if ( $user->has_cap( 'pending_friend_request' ) ) {
			// Friend request was deleted on the other side and then re-initated.
			$user->set_role( 'friend_request' );
		}

		$token = sha1( wp_generate_password( 50 ) );
		update_user_meta( $user_id, 'friends_friend_request_token', $token );

		return array(
			'friend_request_pending' => $token,
		);
	}

	private function get_user_login_for_site_url( $site_url ) {
		$host = parse_url( $site_url, PHP_URL_HOST );
		$path = parse_url( $site_url, PHP_URL_PATH );

		$user_login = trim( preg_replace( '#[^a-z0-9.-]+#', '_', strtolower( $host . '_' . $path ) ), '_' );
		return $user_login;
	}

	private function create_user( $site_url, $role, $name = null, $email = null ) {
		if ( ! in_array( $role, array( 'pending_friend_request', 'friend_request' ) ) ) {
			return new WP_Error( 'invalid_role', 'Invalid role for creation specified' );
		}

		$user_login = $this->get_user_login_for_site_url( $site_url );
		$user = get_user_by( 'login', $user_login );
		if ( $user && ! is_wp_error( $user ) ) {
			return $user->ID;
		}

		$userdata = array(
			'user_login' => $user_login,
			'nickname'   => $name,
			'user_email' => $email,
			'user_url'   => $site_url,
			'user_pass'  => wp_generate_password( 50 ),
			'role'       => $role,
		);

		return wp_insert_user( $userdata );
	}

	public function private_title_format() {
		return '%s';
	}

	public function feed_use_excerpt() {
		return false;
	}

	public function additional_feed_namespaces() {
		echo '	xmlns:gravatar="https://gravatar.com/rss/1.0/modules/gravatar"';
	}

	public function feed_gravatar() {
		global $post;
		echo '<gravatar:url>' . esc_html( get_avatar_url( $post->post_author ) ) . '</gravatar:url>';
	}

	function private_feed( $query ) {
		if ( ! $this->authenticated ) {
			return $query;
		}

		if ( ! $query->is_admin && $query->is_feed ) {
			$query->set( 'post_status', array( 'publish', 'private' ) );
			add_filter( 'private_title_format', array( $this, 'private_title_format' ) );
		}

		return $query;
	}

	public function template_override( $template ) {
		global $wp_query;

		if ( 'friends' === $wp_query->query['pagename'] ) {
			if ( current_user_can( 'edit_posts' ) ) {
				$this->retrieve_friend_posts();

				$friends = new WP_User_Query( array( 'role' => 'friend' ) );
				if ( empty( $friends->get_results() ) ) {
					return __DIR__ . '/templates/friends/no-friends.php';
				}

				if ( ! have_posts() ) {
					return __DIR__ . '/templates/friends/no-posts.php';
				}
				return __DIR__ . '/templates/friends/posts.php';
			}

			if ( $wp_query->is_404 ) {
				$wp_query->is_404 = false;
				if ( current_user_can( 'friend' ) ) {
					$user = wp_get_current_user();
					wp_redirect( $user->user_url . '/friends/' );
					exit;
				}

				return __DIR__ . '/templates/friends/logged-out.php';

			}
		}
		return $template;
	}

	public function friend_post_edit_link( $link, $post_id ) {
		global $post;

		if ( 'friend_post' === $post->post_type ) {
			$link = false;
		}
		return $link;
	}

	public function friend_post_link( $post_link, $post, $leavename, $sample ) {
		return get_the_guid( $post );
	}

	public function friend_posts_query( $query ) {
		if ( 'friends' === get_query_var( 'pagename' ) && current_user_can( 'edit_posts' ) ) {
			$query->set( 'pagename', null );
			$query->set( 'page', null );
			$query->is_page = false;
			$query->set( 'post_type', array( 'friend_post', 'post' ) );
			$query->set( 'post_status', array( 'published', 'private' ) );
			add_filter( 'private_title_format', array( $this, 'private_title_format' ) );
		}
		return $query;
	}

	public function feed_content( $content ) {
		return $content;
	}

	public function remote_login() {
		if ( ! isset( $_GET['friend_auth'] ) ) {
			return;
		}
		$user_id = get_option( 'friends_token_' . $_GET['friend_auth'] );
		if ( $user_id ) {
			settype( $user_id, 'int' );
			if ( get_user_meta( $user_id, 'friends_token', true ) === $_GET['friend_auth' ] ) {
				wp_set_auth_cookie( $user_id );
				wp_redirect( str_replace( array( '?friend_auth=' . $_GET['friend_auth'], '&friend_auth=' . $_GET['friend_auth'] ), '', $_SERVER['REQUEST_URI'] ) );
				exit;
			}
		}
	}

	public function authenticate( $incoming_user_id ) {
		// TODO check if this should be improved with calculating atuh with a shared secret.
		if ( isset( $_GET['friend'] ) ) {
			$user_id = get_option( 'friends_token_' . $_GET['friend'] );
			if ( $user_id ) {
				settype( $user_id, 'int' );
				if ( get_user_meta( $user_id, 'friends_token', true ) === $_GET['friend' ] ) {
					$this->authenticated = $user_id;
					return $user_id;
				}
			}
		}

		return $incoming_user_id;
	}

	public function notify_friend_request_accepted( $user_id, $new_role, $old_roles ) {
		if ( $new_role !== 'friend' ) {
			return;
		}

		$token = get_user_meta( $user_id, 'friends_friend_request_token', true );
		if ( ! $token ) {
			return;
		}

		$user = new WP_User( $user_id );
		$response = wp_safe_remote_post( $user->user_url . '/wp-json/' . self::REST_NAMESPACE . '/friend-request-accepted', array(
			'body' => array( 'token' => $token ),
		) );

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( isset( $json->friend ) ) {
			$this->make_friend( $user_id, $json->friend );
		} else {
			$user->set_role( 'pending_friend_request' );
			if ( isset( $json->friend_request_pending ) ) {
				update_option( 'friends_request_token_' . $json->friend_request_pending, $user_id );
			}
		}
	}

	private function make_friend( $user_id, $token ) {
		$user = new WP_User( $user_id );
		if ( ! $user || is_wp_error( $user ) ) {
			return $user;
		}

		$user->set_role( 'friend' );
		update_user_meta( $user_id, 'friends_token', $token );
		update_option( 'friends_token_' . $token, $user_id );

		$this->retrieve_friend_posts();

		return true;
	}

	public function delete_friend_token( $user_id ) {
		$current_secret = get_user_meta( $user_id, 'friends_token', true );
		if ( $current_secret ) {
			delete_option( 'friends_token_' . $current_secret );
		}

		return $current_secret;
	}

	public function update_friend_token( $user_id, $new_role, $old_roles ) {
		if ( $new_role === 'friend' && in_array( $new_role, $old_roles ) ) {
			return;
		}
		$current_secret = $this->delete_friend_token( $user_id );

		if ( $new_role !== 'friend' || in_array( $new_role, $old_roles ) ) {
			return;
		}

		$secret = sha1( wp_generate_password( 50 ) );
		if ( update_user_meta( $user_id, 'friends_token', $secret, $current_secret ) ) {
			update_option( 'friends_token_' . $secret, $user_id );
		}
	}

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
		$friend_request->add_cap( 'level_0' );
	}

	public static function activate_plugin() {
		self::setup_roles();
	}

	public static function deactivate_plugin() {
		// TODO determine if we really want to delete all authentications.
		return;

		remove_role( 'friend' );
		remove_role( 'friend_request' );
		remove_role( 'pending_friend_request' );

		foreach ( wp_load_alloptions() as $name => $value ) {
			if ( 'friends_' === substr( $name, 0, 8 ) ) {
				delete_option( $name );
			}
		}
	}
}

add_action( 'plugins_loaded', array( 'Friends', 'init' ) );
register_activation_hook( __FILE__, array( 'Friends', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( 'Friends', 'deactivate_plugin' ) );
