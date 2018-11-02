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
	const VERSION           = '0.13';
	const FRIEND_POST_CACHE = 'friend_post_cache';
	const PLUGIN_URL        = 'https://wordpress.org/plugins/friends/';
	const REQUIRED_ROLE     = 'administrator';

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		static::get_instance();
	}

	/**
	 * A reference to the Friends_Admin object.
	 *
	 * @var Friends_Admin
	 */
	public $admin;

	/**
	 * A reference to the Friends_Access_Control object.
	 *
	 * @var Friends_Access_Control
	 */
	public $access_control;

	/**
	 * A reference to the Friends_Feed object.
	 *
	 * @var Friends_Feed
	 */
	public $feed;

	/**
	 * A reference to the Friends_Notifications object.
	 *
	 * @var Friends_Notifications
	 */
	public $notifications;

	/**
	 * A reference to the Friends_Page object.
	 *
	 * @var Friends_Page
	 */
	public $page;

	/**
	 * A reference to the Friends_REST object.
	 *
	 * @var Friends_REST
	 */
	public $rest;

	/**
	 * Get the class singleton
	 *
	 * @return Friends A class instance.
	 */
	public static function get_instance() {
		static $instance;
		if ( ! isset( $instance ) ) {
			$self     = get_called_class();
			$instance = new $self();
		}
		return $instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->access_control = new Friends_Access_Control( $this );
		$this->admin          = new Friends_Admin( $this );
		$this->feed           = new Friends_Feed( $this );
		$this->notifications  = new Friends_Notifications( $this );
		$this->page           = new Friends_Page( $this );
		$this->recommendation = new Friends_Recommendation( $this );
		$this->reactions      = new Friends_Reactions( $this );
		$this->rest           = new Friends_REST( $this );

		new Friends_3rd_Parties( $this );
		new Friends_Shortcodes( $this );
		new Friends_Gutenberg( $this );

		$this->register_hooks();
		load_plugin_textdomain( 'friends', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_filter( 'init', array( $this, 'register_custom_post_types' ) );
		add_filter( 'friends_template_path', array( $this, 'friends_template_path' ) );
		add_filter( 'get_avatar_data', array( $this, 'get_avatar_data' ), 10, 2 );
		add_filter( 'wp_head', array( $this, 'html_link_rel_friends_base_url' ) );
	}

	/**
	 * Registers the custom post types
	 */
	public function register_custom_post_types() {
		$labels = array(
			'name'               => _x( 'Friend Posts', 'taxonomy plural name', 'friends' ),
			'singular_name'      => _x( 'Friend Post', 'taxonomy singular name', 'friends' ),
			'add_new'            => _x( 'Add New', 'post' ),
			'add_new_item'       => __( 'Add New Friend Post', 'friends' ),
			'edit_item'          => __( 'Edit Friend Post', 'friends' ),
			'new_item'           => __( 'New Friend Post', 'friends' ),
			'all_items'          => __( 'All Friend Posts', 'friends' ),
			'view_item'          => __( 'View Friend Post', 'friends' ),
			'search_items'       => __( 'Search Friend Posts', 'friends' ),
			'not_found'          => __( 'No Friend Posts found', 'friends' ),
			'not_found_in_trash' => __( 'No Friend Posts found in the Trash', 'friends' ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Cached Friend Posts' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( "A cached friend's post", 'friends' ),
			'publicly_queryable'  => $this->access_control->private_rss_is_authenticated(),
			'show_ui'             => apply_filters( 'friends_show_cached_posts', false ),
			'show_in_menu'        => apply_filters( 'friends_show_cached_posts', false ),
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'exclude_from_search' => apply_filters( 'friends_show_cached_posts', false ),
			'public'              => false,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
			'has_archive'         => true,
		);
		register_post_type( self::FRIEND_POST_CACHE, $args );
	}

	/**
	 * Create the Friend user roles
	 */
	private static function setup_roles() {
		$friend = get_role( 'friend' );
		if ( ! $friend ) {
			_x( 'Friend', 'User role', 'friends' );
			$friend = add_role( 'friend', 'Friend' );
		}
		$friend->add_cap( 'read_private_posts' );
		$friend->add_cap( 'read' );
		$friend->add_cap( 'friend' );
		$friend->add_cap( 'level_0' );

		$restricted_friend = get_role( 'restricted_friend' );
		if ( ! $restricted_friend ) {
			_x( 'Restricted Friend', 'User role', 'friends' );
			$restricted_friend = add_role( 'restricted_friend', 'Restricted Friend' );
		}
		$restricted_friend->add_cap( 'read' );
		$restricted_friend->add_cap( 'friend' );
		$restricted_friend->add_cap( 'restricted_friend' );
		$restricted_friend->add_cap( 'level_0' );

		$friend_request = get_role( 'friend_request' );
		if ( ! $friend_request ) {
			_x( 'Friend Request', 'User role', 'friends' );
			$friend_request = add_role( 'friend_request', 'Friend Request' );
		}
		$friend_request->add_cap( 'friend_request' );
		$friend_request->add_cap( 'level_0' );

		$pending_friend_request = get_role( 'pending_friend_request' );
		if ( ! $pending_friend_request ) {
			_x( 'Pending Friend Request', 'User role', 'friends' );
			$pending_friend_request = add_role( 'pending_friend_request', 'Pending Friend Request' );
		}
		$pending_friend_request->add_cap( 'pending_friend_request' );
		$pending_friend_request->add_cap( 'level_0' );

		$subscription = get_role( 'subscription' );
		if ( ! $subscription ) {
			_x( 'Subscription', 'User role', 'friends' );
			$subscription = add_role( 'subscription', 'Subscription' );
		}
		$subscription->add_cap( 'subscription' );
		$subscription->add_cap( 'level_0' );
	}

	/**
	 * Gets all friends.
	 */
	public static function all_friends() {
		static $all_friends;
		if ( ! isset( $all_friends ) ) {
			$all_friends = new WP_User_Query( array( 'role__in' => array( 'friend', 'restricted_friend' ) ) );
		}
		return $all_friends;
	}

	/**
	 * Gets all friend requests.
	 */
	public static function all_friend_requests() {
		static $all_friend_requests;
		if ( ! isset( $all_friend_requests ) ) {
			$all_friend_requests = new WP_User_Query( array( 'role' => 'friend_request' ) );
		}
		return $all_friend_requests;
	}
	/**
	 * Gets all subscriptions.
	 */
	public static function all_subscriptions() {
		static $all_subscriptions;
		if ( ! isset( $all_subscriptions ) ) {
			$all_subscriptions = new WP_User_Query( array( 'role' => 'subscription' ) );
		}
		return $all_subscriptions;
	}

	/**
	 * Gets all admin users.
	 */
	public static function all_admin_users() {
		static $all_admin_users;
		if ( ! isset( $all_admin_users ) ) {
			$all_admin_users = new WP_User_Query( array( 'role' => self::REQUIRED_ROLE ) );
		}
		return $all_admin_users;
	}

	/**
	 * Creates a page /friends/ to enable customization via shortcodes.
	 */
	public static function create_friends_page() {
		$query = new WP_Query( array( 'name' => 'friends' ) );
		if ( $query->have_posts() ) {
			return;
		}

		$content  = '[only-friends]';
		$content .= __( 'Hi Friend!', 'friends' );
		$content .= PHP_EOL;
		$content .= __( 'Do you know any of my friends? Maybe you want to become friends with them as well?', 'friends' );
		$content .= PHP_EOL;
		$content .= '[friends-list include-links=true]';
		$content .= '[/only-friends]';

		$content .= '[not-friends]';
		$content .= __( 'I have connected with my friends using <strong>WordPress</strong> and the <strong>Friends plugin</strong>. This means I can share private posts with just my friends while keeping my data under control.', 'friends' );
		$content .= PHP_EOL;
		// translators: %1$s and %2$s are URLs.
		$content .= sprintf( __( 'If you also have a WordPress site with the friends plugin, you can send me a friend request. If not, follow me and get your own <a href="%1$s">WordPress</a> now and install the <a href="%2$s">Friends plugin</a>!', 'friends' ), 'https://wordpress.org/', self::PLUGIN_URL );
		$content .= PHP_EOL;
		$content .= '[/not-friends]';

		$post_data = array(
			'post_title'   => __( 'Welcome my Friends Page', 'friends' ),
			'post_content' => $content,
			'post_type'    => 'page',
			'post_name'    => 'friends',
			'post_status'  => 'publish',
		);
		$post_id   = wp_insert_post( $post_data );
	}

	/**
	 * Actions to take upon plugin activation.
	 */
	public static function activate_plugin() {
		self::setup_roles();
		self::create_friends_page();

		if ( false === get_option( 'friends_main_user_id' ) ) {
			update_option( 'friends_main_user_id', get_current_user_id() );
		}

		if ( false === get_option( 'friends_private_rss_key' ) ) {
			update_option( 'friends_private_rss_key', sha1( wp_generate_password( 256 ) ) );
		}

		if ( false === get_option( 'friends_default_friend_role' ) ) {
			update_option( 'friends_default_friend_role', 'friend' );
		}

		if ( ! wp_next_scheduled( 'cron_friends_refresh_feeds' ) ) {
			wp_schedule_event( time(), 'hourly', 'cron_friends_refresh_feeds' );
		}

		self::add_default_sidebars_widgets();
	}

	/**
	 * Add default widgets to the sidebars.
	 */
	public static function add_default_sidebars_widgets() {
		$sidebars_widgets = get_option( 'sidebars_widgets' );

		$id = count( $sidebars_widgets );

		foreach ( array(
			'friends-topbar'  => array(
				'friends-widget-new-private-post' => array(
					'title' => 'Friends',
				),
			),
			'friends-sidebar' => array(
				'friends-widget-refresh'        => array(),
				'friends-widget-friend-list'    => array(
					'title' => 'Friends',
				),
				'friends-widget-friend-request' => array(),
			),
		) as $sidebar_id => $default_widgets ) {
			if ( ! empty( $sidebars_widgets[ $sidebar_id ] ) ) {
				continue;
			}
			$sidebars_widgets[ $sidebar_id ] = array();
			foreach ( $default_widgets as $widget_id => $options ) {
				$id                               += 1;
				$sidebars_widgets[ $sidebar_id ][] = $widget_id . '-' . $id;
				update_option( 'widget_' . $widget_id, array( $id => $options ) );
			}
		}

		update_option( 'sidebars_widgets', $sidebars_widgets );
	}

	/**
	 * Actions to take upon plugin deactivation.
	 */
	public static function deactivate_plugin() {
		$timestamp = wp_next_scheduled( 'cron_friends_refresh_feeds' );
		wp_unschedule_event( $timestamp, 'cron_friends_refresh_feeds' );
	}

	/**
	 * Add the default path to the template file.
	 *
	 * @param string $template_file The relative file path of the template to load.
	 */
	public static function friends_template_path( $template_file ) {
		return __DIR__ . '/templates/' . $template_file;
	}

	/**
	 * Get the main friend user id.
	 *
	 * @return int The user_id.
	 */
	public static function get_main_friend_user_id() {
		$main_user_id = get_option( 'friends_main_user_id' );

		if ( false === $main_user_id ) {
			// Backfill the main user id.
			if ( get_current_user_id() ) {
				$main_user_id = get_current_user_id();
			} else {
				$users = self::all_admin_users();
				foreach ( $users->get_results() as $user ) {
					$main_user_id = $user->ID;
					break;
				}
			}
			update_option( 'friends_main_user_id', $main_user_id );
		}
		return $main_user_id;
	}

	/**
	 * Override Avatar URL for friends
	 *
	 * @param  array $args         The avatar details array.
	 * @param  mixed $id_or_email  Identifies what to retrieve the avatar for.
	 * @return array              The avatar (potentially modified) details array.
	 */
	public function get_avatar_data( $args, $id_or_email ) {
		if ( is_object( $id_or_email ) ) {
			if ( $id_or_email instanceof WP_User ) {
				$id_or_email = $id_or_email->ID;
			} elseif ( $id_or_email instanceof WP_Post ) {
				$id_or_email = $id_or_email->post_author;
			} elseif ( $id_or_email instanceof WP_Comment ) {
				$id_or_email = $id_or_email->user_id;
			}
		}

		if ( is_numeric( $id_or_email ) ) {
			$url = get_user_option( 'friends_gravatar', $id_or_email );
			if ( $url ) {
				$args['url']          = $url;
				$args['found_avatar'] = true;
			}
		}
		return $args;
	}

	/**
	 * Output the friends base URL as a link in the HTML head.
	 */
	public static function html_link_rel_friends_base_url() {
		echo self::get_html_link_rel_friends_base_url();
	}

	/**
	 * Generate a link tag with the friends base URL
	 */
	public static function get_html_link_rel_friends_base_url() {
		return '<link rel="friends-base-url" href="' . esc_attr( get_rest_url() . Friends_REST::PREFIX ) . '" />';
	}
	/**
	 * Delete all the data the plugin has stored in WordPress
	 */
	public static function uninstall_plugin() {
		$affected_users = new WP_User_Query( array( 'role__in' => array( 'friend', 'restricted_friend', 'friend_request', 'pending_friend_request', 'subscription' ) ) );
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

		delete_option( 'friends_main_user_id' );
		remove_role( 'friend' );
		remove_role( 'restricted_friend' );
		remove_role( 'friend_request' );
		remove_role( 'pending_friend_request' );
		remove_role( 'subscription' );

		$friend_posts = new WP_Query(
			array(
				'post_type'   => self::FRIEND_POST_CACHE,
				'post_status' => array( 'publish', 'private', 'trash' ),
			)
		);

		while ( $friend_posts->have_posts() ) {
			$post = $friend_posts->next_post();
			wp_delete_post( $post->ID, true );
		}
	}
}
