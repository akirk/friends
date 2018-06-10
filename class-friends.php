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
	const VERSION           = '0.7';
	const FRIEND_POST_CACHE = 'friend_post_cache';
	const PLUGIN_URL        = 'https://wordpress.org/plugins/friends/';

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
		$this->rest           = new Friends_REST( $this );
		$this->third_parties  = new Friends_3rd_Parties( $this );

		$this->register_hooks();
		load_plugin_textdomain( 'friends', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_filter( 'init', array( $this, 'register_custom_post_types' ) );
		add_filter( 'friends_template_path', array( $this, 'friends_template_path' ) );
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
	 * Create the Friend user roles
	 */
	private static function setup_roles() {
		$friend = get_role( 'friend' );
		if ( ! $friend ) {
			$friend = add_role( 'friend', __( 'Friend', 'friends' ) );
		}
		$friend->add_cap( 'read_private_posts' );
		$friend->add_cap( 'read' );
		$friend->add_cap( 'friend' );
		$friend->add_cap( 'level_0' );

		$friend_request = get_role( 'friend_request' );
		if ( ! $friend_request ) {
			$friend_request = add_role( 'friend_request', __( 'Friend Request', 'friends' ) );
		}
		$friend_request->add_cap( 'friend_request' );
		$friend_request->add_cap( 'level_0' );

		$pending_friend_request = get_role( 'pending_friend_request' );
		if ( ! $pending_friend_request ) {
			$pending_friend_request = add_role( 'pending_friend_request', __( 'Pending Friend Request', 'friends' ) );
		}
		$pending_friend_request->add_cap( 'pending_friend_request' );
		$pending_friend_request->add_cap( 'level_0' );

		$subscription = get_role( 'subscription' );
		if ( ! $subscription ) {
			$subscription = add_role( 'subscription', __( 'Subscription', 'friends' ) );
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
	 * Add the default path to the template file.
	 *
	 * @param string $template_file The relative file path of the template to load.
	 */
	public static function friends_template_path( $template_file ) {
		return __DIR__ . '/templates/' . $template_file;
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
				'post_type'   => self::FRIEND_POST_CACHE,
				'post_status' => array( 'publish', 'private' ),
			)
		);

		while ( $friend_posts->have_posts() ) {
			$post = $friend_posts->next_post();
			wp_delete_post( $post->ID, true );
		}
	}
}
