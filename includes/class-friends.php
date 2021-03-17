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
	const VERSION       = '1.5.3';
	const CPT           = 'friend_post_cache';
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
	 * Get the Friends_Template_Loader singleton
	 *
	 * @return Friends_Template_Loader A class instance.
	 */
	public static function template_loader() {
		static $template_loader;
		if ( ! isset( $template_loader ) ) {
			$template_loader = new Friends_Template_Loader();
		}
		return $template_loader;
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
		$this->rest           = new Friends_REST( $this );

		new Friends_3rd_Parties( $this );
		new Friends_Blocks( $this );
		new Friends_Logging( $this );
		new Friends_Shortcodes( $this );
		$this->register_hooks();
		load_plugin_textdomain( 'friends', false, FRIENDS_PLUGIN_FILE . '/languages/' );
		do_action( 'friends_init', $this );
		do_action( 'friends_register_parser', $this->feed );
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_filter( 'init', array( $this, 'register_custom_post_type' ) );
		add_filter( 'init', array( Friend_User_Feed::class, 'register_taxonomy' ) );
		add_filter( 'get_avatar_data', array( $this, 'get_avatar_data' ), 10, 2 );
		add_filter( 'wp_head', array( $this, 'html_link_rel_friends_base_url' ) );
		add_filter( 'wp_head', array( $this, 'html_link_rel_alternate_post_formats' ) );
		add_filter( 'login_head', array( $this, 'html_link_rel_friends_base_url' ) );
		add_filter( 'after_setup_theme', array( $this, 'enable_post_formats' ) );
		add_filter( 'pre_get_posts', array( $this, 'pre_get_posts_filter_by_post_format' ), 20 );
		add_filter( 'template_redirect', array( $this, 'disable_friends_author_page' ) );

		add_filter( 'request', array( $this, 'limit_post_format_request' ), 20 );
	}

	/**
	 * Registers the custom post type
	 */
	public function register_custom_post_type() {
		$labels = array(
			'name'               => __( 'Friend Posts', 'friends' ),
			'singular_name'      => __( 'Friend Post', 'friends' ),
			'add_new'            => __( 'Add New', 'friends' ),
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
			'publicly_queryable'  => $this->access_control->private_rss_is_authenticated() || ( is_admin() && current_user_can( Friends::REQUIRED_ROLE ) && apply_filters( 'friends_show_cached_posts', false ) ),
			'show_ui'             => true,
			'show_in_menu'        => apply_filters( 'friends_show_cached_posts', false ),
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'public'              => false,
			'delete_with_user'    => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'title', 'editor', 'author', 'revisions', 'thumbnail', 'excerpt', 'comments', 'post-formats' ),
			'taxonomies'          => array( 'post_tag', 'post_format' ),
			'has_archive'         => true,
		);

		register_post_type( self::CPT, $args );
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
		$query = new WP_Query(
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
		$content .= sprintf( __( 'If you also have a WordPress site with the friends plugin, you can send me a friend request. If not, follow me and get your own <a href="%1$s">WordPress</a> now and install the <a href="%2$s">Friends plugin</a>!', 'friends' ), 'https://wordpress.org/', self::PLUGIN_URL );
		$content .= PHP_EOL . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL;

		$post_data = array(
			'post_title'   => __( 'Welcome to my Friends Page', 'friends' ),
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
			update_option( 'friends_private_rss_key', wp_generate_password( 128, false ) );
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
	 * Determine whether we are on the /friends/ page or a subpage.
	 *
	 * @return boolean Whether we are on a friends page URL.
	 */
	public static function on_frontend() {
		global $wp_query;

		if ( ! isset( $wp_query ) || ! isset( $wp_query->query['pagename'] ) ) {
			return false;
		}

		if ( isset( $_GET['public'] ) ) {
			return false;
		}

		if ( ! current_user_can( self::REQUIRED_ROLE ) || ( is_multisite() && is_super_admin( get_current_user_id() ) ) ) {
			return false;
		}

		$pagename_parts = explode( '/', trim( $wp_query->query['pagename'], '/' ) );
		return count( $pagename_parts ) > 0 && 'friends' === $pagename_parts[0];
	}

	/**
	 * Disables the author page for friends users.
	 */
	public function disable_friends_author_page() {
		global $wp_query;

		if ( is_author() ) {
			$author_obj = $wp_query->get_queried_object();
			if ( $author_obj instanceof WP_User && Friend_User::is_friends_plugin_user( $author_obj ) && ! self::on_frontend() ) {
				$wp_query->set_404();
				status_header( 404 );
			}
		}
	}

	/**
	 * Modify the main query to allow limiting the post format on the homepage.
	 *
	 * @param      WP_Query $query  The query.
	 */
	public function pre_get_posts_filter_by_post_format( $query ) {
		global $wp_query;

		if ( ! ( $query->is_main_query() || $query->is_feed() ) || ! empty( $wp_query->query['post_format'] ) || $query->is_friends_page ) {
			return;
		}

		$tax_query = $this->wp_query_get_post_format_tax_query( get_option( 'friends_limit_homepage_post_format', false ) );
		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Fix a bug in core where it outputs cached friend posts.
	 *
	 * @param array $qvs Query variables.
	 * @return array
	 */
	public function limit_post_format_request( $qvs ) {
		if ( isset( $qvs['post_type'] ) ) {
			$qvs['post_type'] = array_filter( (array) $qvs['post_type'], 'is_post_type_viewable' );
		}

		return $qvs;
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

		return sprintf( translate_nooped_plural( $plurals[ $format ], $count, 'friends' ), number_format_i18n( $count ) );
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
				'post_type'   => Friends::CPT,
				'post_status' => array( 'publish', 'private', 'trash' ),
			)
		);

		while ( $friend_posts->have_posts() ) {
			$post = $friend_posts->next_post();
			wp_delete_post( $post->ID, true );
		}
	}
}
