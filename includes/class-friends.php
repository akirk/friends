<?php
/**
 * Friends
 *
 * A plugin to connect WordPresses and communicate privately with your friends.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Friends Plugin.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends {
	const VERSION       = FRIENDS_VERSION;
	const CPT           = 'friend_post_cache';
	const TAG_TAXONOMY  = 'friend_tag';
	const FEED_URL      = 'friends-feed-url';
	const PLUGIN_URL    = 'https://wordpress.org/plugins/friends/';
	const REQUIRED_ROLE = 'edit_private_posts';

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		static::get_instance();
	}

	/**
	 * A reference to the Admin object.
	 *
	 * @var Admin
	 */
	public $admin;

	/**
	 * A reference to the Access_Control object.
	 *
	 * @var Access_Control
	 */
	public $access_control;

	/**
	 * A reference to the Feed object.
	 *
	 * @var Feed
	 */
	public $feed;

	/**
	 * A reference to the Messages object.
	 *
	 * @var Messages
	 */
	public $messages;

	/**
	 * A reference to the Notifications object.
	 *
	 * @var Notifications
	 */
	public $notifications;

	/**
	 * A reference to the Frontend object.
	 *
	 * @var Frontend
	 */
	public $frontend;

	/**
	 * A reference to the REST object.
	 *
	 * @var REST
	 */
	public $rest;

	/**
	 * A reference to the Reactions object.
	 *
	 * @var Reactions
	 */
	public $reactions;

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
	 * Get the Template_Loader singleton
	 *
	 * @return Template_Loader A class instance.
	 */
	public static function template_loader() {
		static $template_loader;
		if ( ! isset( $template_loader ) ) {
			$template_loader = new Template_Loader();
		}
		return $template_loader;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->access_control = new Access_Control( $this );
		$this->admin          = new Admin( $this );
		$this->feed           = new Feed( $this );
		$this->messages       = new Messages( $this );
		$this->notifications  = new Notifications( $this );
		$this->frontend       = new Frontend( $this );
		$this->reactions      = new Reactions( $this );
		$this->rest           = new REST( $this );

		new Third_Parties( $this );
		new Blocks( $this );
		new Logging( $this );
		new Shortcodes( $this );
		new Automatic_Status( $this );
		$this->register_hooks();
		load_plugin_textdomain( 'friends', false, FRIENDS_PLUGIN_FILE . '/languages/' );

		/**
		 * Friends has loaded.
		 *
		 * @param Friends $friends The friends object.
		 *
		 * You can now assume that all the Friends hooks and objects are available.
		 *
		 * Example:
		 * ```php
		 * add_action( 'friends_loaded', function( Friends $friends ) {
		 *      add_action( 'init', 'initialize_my_plugin' );
		 * } );
		 * ```
		 */
		do_action( 'friends_loaded', $this );

		/**
		 * Time to register your parser.
		 *
		 * @param Feed $feed The feed object.
		 *
		 * You'll receive the feed object on which you need to call `register_parser()`.
		 *
		 * Example:
		 * ```php
		 * add_action( 'friends_load_parsers', function( Friends\Feed $friends_feed ) {
		 *     $friends_feed->register_parser( 'simplepie', new Feed_Parser_SimplePie( $friends_feed ) );
		 * } );
		 * ```
		 */
		do_action( 'friends_load_parsers', $this->feed );

		/**
		 * Time to register your theme.
		 *
		 * @param Frontend $frontend The frontend object.
		 *
		 * You'll receive the frontend object on which you need to call `register_theme()`.
		 *
		 * Example:
		 * ```php
		 * add_action( 'friends_load_themes', function( Friends\Frontend $friends_frontend ) {
		 *     $friends_frontend->register_theme( 'Mastodon', 'mastodon' );
		 * } );
		 * ```
		 */
		do_action( 'friends_load_themes', $this->frontend );
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'init', array( $this, 'register_friend_tag_taxonomy' ) );
		add_action( 'init', array( 'Friends\Subscription', 'register_taxonomy' ) );

		add_action( 'init', array( 'Friends\User_Feed', 'register_taxonomy' ) );
		add_action( 'wp', array( $this, 'allow_browser_extension_request' ) );
		add_filter( 'get_avatar_data', array( $this, 'get_avatar_data' ), 10, 2 );

		add_action( 'template_redirect', array( $this, 'http_header' ), 5 );
		add_filter( 'wp_head', array( $this, 'html_rel_links' ) );
		add_filter( 'login_head', array( $this, 'html_rel_links' ) );

		add_filter( 'after_setup_theme', array( $this, 'enable_post_formats' ) );
		add_filter( 'cron_schedules', array( $this, 'add_fifteen_minutes_interval' ) ); // phpcs:ignore WordPressVIPMinimum.Performance.IntervalInSeconds.IntervalInSeconds
		add_action( 'cron_friends_delete_old_posts', array( $this, 'cron_friends_delete_outdated_posts' ) );
		add_action( 'friends_migrate_post_tags_batch', array( $this, 'cron_migrate_post_tags_batch' ) );
		add_action( 'template_redirect', array( $this, 'disable_friends_author_page' ) );

		// Site Health integration.
		add_filter( 'site_status_tests', array( $this, 'add_site_health_tests' ) );
		add_action( 'wp_ajax_friends_restart_migration', array( $this, 'ajax_restart_migration' ) );
		add_action( 'wp_ajax_friends_cleanup_post_tags', array( $this, 'ajax_cleanup_post_tags' ) );
		add_action( 'wp_ajax_friends_recalculate_post_tag_counts', array( $this, 'ajax_recalculate_post_tag_counts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_site_health_scripts' ) );

		add_action( 'comment_form_defaults', array( $this, 'comment_form_defaults' ) );
		add_filter( 'friends_frontend_post_types', array( $this, 'add_frontend_post_types' ) );

		add_filter( 'request', array( $this, 'limit_post_format_request' ), 20 );
		add_filter( 'my_apps_plugins', array( $this, 'register_my_apps' ) );
		User::register_wrapper_hooks();
	}

	/**
	 * Register the friend tag taxonomy
	 */
	public function register_friend_tag_taxonomy() {
		$labels = array(
			'name'          => __( 'Friend Tags', 'friends' ),
			'singular_name' => __( 'Friend Tag', 'friends' ),
			'search_items'  => __( 'Search Friend Tags', 'friends' ),
			'all_items'     => __( 'All Friend Tags', 'friends' ),
			'edit_item'     => __( 'Edit Friend Tag', 'friends' ),
			'update_item'   => __( 'Update Friend Tag', 'friends' ),
			'add_new_item'  => __( 'Add New Friend Tag', 'friends' ),
			'new_item_name' => __( 'New Friend Tag Name', 'friends' ),
			'menu_name'     => __( 'Friend Tags', 'friends' ),
		);

		$args = array(
			'hierarchical'       => false,
			'labels'             => $labels,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'friend-tag' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_in_rest'       => true,
		);

		register_taxonomy( self::TAG_TAXONOMY, self::CPT, $args );
	}

	/**
	 * Registers the custom post type
	 */
	public function register_custom_post_type() {
		$labels = array(
			'name'               => __( 'Friend Posts', 'friends' ),
			'singular_name'      => __( 'Friend Post', 'friends' ),
			'add_new'            => _x( 'Add New', 'cached friend post', 'friends' ),
			'add_new_item'       => __( 'Add New Friend Post', 'friends' ),
			'edit_item'          => __( 'Edit Friend Post', 'friends' ),
			'new_item'           => __( 'New Friend Post', 'friends' ),
			'all_items'          => __( 'All Friend Posts', 'friends' ),
			'view_item'          => __( 'View Friend Post', 'friends' ),
			'search_items'       => __( 'Search Friend Posts', 'friends' ),
			'not_found'          => __( 'No Friend Posts found', 'friends' ),
			'not_found_in_trash' => __( 'No Friend Posts found in the Trash', 'friends' ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Cached Friend Posts', 'friends' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => "A cached friend's post",
			'publicly_queryable'  => self::authenticated_for_posts(),
			'show_ui'             => true,
			'show_in_menu'        => apply_filters( 'friends_show_cached_posts', false ),
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => is_user_logged_in(),
			'exclude_from_search' => true,
			'public'              => false,
			'delete_with_user'    => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'title', 'editor', 'author', 'revisions', 'thumbnail', 'excerpt', 'comments', 'post-formats' ),
			'taxonomies'          => array( self::TAG_TAXONOMY, 'post_format', 'friend-reaction-' . get_current_user_id() ),
			'has_archive'         => true,
			'rewrite'             => false,
		);

		register_post_type( self::CPT, $args );

		register_post_meta(
			self::CPT,
			Feed::COMMENTS_FEED_META,
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);

		register_post_meta(
			self::CPT,
			'remote_post_id',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);

		register_post_meta(
			self::CPT,
			'parser',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);

		register_post_meta(
			self::CPT,
			'feed_url',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);

		register_post_meta(
			self::CPT,
			'reblogged',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);

		register_post_meta(
			self::CPT,
			'reblogged_by',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'integer',
			)
		);
	}

	public function allow_browser_extension_request() {
		if ( get_http_origin() && is_home() ) {
			$scheme = wp_parse_url( get_http_origin(), PHP_URL_SCHEME );
			if ( 'moz-extension' === $scheme ) {
				header( 'access-control-allow-origin: ' . get_http_origin() );
			}
		}
	}

	public static function get_role_capabilities( $role ) {
		$capabilities = array();

		$capabilities['friend_request'] = array(
			'friend_request' => true,
		);

		$capabilities['pending_friend_request'] = array(
			'pending_friend_request' => true,
		);

		$capabilities['subscription'] = array(
			'subscription' => true,
		);

		$capabilities['acquaintance'] = array(
			'read'   => true,
			'friend' => true,
		);

		// Friend is an Acquaintance who can read private posts.
		$capabilities['friend'] = $capabilities['acquaintance'];
		$capabilities['friend']['read_private_posts'] = true;

		// All roles belonging to this plugin have the friends_plugin capability.
		foreach ( array_keys( $capabilities ) as $type ) {
			$capabilities[ $type ]['friends_plugin'] = true;
		}

		if ( ! isset( $capabilities[ $role ] ) ) {
			return array();
		}

		return $capabilities[ $role ];
	}

	/**
	 * Create the Friend user roles
	 */
	private static function setup_roles() {
		$default_roles = array(
			'friend'                 => _x( 'Friend', 'User role', 'friends' ),
			'acquaintance'           => _x( 'Acquaintance', 'User role', 'friends' ),
			'friend_request'         => _x( 'Friend Request', 'User role', 'friends' ),
			'pending_friend_request' => _x( 'Pending Friend Request', 'User role', 'friends' ),
			'subscription'           => _x( 'Subscription', 'User role', 'friends' ),
		);

		$roles = new \WP_Roles();

		foreach ( $default_roles as $type => $name ) {
			$role = false;
			foreach ( $roles->roles as $slug => $data ) {
				if ( isset( $data['capabilities'][ $type ] ) ) {
					$role = get_role( $slug );
					break;
				}
			}
			if ( ! $role ) {
				$role = add_role( $type, $name, self::get_role_capabilities( $type ) );
				continue;
			}

			// This might update missing capabilities.
			foreach ( array_keys( self::get_role_capabilities( $type ) ) as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Creates a page /friends/ to enable customization via.
	 */
	public static function create_friends_page() {
		$query = new \WP_Query(
			array(
				'name'      => 'friends',
				'post_type' => 'page',
			)
		);
		if ( $query->have_posts() ) {
			return;
		}
		$content  = '<!-- wp:paragraph {"className":"only-friends"} -->' . PHP_EOL . '<p class="only-friends">';
		$content .= __( 'Hi Friend!', 'friends' );
		$content .= '<br/><br/>';
		$content .= __( 'Do you know any of my friends? Maybe you want to become friends with them as well?', 'friends' );
		$content .= PHP_EOL . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL;

		$content .= '<!-- wp:friends/friends-list {"className":"only-friends","user_types":"friends"} /-->' . PHP_EOL;

		$content .= '<!-- wp:paragraph {"className":"not-friends"} -->' . PHP_EOL . '<p class="not-friends">';
		$content .= __( 'I have connected with my friends using <strong>WordPress</strong> and the <strong>Friends plugin</strong>. This means I can share private posts with just my friends while keeping my data under control.', 'friends' );
		$content .= PHP_EOL;
		// translators: %1$s and %2$s are URLs.
		$content .= sprintf( __( 'If you also have a WordPress site with the friends plugin, you can send me a friend request. If not, get your own <a href="%1$s">WordPress</a> now, install the <a href="%2$s">Friends plugin</a>, and follow me!', 'friends' ), 'https://wordpress.org/', self::PLUGIN_URL );
		$content .= PHP_EOL . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL;

		$content .= '<!-- wp:friends/follow-me {"className":"not-friends"} -->' . PHP_EOL . '<div class="wp-block-friends-follow-me not-friends">';
		$content .= '<form method="post"><!-- wp:paragraph -->' . PHP_EOL . '<p>';
		$content .= __( 'Enter your blog URL to join my network. <a href="https://wpfriends.at/follow-me">Learn more</a>', 'friends' );
		$content .= '</p>' . PHP_EOL;
		$content .= '<!-- /wp:paragraph --><div><input type="text" name="friends_friend_request_url" placeholder="https://example.com/"/> <button>';
		$content .= __( 'Follow this site', 'friends' );
		$content .= '</button></div></form></div>' . PHP_EOL;
		$content .= '</p>' . PHP_EOL . '<!-- /wp:friends/follow-me -->' . PHP_EOL;

		$post_data = array(
			'post_title'   => __( 'Friends', 'friends' ),
			'post_content' => $content,
			'post_type'    => 'page',
			'post_name'    => 'friends',
			'post_status'  => 'publish',
		);
		wp_insert_post( $post_data );
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
	public static function translate_user_role( $translations, $text, $context, $domain ) {
		$roles = array(
			'Friend',
			'Acquaintance',
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
	 * Gets the roles that the plugin uses.
	 *
	 * @return     array  The roles.
	 */
	public static function get_friends_plugin_roles() {
		return apply_filters( 'friends_plugin_roles', array( 'friend', 'pending_friend_request', 'friend_request', 'subscription' ) );
	}

	/**
	 * If a plugin version upgrade requires changes, they can be done here
	 *
	 * @param      \WP_Upgrader $upgrader_object  The WP_Upgrader instance.
	 * @param      array        $options          Array of bulk item update data.
	 */
	public static function upgrade_plugin_trigger( $upgrader_object, $options ) {
		$upgraded = false;
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] && isset( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin ) {
				if ( FRIENDS_PLUGIN_BASENAME === $plugin ) {
					return self::upgrade_plugin();
				}
			}
		}
	}

	public static function upgrade_plugin() {
		$previous_version = get_option( 'friends_plugin_version' );

		// Bail early if no migration is necessary.
		if ( version_compare( $previous_version, Friends::VERSION, '>=' ) ) {
			return;
		}

		// Load migration class for any upgrade.
		require_once __DIR__ . '/class-migration.php';

		if ( version_compare( $previous_version, '0.20.1', '<' ) ) {
			Migration::migrate_gravatar_to_user_icon_url();
		}

		if ( version_compare( $previous_version, '2.1.3', '<' ) ) {
			self::setup_roles();
		}

		if ( version_compare( $previous_version, '2.6.0', '<' ) ) {
			Migration::migrate_feed_options_to_user_options();
		}

		if ( version_compare( $previous_version, '2.8.7', '<' ) ) {
			Migration::enable_wp_friendships_if_used();
		}

		if ( version_compare( $previous_version, '2.9.4', '<' ) ) {
			Migration::migrate_external_user_and_cron();
		}

		if ( version_compare( $previous_version, '3.1.8', '<' ) ) {
			Migration::migrate_frontend_default_view_to_user_option();
		}

		if ( version_compare( $previous_version, '4.0.0', '<' ) ) {
			Migration::migrate_post_tags_to_friend_tags();
		}

		update_option( 'friends_plugin_version', Friends::VERSION );
	}

	/**
	 * Actions to take upon plugin activation.
	 *
	 * @param      bool $network_activate  Whether the plugin has been activated network-wide.
	 */
	public static function activate_plugin( $network_activate = null ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $network_activate ) {
				// Only Super Admins can use Network Activate.
				if ( ! is_super_admin() ) {
					return;
				}

				// Activate for each site.
				foreach ( get_sites() as $blog ) {
					switch_to_blog( $blog->blog_id );
					self::setup();
					restore_current_blog();
				}
			} elseif ( current_user_can( 'activate_plugins' ) ) {
				self::setup();
			}
			return;
		}

		self::setup();
	}

	/**
	 * Actions to take upon plugin activation.
	 *
	 * @param      int|WP_Site $blog_id  Blog ID.
	 */
	public static function activate_for_blog( $blog_id ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( $blog_id instanceof \WP_Site ) {
			$blog_id = (int) $blog_id->blog_id;
		}

		if ( is_plugin_active_for_network( FRIENDS_PLUGIN_BASENAME ) ) {
			switch_to_blog( $blog_id );
			self::setup();
			restore_current_blog();
		}
	}

	/**
	 * Actions to take upon plugin activation.
	 */
	private static function setup() {
		self::setup_roles();
		self::create_friends_page();

		self::upgrade_plugin(
			null,
			array(
				'action'  => 'update',
				'plugins' => array( FRIENDS_PLUGIN_BASENAME ),
				'type'    => 'plugin',
			)
		);

		if ( false === get_option( 'friends_main_user_id' ) ) {
			update_option( 'friends_main_user_id', get_current_user_id() );
		}

		if ( false === get_option( 'friends_private_rss_key' ) ) {
			update_option( 'friends_private_rss_key', wp_generate_password( 128, false ) );
		}

		if ( false === get_option( 'friends_default_friend_role' ) ) {
			update_option( 'friends_default_friend_role', 'friend' );
		}

		if ( ! wp_next_scheduled( 'cron_friends_refresh_feeds' ) ) {
			wp_schedule_event( time(), 'fifteen-minutes', 'cron_friends_refresh_feeds' );
		}

		if ( ! wp_next_scheduled( 'cron_friends_delete_old_posts' ) ) {
			wp_schedule_event( time(), 'hourly', 'cron_friends_delete_old_posts' );
		}

		self::add_default_sidebars_widgets();
		flush_rewrite_rules();
	}

	public function add_fifteen_minutes_interval( $schedules ) {
		$schedules['fifteen-minutes'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes', 'friends' ),
		);

		return $schedules;
	}

	/**
	 * Add default widgets to the sidebars.
	 */
	public static function add_default_sidebars_widgets() {
		$sidebars_widgets = get_option( 'sidebars_widgets' );

		if ( ! is_array( $sidebars_widgets ) ) {
			$sidebars_widgets = array();
		}
		$id = count( $sidebars_widgets );

		foreach ( array(
			'friends-topbar'  => array(
				'friends-widget-header'           => array(
					'title' => 'Friends',
				),
				'friends-widget-new-private-post' => array(),
			),
			'friends-sidebar' => array(
				'friends-widget-stats'          => array(),
				'friends-widget-refresh'        => array(),
				'friends-widget-post-formats'   => array(),
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
				++$id;
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
	 * Get the required capability for the menu entries.
	 */
	public static function required_menu_role() {
		return 'edit_private_posts';
	}

	/**
	 * Check if the user hast the required priviliges.
	 */
	public static function has_required_privileges() {
		return self::is_main_user() || current_user_can( 'manage_options' );
	}

	/**
	 * Check if the current user is the main user.
	 */
	public static function is_main_user() {
		return is_user_logged_in() && get_current_user_id() === self::get_main_friend_user_id();
	}

	/**
	 * Get the main friend user id.
	 *
	 * @return int The user_id.
	 */
	public static function get_main_friend_user_id() {
		$main_user_id = get_option( 'friends_main_user_id' );

		if (
			false === $main_user_id
			|| ( is_multisite() && ! is_user_member_of_blog( $main_user_id, get_current_blog_id() ) )
		) {
			$users = User_Query::all_admin_users();
			foreach ( $users->get_results() as $user ) {
				$main_user_id = $user->ID;
				break;
			}
			if ( $main_user_id ) {
				update_option( 'friends_main_user_id', $main_user_id );
			}
		}
		return intval( $main_user_id );
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
			if ( $id_or_email instanceof User ) {
				$id_or_email = $id_or_email->ID;
			} elseif ( $id_or_email instanceof \WP_Post ) {
				$id_or_email = $id_or_email->post_author;
			} elseif ( $id_or_email instanceof \WP_Comment ) {
				$id_or_email = $id_or_email->user_id;
			}
		}

		$user = false;
		if ( is_numeric( $id_or_email ) && $id_or_email > 0 ) {
			$user = get_user_by( 'ID', $id_or_email );
			if ( $user ) {
				$user = new User( $user );
			}
		} elseif ( is_string( $id_or_email ) ) {
			$user = User::get_by_username( $id_or_email );
		}

		if ( $user ) {
			if ( is_wp_error( $user ) ) {
				return $args;
			}

			$url = $user->get_avatar_url();
			if ( $url ) {
				$args['url']          = $url;
				$args['found_avatar'] = true;
			}
		}
		return $args;
	}

	/**
	 * Enables the post formats.
	 */
	public function enable_post_formats() {
		if ( get_option( 'friends_force_enable_post_formats' ) ) {
			add_theme_support( 'post-formats', get_post_format_slugs() );
		}
	}

	/**
	 * Determine whether we are on the /friends/ page or a subpage.
	 *
	 * @return boolean Whether we are on a friends page URL.
	 */
	public static function on_frontend() {
		global $wp_query;

		if ( ! isset( $wp_query ) || ! isset( $wp_query->query['pagename'] ) ) {
			// The request has not yet been parsed but we need to know, so this snippet is from wp->parse_request().
			list( $req_uri ) = explode( '?', remove_query_arg( '' ) );
			$home_path       = wp_parse_url( home_url(), PHP_URL_PATH );
			$home_path_regex = '';
			if ( is_string( $home_path ) && '' !== $home_path ) {
				$home_path       = trim( $home_path, '/' );
				$home_path_regex = sprintf( '|^%s|i', preg_quote( $home_path, '|' ) );
			}
			$req_uri = trim( $req_uri, '/' );

			if ( ! empty( $home_path_regex ) ) {
				$req_uri  = preg_replace( $home_path_regex, '', $req_uri );
				$req_uri  = trim( $req_uri, '/' );
			}
			$pagename = $req_uri;
		} else {
			$pagename = $wp_query->query['pagename'];
		}

		// A nonce is not needed here since this is to show the public page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['public'] ) ) {
			return false;
		}

		if ( ! current_user_can( self::REQUIRED_ROLE ) || ( is_multisite() && ! is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) && is_super_admin( get_current_user_id() ) ) ) {
			return false;
		}

		$pagename_parts = explode( '/', trim( $pagename, '/' ) );
		return count( $pagename_parts ) > 0 && 'friends' === $pagename_parts[0];
	}

	/**
	 * Check whether the request has been authenticated to display (private) posts.
	 *
	 * @return     bool  Whether the posts can be accessed.
	 */
	public static function authenticated_for_posts() {
		return Access_Control::private_rss_is_authenticated() || ( is_admin() && self::is_main_user() && apply_filters( 'friends_show_cached_posts', false ) );
	}

	/**
	 * Adds the post types to be displayed on the frontend.
	 *
	 * @param      string $post_types  The incoming post types.
	 *
	 * @return     array  The frontend post types.
	 */
	public static function add_frontend_post_types( $post_types ) {
		return array_merge( array( Friends::CPT ), $post_types );
	}

	/**
	 * Gets the post count by post status.
	 *
	 * @param      bool $force_fetching  Whether to force fetching.
	 *
	 * @return     object  The post count.
	 */
	public function get_post_count_by_post_status( $force_fetching = false ) {
		$cache_key = 'friends_post_count';

		$post_types = apply_filters( 'friends_frontend_post_types', array() );

		$counts = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $counts ) {
			return $counts;
		}

		$counts = get_transient( $cache_key );
		if ( false !== $counts ) {
			return $counts;
		} elseif ( ! $force_fetching ) {
			return (object) array(
				'trash' => '...',
			);
		}

		$counts = new \stdClass();

		foreach ( $post_types as $post_type ) {
			$count = wp_count_posts( $post_type );
			foreach ( (array) $count as $post_status => $c ) {
				if ( ! isset( $counts->$post_status ) ) {
					$counts->$post_status = 0;
				}
				$counts->$post_status += $c;
			}
		}

		set_transient( $cache_key, $counts, HOUR_IN_SECONDS );
		wp_cache_set( $cache_key, $counts, 'friends', HOUR_IN_SECONDS );

		return $counts;
	}

	/**
	 * Gets the post count by post format.
	 *
	 * @param      bool $force_fetching  Whether to force fetching.
	 *
	 * @return     array  The post count by post format.
	 */
	public function get_post_count_by_post_format( $force_fetching = false ) {
		$cache_key = 'post_count_by_post_format';

		$post_types = apply_filters( 'friends_frontend_post_types', array() );

		$counts = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $counts ) {
			return $counts;
		}

		$counts = get_transient( $cache_key );
		if ( false !== $counts ) {
			return $counts;
		} elseif ( ! $force_fetching ) {
			$post_formats = get_post_format_slugs();
			uksort(
				$post_formats,
				function ( $a, $b ) {
					// Sort standard to the top.
					if ( 'standard' === $a ) {
						return -1;
					} elseif ( 'standard' === $b ) {
						return 1;
					}
					return strnatcmp( $a, $b );
				}
			);
			$counts = array_fill_keys( $post_formats, '...' );
			return $counts;
		}

		$counts = array();

		global $wpdb;

		$counts = array();

		$post_format_counts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT terms.slug AS post_format, COUNT(terms.slug) AS count
				FROM {$wpdb->posts} AS posts
				JOIN {$wpdb->term_relationships} AS relationships
				JOIN {$wpdb->term_taxonomy} AS taxonomy
				JOIN {$wpdb->terms} AS terms

				WHERE posts.post_status IN ( 'publish', 'private' )
				AND posts.post_type IN ( " . implode( ',', array_fill( 0, count( $post_types ), '%s' ) ) . " )
				AND relationships.object_id = posts.ID
				AND relationships.term_taxonomy_id = taxonomy.term_taxonomy_id
				AND taxonomy.taxonomy = 'post_format'

				AND terms.term_id = taxonomy.term_id
				GROUP BY terms.slug",
				$post_types
			)
		);
		$post_count = null;
		foreach ( $post_types as $post_type ) {
			$count = wp_count_posts( $post_type );
			if ( is_null( $post_count ) ) {
				$post_count = $count;
			} else {
				foreach ( (array) $count as $post_status => $c ) {
					$post_count->$post_status = ( isset( $post_count->$post_status ) ? $post_count->$post_status : 0 ) + $c;
				}
			}
		}
		$counts['standard'] = $post_count->publish + $post_count->private;

		foreach ( $post_format_counts as $row ) {
			$counts[ str_replace( 'post-format-', '', $row->post_format ) ] = $row->count;
			$counts['standard'] -= $row->count;
		}

		$counts = array_filter( $counts );

		set_transient( $cache_key, $counts, HOUR_IN_SECONDS );
		wp_cache_set( $cache_key, $counts, 'friends', HOUR_IN_SECONDS );

		return $counts;
	}

	/**
	 * Get the retention by number of posts.
	 *
	 * @return int The retention by number of posts.
	 */
	public static function get_retention_number() {
		$number = get_option( 'friends_retention_number' );
		if ( $number <= 0 ) {
			return 2000;
		}

		return $number;
	}

	/**
	 * Get the retention by days of posts.
	 *
	 * @return int The retention by days of posts.
	 */
	public static function get_retention_days() {
		$days = get_option( 'friends_retention_days' );
		if ( $days <= 0 ) {
			return 30;
		}

		return $days;
	}

	/**
	 * Gets the post stats.
	 *
	 * @return     object  The post stats.
	 */
	public static function get_post_stats() {
		global $wpdb;
		$cache_key = 'post_stats';
		$post_stats = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $post_stats ) {
			return $post_stats;
		}

		$post_types = apply_filters( 'friends_frontend_post_types', array() );
		$post_stats = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				'SELECT SUM(
				LENGTH( ID ) +
				LENGTH( post_author ) +
				LENGTH( post_date ) +
				LENGTH( post_date_gmt ) +
				LENGTH( post_content ) +
				LENGTH( post_title ) +
				LENGTH( post_excerpt ) +
				LENGTH( post_status ) +
				LENGTH( comment_status ) +
				LENGTH( ping_status ) +
				LENGTH( post_password ) +
				LENGTH( post_name ) +
				LENGTH( to_ping ) +
				LENGTH( pinged ) +
				LENGTH( post_modified ) +
				LENGTH( post_modified_gmt ) +
				LENGTH( post_content_filtered ) +
				LENGTH( post_parent ) +
				LENGTH( guid ) +
				LENGTH( menu_order ) +
				LENGTH( post_type ) +
				LENGTH( post_mime_type ) +
				LENGTH( comment_count )
				) AS total_size,
				COUNT(*) as post_count
			FROM ' . $wpdb->posts . ' WHERE post_type IN ( ' . implode( ', ', array_fill( 0, count( $post_types ), '%s' ) ) . ' )',
				$post_types
			),
			ARRAY_A
		);
		$post_stats['earliest_post_date'] = mysql2date(
			'U',
			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"SELECT MIN(post_date) FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ( " . implode( ', ', array_fill( 0, count( $post_types ), '%s' ) ) . ' )',
					$post_types
				)
			)
		);

		wp_cache_set( $cache_key, $post_stats, 'friends', HOUR_IN_SECONDS );
		return $post_stats;
	}

	/**
	 * Gets the main header data.
	 *
	 * @return     array  The main header data.
	 */
	public function get_main_header_data() {
		$data = array(
			'description'               => '',
			'post_count_by_post_format' => $this->get_post_count_by_post_format(),
			'post_count_by_post_status' => $this->get_post_count_by_post_status(),
		);

		return $data;
	}

	/**
	 * Disables the author page for friends users.
	 */
	public function disable_friends_author_page() {
		global $wp_query;

		if ( is_author() && ! self::authenticated_for_posts() ) {
			$author_obj = $wp_query->get_queried_object();
			if ( $author_obj instanceof \WP_User && User::is_friends_plugin_user( $author_obj ) && ! self::on_frontend() ) {
				$wp_query->set_404();
				status_header( 404 );
			}
		}
	}

	/**
	 * Fix a bug in core where it outputs cached friend posts.
	 *
	 * @param array $qvs Query variables.
	 * @return array
	 */
	public function limit_post_format_request( $qvs ) {
		if ( isset( $qvs['post_type'] ) && ! is_admin() ) {
			if ( is_array( $qvs['post_type'] ) ) {
				$qvs['post_type'] = array_filter( $qvs['post_type'], 'is_post_type_viewable' );
			} elseif ( ! is_post_type_viewable( $qvs['post_type'] ) ) {
				unset( $qvs['post_type'] );
			}
		}

		return $qvs;
	}

	public function register_my_apps( $apps ) {
		$apps['friends'] = array(
			'name'     => __( 'Friends', 'friends' ),
			'icon_url' => 'https://ps.w.org/friends/assets/icon-256x256.png',
			'url'      => '/friends/',
		);
		return $apps;
	}

	/**
	 * Add a post_format filter to a \WP_Query.
	 *
	 * @param      array        $tax_query             The tax query.
	 * @param      string|array $filter_by_post_format  The filter by post format.
	 *
	 * @return     array|null  The tax query, if any.
	 */
	public function wp_query_get_post_format_tax_query( $tax_query, $filter_by_post_format ) {
		if ( empty( $filter_by_post_format ) ) {
			return $tax_query;
		}

		if ( ! is_array( $filter_by_post_format ) ) {
			$filter_by_post_format = array( $filter_by_post_format );
		}

		$post_formats = get_post_format_slugs();
		$filter_by_post_format = array_filter(
			$filter_by_post_format,
			function ( $post_format ) use ( $post_formats ) {
				return in_array( $post_format, $post_formats, true );
			}
		);
		if ( empty( $filter_by_post_format ) ) {
			return $tax_query;
		}

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
		}
		$post_format_query = array(
			'taxonomy' => 'post_format',
			'field'    => 'slug',
		);

		if ( in_array( 'standard', $filter_by_post_format, true ) ) {
			$post_format_query['operator'] = 'NOT IN';
			$post_format_query['terms']    = array_values(
				array_map(
					function ( $post_format ) {
						return 'post-format-' . $post_format;
					},
					array_diff( $post_formats, $filter_by_post_format )
				)
			);
		} else {
			$post_format_query['operator'] = 'IN';
			$post_format_query['terms']    = array_map(
				function ( $post_format ) {
					return 'post-format-' . $post_format;
				},
				$filter_by_post_format
			);
		}
		if ( ! empty( $tax_query ) ) {
			$tax_query[] = $post_format_query;
		} else {
			$tax_query = array( $post_format_query );
		}

		return $tax_query;
	}

	public function wp_query_get_post_tag_tax_query( $tax_query, $filter_by_post_tag ) {
		if ( empty( $filter_by_post_tag ) ) {
			return $tax_query;
		}

		if ( ! is_array( $filter_by_post_tag ) ) {
			$filter_by_post_tag = array( $filter_by_post_tag );
		}

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
		}
		$post_tag_query = array(
			'taxonomy' => self::TAG_TAXONOMY,
			'field'    => 'slug',
			'operator' => 'IN',
			'terms'    => $filter_by_post_tag,
		);

		if ( ! empty( $tax_query ) ) {
			$tax_query[] = $post_tag_query;
		} else {
			$tax_query = array( $post_tag_query );
		}

		return $tax_query;
	}


	/**
	 * Plural texts for post formats.
	 *
	 * @param      string $format  The post format.
	 * @param      int    $count   The count.
	 *
	 * @return     string  The plural string.
	 */
	public function get_post_format_plural_string( $format, $count ) {
		if ( ! in_array( $format, get_post_format_slugs(), true ) ) {
			$format = 'standard';
		}

		$plurals = array(
			// translators: %s is a count.
			'standard' => _nx_noop( '%s post', '%s posts', 'Post format', 'friends' ), // Special case. Any value that evals to false will be considered standard.
			// translators: %s is a count.
			'aside'    => _nx_noop( '%s aside', '%s asides', 'Post format', 'friends' ),
			// translators: %s is a count.
			'chat'     => _nx_noop( '%s chat', '%s chats', 'Post format', 'friends' ),
			// translators: %s is a count.
			'gallery'  => _nx_noop( '%s gallery', '%s galleries', 'Post format', 'friends' ),
			// translators: %s is a count.
			'link'     => _nx_noop( '%s link', '%s links', 'Post format', 'friends' ),
			// translators: %s is a count.
			'image'    => _nx_noop( '%s image', '%s images', 'Post format', 'friends' ),
			// translators: %s is a count.
			'quote'    => _nx_noop( '%s quote', '%s quotes', 'Post format', 'friends' ),
			// translators: %s is a count.
			'status'   => _nx_noop( '%s status', '%s statuses', 'Post format', 'friends' ),
			// translators: %s is a count.
			'video'    => _nx_noop( '%s video', '%s videos', 'Post format', 'friends' ),
			// translators: %s is a count.
			'audio'    => _nx_noop( '%s audio', '%s audios', 'Post format', 'friends' ),
		);

		if ( ! is_numeric( $count ) ) {
			return sprintf( translate_nooped_plural( $plurals[ $format ], 0, 'friends' ), $count );
		}

		return sprintf( translate_nooped_plural( $plurals[ $format ], $count, 'friends' ), number_format_i18n( $count ) );
	}

	/**
	 * Overwrite the must_log_in message.
	 *
	 * @param      array $defaults  The default strings.
	 *
	 * @return     array  The modified defaults.
	 */
	public function comment_form_defaults( $defaults ) {
		$comment_registration_message = get_option( 'friends_comment_registration_message', __( 'Only people in my network can comment.', 'friends' ) );
		$comment_registration_message = str_replace( __( 'my network', 'friends' ), '<a href="' . esc_attr( home_url() . '/friends/' ) . '">' . __( 'my network', 'friends' ) . '</a>', $comment_registration_message );
		$defaults['must_log_in'] = $comment_registration_message;
		return $defaults;
	}

	/**
	 * Get all of the rel links for the HTML head.
	 */
	public static function get_link_rels() {
		$rest_prefix = get_rest_url() . REST::PREFIX;
		$links = array(
			array(
				'rel'  => 'friends-base-url',
				'href' => $rest_prefix,
			),
		);

		if ( get_option( 'friends_expose_post_format_feeds' ) && current_theme_supports( 'post-formats' ) ) {
			$links = array_merge( $links, self::get_html_link_rel_alternate_post_formats() );
		}

		return $links;
	}

	/**
	 * Strip non-ascii bytes.
	 *
	 * @param      string $text   The text.
	 *
	 * @return     string  The text in ASCII only.
	 */
	private static function strip_non_ascii( $text ) {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 );
		$text = str_replace( '»', '>', $text );
		$text = strtr( $text, '"', '' );
		return filter_var( $text, FILTER_DEFAULT, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_NO_ENCODE_QUOTES );
	}

	/**
	 * Get all of the rel links for the HTTP header.
	 */
	public static function http_header() {
		foreach ( self::get_link_rels() as $link ) {
			$header = 'Link: <' . esc_url( $link['href'] ) . '>; rel="' . esc_attr( $link['rel'] ) . '"';
			if ( isset( $link['type'] ) ) {
				$header .= '; type="' . self::strip_non_ascii( $link['type'] ) . '"';
			}
			if ( isset( $link['title'] ) ) {
				$header .= '; title="' . self::strip_non_ascii( $link['title'] ) . '"';
			}
			header( $header, false );
		}
	}

	/**
	 * Get all of the rel links for the HTML head.
	 */
	public static function get_html_rel_links() {
		return array_map(
			function ( $link ) {
				return '<link' .
					' rel="' . esc_attr( $link['rel'] ) . '"' .
					' href="' . esc_url( $link['href'] ) . '"' .
					( isset( $link['type'] ) ? ( ' type="' . esc_attr( $link['type'] ) . '"' ) : '' ) .
					( isset( $link['title'] ) ? ( ' title="' . esc_attr( $link['title'] ) . '"' ) : '' ) .
					' />';
			},
			self::get_link_rels()
		);
	}

	/**
	 * Output all of the rel links for the HTML head.
	 */
	public static function html_rel_links() {
		echo wp_kses(
			implode( PHP_EOL, self::get_html_rel_links() ),
			array(
				'link' => array(
					'rel'   => array(),
					'type'  => array(),
					'href'  => array(),
					'title' => array(),
				),
			)
		), PHP_EOL;
	}

	/**
	 * Generate link tag(s) with the alternate post formats.
	 */
	public static function get_html_link_rel_alternate_post_formats() {
		$separator = _x( '&raquo;', 'feed link' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		$blog_title = get_bloginfo( 'name' );

		$links = array();
		foreach ( get_post_format_strings() as $format => $title ) {
			// translators: 1: Blog title, 2: Separator (raquo), 3: Post Format.
			$title = sprintf( __( '%1$s %2$s %3$s Feed', 'friends' ), $blog_title, $separator, $title );
			$links[] = array(
				'rel'   => 'alternate',
				'type'  => 'application/rss+xml',
				'href'  => esc_url( home_url( '/type/' . $format . '/feed/' ) ),
				'title' => $title,
			);
		}

		return $links;
	}

	/**
	 * Check whether this is a valid URL
	 *
	 * @param string $url The URL to check.
	 * @return false|string URL or false on failure.
	 */
	public static function check_url( $url ) {
		$pre = apply_filters( 'friends_pre_check_url', null, $url );
		if ( ! is_null( $pre ) ) {
			return $pre;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );

		$check_url = apply_filters( 'friends_host_is_valid', null, $host );
		if ( ! is_null( $check_url ) ) {
			return $check_url;
		}

		return wp_http_validate_url( $url );
	}

	public static function url_truncate( $url, $max_length = 50 ) {
		$p = wp_parse_url( untrailingslashit( $url ) );
		$parts = array( $p['host'] );
		if ( trim( $p['path'] ) ) {
			$parts = array_merge( $parts, explode( '/', $p['path'] ) );
		}

		$url = join( '/', array_filter( $parts ) );
		$reduce = 4;
		$url_length = strlen( $url );
		while ( $url_length > $max_length ) {
			$last_part = array_pop( $parts );
			$last_part = substr( $last_part, strlen( $last_part ) - $reduce );
			foreach ( $parts as $k => $part ) {
				$parts[ $k ] = substr( $part, 0, strlen( $part ) - $reduce );
			}
			$url = join( '../', array_filter( $parts ) ) . '../..' . $last_part;
			array_push( $parts, $last_part );
			$reduce = 1;
			$url_length = strlen( $url );
		}

		return $url;
	}

	/**
	 * Cron function to delete old posts.
	 */
	public function cron_friends_delete_outdated_posts() {
		foreach ( User_Feed::get_all_users() as $friend_user ) {
			$friend_user->delete_outdated_posts();
		}
		$this->delete_outdated_posts();
		$this->cleanup_orphaned_friend_tags();
	}

	/**
	 * Cron function to process post tag migration batches.
	 * Ensures the Migration class is loaded before calling the batch method.
	 */
	public function cron_migrate_post_tags_batch() {
		require_once __DIR__ . '/class-migration.php';
		Migration::migrate_post_tags_batch();
	}

	/**
	 * Add Friends-specific tests to Site Health.
	 *
	 * @param array $tests Site Health tests.
	 * @return array Modified tests array.
	 */
	public function add_site_health_tests( $tests ) {
		$tests['direct']['friends_post_tag_migration'] = array(
			'label' => __( 'Friends Post Tag Migration', 'friends' ),
			'test'  => array( $this, 'site_health_test_migration' ),
		);

		$tests['direct']['friends_post_tag_cleanup'] = array(
			'label' => __( 'Friends Post Tag Cleanup', 'friends' ),
			'test'  => array( $this, 'site_health_test_post_tag_cleanup' ),
		);

		$tests['direct']['friends_post_tag_count_recalculation'] = array(
			'label' => __( 'Post Tag Count Recalculation', 'friends' ),
			'test'  => array( $this, 'site_health_test_post_tag_count_recalculation' ),
		);

		return $tests;
	}

	/**
	 * Site Health test for post tag migration status.
	 *
	 * @return array Site Health test result.
	 */
	public function site_health_test_migration() {
		require_once __DIR__ . '/class-migration.php';
		$status = Migration::get_migration_status();

		if ( $status['completed'] ) {
			$result = array(
				'label'       => __( 'Post tag migration completed', 'friends' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'green',
				),
				'description' => sprintf(
					'<p>%s</p>',
					$status['completed_time'] ? sprintf(
						// translators: %s is a human-readable time difference.
						__( 'The migration from post_tag to friend_tag taxonomy was completed %s ago.', 'friends' ),
						human_time_diff( $status['completed_time'] )
					) : __( 'The migration from post_tag to friend_tag taxonomy has been completed.', 'friends' )
				),
				'actions'     => sprintf(
					'<p><a href="#" class="button" onclick="friendsRestartMigration(); return false;">%s</a></p>',
					__( 'Re-run Migration', 'friends' )
				),
				'test'        => 'friends_post_tag_migration',
			);
		} elseif ( $status['in_progress'] ) {
			$result = array(
				'label'       => __( 'Post tag migration in progress', 'friends' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'blue',
				),
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						// translators: %1$d is processed posts, %2$d is total posts, %3$s is percentage.
						__( 'Migration is currently running. Progress: %1$d of %2$d posts processed (%3$s%%).', 'friends' ),
						$status['processed'],
						$status['total'],
						$status['progress_percent']
					)
				),
				'test'        => 'friends_post_tag_migration',
			);
		} else {
			// Check if migration is needed.
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			$posts_to_migrate = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE p.post_type = %s AND tt.taxonomy = 'post_tag'",
					self::CPT
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( $posts_to_migrate > 0 ) {
				$result = array(
					'label'       => __( 'Post tag migration recommended', 'friends' ),
					'status'      => 'recommended',
					'badge'       => array(
						'label' => __( 'Friends', 'friends' ),
						'color' => 'orange',
					),
					'description' => sprintf(
						'<p>%s</p>',
						sprintf(
							// translators: %d is the number of posts that need migration.
							_n(
								'%d Friends post is using the old post_tag taxonomy and should be migrated to the new friend_tag taxonomy.',
								'%d Friends posts are using the old post_tag taxonomy and should be migrated to the new friend_tag taxonomy.',
								$posts_to_migrate,
								'friends'
							),
							$posts_to_migrate
						)
					),
					'actions'     => sprintf(
						'<p><a href="#" class="button button-primary" onclick="friendsStartMigration(); return false;">%s</a></p>',
						__( 'Start Migration', 'friends' )
					),
					'test'        => 'friends_post_tag_migration',
				);
			} else {
				$result = array(
					'label'       => __( 'No post tag migration needed', 'friends' ),
					'status'      => 'good',
					'badge'       => array(
						'label' => __( 'Friends', 'friends' ),
						'color' => 'green',
					),
					'description' => sprintf(
						'<p>%s</p>',
						__( 'All Friends posts are already using the correct friend_tag taxonomy.', 'friends' )
					),
					'test'        => 'friends_post_tag_migration',
				);
			}
		}

		return $result;
	}

	/**
	 * Site Health test for orphaned post_tag cleanup.
	 *
	 * @return array Site Health test result.
	 */
	public function site_health_test_post_tag_cleanup() {
		require_once __DIR__ . '/class-migration.php';

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Count ALL post_tag terms that have no actual post associations (excluding Friends posts).
		$orphaned_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				LEFT JOIN (
					SELECT DISTINCT tt2.term_id
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr.term_taxonomy_id = tt2.term_taxonomy_id
					INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
					WHERE tt2.taxonomy = 'post_tag' 
					AND p.post_status IN ('publish', 'private')
					AND p.post_type != %s
				) used_terms ON t.term_id = used_terms.term_id
				WHERE tt.taxonomy = 'post_tag' AND used_terms.term_id IS NULL",
				self::CPT
			)
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $orphaned_count > 0 ) {
			$result = array(
				'label'       => __( 'Orphaned post tags found', 'friends' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						// translators: %d is the number of orphaned post_tag terms.
						_n(
							'%d orphaned post_tag term was found with no associated posts.',
							'%d orphaned post_tag terms were found with no associated posts.',
							$orphaned_count,
							'friends'
						),
						$orphaned_count
					) . ' ' . __( 'These tags may have been created by Friends posts before migration or other sources and can be safely removed to keep your tag cloud clean.', 'friends' )
				),
				'actions'     => sprintf(
					'<p><a href="#" class="button" onclick="friendsCleanupPostTags(); return false;">%s</a></p>',
					__( 'Clean Up Orphaned Tags', 'friends' )
				),
				'test'        => 'friends_post_tag_cleanup',
			);
		} else {
			$result = array(
				'label'       => __( 'No orphaned post tags found', 'friends' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'green',
				),
				'description' => sprintf(
					'<p>%s</p>',
					__( 'Your post_tag taxonomy is clean. No orphaned tags from Friends posts were found.', 'friends' )
				),
				'test'        => 'friends_post_tag_cleanup',
			);
		}

		return $result;
	}

	/**
	 * Site Health test for comprehensive post_tag count recalculation.
	 *
	 * @return array Site Health test result.
	 */
	public function site_health_test_post_tag_count_recalculation() {
		require_once __DIR__ . '/class-migration.php';

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Count all post_tag terms.
		$total_post_tags = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			WHERE tt.taxonomy = 'post_tag'"
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $total_post_tags > 0 ) {
			$result = array(
				'label'       => __( 'Post tag count recalculation available', 'friends' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p><p>%s</p>',
					sprintf(
						// translators: %d is the number of post_tag terms.
						__( 'Found %d post_tag terms. Some may have outdated counts or be orphaned tags (excluding Friends posts which now use friend_tag taxonomy).', 'friends' ),
						$total_post_tags
					),
					sprintf(
						'<button type="button" class="button button-primary" onclick="friendsRecalculatePostTagCounts(this)" data-nonce="%s">%s</button>',
						wp_create_nonce( 'friends_recalculate_post_tag_counts' ),
						__( 'Recalculate All Post Tag Counts and Clean Up', 'friends' )
					)
				),
				'actions'     => sprintf(
					'<p><a href="#" onclick="friendsRecalculatePostTagCounts(this); return false;" data-nonce="%s">%s</a></p>',
					wp_create_nonce( 'friends_recalculate_post_tag_counts' ),
					__( 'Recalculate All Post Tag Counts and Clean Up', 'friends' )
				),
				'test'        => 'friends_post_tag_count_recalculation',
			);
		} else {
			$result = array(
				'label'       => __( 'No post tags to recalculate', 'friends' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'green',
				),
				'description' => sprintf(
					'<p>%s</p>',
					__( 'No post_tag terms were found, so no recalculation is needed.', 'friends' )
				),
				'test'        => 'friends_post_tag_count_recalculation',
			);
		}

		return $result;
	}

	/**
	 * AJAX handler to restart migration.
	 */
	public function ajax_restart_migration() {
		check_ajax_referer( 'friends_restart_migration', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'friends' ) );
		}

		require_once __DIR__ . '/class-migration.php';
		Migration::trigger_migration_manually();

		wp_send_json_success(
			array(
				'message' => __( 'Migration has been restarted and is running in the background.', 'friends' ),
			)
		);
	}

	/**
	 * AJAX handler to clean up orphaned post tags.
	 */
	public function ajax_cleanup_post_tags() {
		check_ajax_referer( 'friends_cleanup_post_tags', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'friends' ) );
		}

		require_once __DIR__ . '/class-migration.php';
		$results = Migration::cleanup_orphaned_post_tags();

		if ( $results['deleted'] > 0 ) {
			$message = sprintf(
				// translators: %1$d is the number of deleted tags, %2$d is the number checked.
				_n(
					'Cleanup completed: %1$d orphaned post_tag was deleted (%2$d tags checked).',
					'Cleanup completed: %1$d orphaned post_tags were deleted (%2$d tags checked).',
					$results['deleted'],
					'friends'
				),
				$results['deleted'],
				$results['checked']
			);
		} elseif ( $results['checked'] > 0 ) {
			$message = sprintf(
				// translators: %d is the number of tags checked.
				__( 'Cleanup completed: All %d tags checked were already clean (no deletions needed).', 'friends' ),
				$results['checked']
			);
		} else {
			$message = __( 'No orphaned post_tags were found to clean up.', 'friends' );
		}

		wp_send_json_success(
			array(
				'message' => $message,
				'results' => $results,
			)
		);
	}

	/**
	 * AJAX handler to recalculate all post tag counts and clean up orphaned tags.
	 */
	public function ajax_recalculate_post_tag_counts() {
		check_ajax_referer( 'friends_recalculate_post_tag_counts', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'friends' ) );
		}

		require_once __DIR__ . '/class-migration.php';
		$results = Migration::recalculate_all_post_tag_counts();

		if ( $results['deleted'] > 0 ) {
			$message = sprintf(
				// translators: %1$d is the number of deleted tags, %2$d is the number checked, %3$d is the number recalculated.
				_n(
					'Cleanup completed: %1$d orphaned post_tag was deleted after recalculating %3$d tags (%2$d total tags checked). Counts now exclude Friends posts.',
					'Cleanup completed: %1$d orphaned post_tags were deleted after recalculating %3$d tags (%2$d total tags checked). Counts now exclude Friends posts.',
					$results['deleted'],
					'friends'
				),
				$results['deleted'],
				$results['checked'],
				$results['recalculated']
			);
		} elseif ( $results['recalculated'] > 0 ) {
			$message = sprintf(
				// translators: %1$d is the number recalculated, %2$d is the number checked.
				__( 'Cleanup completed: %1$d post_tag counts updated, %2$d total tags checked (no deletions needed). Counts now exclude Friends posts.', 'friends' ),
				$results['recalculated'],
				$results['checked']
			);
		} else {
			$message = __( 'No post_tags were found to recalculate or clean up.', 'friends' );
		}

		wp_send_json_success(
			array(
				'message' => $message,
				'results' => $results,
			)
		);
	}

	/**
	 * Enqueue scripts for Site Health page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_site_health_scripts( $hook_suffix ) {
		if ( 'site-health.php' !== $hook_suffix ) {
			return;
		}

		$script = "
		function friendsStartMigration() {
			if (confirm('" . esc_js( __( 'This will migrate all post tags to friend tags for Friends posts. This process runs in the background. Continue?', 'friends' ) ) . "')) {
				friendsRunMigration();
			}
		}
		
		function friendsRestartMigration() {
			if (confirm('" . esc_js( __( 'This will restart the post tag migration. Any previous progress will be reset. Continue?', 'friends' ) ) . "')) {
				friendsRunMigration();
			}
		}
		
		function friendsCleanupPostTags() {
			if (confirm('" . esc_js( __( 'This will clean up orphaned post_tag terms that were created by Friends posts. Only unused tags will be deleted. Continue?', 'friends' ) ) . "')) {
				friendsRunCleanup();
			}
		}
		
		function friendsRecalculatePostTagCounts(button) {
			if (confirm('" . esc_js( __( 'This will recalculate counts for ALL post_tag terms (excluding Friends posts) and delete any with zero count. This includes both Friends-related and regular post tags. Continue?', 'friends' ) ) . "')) {
				const nonce = button.getAttribute('data-nonce');
				friendsRunRecalculation(nonce);
			}
		}
		
		function friendsRunMigration() {
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'friends_restart_migration',
					nonce: '" . wp_create_nonce( 'friends_restart_migration' ) . "'
				})
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					alert(data.data.message);
					location.reload();
				} else {
					alert('Error: ' + (data.data || 'Unknown error'));
				}
			})
			.catch(error => {
				console.error('Error:', error);
				alert('An error occurred while starting the migration.');
			});
		}
		
		function friendsRunCleanup() {
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'friends_cleanup_post_tags',
					nonce: '" . wp_create_nonce( 'friends_cleanup_post_tags' ) . "'
				})
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					alert(data.data.message);
					location.reload();
				} else {
					alert('Error: ' + (data.data || 'Unknown error'));
				}
			})
			.catch(error => {
				console.error('Error:', error);
				alert('An error occurred while cleaning up post tags.');
			});
		}
		
		function friendsRunRecalculation(nonce) {
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'friends_recalculate_post_tag_counts',
					nonce: nonce
				})
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					alert(data.data.message);
					location.reload();
				} else {
					alert('Error: ' + (data.data || 'Unknown error'));
				}
			})
			.catch(error => {
				console.error('Error:', error);
				alert('An error occurred while recalculating post tag counts.');
			});
		}
		";

		wp_add_inline_script( 'jquery', $script );
	}

	/**
	 * Maybe delete an outdated post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $reason The reason for deletion.
	 * @return int|false The post ID if it was deleted, false otherwise.
	 */
	public static function maybe_delete_outdated_post( int $post_id, string $reason = '' ) {
		if ( get_post_type( $post_id ) !== Friends::CPT ) {
			return false;
		}

		if ( ! get_option( 'friends_retention_delete_reacted' ) ) {
			// get all terms for a post, no matter whether it's registered or not.
			$term_query = new \WP_Term_Query(
				array(
					'object_ids' => $post_id,
				)
			);
			$reactions = array();
			foreach ( $term_query->get_terms() as $term ) {
				if ( substr( $term->taxonomy, 0, 16 ) !== 'friend-reaction-' ) {
					continue;
				}
				$reactions[ $term->slug ] = true;

			}
			if ( $reactions ) {
				if ( apply_filters( 'friends_debug', false ) && ! wp_doing_cron() ) {
					echo 'Skipping ', esc_html( $post_id ), ' because it has reactions (';
					foreach ( array_keys( $reactions ) as $emoji ) {
						echo esc_html( Reactions::validate_emoji( $emoji ) ), ' ';
					}
					echo ')<br/>', PHP_EOL;

				}
				return false;
			}
		}

		if ( apply_filters( 'friends_debug', false ) && ! wp_doing_cron() ) {
			echo 'Deleting ', esc_html( $post_id ), '(date: ', esc_html( get_the_date( 'Y-m-d H:i:s', $post_id ) ), ') because ', esc_html( $reason ), '<br/>', PHP_EOL;
		}
		wp_delete_post( $post_id, true );

		return $post_id;
	}

	/**
	 * Delete posts the user decided to automatically delete.
	 */
	public function delete_outdated_posts() {
		$deleted_posts = array();

		$args = array(
			'post_type'      => Friends::CPT,
			'post_status'    => array( 'publish', 'trash' ),
			'fields'         => 'ids',
			'posts_per_page' => 10,
		);

		if ( get_option( 'friends_enable_retention_days' ) ) {
			$args['date_query'] = array(
				'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( Friends::get_retention_days() * 24 ) . 'hours' ) ),
			);

			$query = new \WP_Query();
			foreach ( $args as $key => $value ) {
				$query->set( $key, $value );
			}

			foreach ( $query->get_posts() as $post_id ) {
				$post_id = self::maybe_delete_outdated_post( $post_id, 'date overflow' );
				if ( $post_id ) {
					$deleted_posts[] = $post_id;
				}
			}
			unset( $args['date_query'] );
		}

		$args['orderby'] = 'date';
		$args['order'] = 'desc';
		if ( get_option( 'friends_enable_retention_number' ) ) {
			$args['offset'] = Friends::get_retention_number();
			$query = new \WP_Query();
			foreach ( $args as $key => $value ) {
				$query->set( $key, $value );
			}

			foreach ( $query->get_posts() as $post_id ) {
				$post_id = self::maybe_delete_outdated_post( $post_id, 'global number overflow' );
				if ( $post_id ) {
					$deleted_posts[] = $post_id;
				}
			}
		}

		// In any case, don't overflow the trash.
		$args = array(
			'post_type'      => Friends::CPT,
			'post_status'    => 'trash',
			'offset'         => 100,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'asc',
			'posts_per_page' => 10,
		);

		$query = new \WP_Query();
		foreach ( $args as $key => $value ) {
			$query->set( $key, $value );
		}

		foreach ( $query->get_posts() as $post_id ) {
			$post_id = self::maybe_delete_outdated_post( $post_id, 'trash' );
			if ( $post_id ) {
				$deleted_posts[] = $post_id;
			}
		}

		return $deleted_posts;
	}

	/**
	 * Clean up orphaned friend tags that have no posts.
	 */
	public function cleanup_orphaned_friend_tags() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAG_TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			if ( 0 === $term->count ) {
				wp_delete_term( $term->term_id, self::TAG_TAXONOMY );
			}
		}
	}

	/**
	 * Delete all the data the plugin has stored in WordPress
	 */
	public static function uninstall_plugin() {
		$taxonomies = array(
			User_Feed::TAXONOMY,
			User_Feed::POST_TAXONOMY,
			Subscription::TAXONOMY,
			self::TAG_TAXONOMY,
		);

		$affected_users = new \WP_User_Query( array( 'role__in' => array( 'friend', 'acquaintance', 'friend_request', 'pending_friend_request', 'subscription' ) ) );
		foreach ( $affected_users as $user ) {
			$in_token = get_user_option( 'friends_in_token', $user->ID );
			delete_option( 'friends_in_token_' . $in_token );
			delete_user_option( $user->ID, 'friends_out_token' );
			delete_user_option( $user->ID, 'friends_in_token' );
			delete_user_option( $user->ID, 'friends_new_friend' );
			$taxonomies[] = 'friend-reaction-' . $user->ID;
		}

		delete_option( 'friends_main_user_id' );
		remove_role( 'friend' );
		remove_role( 'acquaintance' );
		remove_role( 'friend_request' );
		remove_role( 'pending_friend_request' );
		remove_role( 'subscription' );

		$friend_posts = new \WP_Query(
			array(
				'post_type'   => Friends::CPT,
				'post_status' => array( 'publish', 'private', 'trash' ),
			)
		);

		while ( $friend_posts->have_posts() ) {
			$post = $friend_posts->next_post();
			wp_delete_post( $post->ID, true );
		}

		// We have to resort to using direct database statements since the taxonomy is not registered because the plugin is deactivated.
		global $wpdb;
		foreach ( $taxonomies as $taxonomy ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			$terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s ORDER BY t.name ASC", $taxonomy ) );

			if ( $terms ) {
				foreach ( $terms as $term ) {
					$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
					$wpdb->delete( $wpdb->terms, array( 'term_id' => $term->term_id ) );
					delete_option( 'prefix_' . $term->slug . '_option_name' );
				}
			}

			$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $taxonomy ), array( '%s' ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}
}
