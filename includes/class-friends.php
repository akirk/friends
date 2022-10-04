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
	const VERSION       = '2.0.3';
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
		do_action( 'friends_loaded', $this );
		do_action( 'friends_load_parsers', $this->feed );
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_action( 'init', array( User_Feed::class, 'register_taxonomy' ) );
		add_filter( 'get_avatar_data', array( $this, 'get_avatar_data' ), 10, 2 );

		add_action( 'template_redirect', array( $this, 'http_header' ), 5 );
		add_filter( 'wp_head', array( $this, 'html_rel_links' ) );
		add_filter( 'login_head', array( $this, 'html_rel_links' ) );

		add_filter( 'after_setup_theme', array( $this, 'enable_post_formats' ) );
		add_filter( 'pre_get_posts', array( $this, 'pre_get_posts_filter_by_post_format' ), 20 );
		add_filter( 'template_redirect', array( $this, 'disable_friends_author_page' ) );

		add_action( 'comment_form_defaults', array( $this, 'comment_form_defaults' ) );

		add_filter( 'request', array( $this, 'limit_post_format_request' ), 20 );
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
			'taxonomies'          => array( 'post_tag', 'post_format', 'friend-reaction-' . get_current_user_id() ),
			'has_archive'         => true,
			'rewrite'             => false,
		);

		register_post_type( self::CPT, $args );
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

		$roles = new \WP_Roles;

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
		$content .= sprintf( __( 'If you also have a WordPress site with the friends plugin, you can send me a friend request. If not, follow me and get your own <a href="%1$s">WordPress</a> now and install the <a href="%2$s">Friends plugin</a>!', 'friends' ), 'https://wordpress.org/', self::PLUGIN_URL );
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
			'post_title'   => __( 'Welcome to my Friends Page', 'friends' ),
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
		return array( 'friend', 'pending_friend_request', 'friend_request', 'subscription' );
	}

	/**
	 * If a plugin version upgrade requires changes, they can be done here
	 *
	 * @param  string $previous_version The previous plugin version number.
	 */
	public static function upgrade_plugin( $previous_version ) {
		if ( version_compare( $previous_version, '0.20.1', '<' ) ) {
			$friends_subscriptions = User_Query::all_friends_subscriptions();
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
		flush_rewrite_rules();
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
			if ( $id_or_email instanceof \WP_User ) {
				$id_or_email = $id_or_email->ID;
			} elseif ( $id_or_email instanceof \WP_Post ) {
				$id_or_email = $id_or_email->post_author;
			} elseif ( $id_or_email instanceof \WP_Comment ) {
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
		if ( ! current_user_can( self::REQUIRED_ROLE ) || ( is_multisite() && ! is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) && is_super_admin( get_current_user_id() ) ) ) {
			return false;
		}

		$pagename_parts = explode( '/', trim( $wp_query->query['pagename'], '/' ) );
		return count( $pagename_parts ) > 0 && 'friends' === $pagename_parts[0];
	}

	/**
	 * Check whether the request has been authenticated to display (private) posts.
	 *
	 * @return     bool  Whether the posts can be accessed.
	 */
	public static function authenticated_for_posts() {
		return Access_Control::private_rss_is_authenticated() || ( is_admin() && current_user_can( Friends::REQUIRED_ROLE ) && apply_filters( 'friends_show_cached_posts', false ) );
	}

	/**
	 * Gets the post types to be displayed on the frontend, modifyable by filter.
	 *
	 * @return     array  The frontend post types.
	 */
	public static function get_frontend_post_types() {
		return apply_filters( 'friends_frontend_post_types', array( Friends::CPT ) );
	}

	/**
	 * Gets the post count by post format.
	 *
	 * @param      int $author_id  The author userid.
	 *
	 * @return     array  The post count by post format.
	 */
	public function get_post_count_by_post_format( $author_id = null ) {
		$cache_key = 'friends_post_count_by_post_format';
		if ( is_numeric( $author_id ) && $author_id > 0 ) {
			$author_id = intval( $author_id );
			$cache_key .= '_author_' . $author_id;
		} else {
			$author_id = null;
		}

		$counts = get_transient( $cache_key );
		if ( false === $counts ) {
			$counts = array();
			$post_types = Friends::get_frontend_post_types();

			global $wpdb;

			$counts = array();
			if ( $author_id ) {
				$post_format_counts = $wpdb->get_results(
					$wpdb->prepare(
						sprintf(
							"SELECT terms.slug AS post_format, COUNT(terms.slug) AS count
							FROM %s AS posts
							JOIN %s AS relationships
							JOIN %s AS taxonomy
							JOIN %s AS terms

							WHERE posts.post_author = %s
							AND posts.post_status IN ( 'publish', 'private' )
							AND posts.post_type IN ( %s )
							AND relationships.object_id = posts.ID
							AND relationships.term_taxonomy_id = taxonomy.term_taxonomy_id
							AND taxonomy.taxonomy = 'post_format'

							AND terms.term_id = taxonomy.term_id
							GROUP BY terms.slug",
							$wpdb->posts,
							$wpdb->term_relationships,
							$wpdb->term_taxonomy,
							$wpdb->terms,
							'%d',
							implode( ',', array_fill( 0, count( $post_types ), '%s' ) )
						),
						array_merge(
							array( $author_id ),
							$post_types
						)
					)
				);
				$counts['standard'] = count_user_posts( $author_id, $post_types );
			} else {
				$post_format_counts = $wpdb->get_results(
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
							$post_count->$post_status += $c;
						}
					}
				}
				$counts['standard'] = $post_count->publish + $post_count->private;
			}

			foreach ( $post_format_counts as $row ) {
				$counts[ str_replace( 'post-format-', '', $row->post_format ) ] = $row->count;
				$counts['standard'] -= $row->count;
			}

			$counts = array_filter( $counts );

			set_transient( $cache_key, $counts, HOUR_IN_SECONDS );
		}

		return $counts;
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
	 * Modify the main query to allow limiting the post format on the homepage.
	 *
	 * @param      \WP_Query $query  The query.
	 */
	public function pre_get_posts_filter_by_post_format( $query ) {
		global $wp_query;

		if ( ! ( $query->is_main_query() || $query->is_feed() ) || ! empty( $wp_query->query['post_format'] ) || $query->is_friends_page ) {
			return;
		}

		$tax_query = $this->wp_query_get_post_format_tax_query( array(), get_option( 'friends_limit_homepage_post_format', false ) );
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
		if ( isset( $qvs['post_type'] ) && ! is_admin() ) {
			if ( is_array( $qvs['post_type'] ) ) {
				$qvs['post_type'] = array_filter( $qvs['post_type'], 'is_post_type_viewable' );
			} elseif ( ! is_post_type_viewable( $qvs['post_type'] ) ) {
				unset( $qvs['post_type'] );
			}
		}

		return $qvs;
	}
	/**
	 * Add a post_format filter to a \WP_Query.
	 *
	 * @param      array  $tax_query             The tax query.
	 * @param      string $filter_by_post_format  The filter by post format.
	 *
	 * @return     array|null  The tax query, if any.
	 */
	public function wp_query_get_post_format_tax_query( $tax_query, $filter_by_post_format ) {
		if ( ! $filter_by_post_format ) {
			return $tax_query;
		}
		$post_formats = get_post_format_slugs();
		if ( ! isset( $post_formats[ $filter_by_post_format ] ) ) {
			return $tax_query;
		}

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
		}

		if ( 'standard' === $filter_by_post_format ) {
			$formats = array();

			foreach ( $post_formats as $format ) {
				if ( ! in_array( $format, array( 'standard' ) ) ) {
					$formats[] = 'post-format-' . $format;
				}
			}

			if ( ! empty( $formats ) ) {
				$tax_query[] = array(
					'operator' => 'NOT IN',
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => $formats,
				);
			}
		} else {
				$tax_query[] = array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => array( 'post-format-' . $filter_by_post_format ),
				);
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
		$text = html_entity_decode( $text );
		$text = str_replace( '»', '>', $text );
		$text = strtr( $text, '"', '' );
		return filter_var( $text, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_NO_ENCODE_QUOTES );
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
		$host = parse_url( $url, PHP_URL_HOST );

		$check_url = apply_filters( 'friends_host_is_valid', null, $host );
		if ( ! is_null( $check_url ) ) {
			return $check_url;
		}

		return wp_http_validate_url( $url );
	}

	/**
	 * Delete all the data the plugin has stored in WordPress
	 */
	public static function uninstall_plugin() {
		$affected_users = new \WP_User_Query( array( 'role__in' => array( 'friend', 'acquaintance', 'friend_request', 'pending_friend_request', 'subscription' ) ) );
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
	}
}
