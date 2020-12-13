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
	const VERSION       = '1.0';
	const CPT           = 'friend_post_cache';
	const CPT_PREFIX    = 'friends_cache';
	const FEED_URL      = 'friends-feed-url';
	const PLUGIN_URL    = 'https://wordpress.org/plugins/friends/';
	const REQUIRED_ROLE = 'administrator';

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
	 * A reference to the Friends_Frontend object.
	 *
	 * @var Friends_Frontend
	 */
	public $frontend;

	/**
	 * A reference to the Friends_REST object.
	 *
	 * @var Friends_REST
	 */
	public $rest;

	/**
	 * A reference to the Friends_Post_Types object.
	 *
	 * @var Friends_Post_Types
	 */
	public $post_types;

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
		$this->frontend       = new Friends_Frontend( $this );
		$this->post_types     = new Friends_Post_Types( $this );
		$this->recommendation = new Friends_Recommendation( $this );
		$this->reactions      = new Friends_Reactions( $this );
		$this->rest           = new Friends_REST( $this );

		new Friends_3rd_Parties( $this );
		new Friends_Blocks( $this );
		new Friends_Logging( $this );
		new Friends_Shortcodes( $this );
		$this->register_hooks();
		load_plugin_textdomain( 'friends', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		do_action( 'friends_init', $this );
		do_action( 'friends_register_parser', $this->feed );
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_filter( 'init', array( $this, 'register_custom_post_type' ) );
		add_filter( 'init', array( Friend_User_Feed::class, 'register_taxonomy' ) );
		add_filter( 'friends_template_path', array( $this, 'friends_template_path' ) );
		add_filter( 'get_avatar_data', array( $this, 'get_avatar_data' ), 10, 2 );
		add_filter( 'wp_head', array( $this, 'html_link_rel_friends_base_url' ) );
		add_filter( 'wp_head', array( $this, 'html_link_rel_alternate_post_formats' ) );
		add_filter( 'login_head', array( $this, 'html_link_rel_friends_base_url' ) );
		add_filter( 'after_setup_theme', array( $this, 'enable_post_formats' ) );
		add_filter( 'pre_get_posts', array( $this, 'pre_get_posts_filter_by_post_format' ) );
	}

	/**
	 * Registers the custom post type
	 */
	public function register_custom_post_type() {
		$labels = array(
			'name'               => 'Friend Posts',
			'singular_name'      => 'Friend Post',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Friend Post',
			'edit_item'          => 'Edit Friend Post',
			'new_item'           => 'New Friend Post',
			'all_items'          => 'All Friend Posts',
			'view_item'          => 'View Friend Post',
			'search_items'       => 'Search Friend Posts',
			'not_found'          => 'No Friend Posts found',
			'not_found_in_trash' => 'No Friend Posts found in the Trash',
			'parent_item_colon'  => '',
			'menu_name'          => 'Cached Friend Posts',
		);

		$args = array(
			'labels'              => $labels,
			'description'         => "A cached friend's post",
			'publicly_queryable'  => $this->access_control->private_rss_is_authenticated(),
			'show_ui'             => apply_filters( 'friends_show_cached_posts', false ),
			'show_in_menu'        => apply_filters( 'friends_show_cached_posts', false ),
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'exclude_from_search' => apply_filters( 'friends_show_cached_posts', false ),
			'public'              => false,
			'delete_with_user'    => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'post-formats' ),
			'taxonomies'          => array( 'post_tag', 'post_format' ),
			'has_archive'         => true,
		);

		register_post_type( self::CPT, $args );
		$this->post_types->register( 'post' );
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

		$acquaintance = get_role( 'acquaintance' );
		if ( ! $acquaintance ) {
			_x( 'Acquaintance', 'User role', 'friends' );
			$acquaintance = add_role( 'acquaintance', 'Acquaintance' );
		}
		$acquaintance->add_cap( 'read' );
		$acquaintance->add_cap( 'friend' );
		$acquaintance->add_cap( 'acquaintance' );
		$acquaintance->add_cap( 'level_0' );

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
	 * Creates a page /friends/ to enable customization via.
	 */
	public static function create_friends_page() {
		$query = new WP_Query( array( 'post_name' => 'friends' ) );
		if ( $query->have_posts() ) {
			return;
		}

		$content  = '<!-- wp:paragraph {"friendsVisibility":"only-friends"} -->' . PHP_EOL . '<p>';
		$content .= __( 'Hi Friend!', 'friends' );
		$content .= '<br/><br/>';
		$content .= __( 'Do you know any of my friends? Maybe you want to become friends with them as well?', 'friends' );
		$content .= PHP_EOL . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL;

		$content .= '<!-- wp:friends/friends-list {"friendsVisibility":"only-friends","user_types":"friends"} /-->' . PHP_EOL;

		$content .= '<!-- wp:paragraph {"friendsVisibility":"not-friends"} -->' . PHP_EOL . '<p>';
		$content .= __( 'I have connected with my friends using <strong>WordPress</strong> and the <strong>Friends plugin</strong>. This means I can share private posts with just my friends while keeping my data under control.', 'friends' );
		$content .= PHP_EOL;
		// translators: %1$s and %2$s are URLs.
		$content .= sprintf( __( 'If you also have a WordPress site with the friends plugin, you can send me a friend request. If not, follow me and get your own <a href="%1$s">WordPress</a> now and install the <a href="%2$s">Friends plugin</a>!', 'friends' ), 'https://wordpress.org/', self::PLUGIN_URL );
		$content .= PHP_EOL . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL;

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
	 * If a plugin version upgrade requires changes, they can be done here
	 *
	 * @param  string $previous_version The previous plugin version number.
	 */
	public static function upgrade_plugin( $previous_version ) {
		if ( version_compare( $previous_version, '0.20.1', '<' ) ) {
			$friends_subscriptions = Friend_User_Query::all_friends_subscriptions();
			foreach ( $friends_subscriptions->get_results() as $user ) {
				$gravatar = get_user_option( 'friends_gravatar', $user->ID );
				$user_icon_url = get_user_option( 'friends_user_icon_url', $user->ID );
				if ( $gravatar ) {
					if ( ! $user_icon_url ) {
						update_user_option( $user->ID, 'friends_user_icon_url', $gravatar );
					}
					delete_user_option( $user->ID, 'friends_gravatar' );
				}
			}
		}

		update_option( 'friends_plugin_version', self::VERSION );
	}

	/**
	 * Actions to take upon plugin activation.
	 */
	public static function activate_plugin() {
		self::setup_roles();
		self::create_friends_page();

		$previous_version = get_option( 'friends_plugin_version' );
		if ( self::VERSION !== $previous_version ) {
			self::upgrade_plugin( $previous_version );
		}

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
				'friends-widget-header'           => array(
					'title' => 'Friends',
				),
				'friends-widget-new-private-post' => array(),
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
				$users = Friend_User_Query::all_admin_users();
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

		if ( is_numeric( $id_or_email ) && $id_or_email > 0 ) {
			$url = get_user_option( 'friends_user_icon_url', $id_or_email );
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
	 * Modify the main query to allow limiting the post format on the homepage.
	 *
	 * @param      WP_Query $query  The query.
	 */
	public function pre_get_posts_filter_by_post_format( $query ) {
		global $wp_query;

		if ( ! $query->is_main_query() || ! empty( $wp_query->query['post_format'] ) || ! $query->is_feed() ) {
			return;
		}

		$tax_query = $this->wp_query_get_post_format_tax_query( get_option( 'friends_limit_homepage_post_format', false ) );
		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Add a post_format filter to a WP_Query.
	 *
	 * @param      string $filter_by_post_format  The filter by post format.
	 *
	 * @return     array|null  The tax query, if any.
	 */
	public function wp_query_get_post_format_tax_query( $filter_by_post_format ) {
		if ( ! $filter_by_post_format ) {
			return null;
		}
		$post_formats = get_post_format_slugs();
		if ( ! isset( $post_formats[ $filter_by_post_format ] ) ) {
			return null;
		}

		if ( 'standard' === $filter_by_post_format ) {
			$formats = array();

			foreach ( $post_formats as $format ) {
				if ( ! in_array( $format, array( 'standard' ) ) ) {
					$formats[] = 'post-format-' . $format;
				}
			}

			if ( ! empty( $formats ) ) {
				return array(
					'relation' => 'AND',
					array(
						'operator' => 'NOT IN',
						'taxonomy' => 'post_format',
						'field'    => 'slug',
						'terms'    => $formats,
					),
				);
			}
		}

		return array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array( 'post-format-' . $filter_by_post_format ),
			),
		);
	}

	/**
	 * Output the alternate post formats as a link in the HTML head.
	 */
	public static function html_link_rel_alternate_post_formats() {
		if ( get_option( 'friends_expose_post_format_feeds' ) && current_theme_supports( 'post-formats' ) ) {
			echo implode( PHP_EOL, self::get_html_link_rel_alternate_post_formats() ), PHP_EOL;
		}
	}

	/**
	 * Generate link tag(s) with the alternate post formats.
	 */
	public static function get_html_link_rel_alternate_post_formats() {
		$separator = _x( '&raquo;', 'feed link' );
		$blog_title = get_bloginfo( 'name' );

		$links = array();
		foreach ( get_post_format_strings() as $format => $title ) {
			// translators: 1: Blog title, 2: Separator (raquo), 3: Post Format.
			$title = sprintf( __( '%1$s %2$s %3$s Feed', 'friends' ), $blog_title, $separator, $title );
			$links[] = '<link rel="alternate" type="application/rss+xml" href="' . esc_url( site_url( '/type/' . $format . '/feed/' ) ) . '" title="' . esc_attr( $title ) . '" />';
		}

		return $links;
	}

	/**
	 * Output the friends base URL as a link in the HTML head.
	 */
	public static function html_link_rel_friends_base_url() {
		echo self::get_html_link_rel_friends_base_url(), PHP_EOL;
	}

	/**
	 * Generate a link tag with the friends base URL
	 */
	public static function get_html_link_rel_friends_base_url() {
		return '<link rel="friends-base-url" type="application/wp-friends-plugin" href="' . esc_attr( get_rest_url() . Friends_REST::PREFIX ) . '" />';
	}

	/**
	 * Check whether this is a valid URL
	 *
	 * @param string $url The URL to check.
	 * @return false|string URL or false on failure.
	 */
	public static function check_url( $url ) {
		$host = parse_url( $url, PHP_URL_HOST );
		if ( 'me.local' === $host || 'friend.local' === $host || 'example.org' === $host ) {
			// Hosts used for test cases.
			return $url;
		}
		return wp_http_validate_url( $url );
	}

	/**
	 * Delete all the data the plugin has stored in WordPress
	 */
	public static function uninstall_plugin() {
		$affected_users = new WP_User_Query( array( 'role__in' => array( 'friend', 'acquaintance', 'friend_request', 'pending_friend_request', 'subscription' ) ) );
		foreach ( $affected_users as $user ) {
			$in_token = get_user_option( 'friends_in_token', $user->ID );
			delete_option( 'friends_in_token_' . $in_token );
			delete_user_option( $user->ID, 'friends_out_token' );
			delete_user_option( $user->ID, 'friends_in_token' );
			delete_user_option( $user->ID, 'friends_new_friend' );
		}

		delete_option( 'friends_main_user_id' );
		remove_role( 'friend' );
		remove_role( 'acquaintance' );
		remove_role( 'friend_request' );
		remove_role( 'pending_friend_request' );
		remove_role( 'subscription' );

		$friends = Friends::get_instance();
		$friend_posts = new WP_Query(
			array(
				'post_type'   => $friends->post_types->get_all_cached(),
				'post_status' => array( 'publish', 'private', 'trash' ),
			)
		);

		while ( $friend_posts->have_posts() ) {
			$post = $friend_posts->next_post();
			wp_delete_post( $post->ID, true );
		}
	}
}
