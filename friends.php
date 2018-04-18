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
		$this->register_custom_post_types();
	}

	private function add_hooks() {
		add_filter( 'determine_current_user',     array( $this, 'authenticate' ), 1 );
		add_filter( 'pre_get_posts',              array( $this, 'private_feed' ), 1 );
		add_filter( 'private_title_format',       array( $this, 'private_title_format' ) );
		add_filter( 'the_content_feed',           array( $this, 'feed_content' ), 90 );
		add_filter( 'the_excerpt_rss',            array( $this, 'feed_content' ), 90 );
		add_filter( 'comment_text_rss',           array( $this, 'feed_content' ), 90 );
		add_filter( 'rss_use_excerpt',            array( $this, 'feed_use_excerpt' ), 90 );
		add_filter( 'user_row_actions',           array( $this, 'user_row_actions' ), 10, 2 );
		add_filter( 'handle_bulk_actions-users',  array( $this, 'handle_friend_request_approval' ), 10, 3 );
		add_filter( 'bulk_actions-users',         array( $this, 'add_bulk_option_approve_friend_request' ), 10, 1 );
		add_filter( 'http_request_host_is_external', function( $in, $host ) {
			if ( $host === 'friend1.rebam.com' ) return true;
			if ( $host === 'friend2.rebam.com' ) return true;
			return $in;
		}, 10, 2 );

		add_action( 'admin_menu',                 array( $this, 'register_admin_menu' ), 10, 3 );
		add_action( 'set_user_role',              array( $this, 'generate_feed_secret' ), 10, 3 );
		add_action( 'set_user_role',              array( $this, 'notify_friend_request_approved' ), 10, 3 );
		add_action( 'rest_api_init',              array( $this, 'add_api_routes' ) );
	}

	private function register_custom_post_types() {
	}

	public function register_admin_menu() {
		add_menu_page( 'Friends', 'Friends', 'manage_options', 'friends-feed', null, '', 3.731 );
		add_submenu_page( 'friends-feed', 'Feed', 'Feed', 'manage_options', 'friends-feed', array( $this, 'render_admin_friends_feed' ) );
		add_submenu_page( 'friends-feed', 'Send Friend Request', 'Send Friend Request', 'manage_options', 'send-friend-request', array( $this, 'render_admin_send_friend_request' ) );
	}

	public function render_admin_friends_feed() {
		?><h1>Feed</h1><?php

		$friends = new WP_User_Query( array( 'role' => 'friend' ) );

		if ( empty( $friends->get_results() ) ) {
			return;
		}

		?><p><?php printf( _n( "You've got %d friend.", "You've got %d friends.", $friends->get_total() ), $friends->get_total() ); ?></p><?php

		foreach ( $friends->get_results() as $user ) {
			$token = get_user_meta( $user->ID, 'friends_token', true );
			if ( ! $token ) {
				continue;

			}
			$feed_url = $user->user_url . '/feed/?friend=' . get_user_meta( $user->ID, 'friends_token', true );
			$feed = fetch_feed( $feed_url );
			if ( is_wp_error( $feed ) ) {
				continue;
			}

			foreach ( $feed->get_items() as $item ) {
				$author = $item->get_author();

				?><div class="friend-post">
					<h2><a href="<?php echo esc_url( $item->get_link() ); ?>"><?php echo esc_html( $item->get_title() ); ?></a> by <?php echo esc_html( $author->get_name() ); ?></h2>

					<p><?php echo $item->get_description(); ?></p>
					</div><?php
				}
			}
	}
	public function render_admin_send_friend_request() {
		if ( ! empty( $_POST ) ) {
			$site = $_POST['site'];
			if ( is_string( $site ) && wp_http_validate_url( $site ) ) {
				$response = wp_remote_post( $site . '/wp-json/' . self::REST_NAMESPACE . '/friend-request', array(
					'body' => array( 'site' => site_url() ),
				) );
				if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
					$json = json_decode( wp_remote_retrieve_body( $response ) );
					var_dump( $json );
				} else {
					$json = json_decode( wp_remote_retrieve_body( $response ) );
					if ( $json->success ) {
						update_option( 'friends_request_token_' . $json->success, $site );
						// TODO nicer message
						echo 'Friendship requested ' . $json->success;
					}
				}
			}

		}
		?><h1>Send Friend Request</h1><form method="post">
			Site: <input type="url" name="site" value="" required />
			<button>Request Friend</button>
		</form><?php
	}

	public function user_row_actions( array $actions, WP_User $user ) {
		if ( ! $user->has_cap( 'friend_request') ) {
			return $actions;
		}

		$link = self_admin_url( wp_nonce_url( 'users.php?action=approve_friend_request&users[]=' . $user->ID ) );
		$actions['user_approve_friend_request'] = '<a href="' . $link . '">' . __( 'Approve Friend Request', 'friends' ) . '</a>';
		return $actions;
	}

	public function handle_friend_request_approval( $sendback, $action, $users ) {
		if ( $action !== 'approve_friend_request' ) {
			return false;
		}
		$approved = 0;
		foreach ( $users as $user_id ) {
			$user = new WP_User( $user_id );
			if ( ! is_wp_error( $user ) ) {
				if ( $user->set_role( 'friend' ) ) {
					$approved += 1;
				}
			}
		}

		$sendback = add_query_arg( 'approved', $approved, $sendback );
		wp_redirect( $sendback );
	}

	public function add_bulk_option_approve_friend_request( $actions ) {
		$actions['approve_friend_request'] = 'Approve Friend Request';
		return $actions;
	}

	public function add_api_routes() {
		register_rest_route( self::REST_NAMESPACE, 'friend-request', array(
			'methods' => 'POST',
			'callback' => array( $this, 'friend_request' ),
		) );
		register_rest_route( self::REST_NAMESPACE, 'friend-request-approved', array(
			'methods' => 'POST',
			'callback' => array( $this, 'friend_request_approved' ),
		) );
		register_rest_route( self::REST_NAMESPACE, 'hello', array(
			'methods' => 'GET',
			'callback' => array( $this, 'verify_installation' ),
		) );

		return;
	}

	public function verify_installation( $request ) {
		return array(
			'version' => self::VERSION,
		);
	}

	public function friend_request_approved( $request ) {
		$token = $request->get_param( 'token' );
		$site = get_option( 'friends_request_token_' . $token );
		if ( ! $token || ! $site ) {
			return new WP_Error(
				'friends_invalid_parameters',
				'Not all necessary parameters were given.',
				array(
					'status' => 403,
				)
			);
		}

		$user_id = $this->create_user( $site, 'friend', $request->get_param( 'name' ), $request->get_param( 'email' ) );

		$token = get_user_meta( $user_id, 'friends_token', true );

		return array(
			'success' => $token,
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
		$site = $request->get_param( 'site' );
		if ( ! is_string( $site ) || ! wp_http_validate_url( $site ) || $site === strtolower( site_url() ) ) {
			return new WP_Error(
				'friends_invalid_site',
				'An invalid site was given.',
				array(
					'status' => 403,
				)
			);
		}
		// TODO: rate limit
		$response = wp_safe_remote_get( $site . '/wp-json/' . self::REST_NAMESPACE . '/hello' );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'friends_unsupported_site',
				'An unsupported site was given.',
				array(
					'status' => 403,
				)
			);
		}

		$user_id = $this->create_user( $site, 'friend_request', $request->get_param( 'name' ), $request->get_param( 'email' ) );
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error(
				'friends_friend_request_failed',
				'Could not respond to the friend request.',
				array(
					'status' => 403,
				)
			);
		}

		$token = sha1( wp_generate_password( 50 ) );
		update_user_meta( $user_id, 'friends_friend_request_token', $token );

		return array(
			'success' => $token,
		);
	}

	public function create_user( $site, $role, $name = null, $email = null ) {
		if ( ! in_array( $role, array( 'friend', 'friend_request' ) ) ) {
			return new WP_Error( 'invalid_role', 'Invalid role for creation specified' );
		}

		$host = parse_url( $site, PHP_URL_HOST );
		$path = parse_url( $site, PHP_URL_PATH );

		$user_login = trim( preg_replace( '#[^a-z0-9]+#', '_', strtolower( $host . '_' . $path ) ), '_' );
		$password = wp_generate_password( 50 );
		$userdata = array(
		    'user_login' => $user_login,
		    'nickname'   => $name,
		    'user_email' => $email,
		    'user_url'   => $site,
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

	function private_feed( $query ) {
		if ( ! $this->authenticated ) {
			return $query;
		}

	    if ( ! $query->is_admin && $query->is_feed ) {
	        $query->set( 'post_status', array( 'publish', 'private' ) );
	    }

	    return $query;
	}

	public function feed_content( $content ) {
		return $content;
	}

	public function authenticate( $incoming_user_id ) {
		if ( ! isset( $_GET['friend'] ) ) {
			return $incoming_user_id;
		}

		$user_id = get_option( 'friends_token_' . $_GET['friend'] );
		if ( $user_id ) {
			settype( $user_id, 'int' );
			if ( get_user_meta( $user_id, 'friends_token', true ) === $_GET['friend' ] ) {
				$this->authenticated = $user_id;
				return $user_id;
			}
		}

		return $incoming_user_id;
	}

	public function notify_friend_request_approved( $user_id, $new_role, $old_roles ) {
		if ( $new_role !== 'friend' ) {
			return;
		}

		$token = get_user_meta( $user_id, 'friends_friend_request_token', true );
		if ( ! $token ) {
			return;
		}

		$user = new WP_User( $user_id );
		$response = wp_safe_remote_post( $user->user_url . '/wp-json/' . self::REST_NAMESPACE . '/friend-request-approved', array(
			'body' => array( 'token' => $token ),
		) );

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$json = json_decode( wp_remote_retrieve_body( $response ) );
			if ( $json->success ) {
				update_user_meta( $user_id, 'friends_token', $json->success );
				update_option( 'friends_token_' . $json->success, $user_id );
			}
		}
	}

	public function generate_feed_secret( $user_id, $new_role, $old_roles ) {

		$current_secret = get_user_meta( $user_id, 'friends_token', true );
		if ( $current_secret ) {
			delete_option( 'friends_token_' . $secret );
		}

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
	}

	public static function activate_plugin() {
		self::setup_roles();
	}

	public static function deactivate_plugin() {
		// TODO determine if we really want to delete all users.
		return;

		remove_role( 'friend' );
		remove_role( 'friend_request' );

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
