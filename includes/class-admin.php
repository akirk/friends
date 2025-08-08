<?php
/**
 * Friends Admin
 *
 * This contains the functions for the admin section.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Friends Plugin Admin section.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Admin {
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
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'users_list_table_query_args', array( $this, 'allow_role_multi_select' ) );
		add_filter( 'the_title', array( $this, 'override_post_format_title' ), 10, 2 );
		add_filter( 'get_edit_user_link', array( $this, 'admin_edit_user_link' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_friends_menu' ), 39 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_new_content' ), 71 );
		add_action( 'wp_head', array( $this, 'admin_bar_mobile' ) );
		add_action( 'current_screen', array( $this, 'register_help' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 39 );
		add_action( 'gettext_with_context', array( $this->friends, 'translate_user_role' ), 10, 4 );
		add_action( 'wp_ajax_friends_preview_rules', array( $this, 'ajax_preview_friend_rules' ) );
		add_action( 'wp_ajax_friends_fetch_feeds', array( $this, 'ajax_fetch_feeds' ) );
		add_action( 'wp_ajax_friends_set_avatar', array( $this, 'ajax_set_avatar' ) );
		add_action( 'wp_ajax_friends-preview-subscription', array( $this, 'ajax_preview_subscription' ) );
		add_action( 'delete_user_form', array( $this, 'delete_user_form' ), 10, 2 );
		add_action( 'delete_user', array( $this, 'delete_user' ) );
		add_action( 'remove_user_from_blog', array( $this, 'delete_user' ) );
		add_action( 'tool_box', array( $this, 'toolbox_bookmarklets' ) );
		add_action( 'dashboard_glance_items', array( $this, 'dashboard_glance_items' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ), 8 );
		add_action( 'wp_ajax_friends_dashboard', array( $this, 'ajax_friends_dashboard' ) );
		add_filter( 'site_status_tests', array( $this, 'site_status_tests' ) );
		add_filter( 'site_status_test_php_modules', array( $this, 'site_status_test_php_modules' ) );
		add_filter( 'debug_information', array( $this, 'site_health_debug' ) );
		add_filter( 'friends_create_and_follow', array( $this, 'create_and_follow' ), 10, 4 );

		if ( ! get_option( 'permalink_structure' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_unsupported_permalink_structure' ) );
		}
		add_filter( 'pre_get_posts', array( $this, 'admin_friend_posts_query' ) );
	}

	/**
	 * Display admin notice about an unsupported permalink structure
	 */
	public function admin_notice_unsupported_permalink_structure() {
		$screen = get_current_screen();

		if ( 'plugins' !== $screen->id ) {
			return;
		}

		?>
		<div class="friends-notice notice notice-error">
			<p style="max-width:800px;"><b><?php esc_html_e( 'Friends', 'friends' ); ?></b><?php esc_html_e( '&#151; You are running an unsupported permalink structure.', 'friends' ); ?></p>
			<p style="max-width:800px;">
				<?php
				echo wp_kses_post(
					sprintf(
						// translators: 1: URL to permalink settings, 2: the name of the Permalink Settings page.
						__( 'In order to be able to view the Friends page, you need to enable a custom permalink structure. Please go to <a href="%1$s">%2$s</a> and enable an option other than Plain.', 'friends' ),
						admin_url( 'options-permalink.php' ),
						__( 'Permalink Settings' ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Registers the admin menus
	 */
	public function admin_menu() {
		if ( isset( $_REQUEST['rerun-activate'] ) && isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'friends-settings' ) ) {
			Friends::activate_plugin();
			wp_safe_redirect( add_query_arg( array( 'reran-activation' => 'friends' ), wp_get_referer() ) );
			exit;
		}
		$required_role = Friends::required_menu_role();
		$unread_badge = $this->get_unread_badge();

		$menu_title = __( 'Friends', 'friends' ) . $unread_badge;
		$page_type = sanitize_title( $menu_title );
		$current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		add_menu_page( 'friends', $menu_title, $required_role, 'friends', null, 'dashicons-groups', 3 );
		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		add_submenu_page( 'friends', __( 'Home' ), __( 'Home' ), $required_role, 'friends', array( $this, 'render_admin_home' ) );
		add_action( 'load-' . $page_type . '_page_friends-page', array( $this, 'redirect_to_friends_page' ) );
		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		add_submenu_page( 'friends', __( 'Settings' ), __( 'Settings' ), $required_role, 'friends-settings', array( $this, 'render_admin_settings' ) );
		if (
			in_array(
				$current_page,
				apply_filters( 'friends_admin_settings_slugs', array( 'friends-settings', 'friends-notification-manager', 'friends-wp-friendships', 'friends-import-export' ) )
			)
		) {
			add_submenu_page( 'friends', __( 'Notifications', 'friends' ), '- ' . __( 'Notifications', 'friends' ), $required_role, 'friends-notification-manager', array( $this, 'render_admin_notification_manager' ) );
			add_submenu_page( 'friends', __( 'Import/Export', 'friends' ), '- ' . __( 'Import/Export', 'friends' ), $required_role, 'friends-import-export', array( $this, 'render_admin_import_export' ) );
			do_action( 'friends_admin_menu_settings', $page_type );
		}
		add_action( 'load-' . $page_type . '_page_friends-notification-manager', array( $this, 'process_admin_notification_manager' ) );
		add_action( 'load-' . $page_type . '_page_friends-import-export', array( $this, 'process_admin_import_export' ) );
		add_action( 'load-' . $page_type . '_page_friends-settings', array( $this, 'process_admin_settings' ) );

		if (
			isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'friends-refresh' ) && 'friends-refresh' === $current_page
		) {
			add_submenu_page( 'friends', __( 'Refresh', 'friends' ), __( 'Refresh', 'friends' ), $required_role, 'friends-refresh', array( $this, 'admin_refresh_friend_posts' ) );
		}

		if ( isset( $_GET['details'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'friends-plugin-overview' ) ) {
			add_action( 'load-' . $page_type . '_page_friends-plugins', array( 'Friends\Plugin_Installer', 'plugin_details' ) );
		}

		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		add_submenu_page( 'friends', __( 'Plugins' ), __( 'Plugins' ), $required_role, 'friends-plugins', array( $this, 'admin_plugin_installer' ) );

		$friend_submenu_items = array(
			'edit-friend'               => __( 'Edit User', 'friends' ),
			'edit-friend-feeds'         => __( 'Edit Feeds', 'friends' ),
			'edit-friend-notifications' => __( 'Edit Notifications', 'friends' ),
			'edit-friend-rules'         => __( 'Edit Rules', 'friends' ),
			'duplicate-remover'         => __( 'Duplicates', 'friends' ),
		);
		if ( isset( $friend_submenu_items[ $current_page ] ) ) {
			foreach ( $friend_submenu_items as $slug => $title ) {
				$user_param = '';
				if ( isset( $_GET['user'] ) ) {
					$username = sanitize_user( wp_unslash( $_GET['user'] ) );
					$user_param = '&user=' . $username . '&_wpnonce=' . wp_create_nonce( $slug . '-' . $username );
				}
				$slug_ = strtr( $slug, '-', '_' );

				add_submenu_page(
					'friends',
					$title,
					$title,
					$required_role,
					$slug . ( $slug === $current_page ? '' : $user_param ),
					array( $this, 'render_admin_' . $slug_ )
				);

				add_action(
					'load-' . $page_type . '_page_' . $slug,
					array( $this, 'process_admin_' . $slug_ )
				);
			}
		}

		if ( isset( $_GET['page'] ) && 'friends-logs' === $_GET['page'] ) {
			// translators: as in log file.
			$title = __( 'Log', 'friends' );
			add_submenu_page( 'friends', $title, $title, $required_role, 'friends-logs', array( $this, 'render_friends_logs' ) );
		}

		if ( isset( $_GET['page'] ) && 'friends-browser-extension' === $_GET['page'] ) {
			$title = __( 'Browser Extension', 'friends' );
			add_submenu_page( 'friends', $title, $title, $required_role, 'friends-browser-extension', array( $this, 'render_browser_extension' ) );
		}

		if ( isset( $_GET['page'] ) && 'unfriend' === $_GET['page'] ) {
			$user = new User( intval( $_GET['user'] ) );
			if ( $user ) {
				$title = /* translators: %s is a username. */ sprintf( __( 'Unfriend %s', 'friends' ), $user->user_login );
				add_submenu_page( 'friends', $title, $title, $required_role, 'unfriend', array( $this, 'render_admin_unfriend' ) );
				add_action( 'load-' . $page_type . '_page_unfriend', array( $this, 'process_admin_unfriend' ) );
			}
		}
	}

	/**
	 * Allow making use of the role__in query.
	 *
	 * @param      array $args   The arguments.
	 *
	 * @return     array  The modified array.
	 */
	public function allow_role_multi_select( $args ) {
		if ( isset( $args['role'] ) && ! isset( $args['role__in'] ) ) {
			if ( false !== strpos( $args['role'], ',' ) ) {
				$args['role__in'] = explode( ',', $args['role'] );
				unset( $args['role'] );
			}

			$roles = self::get_associated_roles();
			if (
				( isset( $args['role__in'] ) && array_intersect( $args['role__in'], array_keys( $roles ) ) )
				|| ( isset( $args['role'] ) && isset( $roles[ $args['role'] ] ) )
			) {
				add_action( 'admin_head-users.php', array( $this, 'keep_friends_open_on_users_screen' ) );
			}
		}
		return $args;
	}

	/**
	 * Use JavaScript to keep the Friends menu open when responding to a Friend Request.
	 */
	public function keep_friends_open_on_users_screen() {
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function ( $ ) {
				$( '#toplevel_page_friends-settings, #toplevel_page_friends-settings > a' ).addClass( 'wp-has-current-submenu wp-menu-open' ).removeClass( 'wp-not-current-submenu' );
				$( '#menu-users > a' ).removeClass( 'wp-has-current-submenu wp-menu-open' );
				$( "#toplevel_page_friends-settings ul li a[href='<?php echo esc_html( self::get_users_url() ); ?>']" ).closest( 'li' ).addClass( 'current' );
			} );
		</script>
		<?php
	}

	/**
	 * Add our help information
	 *
	 * @param  \WP_Screen $screen The current wp-admin screen.
	 */
	public function register_help( $screen ) {
		if ( ! ( $screen instanceof \WP_Screen ) ) {
			return;
		}

		switch ( $screen->id ) {
			case 'toplevel_page_friends-settings':
				$screen->add_help_tab(
					array(
						'id'      => 'overview',
						'title'   => __( 'Overview', 'friends' ),
						'content' =>
							'<p>' .
							__( 'Welcome to the Friends Settings! You can configure the Friends plugin here to your liking.', 'friends' ) .
							'</p>' .
							'<p>' .
							sprintf(
								// translators: %1$s is a URL, %2$s is the name of a wp-admin screen.
								__( 'There are more settings available for each friend or subscription individually. To get there, click on the user on the <a href=%1$s>%2$s</a> screen.', 'friends' ),
								'"' . esc_attr( self_admin_url( self::get_users_url() ) ) . '"',
								__( 'Friends &amp; Requests', 'friends' )
							) .
							'</p>',
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
		}
	}

	/**
	 * Reference our script for the /friends page
	 */
	public function admin_enqueue_scripts() {
		$handle = 'friends-admin';
		$file = 'friends-admin.js';
		$version = Friends::VERSION;
		wp_enqueue_script( $handle, plugins_url( $file, FRIENDS_PLUGIN_FILE ), array( 'jquery' ), apply_filters( 'friends_debug_enqueue', $version, $handle, dirname( FRIENDS_PLUGIN_FILE ) . '/' . $file ), true );

		$variables = array(
			'ajax_url'                        => admin_url( 'admin-ajax.php' ),
			'add_friend_url'                  => self_admin_url( 'admin.php?page=add-friend' ),
			'add_friend_text'                 => __( 'Add a Friend', 'friends' ),
			'copy_text'                       => __( 'Copy', 'friends' ),
			'copied_text'                     => __( 'Copied!', 'friends' ),
			'delete_feed_question'            => __( 'Delete the feed? You need to click "Save Changes" to really delete it.', 'friends' ),
			'role_friend'                     => __( 'Friend', 'friends' ),
			'role_acquaintance'               => __( 'Acquaintance', 'friends' ),
			'role_subscription'               => __( 'Following', 'friends' ),
			'role_connection'                 => __( 'Connection', 'friends' ),
			'role_contact'                    => __( 'Contact', 'friends' ),
			'role_connection_request'         => __( 'Connection Request', 'friends' ),
			'role_pending_connection_request' => __( 'Pending Connection Request', 'friends' ),
			'role_following'                  => __( 'Following', 'friends' ),
		);
		wp_localize_script( 'friends-admin', 'friends', $variables );

		$handle = 'friends-admin';
		$file = 'friends-admin.css';
		$version = Friends::VERSION;
		wp_enqueue_style( $handle, plugins_url( $file, FRIENDS_PLUGIN_FILE ), array(), apply_filters( 'friends_debug_enqueue', $version, $handle, dirname( FRIENDS_PLUGIN_FILE ) . '/' . $file ) );
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
			function ( $feed_url, $friend_user ) {
				// translators: %1s is the name of the friend, %2$s is the feed URL.
				printf( __( 'Refreshing %1$s at %2$s', 'friends' ) . '<br/>', '<a href="' . esc_url( $friend_user->get_local_friends_page_url() ) . '">' . esc_html( $friend_user->user_login ) . '</a>', '<a href="' . esc_url( $feed_url ) . '">' . esc_html( $feed_url ) . '</a>' );
				return $feed_url;
			},
			10,
			2
		);

		add_action(
			'friends_retrieved_new_posts',
			function ( $user_feed, $new_posts, $modified_posts ) {
				// translators: %s is the number of new posts found.
				echo esc_html( sprintf( _n( 'Found %d new post.', 'Found %d new posts.', count( $new_posts ), 'friends' ), count( $new_posts ) ) );
				?>
				<br />
				<?php
				// translators: %s is the number of modified posts.
				echo esc_html( sprintf( _n( '%d post was modified.', '%d posts were modified.', count( $modified_posts ), 'friends' ), count( $modified_posts ) ) );
				?>
				<br />
				<?php
			},
			10,
			3
		);

		add_action(
			'friends_incoming_feed_items',
			function ( $items ) {
				// translators: %s is the number of posts found.
				echo esc_html( sprintf( _n( 'Found %d item in the feed.', 'Found %d items in the feed.', count( $items ), 'friends' ) . ' ', count( $items ) ) );
			}
		);

		add_action(
			'friends_retrieve_friends_error',
			function ( $user_feed, $error ) {
				esc_html_e( 'An error occurred while retrieving the posts.', 'friends' );
				echo esc_html( $error->get_error_message() ), '<br/>';
			},
			10,
			2
		);

		if ( isset( $_GET['user'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$friend_user = User::get_by_username( sanitize_user( wp_unslash( $_GET['user'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! $friend_user || is_wp_error( $friend_user ) || ! $friend_user->can_refresh_feeds() ) {
				wp_die( esc_html__( 'Invalid user ID.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			}
			$friend_user->retrieve_posts_from_active_feeds();
		} else {
			$this->friends->feed->retrieve_friend_posts();
		}
	}

	/**
	 * Admin page for installing plugins.
	 */
	public function admin_plugin_installer() {
		Friends::template_loader()->get_template_part( 'admin/plugin-installer-header' );
		Plugin_Installer::init();
		Friends::template_loader()->get_template_part( 'admin/plugin-installer-footer' );
	}

	/**
	 * Don't show the edit link for friend posts
	 *
	 * @param  string   $link    The edit link.
	 * @param  int|User $user The user.
	 * @return string|bool The edit link or false.
	 */
	public static function admin_edit_user_link( $link, $user ) {
		if ( ! $user instanceof \WP_User ) {
			if ( is_string( $user ) ) {
				$user = User::get_by_username( $user );
			} else {
				$user = new \WP_User( $user );
			}
		}

		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return $link;
		}
		if ( ! $user->has_cap( 'friends_plugin' ) ) {
			return $link;
		}

		return self_admin_url( 'admin.php?page=edit-friend&user=' . $user->user_login );
	}

	public static function get_edit_friend_link( $user ) {
		if ( ! $user instanceof \WP_User ) {
			$user = new \WP_User( $user );
		}
		return apply_filters( 'get_edit_user_link', $user->user_url, $user->user_login );
	}

	public static function get_unfriend_link( $user ) {
		if ( ! $user->has_cap( 'friends_plugin' ) ) {
			return '';
		}

		return wp_nonce_url( self_admin_url( 'admin.php?page=unfriend&user=' . $user->user_login ), 'unfriend_' . $user->user_login );
	}

	/**
	 * Redirect to the Friends page
	 */
	public function redirect_to_friends_page() {
		wp_safe_redirect( home_url( '/friends/' ) );
		exit;
	}

	/**
	 * Check access for the Friends Admin settings page
	 */
	public function check_admin_settings() {
		if ( ! Friends::has_required_privileges() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the settings.', 'friends' ) );
		}
	}

	/**
	 * Process the Friends Admin settings page
	 */
	public function process_admin_settings() {
		if ( empty( $_REQUEST ) || ! isset( $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'friends-settings' ) ) {
			return;
		}

		$this->check_admin_settings();

		if ( current_user_can( 'manage_options' ) ) {
			foreach ( array( 'force_enable_post_formats', 'expose_post_format_feeds' ) as $checkbox ) {
				if ( isset( $_POST[ $checkbox ] ) && boolval( $_POST[ $checkbox ] ) ) {
					update_option( 'friends_' . $checkbox, true );
				} else {
					delete_option( 'friends_' . $checkbox );
				}
			}
		}

		if ( isset( $_POST['available_emojis'] ) && is_array( $_POST['available_emojis'] ) ) {
			$available_emojis = array();
			foreach ( wp_unslash( $_POST['available_emojis'] ) as $id ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$id = sanitize_key( $id );
				$data = Reactions::get_emoji_data( $id );
				if ( $data ) {
					$available_emojis[ $id ] = $data;
				}
			}
			update_option( 'friends_selected_emojis', $available_emojis );
		} else {
			delete_option( 'friends_selected_emojis' );
		}

		// Global retention.
		$retention_number_enabled = boolval( isset( $_POST['friends_enable_retention_number'] ) && $_POST['friends_enable_retention_number'] );
		update_option( 'friends_enable_retention_number', $retention_number_enabled );
		if ( $retention_number_enabled && isset( $_POST['friends_retention_number'] ) ) {
			update_option( 'friends_retention_number', max( 1, intval( $_POST['friends_retention_number'] ) ) );
		}
		$retention_days_enabled = boolval( isset( $_POST['friends_enable_retention_days'] ) && $_POST['friends_enable_retention_days'] );
		update_option( 'friends_enable_retention_days', $retention_days_enabled );
		if ( $retention_days_enabled && isset( $_POST['friends_retention_days'] ) ) {
			update_option( 'friends_retention_days', max( 1, intval( $_POST['friends_retention_days'] ) ) );
		}

		if ( isset( $_POST['retention_delete_reacted'] ) && 1 === intval( $_POST['retention_delete_reacted'] ) ) {
			delete_option( 'friends_retention_delete_reacted' );
		} else {
			update_option( 'friends_retention_delete_reacted', true );
		}

		if ( isset( $_POST['frontend_default_view'] ) && in_array(
			wp_unslash( $_POST['frontend_default_view'] ),
			array(
				'collapsed',
			)
		) ) {
			update_user_option( get_current_user_id(), 'friends_frontend_default_view', wp_unslash( $_POST['frontend_default_view'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} else {
			delete_user_option( get_current_user_id(), 'friends_frontend_default_view' );
		}

		foreach ( array_merge( array( '' ), get_post_format_slugs() ) as $post_type ) {
			$name = 'friends_frontend_theme';
			if ( $post_type ) {
				$name = 'friends_frontend_theme_' . $post_type;
			}
			$theme = 'default';
			if ( isset( $_POST[ $name ] ) && in_array( $theme, array_keys( Frontend::get_themes() ) ) ) {
				$theme = wp_unslash( $_POST[ $name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}
			if ( 'default' === $theme ) {
				delete_user_option( get_current_user_id(), $name );
			} else {
				update_user_option( get_current_user_id(), $name, $theme );
			}
		}

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( 'updated', '1', remove_query_arg( array( '_wp_http_referer', '_wpnonce' ) ) ) );
		}
		exit;
	}

	/**
	 * Gets the frontend locale.
	 *
	 * @return     string  The frontend locale.
	 */
	public function get_frontend_locale() {
		$locale = get_option( 'WPLANG' );
		return empty( $locale ) ? 'en_US' : $locale;
	}

	/**
	 * Render the Friends Admin home page
	 */
	public function render_admin_home() {
		$friends_subscriptions = User_Query::all_associated_users();
		$has_friend_users = $friends_subscriptions->get_total() > 0;
		wp_enqueue_script( 'plugin-install' );
		add_thickbox();
		wp_enqueue_script( 'updates' );

		Friends::template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'active' => 'friends',
			)
		);

		Friends::template_loader()->get_template_part( 'admin/welcome', null, array( 'installed_plugins' => get_plugins() ) );

		Friends::template_loader()->get_template_part( 'admin/settings-footer' );
	}

	/**
	 * Render the Friends Admin settings page
	 */
	public function render_admin_settings() {
		Friends::template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'active' => 'friends-settings',
			)
		);
		$this->check_admin_settings();

		if ( isset( $_GET['updated'] ) && boolval( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			?>
			<div id="message" class="updated notice is-dismissible"><p>
				<?php
				esc_html_e( 'Your settings were updated.', 'friends' );
				?>
			</p></div>
			<?php
		}

		$post_stats = Friends::get_post_stats();
		$post_type_themes = array();
		foreach ( get_post_format_slugs() as $slug ) {
			$post_type_themes[ 'frontend_theme_' . $slug ] = get_user_option( 'friends_frontend_theme_' . $slug );
		}

		Friends::template_loader()->get_template_part(
			'admin/settings',
			null,
			array_merge(
				Friends::get_post_stats(),
				$post_type_themes,
				array(
					'force_enable_post_formats'  => get_option( 'friends_force_enable_post_formats' ),
					'post_format_strings'        => get_post_format_strings(),
					'limit_homepage_post_format' => get_option( 'friends_limit_homepage_post_format', false ),
					'expose_post_format_feeds'   => get_option( 'friends_expose_post_format_feeds' ),
					'retention_days'             => Friends::get_retention_days(),
					'retention_number'           => Friends::get_retention_number(),
					'retention_days_enabled'     => get_option( 'friends_enable_retention_days' ),
					'retention_number_enabled'   => get_option( 'friends_enable_retention_number' ),
					'retention_delete_reacted'   => get_option( 'friends_retention_delete_reacted' ),
					'frontend_default_view'      => get_user_option( 'friends_frontend_default_view', get_current_user_id() ),
					'frontend_theme'             => get_user_option( 'friends_frontend_theme' ),
				)
			)
		);

		Friends::template_loader()->get_template_part( 'admin/settings-footer' );
	}

	/**
	 * Process access for the Friends Edit Rules page
	 */
	private function check_admin_edit_friend_rules() {
		if ( ! Friends::is_main_user() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit the rules.', 'friends' ) );
		}

		if ( ! isset( $_GET['user'] ) ) {
			wp_die( esc_html__( 'Invalid user.', 'friends' ) );
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'edit-friend-rules-' . sanitize_user( wp_unslash( $_GET['user'] ) ) ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'friends' ) );
		}

		$friend = User::get_by_username( sanitize_user( wp_unslash( $_GET['user'] ) ) );
		if ( ! $friend || is_wp_error( $friend ) ) {
			wp_die( esc_html__( 'Invalid username.', 'friends' ) );
		}

		if (
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
		if ( isset( $_POST['_wpnonce'] ) && ! empty( $_POST['friend-rules-raw'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'friend-rules-raw-' . $friend->user_login ) ) {
			$rules = validate_feed_rules( wp_unslash( $_POST['friend-rules-raw'] ) );
			if ( false === $rules ) {
				$arg = 'error';
			} else {
				$friend->update_user_option( 'friends_feed_rules', $rules );
			}
		} elseif ( isset( $_POST['_wpnonce'] ) && ! empty( $_POST['rules'] ) && ! empty( $_POST['catch_all'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'edit-friend-rules-' . sanitize_user( $friend->user_login ) ) ) {
				$friend->update_user_option(
					'friends_feed_catch_all',
					validate_feed_catch_all( wp_unslash( $_POST['catch_all'] ) )
				);
				$friend->update_user_option(
					'friends_feed_rules',
					validate_feed_rules( wp_unslash( $_POST['rules'] ) )
				);
		} else {
			return;
		}

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( $arg, $arg_value, remove_query_arg( '_wp_http_referer' ) ) );
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

		$this->header_edit_friend( $friend, 'edit-friend-rules' );

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
			'field'   => 'title',
			'regex'   => '',
			'action'  => in_array( $catch_all, array( 'trash', 'delete' ), true ) ? 'accept' : 'trash',
			'replace' => '',
		);

		if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'edit-friend-rules-' . sanitize_user( $friend->user_login ) ) ) {
			if ( isset( $_GET['post'] ) && intval( $_GET['post'] ) ) {
				$post = get_post( intval( $_GET['post'] ) );
			} else {
				$post = null;
			}
		}

		$args = array(
			'rules'     => $rules,
			'friend'    => $friend,
			'catch_all' => $catch_all,
			'post'      => $post,
		);
		Friends::template_loader()->get_template_part( 'admin/edit-rules', null, $args );

		echo '<div id="preview-rules">';
		$this->render_preview_friend_rules( $rules, $catch_all, $post );
		echo '</div>';

		array_pop( $args['rules'] );
		Friends::template_loader()->get_template_part( 'admin/edit-raw-rules', null, $args );
	}

	/**
	 * Respond to the Ajax request to the Friend rules preview
	 */
	public function ajax_preview_friend_rules() {
		if ( ! Friends::has_required_privileges() ) {
			wp_die( -1 );
		}
		if ( ! isset( $_GET['user'] ) ) {
			wp_die( esc_html__( 'Invalid user.', 'friends' ) );
		}

		check_ajax_referer( 'edit-friend-rules-' . sanitize_user( wp_unslash( $_GET['user'] ) ) );

		if ( isset( $_GET['post'] ) && intval( $_GET['post'] ) ) {
			$post = get_post( intval( $_GET['post'] ) );
		} else {
			$post = null;
		}
		$rules = array();
		if ( isset( $_POST['rules'] ) ) {
			$rules = validate_feed_rules( wp_unslash( $_POST['rules'] ) );
		}
		$catch_all = array();
		if ( isset( $_POST['catch_all'] ) ) {
			$catch_all = validate_feed_rules( wp_unslash( $_POST['catch_all'] ) );
		}
		$this->render_preview_friend_rules( $rules, $catch_all, $post );
		wp_die( 1 );
	}

	/**
	 * Respond to the Ajax request to fetch feeds
	 */
	public function ajax_fetch_feeds() {
		if ( ! isset( $_POST['friend'] ) ) {
			wp_send_json_error( 'missing-parameters' );
		}

		check_ajax_referer( 'fetch-feeds-' . sanitize_user( wp_unslash( $_POST['friend'] ) ) );

		$friend_user = User::get_by_username( sanitize_user( wp_unslash( $_POST['friend'] ) ) );
		if ( ! $friend_user ) {
			wp_send_json_error( 'unknown-user' );
		}

		add_filter( 'notify_about_new_friend_post', '__return_false', 999 );

		$friend_user->retrieve_posts_from_active_feeds();

		wp_send_json_success();
	}

	/**
	 * Render the Friend rules preview
	 *
	 * @param  array    $rules     The rules to apply.
	 * @param  string   $catch_all The catch all behavior.
	 * @param  \WP_Post $post       The post.
	 */
	public function render_preview_friend_rules( $rules, $catch_all, ?\WP_Post $post = null ) {
		$friend = $this->check_admin_edit_friend_rules();
		$friend_posts = new \WP_Query();

		$friend_posts->set( 'post_type', Friends::CPT );
		$friend_posts->set( 'post_status', array( 'publish', 'private', 'trash' ) );
		$friend_posts->set( 'posts_per_page', 25 );
		$friend_posts = $friend->modify_query_by_author( $friend_posts );

		$args = array(
			'friend'       => $friend,
			'friend_posts' => $friend_posts,
			'feed'         => $this->friends->feed,
			'post'         => $post,
		);

		$friend->set_feed_rules( $rules );
		$friend->set_feed_catch_all( $catch_all );

		Friends::template_loader()->get_template_part( 'admin/preview-rules', null, $args );
	}

	/**
	 * Process access for the Friends Edit User page
	 */
	private function check_admin_edit_friend() {
		if ( ! friends::has_required_privileges() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this user.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		if ( ! isset( $_GET['user'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_die( esc_html__( 'Invalid user.', 'friends' ) );
		}

		$friend = User::get_by_username( sanitize_user( wp_unslash( $_GET['user'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $friend || is_wp_error( $friend ) ) {
			wp_die( esc_html__( 'Invalid username.', 'friends' ) );
		}

		if ( ! $friend->has_cap( 'friends_plugin' ) ) {
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

		if ( isset( $_GET['convert-to-user'] ) && wp_verify_nonce( sanitize_key( $_GET['convert-to-user'] ), 'convert-to-user-' . $friend->user_login ) ) {
			if ( $friend instanceof Subscription ) {
				Subscription::convert_to_user( $friend );
			}
		} elseif ( isset( $_GET['convert-from-user'] ) && wp_verify_nonce( sanitize_key( $_GET['convert-from-user'] ), 'convert-from-user-' . $friend->user_login ) ) {
			if ( $friend instanceof User && ! $friend instanceof Subscription ) {
				if ( $friend->has_cap( 'friends_plugin' ) ) {
					Subscription::convert_from_user( $friend );
				} else {
					$arg = 'error';
					$arg_value = __( 'A friend cannot be converted to a virtual user.', 'friends' );
				}
			}
		} elseif ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'edit-friend-' . $friend->user_login ) ) {
			if ( isset( $_POST['friends_display_name'] ) ) {
				$friends_display_name = trim( sanitize_text_field( wp_unslash( $_POST['friends_display_name'] ) ) );
				if ( $friends_display_name ) {
					$friend->first_name   = $friends_display_name;
					$friend->display_name = $friends_display_name;
				}
			}
			if ( isset( $_POST['friends_description'] ) ) {
				$friend->description = trim( sanitize_text_field( wp_unslash( $_POST['friends_description'] ) ) );
			}
			if ( isset( $_POST['user_url'] ) ) {
				$user_url = sanitize_text_field( wp_unslash( $_POST['user_url'] ) );
				if ( filter_var( $user_url, FILTER_VALIDATE_URL ) ) {
					$friend->user_url = $user_url;
				}
			}
			$friend->save();
		} else {
			return;
		}

		do_action( 'friends_edit_friend_after_form_submit', $friend );

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( add_query_arg( $arg, rawurlencode( $arg_value ), wp_get_referer() ) );
		} else {
			wp_safe_redirect( add_query_arg( $arg, rawurlencode( $arg_value ), remove_query_arg( array( '_wp_http_referer', '_wpnonce' ) ) ) );
		}
		exit;
	}

	/**
	 * The Friends Edit User header
	 *
	 * @param      User   $friend  The friend.
	 * @param      string $active  The active menu entry.
	 */
	public function header_edit_friend( User $friend, $active ) {
		$append = '&user=' . sanitize_user( $friend->user_login );
		Friends::template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'active' => $active . $append,
				'title'  => $friend->user_login,
				'menu'   => array(
					__( 'Posts' )                    => $friend->get_local_friends_page_url(), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					__( 'Settings' )                 => 'edit-friend' . $append, // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					__( 'Feeds', 'friends' )         => 'edit-friend-feeds' . $append,
					__( 'Notifications', 'friends' ) => 'edit-friend-notifications' . $append,
					__( 'Rules', 'friends' )         => 'edit-friend-rules' . $append . '&_wpnonce=' . wp_create_nonce( 'edit-friend-rules-' . $friend->user_login ),
				),
			)
		);
	}

	/**
	 * Render the Friends Edit User page
	 */
	public function render_admin_edit_friend() {
		$friend = $this->check_admin_edit_friend();

		$args = array_merge(
			$friend->get_post_stats(),
			array(
				'friend'               => $friend,
				'friends_settings_url' => add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=friends-settings' ) ),
				'registered_parsers'   => $this->friends->feed->get_registered_parsers(),
			)
		);

		$this->header_edit_friend( $friend, 'edit-friend' );
		// phpcs:disable WordPress.Security.NonceVerification
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
			<div id="message" class="updated error is-dismissible"><p>
			<?php
			if ( 1 === intval( $_GET['error'] ) ) {
				esc_html_e( 'An error occurred.', 'friends' );
			} else {
				echo esc_html( Rest::translate_error_message( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) );
			}
			?>
			</p></div>
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
		// phpcs:enable WordPress.Security.NonceVerification

		Friends::template_loader()->get_template_part( 'admin/edit-friend', null, $args );
	}

	public function ajax_set_avatar() {
		if ( ! isset( $_POST['user'] ) ) {
			wp_send_json_error( __( 'No user specified.', 'friends' ) );
		}

		check_ajax_referer( 'set-avatar-' . sanitize_user( wp_unslash( $_POST['user'] ) ) );

		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_send_json_error();
			exit;
		}
		if ( empty( $_POST['avatar'] ) ) {
			wp_send_json_error();
			exit;
		}
		$avatar = check_url( wp_unslash( $_POST['avatar'] ) );
		if ( empty( $avatar ) ) {
			wp_send_json_error();
			exit;
		}

		$friend = User::get_by_username( sanitize_user( wp_unslash( $_POST['user'] ) ) );
		if ( ! $friend || is_wp_error( $friend ) ) {
			wp_send_json_error( __( 'Invalid user.', 'friends' ) );
			exit;
		}

		// Use WordPress functions to check the image dimensions.
		$size = \wp_getimagesize( $avatar );
		if ( ! $size ) {
			wp_send_json_error( __( 'Image is in an unknown format.', 'friends' ) );
			exit;
		}
		// Needs to be square and not larger than 512x512.
		if ( $size[0] !== $size[1] || $size[0] > 512 ) {
			wp_send_json_error( __( 'Image must be square and not larger than 512x512.', 'friends' ) );
			exit;
		}

		$url = $friend->update_user_icon_url( $avatar );

		if ( ! $url || is_wp_error( $url ) ) {
			wp_send_json_error( $url );
			exit;
		}

		wp_send_json_success(
			array(
				'url' => $url,
			)
		);
	}

	public function ajax_preview_subscription() {
		if ( ! isset( $_POST['url'] ) ) {
			wp_send_json_error( __( 'No URL provided.', 'friends' ) );
		}

		check_ajax_referer( 'friends_add_subscription' );

		$url = wp_unslash( $_POST['url'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$protocol = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! $protocol ) {
			$url = apply_filters( 'friends_rewrite_incoming_url', 'https://' . $url, $url );
		} else {
			$url = apply_filters( 'friends_rewrite_incoming_url', $url, $url );
		}

		$ret = $this->friends->feed->discover_available_feeds( $url );

		wp_send_json_success( $ret );
	}

	/**
	 * Process the Friends Edit Notifications page
	 */
	public function process_admin_edit_friend_notifications() {
		$friend    = $this->check_admin_edit_friend();
		$arg       = 'updated';
		$arg_value = 1;

		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'edit-friend-notifications-' . $friend->user_login ) ) {
			if ( ! get_user_option( 'friends_no_new_post_notification' ) ) {
				if ( isset( $_POST['friends_new_post_notification'] ) && boolval( $_POST['friends_new_post_notification'] ) ) {
					delete_user_option( get_current_user_id(), 'friends_no_new_post_notification_' . $friend->user_login );
				} else {
					update_user_option( get_current_user_id(), 'friends_no_new_post_notification_' . $friend->user_login, 1 );
				}
			}

			if ( ! get_user_option( 'friends_no_keyword_notification' ) ) {
				if ( isset( $_POST['friends_keyword_notification'] ) && boolval( $_POST['friends_keyword_notification'] ) ) {
					delete_user_option( get_current_user_id(), 'friends_no_keyword_notification_' . $friend->user_login );
				} else {
					update_user_option( get_current_user_id(), 'friends_no_keyword_notification_' . $friend->user_login, 1 );
				}
			}

			do_action( 'friends_edit_friend_notifications_after_form_submit', $friend );
		} else {
			return;
		}

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( $arg, $arg_value, remove_query_arg( array( '_wp_http_referer', '_wpnonce' ) ) ) );
		}
		exit;
	}

	/**
	 * Render the Friends Edit Notifications page
	 */
	public function render_admin_edit_friend_notifications() {
		$friend = $this->check_admin_edit_friend();
		$post_stats = $friend->get_post_stats();

		$this->header_edit_friend( $friend, 'edit-friend-notifications' );

		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['updated'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'Notification Settings were updated.', 'friends' ); ?></p></div>
			<?php
		} elseif ( isset( $_GET['error'] ) ) {
			?>
			<div id="message" class="updated error is-dismissible"><p><?php esc_html_e( 'An error occurred.', 'friends' ); ?></p></div>
			<?php
		}
		// phpcs:enable WordPress.Security.NonceVerification

		Friends::template_loader()->get_template_part(
			'admin/edit-notifications',
			null,
			array(
				'friend' => $friend,
			)
		);
	}

	/**
	 * Process the Friends Edit Feeds page
	 */
	public function process_admin_edit_friend_feeds() {
		$friend    = $this->check_admin_edit_friend();
		$arg       = 'updated';
		$arg_value = 1;

		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'edit-friend-feeds-' . $friend->user_login ) ) {
			$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
			if ( ! $hide_from_friends_page ) {
				$hide_from_friends_page = array();
			}
			if ( ! isset( $_POST['show_on_friends_page'] ) || ! boolval( $_POST['show_on_friends_page'] ) ) {
				if ( ! in_array( $friend->user_login, $hide_from_friends_page ) ) {
					$hide_from_friends_page[] = $friend->user_login;
					update_user_option( get_current_user_id(), 'friends_hide_from_friends_page', $hide_from_friends_page );
				}
			} elseif ( in_array( $friend->user_login, $hide_from_friends_page ) ) {
					$hide_from_friends_page = array_values( array_diff( $hide_from_friends_page, array( $friend->user_login ) ) );
					update_user_option( get_current_user_id(), 'friends_hide_from_friends_page', $hide_from_friends_page );
			}

			if ( $friend->set_retention_number_enabled( boolval( filter_input( INPUT_POST, 'friends_enable_retention_number', FILTER_SANITIZE_NUMBER_INT ) ) ) && isset( $_POST['friends_retention_number'] ) ) {
				$friend->set_retention_number( filter_input( INPUT_POST, 'friends_retention_number', FILTER_SANITIZE_NUMBER_INT ) );
			}
			if ( $friend->set_retention_days_enabled( boolval( filter_input( INPUT_POST, 'friends_enable_retention_days', FILTER_SANITIZE_NUMBER_INT ) ) ) && isset( $_POST['friends_retention_days'] ) ) {
				$friend->set_retention_days( filter_input( INPUT_POST, 'friends_retention_days', FILTER_SANITIZE_NUMBER_INT ) );
			}

			$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
			if ( ! $hide_from_friends_page ) {
				$hide_from_friends_page = array();
			}

			$show_on_dashboard = filter_input( INPUT_POST, 'show_on_dashboard', FILTER_VALIDATE_BOOLEAN );
			$already_on_dashboard = false;
			$widgets = get_user_option( 'friends_dashboard_widgets', get_current_user_id() );
			if ( ! $widgets ) {
				$widgets = array();
			}
			foreach ( $widgets as $k => $widget ) {
				if ( $widget['friend'] === $friend->user_login ) {
					$already_on_dashboard = true;
					if ( ! $show_on_dashboard ) {
						unset( $widgets[ $k ] );
						update_user_option( get_current_user_id(), 'friends_dashboard_widgets', $widgets );
					}
					break;
				}
			}
			if ( $show_on_dashboard && ! $already_on_dashboard ) {
				$widgets[] = array( 'friend' => $friend->user_login );
				update_user_option( get_current_user_id(), 'friends_dashboard_widgets', $widgets );
			}

			if ( isset( $_POST['feeds'] ) ) {
				// Sanitized below.
				$feeds = wp_unslash( $_POST['feeds'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$existing_feeds = $friend->get_feeds();
				if ( isset( $feeds['new'] ) ) {
					if ( ! isset( $feeds['new']['url'] ) || '' === trim( $feeds['new']['url'] ) ) {
						unset( $feeds['new'] );
					} else {
						foreach ( $existing_feeds as $term_id => $user_feed ) {
							if ( $user_feed->get_url() === trim( $feeds['new']['url'] ) ) {
								if ( isset( $feeds[ $term_id ] ) ) {
									// Let a newly entered feed overrule an existing one.
									$feeds[ $term_id ] = array_merge( $feeds[ $term_id ], $feeds['new'] );
									$feeds[ $term_id ]['active'] = 1;
								}
								unset( $feeds['new'] );
								break;
							}
						}
					}
				}
				foreach ( $feeds as $term_id => $feed ) {
					if ( 'new' === $term_id ) {
						if ( ! isset( $feed['url'] ) || '' === trim( $feed['url'] ) ) {
							continue;
						}

						$feed['active'] = true;
						$protocol = wp_parse_url( $feed['url'], PHP_URL_SCHEME );
						if ( ! $protocol ) {
							$feed['url'] = apply_filters( 'friends_rewrite_incoming_url', 'https://' . $feed['url'], $feed['url'] );
						}
						$new_feed = $friend->subscribe( $feed['url'], $feed );
						if ( is_wp_error( $new_feed ) ) {
							do_action( 'friends_process_feed_item_submit_error', $new_feed, $feed );
							continue;
						}

						do_action( 'friends_user_feed_activated', $new_feed );
						do_action( 'friends_process_feed_item_submit', $new_feed, $feed );
						continue;
					}

					if ( ! isset( $existing_feeds[ $term_id ] ) ) {
						continue;
					}
					$user_feed = $existing_feeds[ $term_id ];
					unset( $existing_feeds[ $term_id ] );

					$protocol = wp_parse_url( $feed['url'], PHP_URL_SCHEME );
					if ( ! $protocol ) {
						$feed['url'] = apply_filters( 'friends_rewrite_incoming_url', 'https://' . $feed['url'], $feed['url'] );
					}

					if ( $user_feed->get_url() !== $feed['url'] ) {
						do_action( 'friends_user_feed_deactivated', $user_feed );

						if ( ! isset( $feed['mime-type'] ) ) {
							$feed['mime-type'] = $user_feed->get_mime_type();
						}

						if ( $feed['active'] ) {
							$new_feed = $friend->subscribe( $feed['url'], $feed );
							if ( ! is_wp_error( $new_feed ) ) {
								do_action( 'friends_user_feed_activated', $new_feed );
							}
						} else {
							$new_feed = $friend->save_feed( $feed['url'], $feed );
						}

						// Since the URL has changed, the above will create a new feed, therefore we need to delete the old one.
						$user_feed->delete();

						if ( is_wp_error( $new_feed ) ) {
							do_action( 'friends_process_feed_item_submit_error', $new_feed, $feed );
							continue;
						}

						do_action( 'friends_process_feed_item_submit', $new_feed, $feed );
						continue;
					}

					if ( $user_feed->get_title() !== $feed['title'] ) {
						$user_feed->update_metadata( 'title', $feed['title'] );
					}

					if ( $user_feed->get_parser() !== $feed['parser'] ) {
						$user_feed->update_metadata( 'parser', $feed['parser'] );
					}

					if ( $user_feed->get_post_format() !== $feed['post-format'] ) {
						$user_feed->update_metadata( 'post-format', $feed['post-format'] );
					}

					if ( isset( $feed['mime-type'] ) && $user_feed->get_mime_type() !== $feed['mime-type'] ) {
						$user_feed->update_metadata( 'mime-type', $feed['mime-type'] );
					}

					$was_active = $user_feed->is_active();
					$is_active = isset( $feed['active'] ) && $feed['active'];
					$user_feed->update_metadata( 'active', $is_active );
					if ( $was_active !== $is_active ) {
						if ( $is_active ) {
							do_action( 'friends_user_feed_activated', $user_feed );
						} else {
							do_action( 'friends_user_feed_deactivated', $user_feed );
						}
					}

					do_action( 'friends_process_feed_item_submit', $user_feed, $feed );
				}

				// Delete remaining existing feeds since they were not submitted.
				foreach ( $existing_feeds as $term_id => $user_feed ) {
					do_action( 'friends_user_feed_deactivated', $user_feed );
					$user_feed->delete();
				}
			}
			do_action( 'friends_edit_feeds_after_form_submit', $friend );
		} else {
			return;
		}

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( $arg, $arg_value, remove_query_arg( array( '_wp_http_referer', '_wpnonce' ) ) ) );
		}
		exit;
	}

	/**
	 * Render the Friends Edit Feeds page
	 */
	public function render_admin_edit_friend_feeds() {
		$friend = $this->check_admin_edit_friend();

		$already_on_dashboard = false;
		$widgets = get_user_option( 'friends_dashboard_widgets', get_current_user_id() );

		if ( ! $widgets ) {
			$widgets = array();
		}
		foreach ( $widgets as $widget ) {
			if ( ! empty( $widget['friend'] ) && $widget['friend'] === $friend->user_login ) {
				$already_on_dashboard = true;
				break;
			}
		}

		$args = array_merge(
			$friend->get_post_stats(),
			array(
				'friend'                          => $friend,
				'rules'                           => $friend->get_feed_rules(),
				'hide_from_friends_page'          => get_user_option( 'friends_hide_from_friends_page' ),
				'post_formats'                    => array_merge( array( 'autodetect' => __( 'Autodetect Post Format', 'friends' ) ), get_post_format_strings() ),
				'friends_settings_url'            => add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=friends-settings' ) ),
				'registered_parsers'              => $this->friends->feed->get_registered_parsers(),
				'global_retention_days'           => Friends::get_retention_days(),
				'global_retention_number'         => Friends::get_retention_number(),
				'global_retention_days_enabled'   => get_option( 'friends_enable_retention_days' ),
				'global_retention_number_enabled' => get_option( 'friends_enable_retention_number' ),
				'show_on_dashboard'               => $already_on_dashboard,
			)
		);
		if ( ! $args['hide_from_friends_page'] ) {
			$args['hide_from_friends_page'] = array();
		}
		$this->header_edit_friend( $friend, 'edit-friend-feeds' );

		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['updated'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'Feeds were updated.', 'friends' ); ?></p></div>
			<?php
		} elseif ( isset( $_GET['error'] ) ) {
			?>
			<div id="message" class="updated error is-dismissible"><p><?php esc_html_e( 'An error occurred.', 'friends' ); ?></p></div>
			<?php
		}
		// phpcs:enable WordPress.Security.NonceVerification

		Friends::template_loader()->get_template_part( 'admin/edit-feeds', null, $args );
	}

	/**
	 * Process the Unfriend page
	 */
	public function process_admin_unfriend() {
		$friend    = $this->check_admin_edit_friend();
		$arg       = 'deleted';
		$arg_value = $friend->user_login;

		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'unfriend-' . $friend->user_login ) ) {
			$friend->delete();
		} else {
			return;
		}

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( $arg, $arg_value, self_admin_url( 'admin.php?page=friends-list' ) ) );
		}
		exit;
	}

	/**
	 * Render the Unfriend page
	 */
	public function render_admin_unfriend() {
		$friend = $this->check_admin_edit_friend();
		$post_stats = $friend->get_post_stats();

		$args = array(
			'friend'       => $friend,
			'friend_posts' => $post_stats['post_count'],
			'total_size'   => $post_stats['total_size'],
		);

		Friends::template_loader()->get_template_part( 'admin/unfriend', null, $args );
	}

	/**
	 * Display error messages.
	 *
	 * @param      object $errors  The errors.
	 */
	private function display_errors( $errors ) {
		if ( ! is_wp_error( $errors ) ) {
			return;
		}

		?>
		<div id="message" class="updated error is-dismissible"><p><?php echo esc_html( $errors->get_error_message() ); ?></p>
			<?php
			$error_data = $errors->get_error_data();
			if ( isset( $error_data->error ) ) {
				$error = unserialize( $error_data->error ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				if ( is_wp_error( $error ) ) {
					?>
					<pre>
						<?php
						print_r( $error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
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
	}

	public function create_and_follow( $user_id, $url ) {
		// TODO: replace with frontend functionality.
	}

	/**
	 * Process the admin notification manager form submission.
	 */
	public function process_admin_notification_manager() {
		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'notification-manager' ) ) {
			return;
		}

		$this->check_admin_settings();

		if ( ! empty( $_POST['notification_keywords'] ) && is_array( $_POST['notification_keywords'] ) ) {
			$keywords = array();
			foreach ( wp_unslash( $_POST['notification_keywords'] ) as $i => $keyword ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( trim( $keyword ) ) {
					$keywords[] = array(
						'enabled' => isset( $_POST['notification_keywords_enabled'][ $i ] ) && boolval( $_POST['notification_keywords_enabled'][ $i ] ),
						'keyword' => sanitize_text_field( $keyword ),
					);
				}
			}
			update_option( 'friends_notification_keywords', $keywords );
		}

		if ( isset( $_POST['keyword_notification_override'] ) && boolval( $_POST['keyword_notification_override'] ) ) {
			delete_user_option( get_current_user_id(), 'friends_keyword_notification_override_disabled' );
		} else {
			update_user_option( get_current_user_id(), 'friends_keyword_notification_override_disabled', 1 );
		}

		if ( isset( $_POST['new_post_notification'] ) && boolval( $_POST['new_post_notification'] ) ) {
			delete_user_option( get_current_user_id(), 'friends_no_new_post_notification' );
		} else {
			update_user_option( get_current_user_id(), 'friends_no_new_post_notification', 1 );
		}

		if ( isset( $_POST['friend_follower_notification'] ) && boolval( $_POST['friend_follower_notification'] ) ) {
			delete_user_option( get_current_user_id(), 'friends_no_friend_follower_notification' );
		} else {
			update_user_option( get_current_user_id(), 'friends_no_friend_follower_notification', 1 );
		}

		foreach ( get_post_format_slugs() as $post_format ) {
			if ( isset( $_POST[ 'new_post_format_notification_' . $post_format ] ) && boolval( $_POST[ 'new_post_format_notification_' . $post_format ] ) ) {
				delete_user_option( get_current_user_id(), 'friends_no_new_post_format_notification_' . $post_format );
			} else {
				update_user_option( get_current_user_id(), 'friends_no_new_post_format_notification_' . $post_format, 1 );
			}
		}

		foreach ( array_keys( $this->friends->feed->get_registered_parsers() ) as $parser ) {
			if ( isset( $_POST[ 'new_post_by_parser_notification_' . $parser ] ) && boolval( $_POST[ 'new_post_by_parser_notification_' . $parser ] ) ) {
				delete_user_option( get_current_user_id(), 'friends_no_new_post_by_parser_notification_' . $parser );
			} else {
				update_user_option( get_current_user_id(), 'friends_no_new_post_by_parser_notification_' . $parser, 1 );
			}
		}

		if ( empty( $_POST['friend_listed'] ) ) {
			return;
		}
		// This is an array, it is checked before use below.
		$friend_usernames = wp_unslash( $_POST['friend_listed'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$current_user_id = get_current_user_id();
		$hide_from_friends_page = array();

		foreach ( $friend_usernames as $friend_username ) {
			$friend_user = User::get_by_username( $friend_username );
			if ( ! $friend_user ) {
				continue;
			}
			$friend_username = $friend_user->user_login;
			if ( ! isset( $_POST['show_on_friends_page'][ $friend_username ] ) ) {
				$hide_from_friends_page[] = $friend_username;
			}

			$no_new_post_notification = ! isset( $_POST['new_friend_post_notification'][ $friend_username ] ) || '0' === $_POST['new_friend_post_notification'][ $friend_username ];
			if ( get_user_option( 'friends_no_new_post_notification_' . $friend_username ) !== $no_new_post_notification ) {
				update_user_option( $current_user_id, 'friends_no_new_post_notification_' . $friend_username, $no_new_post_notification );
			}

			$no_keyword_notification = ! isset( $_POST['keyword_notification'][ $friend_username ] );
			if ( get_user_option( 'friends_no_keyword_notification_' . $friend_username ) !== $no_keyword_notification ) {
				update_user_option( $current_user_id, 'friends_no_keyword_notification_' . $friend_username, $no_keyword_notification );
			}
		}

		update_user_option( $current_user_id, 'friends_hide_from_friends_page', $hide_from_friends_page );

		do_action( 'friends_notification_manager_after_form_submit', $friend_usernames );

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( 'updated', '1', remove_query_arg( array( '_wp_http_referer', '_wpnonce' ) ) ) );
		}
		exit;
	}

	/**
	 * Render the admin notification manager.
	 */
	public function render_admin_notification_manager() {
		Friends::template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'active' => 'friends-notification-manager',
				'title'  => __( 'Friends', 'friends' ),
			)
		);
		$this->check_admin_settings();

		$friend_users = new User_Query(
			array(
				'role__in' => array( 'friend', 'acquaintance', 'subscription' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);

		$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
		if ( ! $hide_from_friends_page ) {
			$hide_from_friends_page = array();
		}

		$args = array(
			'friend_users'              => $friend_users->get_results(),
			'friends_settings_url'      => add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=friends-settings' ) ),
			'hide_from_friends_page'    => $hide_from_friends_page,
			'keyword_override_disabled' => get_user_option( 'friends_keyword_notification_override_disabled' ),
			'no_new_post_notification'  => get_user_option( 'friends_no_new_post_notification' ),
			'no_keyword_notification'   => get_user_option( 'friends_no_keyword_notification' ),
			'notification_keywords'     => Feed::get_all_notification_keywords(),
			'active_keywords'           => Feed::get_active_notification_keywords(),
			'feed_parsers'              => $this->friends->feed->get_registered_parsers(),
		);

		if ( class_exists( '\Activitypub\Notification' ) ) {
			$args['no_friend_follower_notification'] = get_user_option( 'friends_no_friend_follower_notification' );
		}

		Friends::template_loader()->get_template_part(
			'admin/notification-manager',
			null,
			$args
		);

		Friends::template_loader()->get_template_part( 'admin/settings-footer' );
	}

	public function render_admin_import_export() {
		Friends::template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'active' => 'friends-import-export',
				'title'  => __( 'Friends', 'friends' ),
			)
		);
		$this->check_admin_settings();

		?>
		<h1><?php esc_html_e( 'Import/Export', 'friends' ); ?></h1>
		<?php

		Friends::template_loader()->get_template_part(
			'admin/import-export',
			null,
			array(
				'private_rss_key' => get_option( 'friends_private_rss_key' ),
			)
		);

		Friends::template_loader()->get_template_part( 'admin/settings-footer' );
	}

	public function process_admin_import_export() {
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'friends-settings' ) ) {
			return;
		}

		if ( ! Friends::has_required_privileges() ) {
			return;
		}

		if ( isset( $_FILES['opml']['tmp_name'] ) ) {
			$opml = file_get_contents( $_FILES['opml']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$feeds = Import::opml( $opml );
			$users_created = count( $feeds );
			$feeds_imported = 0;
			foreach ( $feeds as $user => $user_feeds ) {
				$feeds_imported += count( $user_feeds );
			}
			?>
			<div class="friends-notice notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							// translators: %d is the number of users imported.
							_n( 'Imported %d user.', 'Imported %d users.', $users_created, 'friends' ),
							$users_created
						)
					);
					?>
					<?php
					echo esc_html(
						sprintf(
							// translators: %d is the number of feeds imported.
							_n( 'They had %d feed.', 'They had %d feeds.', $feeds_imported, 'friends' ),
							$feeds_imported
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	public function process_admin_duplicate_remover() {
		$friend = $this->check_admin_duplicate_remover();

		// Nonce verification done in check_admin_duplicate_remover.
		// phpcs:disable WordPress.Security.NonceVerification.Missing

		// We iterate over this array and then we sanitize _id.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $_POST['deleteduplicate'] ) || ! is_array( $_POST['deleteduplicate'] ) ) {
			return;
		}

		$deleted = 0;
		foreach ( array_keys( wp_unslash( $_POST['deleteduplicate'] ) ) as $_id ) {
			if ( ! is_numeric( $_id ) ) {
				continue;
			}

			if ( wp_delete_post( intval( $_id ) ) ) {
				++$deleted;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $deleted ) {
			wp_safe_redirect( add_query_arg( 'deleted', $deleted ) );
			exit;
		}
	}
	public function check_admin_duplicate_remover() {
		if ( ! Friends::is_main_user() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit the rules.', 'friends' ) );
		}

		if ( ! isset( $_GET['user'] ) ) {
			wp_die( esc_html__( 'Invalid user.', 'friends' ) );
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'duplicate-remover-' . sanitize_user( wp_unslash( $_GET['user'] ) ) ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'friends' ) );
		}

		$friend = User::get_by_username( sanitize_user( wp_unslash( $_GET['user'] ) ) );
		if ( ! $friend || is_wp_error( $friend ) ) {
			wp_die( esc_html__( 'Invalid username.', 'friends' ) );
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
	 * Render the duplicates remover
	 */
	public function render_admin_duplicate_remover() {
		$friend = $this->check_admin_duplicate_remover();

		$this->header_edit_friend( $friend, 'duplicate-remover' );
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['deleted'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
				<?php
				$deleted = intval( $_GET['deleted'] );
				echo esc_html(
					sprintf(
						// translators: %d is the number of duplicates deleted.
						_n( 'Deleted %d selected duplicate.', 'Deleted %d selected duplicates.', $deleted, 'friends' ),
						$deleted
					)
				);
				?>
			</p></div>
			<?php
		}
		// phpcs:enable WordPress.Security.NonceVerification

		$friend_posts = new \WP_Query();

		$friend_posts->set( 'post_type', Friends::CPT );
		$friend_posts->set( 'post_status', array( 'publish', 'private', 'trash' ) );
		$friend_posts->set( 'posts_per_page', 100 );
		$friend_posts = $friend->modify_query_by_author( $friend_posts );

		$uniques = array();
		foreach ( $friend_posts->get_posts() as $_post ) {
			$permalink = get_permalink( $_post );
			if ( ! isset( $uniques[ $permalink ] ) ) {
				$uniques[ $permalink ] = $_post->ID;
			}
		}

		$args = array(
			'friend'       => $friend,
			'friend_posts' => $friend_posts,
			'uniques'      => array_flip( $uniques ),
			'feed'         => $this->friends->feed,
		);

		Friends::template_loader()->get_template_part( 'admin/duplicates', null, $args );
	}


	public static function check_browser_api_key( $key ) {
		$parts = explode( '-', $key, 3 );
		if ( 3 !== count( $parts ) ) {
			return false;
		}

		$user_id = (int) $parts[1];
		if ( ! $user_id ) {
			return false;
		}

		$desired_key = self::get_browser_api_key( $user_id );
		if ( ! $desired_key ) {
			return false;
		}

		return $key === $desired_key;
	}

	public static function revoke_browser_api_key( $user_id = false ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		delete_user_option( $user_id, 'friends_browser_api_key' );
	}

	public static function get_browser_api_key( $user_id = false ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$key = get_user_option( 'friends_browser_api_key', $user_id );
		if ( ! $key ) {
			$key = 'friends-' . $user_id . '-' . wp_generate_password( 32, false );
			update_user_option( $user_id, 'friends_browser_api_key', $key );
		}

		return $key;
	}

	public function render_browser_extension() {
		add_filter(
			'friends_admin_tabs',
			function ( $menu ) {
				$menu[ __( 'Browser Extension', 'friends' ) ] = 'friends-browser-extension';
				return $menu;
			}
		);
		Friends::template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'active' => 'friends-browser-extension',
			)
		);
		$this->check_admin_settings();
		$browser_api_key = self::get_browser_api_key();

		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'friends-browser-extension' ) ) {
			if ( isset( $_POST['revoke-api-key'] ) ) {
				self::revoke_browser_api_key();
				$browser_api_key = self::get_browser_api_key();
			}
		}

		Friends::template_loader()->get_template_part(
			'admin/browser-extension',
			null,
			array(
				'browser-api-key' => $browser_api_key,
			)
		);

		Friends::template_loader()->get_template_part( 'admin/settings-footer' );
	}

	public function render_friends_logs() {
		add_filter(
			'friends_admin_tabs',
			function ( $menu ) {
				$menu[ __( 'Logs', 'friends' ) ] = 'friends-logs';
				return $menu;
			}
		);

		Friends::template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'active' => 'friends-logs',
			)
		);
		$this->check_admin_settings();

		Friends::template_loader()->get_template_part(
			'admin/logs',
			null,
			array(
				'logs' => Logging::get_logs(),
			)
		);

		Friends::template_loader()->get_template_part( 'admin/settings-footer' );
	}

	/**
	 * Gets the friend roles.
	 *
	 * @return     array  The friend roles.
	 */
	public function get_friend_roles() {
		$roles = new \WP_Roles();
		$friend_roles = array();
		foreach ( $roles->roles as $role => $data ) {
			if ( isset( $data['capabilities']['friend'] ) ) {
				$friend_roles[ $role ] = $data['name'];
			}
		}
		return $friend_roles;
	}

	/**
	 * Gets the roles associated with the Friends plugin.
	 *
	 * @return     array  The associated roles.
	 */
	public static function get_associated_roles() {
		$roles = new \WP_Roles();
		$friend_roles = array();
		foreach ( $roles->roles as $role => $data ) {
			if ( isset( $data['capabilities']['friends_plugin'] ) ) {
				$friend_roles[ $role ] = $data['name'];
			}
		}
		return $friend_roles;
	}

	public static function get_users_url() {
		return 'admin.php?page=friends-list';
	}

	/**
	 * Override the post title for specific post formats.
	 *
	 * @param      string $title    The title.
	 * @param      int    $post_id  The post id.
	 *
	 * @return     string  The potentially overriden title.
	 */
	public function override_post_format_title( $title, $post_id = null ) {
		if ( $post_id && empty( $title ) && is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && 'edit-post' === $screen->id ) {
				if ( 'status' === get_post_format() ) {
					$post = get_post( $post_id );
					return wp_trim_words( wp_strip_all_tags( $post->post_content ) );
				}
			}
		}
		return $title;
	}

	/**
	 * Get the unread badge HTML
	 *
	 * @return string The unread badge HTML.
	 */
	public function get_unread_badge() {
		$unread_count = apply_filters( 'friends_unread_count', 0 );
		if ( 0 === intval( $unread_count ) ) {
			return '';
		}

		if ( get_user_option( 'friends_unobtrusive_badge' ) ) {
			return ' (' . $unread_count . ')';
		}
		$unread_badge = ' <div class="wp-core-ui wp-ui-notification friends-open-requests" style="display: inline; font-size: 11px; padding: .1em .5em .1em .4em; border-radius: 9px; background-color: #d63638; color: #fff; text-align: center; height: 18px"><span aria-hidden="true">' . $unread_count . '</span><span class="screen-reader-text">';
		// translators: %s is the number of unread items.
		$unread_badge .= sprintf( _n( '%s unread item', '%s unread items', $unread_count, 'friends' ), $unread_count );
		$unread_badge .= '</span></div>';
		return $unread_badge;
	}

	/**
	 * Add a Friends menu to the admin bar
	 *
	 * @param  \WP_Admin_Bar $wp_menu The admin bar to modify.
	 */
	public function admin_bar_friends_menu( \WP_Admin_Bar $wp_menu ) {
		if ( ! Friends::has_required_privileges() ) {
			return;
		}

		$my_url = home_url();
		$my_admin_url = site_url();

		$unread = $this->get_unread_badge();

		$wp_menu->add_node(
			array(
				'id'     => 'friends-menu',
				'parent' => '',
				'title'  => '<span class="ab-icon dashicons dashicons-groups"></span> <span class="ab-label">' . esc_html( __( 'Friends', 'friends' ) ) . $unread . '</span>',
				'href'   => $my_url . '/friends/',
			)
		);

		do_action( 'friends_own_site_menu_top', $wp_menu, $my_url, $my_admin_url );
		do_action( 'friends_current_site_menu_top', $wp_menu, $my_url, $my_admin_url );

		$wp_menu->add_menu(
			array(
				'id'     => 'your-feed',
				'parent' => 'friends-menu',
				'title'  => esc_html__( 'Main Feed', 'friends' ),
				'href'   => home_url( '/friends/' ),
			)
		);

		$wp_menu->add_menu(
			array(
				'id'     => 'add-friend',
				'parent' => 'friends-menu',
				'title'  => esc_html__( 'Add a friend', 'friends' ),
				'href'   => home_url( '/friends/add-subscription' ),
			)
		);
		$wp_menu->add_menu(
			array(
				'id'     => 'friends',
				'parent' => 'friends-menu',
				'title'  => esc_html__( 'Settings' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'href'   => $my_admin_url . '/wp-admin/admin.php?page=friends-settings',
			)
		);
	}

	/**
	 * Add Friend entries to the New Content admin section
	 *
	 * @param  \WP_Admin_Bar $wp_menu The admin bar to modify.
	 */
	public function admin_bar_new_content( \WP_Admin_Bar $wp_menu ) {
		if ( Friends::has_required_privileges() ) {
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
	 * Show friends admin bar item on mobile.
	 */
	public function admin_bar_mobile() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		?>
		<style type="text/css" media="screen">
			@media screen and (max-width: 782px) {
				#wpadminbar #wp-admin-bar-friends, #wpadminbar #wp-admin-bar-friends .ab-icon {
					display: block !important;
				}
				#wpadminbar #wp-admin-bar-friends .ab-label {
					display: none !important;
				}
			}
		</style>
		<?php
	}


	/**
	 * Fires at the end of the delete users form prior to the confirm button.
	 *
	 * @param \WP_User $current_user \WP_User object for the current user.
	 * @param array    $userids      Array of IDs for users being deleted.
	 */
	public function delete_user_form( $current_user, $userids ) {
		$only_friends_affiliated = true;
		foreach ( $userids as $user_id ) {
			$user = new \WP_User( $user_id );
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
				jQuery( function () {
					jQuery( '#delete_option1' ).closest( 'li' ).hide();
				}  );
			</script>
			<?php
		}
	}

	/**
	 * Actions when a (friend) user is deleted.
	 *
	 * @param      integer $user_id  The user identifier.
	 */
	public function delete_user( $user_id ) {
		$friend_user = User::get_user_by_id( $user_id );
		if ( ! $friend_user ) {
			return; // user was already deleted?
		}
		// Allow unsubscribing to all these feeds.
		foreach ( $friend_user->get_active_feeds() as $feed ) {
			do_action( 'friends_user_feed_deactivated', $feed );
			$feed->delete();
		}

		// Delete the rest.
		foreach ( $friend_user->get_feeds() as $feed ) {
			$feed->delete();
		}

		foreach ( $friend_user->get_all_post_ids() as $post_id ) {
			wp_delete_post( $post_id );
		}
	}

	/**
	 * Display the Bookmarklets at the Tools section of wp-admin
	 */
	public function toolbox_bookmarklets() {
		?>
		<div class="card">
			<h2 class="title"><?php esc_html_e( 'Friends', 'friends' ); ?></h2>
			<h3><?php esc_html_e( 'Bookmarklets', 'friends' ); ?></h3>

			<p><?php esc_html_e( "Drag one of these bookmarklets to your bookmarks bar and click it when you're on a site around the web for the appropriate action.", 'friends' ); ?></p>
			<p>
				<a href="javascript:void(location.href='<?php echo esc_attr( self_admin_url( 'admin.php?page=add-friend&url=' ) ); ?>'+encodeURIComponent(location.href))" style="display: inline-block; padding: .5em; border: 1px solid #999; border-radius: 4px; background-color: #ddd;text-decoration: none; margin-right: 3em"><?php echo esc_html_e( 'Add friend', 'friends' ); ?></a>
				<a href="javascript:void(location.href='<?php echo esc_attr( self_admin_url( 'admin.php?page=add-friend&url=' ) ); ?>'+encodeURIComponent(location.href))" style="display: inline-block; padding: .5em; border: 1px solid #999; border-radius: 4px; background-color: #ddd; text-decoration: none; margin-right: 3em"><?php echo esc_html_e( 'Subscribe', 'friends' ); ?></a>
			</p>
			<h3><?php esc_html_e( 'Browser Extension', 'friends' ); ?></h3>

			<p><?php esc_html_e( 'There is also the option to use a browser extension.', 'friends' ); ?></p>
			<p>
				<a href="https://addons.mozilla.org/en-US/firefox/addon/wpfriends/"><?php echo esc_html_e( 'Firefox Extension', 'friends' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Add more "at a glance" items

	 * @param  array $items Items inserted by another plugin.
	 * @return array        Items + our items.
	 */
	public function dashboard_glance_items( $items ) {
		$subscription_count = User_Query::all_subscriptions()->get_total();
		$friend_post_count = wp_count_posts( Friends::CPT );
		$friend_post_count = $friend_post_count->publish + $friend_post_count->private;

		if ( $subscription_count ) {
			// translators: %s is the number of subscriptions.
			$items[] = '<a class="subscriptions" href="' . self_admin_url( 'users.php?role=subscription' ) . '">' . sprintf( _n( '%s Subscription', '%s Subscriptions', $subscription_count, 'friends' ), $subscription_count ) . '</a>';
		}

		if ( $friend_post_count ) {
			// translators: %s is the number of friend posts.
			$items[] = '<a class="friend-posts" href="' . home_url( '/friends/' ) . '">' . sprintf( _n( '%s Post by Friends', '%s Posts by Friends', $friend_post_count, 'friends' ), number_format_i18n( $friend_post_count ) ) . '</a>';
		}
		return $items;
	}

	public function add_dashboard_widgets() {
		if ( ! Friends::has_required_privileges() ) {
			return;
		}
		$user_id = get_current_user_id();
		$widgets = get_user_option( 'friends_dashboard_widgets', $user_id );
		if ( ! $widgets ) {
			$widgets = array( array() );
			update_user_option( $user_id, 'friends_dashboard_widgets', $widgets );
		}
		foreach ( $widgets as $i => $widget ) {
			if ( ! is_array( $widget ) ) {
				continue;
			}
			$title = __( 'Latest Posts', 'friends' );
			if ( isset( $widget['format'] ) ) {
				$title = get_post_format_string( sanitize_key( $widget['format'] ) );
			}

			if ( ! empty( $widget['friend'] ) ) {
				$user = User::get_by_username( $widget['friend'] );
				$title = ' by ' . $user->display_name;
			}
			$title = sprintf(
				// translators: %s is an author name or "Latest Posts".
				__( 'Friends: %s', 'friends' ),
				$title
			);
			wp_add_dashboard_widget( 'friends_dashboard_widget' . $i, $title, array( $this, 'render_dashboard_widget' ), array( $this, 'render_dashboard_widget_controls' ), $widget, 'side', 'high' );
		}
	}

	public function add_new_dashboard_widget( $friend = null, $format = null ) {
		$user_id = get_current_user_id();
		$widgets = get_user_option( 'friends_dashboard_widgets', $user_id );
		if ( ! $widgets ) {
			$widgets = array();
		}
		$widget = array();
		if ( $friend ) {
			$widget['friend'] = $friend;
		}
		if ( $format ) {
			$widget['format'] = $format;
		}
		$widgets[] = $widget;
		update_user_option( $user_id, 'friends_dashboard_widgets', $widgets );
	}

	public function render_dashboard_widget_controls( $id, $widget = false ) {
		if ( empty( $id ) && $widget ) {
			$id = intval( str_replace( 'friends_dashboard_widget', '', $widget['id'] ) );
		}
		$user_id = get_current_user_id();
		$widgets = get_user_option( 'friends_dashboard_widgets', $user_id );
		if ( ! $widgets ) {
			$widgets = array( array() );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['widget_id'] ) ) {

			$id = intval( str_replace( 'friends_dashboard_widget', '', sanitize_text_field( wp_unslash( $_POST['widget_id'] ) ) ) );
			if ( isset( $_POST['add-new'] ) ) {
				$id = count( $widgets );
				$widgets[ $id ] = array();
			}
			if ( ! empty( $_POST['friend'] ) ) {
				$widgets[ $id ]['friend'] = sanitize_text_field( wp_unslash( $_POST['friend'] ) );
			} else {
				unset( $widgets[ $id ]['friend'] );
			}
			if ( ! empty( $_POST['format'] ) ) {
				$widgets[ $id ]['format'] = sanitize_text_field( wp_unslash( $_POST['format'] ) );
			} else {
				unset( $widgets[ $id ]['format'] );
			}
			if ( isset( $_POST['delete'] ) ) {
				unset( $widgets[ $id ] );
			}

			update_user_option( $user_id, 'friends_dashboard_widgets', $widgets );
		}
		// phpcs:enable WordPress.Security.NonceVerification
		$args = array();
		if ( isset( $widgets[ $id ] ) ) {
			$args = $widgets[ $id ];
		}
		echo '<p>';
		echo '<label>';
		esc_html_e( 'Friend:', 'friends' );
		echo '<select name="friend">';
		echo '<option value="">' . esc_html__( 'Any Friend', 'friends' ) . '</option>';
		$users = User_Query::all_associated_users();
		foreach ( $users->get_results() as $user ) {
			echo '<option value="' . esc_attr( $user->user_login ) . '"';
			if ( isset( $args['friend'] ) && $args['friend'] === $user->user_login ) {
				echo ' selected="selected"';
			}
			echo '>' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_login ) . ')</option>';
		}
		echo '</select>';
		echo '</label>';
		echo '</p>';
		echo '<p>';
		echo '<label>';
		esc_html_e( 'Post Format:', 'friends' );
		echo '<select name="format">';
		echo '<option value="">' . esc_html__( 'Any Post Format', 'friends' ) . '</option>';
		foreach ( get_post_format_strings() as $format => $label ) {
			echo '<option value="' . esc_attr( $format ) . '"';
			if ( isset( $args['format'] ) && $args['format'] === $format ) {
				echo ' selected="selected"';
			}
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</label>';
		echo '</p>';
		echo '<p>';
		echo ' <button name="add-new" class="button button-secondary">' . esc_html__( 'Save as a new widget', 'friends' ) . '</button>';
		echo ' <button name="delete" class="button">' . esc_html__( 'Delete this widget', 'friends' ) . '</button>';
		echo '</p>';
	}

	public function render_dashboard_widget( $args, $widget ) {
		$args = $widget['args'];
		echo '<div class="friends-dashboard-widget" data-nonce="';
		echo esc_attr( wp_create_nonce( 'friends-dashboard' ) );
		echo '"';
		if ( ! empty( $args['friend'] ) ) {
			echo ' data-friend="' . esc_attr( $args['friend'] ) . '"';
		}
		if ( ! empty( $args['format'] ) ) {
			echo ' data-format="' . esc_attr( $args['format'] ) . '"';
		}
		echo '></div>';
	}

	public function ajax_friends_dashboard() {
		check_ajax_referer( 'friends-dashboard' );

		$query_args = array();
		$args = array();

		if ( isset( $_POST['friend'] ) ) {
			$friend = User::get_by_username( sanitize_text_field( wp_unslash( $_POST['friend'] ) ) );
			if ( $friend ) {
				$args['friend_user'] = $friend;
				$query_args = $friend->modify_get_posts_args_by_author( $query_args );
			}
		}

		if ( isset( $_POST['format'] ) ) {
			$post_formats = get_post_format_slugs();
			$format = sanitize_text_field( wp_unslash( $_POST['format'] ) );

			if ( isset( $post_formats[ $format ] ) ) {
				$args['post_format'] = $format;
				if ( 'standard' !== $format ) {
					$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'post_format',
							'field'    => 'slug',
							'terms'    => array( 'post-format-' . $format ),
						),
					);
				} else {
					$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'post_format',
							'operator' => 'NOT EXISTS',
						),
					);
				}
			}
		}

		$any_friends = User_Query::all_associated_users();

		ob_start();
		if ( 0 === $any_friends->get_total() && empty( $query_args ) ) {
			Friends::template_loader()->get_template_part(
				'admin/dashboard-widget-welcome',
				null,
				array()
			);

		} else {
			$query_args['post_type'] = apply_filters( 'friends_frontend_post_types', array( 'post' ) );
			$args['posts'] = get_posts( $query_args );
			Friends::template_loader()->get_template_part( 'admin/dashboard-widget', null, $args );
		}
		$data = ob_get_contents();
		ob_end_clean();

		wp_send_json_success(
			$data
		);
	}

	public function site_status_tests( $tests ) {
		$tests['direct']['friends-roles'] = array(
			'label' => __( 'Friend roles were created', 'friends' ),
			'test'  => array( $this, 'friend_roles_test' ),
		);
		$tests['direct']['friends-cron'] = array(
			'label' => __( 'Friends cron job is enabled', 'friends' ),
			'test'  => array( $this, 'friends_cron_test' ),
		);
		$tests['direct']['friends-delete-cron'] = array(
			'label' => __( 'Friends delete old posts cron job is enabled', 'friends' ),
			'test'  => array( $this, 'friends_cron_delete_test' ),
		);
		return $tests;
	}

	public function get_missing_friends_plugin_roles() {
		$missing = Friends::get_friends_plugin_roles();
		$roles = new \WP_Roles();
		foreach ( $roles->roles as $role => $data ) {
			if ( isset( $data['capabilities']['friends_plugin'] ) ) {
				foreach ( $missing as $k => $cap ) {
					if ( isset( $data['capabilities'][ $cap ] ) ) {
						unset( $missing[ $k ] );
						break;
					}
				}
			}
		}

		return array_values( $missing );
	}

	public function friend_roles_test() {
		$result = array(
			'label'       => __( 'The friend roles have been installed', 'friends' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Friends', 'friends' ),
				'color' => 'green',
			),
			'description' =>
				'<p>' .
				__( 'The Friends Plugin uses users and user roles to determine friendship status between sites.', 'friends' ) .
				'</p>' .
				'<p>' .
				sprintf(
					// translators: %s is a list of roles.
					__( 'These are the roles required for the friends plugin: %s', 'friends' ),
					implode( ', ', Friends::get_friends_plugin_roles() )
				) .
				'</p>',
			'test'        => 'friends-roles',
		);

		$missing_friend_roles = $this->get_missing_friends_plugin_roles();
		if ( ! empty( $missing_friend_roles ) ) {

			$result['label'] = sprintf(
				// translators: %s is a list of missing roles.
				__( 'Not all friend roles have been installed. Missing: %s', 'friends' ),
				implode( ', ', $missing_friend_roles )
			);
			$result['badge']['color'] = 'red';
			$result['status'] = 'critical';
			$result['description'] .= '<p>';
			$result['description'] .= wp_kses_post(
				sprintf(
					// translators: %s is a URL.
					__( '<strong>To fix this:</strong> <a href="%s">Re-run activation of the Friends plugin</a>.', 'friends' ),
					esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=friends-settings&rerun-activate' ) ), 'friends-settings' ) )
				)
			);
			$result['description'] .= '</p>';
		}

		return $result;
	}

	public function friends_cron_test() {
		$result = array(
			'label'       => __( 'The refresh cron job is enabled', 'friends' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Friends', 'friends' ),
				'color' => 'green',
			),
			'description' =>
				'<p>' .
				__( 'The Friends Plugin uses a cron job to fetch your friends\' feeds.', 'friends' ) .
				'</p>',
			'test'        => 'friends-cron',
		);
		if ( ! wp_next_scheduled( 'cron_friends_refresh_feeds' ) ) {
			$result['label'] = __( 'The refresh cron job is not enabled', 'friends' );
			$result['badge']['color'] = 'red';
			$result['status'] = 'critical';
			$result['description'] .= '<p>';
			$result['description'] .= wp_kses_post(
				sprintf(
					// translators: %s is a URL.
					__( '<strong>To fix this:</strong> <a href="%s">Enable the Friends cron job</a>.', 'friends' ),
					esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=friends-settings&rerun-activate' ) ), 'friends-settings' ) )
				)
			);
			$result['description'] .= '</p>';
		}

		return $result;
	}

	public function friends_cron_delete_test() {
		$result = array(
			'label'       => __( 'The cron job to delete old posts is enabled', 'friends' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Friends', 'friends' ),
				'color' => 'green',
			),
			'description' =>
				'<p>' .
				__( 'The Friends Plugin uses a cron job to delete old posts your friends.', 'friends' ) .
				'</p>',
			'test'        => 'friends-delete-cron',
		);

		if ( ! wp_next_scheduled( 'cron_friends_delete_old_posts' ) ) {
			$result['label'] = __( 'The cron job to delete old posts is not enabled', 'friends' );
			$result['badge']['color'] = 'orange';
			$result['status'] = 'recommended';
			$result['description'] .= '<p>';
			$result['description'] .= wp_kses_post(
				sprintf(
					// translators: %s is a URL.
					__( '<strong>To fix this:</strong> <a href="%s">Enable the Friends cron job</a>.', 'friends' ),
					esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), self_admin_url( 'admin.php?page=friends-settings&rerun-activate' ) ), 'friends-settings' ) )
				)
			);
			$result['description'] .= '</p>';
		}

		return $result;
	}

	public function site_status_test_php_modules( $modules ) {
		$modules['mbstring']['required'] = true;
		return $modules;
	}

	public function site_health_debug( $debug_info ) {
		$missing_friend_roles = $this->get_missing_friends_plugin_roles();
		$debug_info['friends'] = array(
			'label'  => __( 'Friends', 'friends' ),
			'fields' => array(
				'version'   => array(
					'label' => __( 'Friends Version', 'friends' ),
					'value' => Friends::VERSION,
				),
				'mbstring'  => array(
					'label' => __( 'mbstring is available', 'friends' ),
					'value' => function_exists( 'mb_check_encoding' ) ? __( 'Yes' ) : __( 'No' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				),
				'roles'     => array(
					'label' => __( 'Friend roles missing', 'friends' ),
					'value' => empty( $missing_friend_roles ) ? sprintf(
						// translators: %s is a list of roles.
						__( 'All roles found: %s', 'friends' ),
						implode( ', ', Friends::get_friends_plugin_roles() )
					) : implode( ', ', $missing_friend_roles ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				),
				'main_user' => array(
					'label' => __( 'Main Friend User', 'friends' ),
					'value' => self::human_readable_main_user(),
				),
				'parsers'   => array(
					'label' => __( 'Registered Parsers', 'friends' ),
					'value' => wp_strip_all_tags( implode( ', ', $this->friends->feed->get_registered_parsers() ) ),
				),
			),
		);

		return $debug_info;
	}

	/**
	 * Returns a human readable string for which user is the main user.
	 *
	 * @return string
	 */
	private static function human_readable_main_user() {
		$main_user = Friends::get_main_friend_user_id();

		if ( ! $main_user ) {
			// translators: %d is the number of users.
			return esc_html( sprintf( __( 'No main user set. Admin users: %d', 'friends' ), User_Query::all_admin_users()->get_total() ) );
		}

		$user = new \WP_User( $main_user );

		if ( ! $user ) {
			return sprintf( '#%1$d %2$s', $main_user, '???' );
		}

		return sprintf( '#%1$d %2$s', $user->ID, $user->user_login );
	}

	public function admin_friend_posts_query( $query ) {
		global $wp_query, $wp, $authordata;
		if ( $wp_query !== $query || ! is_admin() ) {
			return $query;
		}
		if ( ! isset( $query->query['post_type'] ) || ! in_array( $query->query['post_type'], apply_filters( 'friends_frontend_post_types', array( 'post' ) ), true ) ) {
			return $query;
		}

		if ( empty( $query->query['author'] ) ) {
			return $query;
		}

		$author = User::get_by_username( $query->query['author'] );
		if ( ! $author ) {
			return $query;
		}
		$author->modify_query_by_author( $query );
		$query->query['author'] = '';

		return $query;
	}
}
