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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'friends_own_site_menu_top', array( $this, 'friends_add_menu_open_friend_request' ), 10, 2 );
		add_filter( 'users_list_table_query_args', array( $this, 'allow_role_multi_select' ) );
		add_filter( 'user_row_actions', array( $this, 'user_row_actions' ), 10, 2 );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_friend_request_approval' ), 10, 3 );
		add_filter( 'bulk_actions-users', array( $this, 'add_user_bulk_options' ) );
		add_filter( 'manage_users_columns', array( $this, 'user_list_columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'user_list_custom_column' ), 10, 3 );
		add_filter( 'the_title', array( $this, 'override_post_format_title' ), 10, 2 );
		add_filter( 'get_edit_user_link', array( $this, 'admin_edit_user_link' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_friends_menu' ), 39 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_new_content' ), 71 );
		add_action( 'wp_head', array( $this, 'admin_bar_mobile' ) );
		add_action( 'current_screen', array( $this, 'register_help' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 39 );
		add_action( 'gettext_with_context', array( $this->friends, 'translate_user_role' ), 10, 4 );
		add_action( 'wp_ajax_friends_preview_rules', array( $this, 'ajax_preview_friend_rules' ) );
		add_action( 'wp_ajax_friends_update_welcome_panel', array( $this, 'ajax_update_welcome_panel' ) );
		add_action( 'wp_ajax_friends_refresh_link_token', array( $this, 'ajax_refresh_link_token' ) );
		add_action( 'delete_user_form', array( $this, 'delete_user_form' ), 10, 2 );
		add_action( 'delete_user', array( $this, 'delete_user' ) );
		add_action( 'tool_box', array( $this, 'toolbox_bookmarklets' ) );
		add_action( 'dashboard_glance_items', array( $this, 'dashboard_glance_items' ) );
		add_filter( 'site_status_tests', array( $this, 'site_status_tests' ) );
		add_filter( 'site_status_test_php_modules', array( $this, 'site_status_test_php_modules' ) );
		add_filter( 'debug_information', array( $this, 'site_health_debug' ) );

		if ( ! get_option( 'permalink_structure' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_unsupported_permalink_structure' ) );
		}
		add_action( 'admin_notices', array( $this, 'admin_notice_no_friends' ) );
		add_filter( 'friends_unread_count', array( $this, 'friends_unread_friend_request_count' ) );
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
	 * Display admin notice when user doesn't have friends or subscriptions
	 */
	public function admin_notice_no_friends() {
		$screen = get_current_screen();

		if ( 'plugins' !== $screen->id && 'dashboard' !== $screen->id ) {
			return;
		}

		if ( isset( $_GET['friends-welcome'] ) ) {
			update_user_meta( get_current_user_id(), 'friends_hide_welcome_panel', ! $_GET['friends-welcome'] );
		}

		if ( get_user_meta( get_current_user_id(), 'friends_hide_welcome_panel', true ) ) {
			return;
		}

		$friends_subscriptions = User_Query::all_friends_subscriptions();
		if ( $friends_subscriptions->get_total() && ! ( isset( $_GET['friends-welcome'] ) && $_GET['friends-welcome'] ) ) {
			return;
		}

		?>
		<div id="friends-welcome-panel" class="welcome-panel notice">
			<?php wp_nonce_field( 'friends-welcome-panel-nonce', 'friendswelcomepanelnonce', false ); ?>
			<a class="welcome-panel-close" href="<?php echo esc_url( admin_url( '?friends-welcome=0' ) ); ?>" aria-label="<?php esc_attr_e( 'Dismiss the welcome panel', 'friends' ); ?>"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Dismiss' ); ?></a>
			<div class="welcome-panel-content">
				<h2><?php esc_html_e( 'Welcome to the Friends Plugin!', 'friends' ); ?></h2>
				<p><?php esc_html_e( "You're seeing this message because you haven't connected with friends or made any subscriptions yet. Here is how to get started:", 'friends' ); ?></p>

				<p>
					<?php
					esc_html_e( 'The Friends plugin is all about connecting with friends and news.', 'friends' );
					echo ' ';
					// translators: 1: URL to add-friend, 2: the name of the Send Friend Request page.
					echo wp_kses( sprintf( __( "First, you'll want to go to <a href=%1\$s>%2\$s</a> and add a new friend or enter the URL of a website or blog you'd like to subscribe to.", 'friends' ), '"' . admin_url( 'admin.php?page=add-friend' ) . '"', __( 'Send Friend Request', 'friends' ) ), array( 'a' => array( 'href' => array() ) ) );
					echo ' ';
					// translators: %s is the URL of the user's friends page.
					echo wp_kses( sprintf( __( "As soon as you have done this, you'll be able see all the compiled posts of your friends (and subscriptions) on your <a href=%s>Friends page</a>.", 'friends' ), home_url( '/friends/' ) ), array( 'a' => array( 'href' => array() ) ) );
					?>
				</p>

				<p>
					<?php
					esc_html_e( 'Furthermore, your friends will be able to see your private posts. This means you can submit posts on your own blog that only they will be able to see and vica versa.', 'friends' );
					echo ' ';
					esc_html_e( 'This allows building your own decentralized social network, no third party involved.', 'friends' );
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Registers the admin menus
	 */
	public function register_admin_menu() {
		$unread_badge = $this->get_unread_badge();

		$menu_title = __( 'Friends', 'friends' ) . $unread_badge;
		$page_type = sanitize_title( $menu_title );
		add_menu_page( 'friends', $menu_title, Friends::REQUIRED_ROLE, 'friends-settings', null, 'dashicons-groups', 3 );
		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		add_submenu_page( 'friends-settings', __( 'Settings' ), __( 'Settings' ), Friends::REQUIRED_ROLE, 'friends-settings', array( $this, 'render_admin_settings' ) );
		add_action( 'load-' . $page_type . '_page_friends-page', array( $this, 'redirect_to_friends_page' ) );
		add_submenu_page( 'friends-settings', __( 'Notification Manager', 'friends' ), __( 'Notification Manager', 'friends' ), Friends::REQUIRED_ROLE, 'friends-notification-manager', array( $this, 'render_admin_notification_manager' ) );
		add_action( 'load-' . $page_type . '_page_friends-notification-manager', array( $this, 'process_admin_notification_manager' ) );
		add_submenu_page( 'friends-settings', __( 'Add New Friend', 'friends' ), __( 'Add New Friend', 'friends' ), Friends::REQUIRED_ROLE, 'add-friend', array( $this, 'render_admin_add_friend' ) );
		add_action( 'load-toplevel_page_friends-settings', array( $this, 'process_admin_settings' ) );

		if ( $this->friends_unread_friend_request_count( 0 ) > 0 ) {
			add_submenu_page( 'friends-settings', __( 'Friend Requests', 'friends' ), __( 'Friend Requests', 'friends' ) . $unread_badge, Friends::REQUIRED_ROLE, 'users.php?role=friend_request' );
		}

		add_submenu_page( 'friends-settings', __( 'Friends &amp; Requests', 'friends' ), __( 'Friends &amp; Requests', 'friends' ), Friends::REQUIRED_ROLE, $this->get_users_url() );

		if ( isset( $_GET['page'] ) && 'friends-refresh' === $_GET['page'] ) {
			add_submenu_page( 'friends-settings', __( 'Refresh', 'friends' ), __( 'Refresh', 'friends' ), Friends::REQUIRED_ROLE, 'friends-refresh', array( $this, 'admin_refresh_friend_posts' ) );
		}

		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		add_submenu_page( 'friends-settings', __( 'Plugins' ), __( 'Plugins' ), Friends::REQUIRED_ROLE, 'friends-plugins', array( $this, 'admin_plugin_installer' ) );

		if ( isset( $_GET['page'] ) && 0 === strpos( $_GET['page'], 'edit-friend' ) ) {
			add_submenu_page( 'friends-settings', __( 'Edit User', 'friends' ), __( 'Edit User', 'friends' ), Friends::REQUIRED_ROLE, 'edit-friend' . ( 'edit-friend' !== $_GET['page'] && isset( $_GET['user'] ) ? '&user=' . $_GET['user'] : '' ), array( $this, 'render_admin_edit_friend' ) );
			add_submenu_page( 'friends-settings', __( 'Edit Rules', 'friends' ), __( 'Edit Rules', 'friends' ), Friends::REQUIRED_ROLE, 'edit-friend-rules' . ( 'edit-friend-rules' !== $_GET['page'] && isset( $_GET['user'] ) ? '&user=' . $_GET['user'] : '' ), array( $this, 'render_admin_edit_friend_rules' ) );
			add_action( 'load-' . $page_type . '_page_edit-friend', array( $this, 'process_admin_edit_friend' ) );
			add_action( 'load-' . $page_type . '_page_edit-friend-rules', array( $this, 'process_admin_edit_friend_rules' ) );
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

			$roles = $this->get_associated_roles();
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
				$( "#toplevel_page_friends-settings ul li a[href='<?php echo esc_html( $this->get_users_url() ); ?>']" ).closest( 'li' ).addClass( 'current' );
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
								'"' . esc_attr( self_admin_url( $this->get_users_url() ) ) . '"',
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
		wp_enqueue_script( 'friends-admin', plugins_url( 'friends-admin.js', FRIENDS_PLUGIN_FILE ), array( 'jquery' ), Friends::VERSION );
		$variables = array(
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'add_friend_url'  => self_admin_url( 'admin.php?page=add-friend' ),
			'add_friend_text' => __( 'Add a Friend', 'friends' ),
		);
		wp_localize_script( 'friends-admin', 'friends', $variables );
		wp_enqueue_style( 'friends-admin', plugins_url( 'friends-admin.css', FRIENDS_PLUGIN_FILE ), array(), Friends::VERSION );

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

		if ( isset( $_GET['user'] ) ) {
			$friend_user = new User( intval( $_GET['user'] ) );
			if ( ! $friend_user || is_wp_error( $friend_user ) || ! $friend_user->can_refresh_feeds() ) {
				wp_die( esc_html__( 'Invalid user ID.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			}
			$friend_user->retrieve_posts();
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
	 * Send a friend request to another WordPress with the Friends plugin
	 *
	 * @param      string $rest_url    The site URL of the friend's
	 *                                 WordPress.
	 * @param      string $user_login  The user login.
	 * @param      string $user_url    The user url.
	 * @param      string $display_name    The display name.
	 * @param      string $codeword    A codeword to send along.
	 * @param      string $message     A message to send along.
	 *
	 * @return     \WP_User|\WP_error  $user The new associated user or an error object.
	 */
	public function send_friend_request( $rest_url, $user_login, $user_url, $display_name, $codeword = 'friends', $message = '' ) {
		if ( ! is_string( $rest_url ) || ! Friends::check_url( $rest_url ) ) {
			return new \WP_Error( 'invalid-url', __( 'You entered an invalid URL.', 'friends' ) );
		}

		$future_in_token = wp_generate_password( 128, false );

		$current_user = wp_get_current_user();
		$response     = wp_safe_remote_post(
			$rest_url . '/friend-request',
			array(
				'body'        => array(
					'version'  => 2,
					'codeword' => $codeword,
					'name'     => $current_user->display_name,
					'url'      => home_url(),
					'icon_url' => get_avatar_url( $current_user->ID ),
					'message'  => mb_substr( trim( $message ), 0, 2000 ),
					'key'      => $future_in_token,
				),
				'timeout'     => 20,
				'redirection' => 5,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( $json && isset( $json->code ) && isset( $json->message ) ) {
				// translators: %s is the message from the other server.
				return new \WP_Error( $json->code, sprintf( __( 'The other side responded: %s', 'friends' ), $json->message ), $json->data );
			}
		}

		if ( ! $json || ! is_object( $json ) ) {
			return new \WP_Error( 'unexpected-rest-response', 'Unexpected remote response: ' . substr( wp_remote_retrieve_body( $response ), 0, 30 ), $response );
		}

		$friend_user = User::create( $user_login, 'pending_friend_request', $user_url, $display_name );
		if ( is_wp_error( $friend_user ) ) {
			return $friend_user;
		}
		$friend_user->update_user_option( 'friends_rest_url', $rest_url );

		if ( isset( $json->request ) ) {
			update_option( 'friends_request_' . sha1( $json->request ), $friend_user->ID );
			$friend_user->update_user_option( 'friends_future_in_token_' . sha1( $json->request ), $future_in_token );
			$friend_user->set_role( 'pending_friend_request' );
		}

		return $friend_user;
	}

	/**
	 * Don't show the edit link for friend posts
	 *
	 * @param  string $link    The edit link.
	 * @param  int    $user_id The user id.
	 * @return string|bool The edit link or false.
	 */
	public function admin_edit_user_link( $link, $user_id ) {
		$user = new \WP_User( $user_id );
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return $link;
		}
		if (
			! $user->has_cap( 'friend_request' ) &&
			! $user->has_cap( 'pending_friend_request' ) &&
			! $user->has_cap( 'friend' ) &&
			! $user->has_cap( 'subscription' )
		) {
			return $link;
		}

		return self_admin_url( 'admin.php?page=edit-friend&user=' . $user->ID );
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
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the settings.', 'friends' ) );
		}
	}

	/**
	 * Process the Friends Admin settings page
	 */
	public function process_admin_settings() {
		$this->check_admin_settings();

		if ( empty( $_REQUEST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'friends-settings' ) ) {
			return;
		}

		if ( isset( $_REQUEST['rerun-activate'] ) ) {
			Friends::activate_for_blog();
			wp_safe_redirect( add_query_arg( array( 'reran-activation' => 'friends' ), wp_get_referer() ) );
			exit;
		}

		foreach ( array( 'ignore_incoming_friend_requests', 'force_enable_post_formats', 'expose_post_format_feeds' ) as $checkbox ) {
			if ( isset( $_POST[ $checkbox ] ) && $_POST[ $checkbox ] ) {
				update_option( 'friends_' . $checkbox, true );
			} else {
				delete_option( 'friends_' . $checkbox );
			}
		}

		if ( isset( $_POST['limit_homepage_post_format'] ) && $_POST['limit_homepage_post_format'] && in_array( $_POST['limit_homepage_post_format'], get_post_format_slugs() ) ) {
			update_option( 'friends_limit_homepage_post_format', $_POST['limit_homepage_post_format'] );
		} else {
			delete_option( 'friends_limit_homepage_post_format' );
		}

		foreach ( array( 'friend_request_notification' ) as $negative_user_checkbox ) {
			if ( isset( $_POST[ $negative_user_checkbox ] ) && $_POST[ $negative_user_checkbox ] ) {
				delete_user_option( get_current_user_id(), 'friends_no_' . $negative_user_checkbox );
			} else {
				update_user_option( get_current_user_id(), 'friends_no_' . $negative_user_checkbox, 1 );
			}
		}

		if ( isset( $_POST['main_user_id'] ) && is_numeric( $_POST['main_user_id'] ) ) {
			update_option( 'friends_main_user_id', intval( $_POST['main_user_id'] ) );
		} else {
			$main_user_id = intval( Friends::get_main_friend_user_id() );
			$main_user_id_exists = false;
			$users = User_Query::all_admin_users();
			foreach ( $users->get_results() as $user ) {
				if ( $user->ID === $main_user_id ) {
					$main_user_id_exists = true;
					break;
				}
			}
			if ( ! $main_user_id_exists ) {
				// Reset the main user id.
				delete_option( 'friends_main_user_id' );
				Friends::get_main_friend_user_id();
			}
		}

		if ( isset( $_POST['comment_registration'] ) && $_POST['comment_registration'] ) {
			update_option( 'comment_registration', true );
		} else {
			delete_option( 'comment_registration' );
		}

		if ( isset( $_POST['comment_registration_message'] ) && $_POST['comment_registration_message'] ) {
			update_option( 'friends_comment_registration_message', $_POST['comment_registration_message'] );
		} else {
			delete_option( 'friends_comment_registration_message' );
		}

		if ( isset( $_POST['require_codeword'] ) && $_POST['require_codeword'] ) {
			update_option( 'friends_require_codeword', true );
		} else {
			delete_option( 'friends_require_codeword' );
		}

		if ( isset( $_POST['codeword'] ) && $_POST['codeword'] ) {
			update_option( 'friends_codeword', $_POST['codeword'] );
		} else {
			delete_option( 'friends_codeword' );
		}

		if ( isset( $_POST['wrong_codeword_message'] ) && $_POST['wrong_codeword_message'] ) {
			update_option( 'friends_wrong_codeword_message', $_POST['wrong_codeword_message'] );
		} else {
			delete_option( 'friends_wrong_codeword_message' );
		}

		if ( isset( $_POST['available_emojis'] ) && $_POST['available_emojis'] ) {
			$available_emojis = array();
			foreach ( $_POST['available_emojis'] as $id ) {
				$data = Reactions::get_emoji_data( $id );
				if ( $data ) {
					$available_emojis[ $id ] = $data;
				}
			}
			update_option( 'friends_selected_emojis', $available_emojis );
		} else {
			delete_option( 'friends_selected_emojis' );
		}

		if ( isset( $_POST['notification_keywords'] ) && $_POST['notification_keywords'] ) {
			$keywords = array();
			foreach ( $_POST['notification_keywords'] as $i => $keyword ) {
				if ( trim( $keyword ) ) {
					$keywords[] = array(
						'enabled' => isset( $_POST['notification_keywords_enabled'][ $i ] ) && $_POST['notification_keywords_enabled'][ $i ],
						'keyword' => $keyword,
					);
				}
			}
			update_option( 'friends_notification_keywords', $keywords );
		}

		if ( isset( $_POST['default_role'] ) && in_array( $_POST['default_role'], array( 'friend', 'acquaintance' ), true ) ) {
			update_option( 'friends_default_friend_role', $_POST['default_role'] );
		}

		if ( isset( $_POST['new_post_notification'] ) && $_POST['new_post_notification'] ) {
			delete_user_option( get_current_user_id(), 'friends_no_new_post_notification' );
		} else {
			update_user_option( get_current_user_id(), 'friends_no_new_post_notification', 1 );
		}

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( 'updated', '1', remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
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
	 * Render the Friends Admin settings page
	 */
	public function render_admin_settings() {
		$this->check_admin_settings();

		$current_user = wp_get_current_user();

		?>
		<h1><?php esc_html_e( 'Friends Settings', 'friends' ); ?></h1>
		<?php
		if ( isset( $_GET['updated'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
				<?php
				esc_html_e( 'Your settings were updated.', 'friends' );
				?>
			</p></div>
			<?php
		}

		// In order to switch to the frontend locale, we need to first pretend that nothing was loaded yet.
		global $l10n;
		$l10n = array();

		switch_to_locale( $this->get_frontend_locale() );
		// Now while loading the next translations we need to ensure that determine_locale() doesn't return the admin language but the frontend language.
		add_filter( 'pre_determine_locale', array( $this, 'get_frontend_locale' ) );

		$wrong_codeword_message = __( 'An invalid codeword was provided.', 'friends' );
		$comment_registration_message = __( 'Only people in my network can comment.', 'friends' );
		$my_network = __( 'my network', 'friends' );
		$comment_registration_default = strip_tags(
			/* translators: %s: Login URL. */
			__( 'You must be <a href="%s">logged in</a> to post a comment.' ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		);
		// Now let's switch back to the admin language.
		remove_filter( 'pre_determine_locale', array( $this, 'get_frontend_locale' ) );
		restore_previous_locale();

		$keywords = get_option( 'friends_notification_keywords', false );

		Friends::template_loader()->get_template_part(
			'admin/settings',
			null,
			array(
				'potential_main_users'           => User_Query::all_admin_users(),
				'main_user_id'                   => Friends::get_main_friend_user_id(),
				'friend_roles'                   => $this->get_friend_roles(),
				'default_role'                   => get_option( 'friends_default_friend_role', 'friend' ),
				'force_enable_post_formats'      => get_option( 'friends_force_enable_post_formats' ),
				'post_format_strings'            => get_post_format_strings(),
				'limit_homepage_post_format'     => get_option( 'friends_limit_homepage_post_format', false ),
				'expose_post_format_feeds'       => get_option( 'friends_expose_post_format_feeds' ),
				'private_rss_key'                => get_option( 'friends_private_rss_key' ),
				'comment_registration'           => get_option( 'comment_registration' ), // WordPress option.
				'comment_registration_message'   => get_option( 'friends_comment_registration_message', $comment_registration_message ),
				'comment_registration_default'   => $comment_registration_default,
				'my_network'                     => $my_network,
				'public_profile_link'            => home_url( '/friends/' ),
				'codeword'                       => get_option( 'friends_codeword', 'friends' ),
				'require_codeword'               => get_option( 'friends_require_codeword' ),
				'wrong_codeword_message'         => get_option( 'friends_wrong_codeword_message', $wrong_codeword_message ),
				'no_friend_request_notification' => get_user_option( 'friends_no_friend_request_notification' ),
				'no_new_post_notification'       => get_user_option( 'friends_no_new_post_notification' ),
				'notification_keywords'          => Feed::get_all_notification_keywords(),
			)
		);
	}

	/**
	 * Process access for the Friends Edit Rules page
	 */
	private function check_admin_edit_friend_rules() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit the rules.', 'friends' ) );
		}

		if ( ! isset( $_GET['user'] ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		$friend = new User( intval( $_GET['user'] ) );
		if ( ! $friend || is_wp_error( $friend ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
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
	 * Process the Friends Edit Rules page
	 */
	public function process_admin_edit_friend_rules() {
		$friend    = $this->check_admin_edit_friend_rules();
		$arg       = 'updated';
		$arg_value = 1;
		if ( isset( $_POST['friend-rules-raw'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'friend-rules-raw-' . $friend->ID ) ) {
			$rules = $this->friends->feed->validate_feed_rules( json_decode( stripslashes( $_POST['rules'] ), true ) );
			if ( false === $rules ) {
				$arg = 'error';
			} else {
				update_option( 'friends_feed_rules_' . $friend->ID, $rules );
			}
		} elseif ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'edit-friend-rules-' . $friend->ID ) ) {
			update_option( 'friends_feed_catch_all_' . $friend->ID, $this->friends->feed->validate_feed_catch_all( $_POST['catch_all'] ) );
			update_option( 'friends_feed_rules_' . $friend->ID, $this->friends->feed->validate_feed_rules( $_POST['rules'] ) );
		} else {
			return;
		}

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( $arg, $arg_value, remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
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
		?>
		<h1>
			<?php
			// translators: %s is the name of a friend.
			printf( __( 'Rules for %s', 'friends' ), esc_html( $friend->display_name ) );
			?>
		</h1>
		<?php

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

		if ( isset( $_GET['post'] ) && intval( $_GET['post'] ) ) {
			$post = get_post( intval( $_GET['post'] ) );
		} else {
			$post = null;
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
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( -1 );
		}

		if ( isset( $_GET['post'] ) && intval( $_GET['post'] ) ) {
			$post = get_post( intval( $_GET['post'] ) );
		} else {
			$post = null;
		}

		$this->render_preview_friend_rules( $_POST['rules'], $_POST['catch_all'], $post );
		wp_die( 1 );
	}

	/**
	 * Respond to the Ajax request to the Friend Welcome Panel
	 */
	public function ajax_update_welcome_panel() {
		check_ajax_referer( 'friends-welcome-panel-nonce', 'friendswelcomepanelnonce' );

		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( -1 );
		}

		update_user_meta( get_current_user_id(), 'friends_hide_welcome_panel', empty( $_POST['hide'] ) ? 0 : 1 );

		wp_die( 1 );
	}
	/**
	 * Respond to the Ajax request to the Friend Welcome Panel
	 */
	public function ajax_refresh_link_token() {
		if ( ! isset( $_POST['url'] ) || ! isset( $_POST['friend'] ) ) {
			wp_die( -1 );
		}
		$url = $_POST['url'];
		check_ajax_referer( 'auth-link-' . $url );

		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( -1 );
		}

		$friend_user = User::get_user( $_POST['friend'] );
		if ( ! $friend_user ) {
			wp_die( -1 );
		}

		wp_send_json_success(
			array(
				'success' => true,
				'data'    => array(
					'token' => $friend_user->get_friend_auth(),
				),
			)
		);
		wp_die();
	}

	/**
	 * Render the Friend rules preview
	 *
	 * @param  array    $rules     The rules to apply.
	 * @param  string   $catch_all The catch all behavior.
	 * @param  \WP_Post $post       The post.
	 */
	public function render_preview_friend_rules( $rules, $catch_all, \WP_Post $post = null ) {
		$friend = $this->check_admin_edit_friend_rules();

		$args = array(
			'friend'       => $friend,
			'friend_posts' => new \WP_Query(
				array(
					'post_type'      => Friends::CPT,
					'post_status'    => array( 'publish', 'private', 'trash' ),
					'author'         => $friend->ID,
					'posts_per_page' => 25,
				)
			),
			'feed'         => $this->friends->feed,
			'post'         => $post,
		);

		User::$feed_rules[ $friend->ID ]     = $this->friends->feed->validate_feed_rules( $rules );
		User::$feed_catch_all[ $friend->ID ] = $this->friends->feed->validate_feed_catch_all( $catch_all );

		Friends::template_loader()->get_template_part( 'admin/preview-rules', null, $args );
	}

	/**
	 * Process access for the Friends Edit User page
	 */
	private function check_admin_edit_friend() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this user.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		if ( ! isset( $_GET['user'] ) || ! is_numeric( $_GET['user'] ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		$friend = new User( intval( $_GET['user'] ) );
		if ( ! $friend || is_wp_error( $friend ) ) {
			wp_die( esc_html__( 'Invalid user ID.' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
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
	 * Process the Friends Edit User page
	 */
	public function process_admin_edit_friend() {
		$friend    = $this->check_admin_edit_friend();
		$arg       = 'updated';
		$arg_value = 1;

		if ( isset( $_GET['accept-friend-request'] ) && wp_verify_nonce( $_GET['accept-friend-request'], 'accept-friend-request-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'friend_request' ) ) {
				$friend->set_role( get_option( 'friends_default_friend_role', 'friend' ) );
				$arg = 'friend';
			}
		} elseif ( isset( $_GET['add-friend'] ) && wp_verify_nonce( $_GET['add-friend'], 'add-friend-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'pending_friend_request' ) || $friend->has_cap( 'subscription' ) ) {
				$rest_url = $this->friends->rest->discover_rest_url( $friend->user_url );
				if ( ! is_wp_error( $rest_url ) ) {
					$response = $this->send_friend_request( $rest_url, $friend->user_login, $friend->user_url, $friend->display_name );
				} else {
					$response = $rest_url;
				}

				if ( is_wp_error( $response ) ) {
					$arg = 'error';
				} elseif ( $response instanceof User ) {
					if ( $response->has_cap( 'pending_friend_request' ) ) {
						$arg = 'sent-request';
						// translators: %s is a Site URL.
						$arg_value = wp_kses( sprintf( __( 'Friendship requested for site %s.', 'friends' ), $response->get_local_friends_page_url() ), array( 'a' => array( 'href' => array() ) ) );
					} elseif ( $response->has_cap( 'friend' ) ) {
						$arg       = 'friend';
						$arg_value = 1;
					} elseif ( $response->has_cap( 'subscription' ) ) {
						$arg       = 'subscribed';
						$arg_value = 1;
					}
				}
			}
		} elseif ( isset( $_GET['change-to-restricted-friend'] ) && wp_verify_nonce( $_GET['change-to-restricted-friend'], 'change-to-restricted-friend-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'friend' ) ) {
				$friend->set_role( 'acquaintance' );
			}
		} elseif ( isset( $_GET['change-to-friend'] ) && wp_verify_nonce( $_GET['change-to-friend'], 'change-to-friend-' . $friend->ID ) ) {
			if ( $friend->has_cap( 'acquaintance' ) ) {
				$friend->set_role( 'friend' );
			}
		} elseif ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'edit-friend-' . $friend->ID ) ) {
			if ( trim( $_POST['friends_display_name'] ) ) {
				$friend->first_name   = trim( $_POST['friends_display_name'] );
				$friend->display_name = trim( $_POST['friends_display_name'] );
			}

			$friend->description = trim( $_POST['friends_description'] );
			if ( trim( $_POST['user_url'] ) && filter_var( $_POST['user_url'], FILTER_VALIDATE_URL ) ) {
				$friend->user_url = $_POST['user_url'];
			}
			wp_update_user( $friend );

			if ( $friend->set_retention_number_enabled( isset( $_POST['friends_enable_retention_number'] ) && $_POST['friends_enable_retention_number'] ) ) {
				$friend->set_retention_number( $_POST['friends_retention_number'] );
			}
			if ( $friend->set_retention_days_enabled( isset( $_POST['friends_enable_retention_days'] ) && $_POST['friends_enable_retention_days'] ) ) {
				$friend->set_retention_days( $_POST['friends_retention_days'] );
			}

			$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
			if ( ! $hide_from_friends_page ) {
				$hide_from_friends_page = array();
			}
			if ( ! isset( $_POST['show_on_friends_page'] ) || ! $_POST['show_on_friends_page'] ) {
				if ( ! in_array( $friend->ID, $hide_from_friends_page ) ) {
					$hide_from_friends_page[] = $friend->ID;
					update_user_option( get_current_user_id(), 'friends_hide_from_friends_page', $hide_from_friends_page );
				}
			} else {
				if ( in_array( $friend->ID, $hide_from_friends_page ) ) {
					$hide_from_friends_page = array_values( array_diff( $hide_from_friends_page, array( $friend->ID ) ) );
					update_user_option( get_current_user_id(), 'friends_hide_from_friends_page', $hide_from_friends_page );
				}
			}

			if ( ! get_user_option( 'friends_no_new_post_notification' ) ) {
				if ( isset( $_POST['friends_new_post_notification'] ) && $_POST['friends_new_post_notification'] ) {
					delete_user_option( get_current_user_id(), 'friends_no_new_post_notification_' . $friend->ID );
				} else {
					update_user_option( get_current_user_id(), 'friends_no_new_post_notification_' . $friend->ID, 1 );
				}
			}

			if ( ! get_user_option( 'friends_no_keyword_notification' ) ) {
				if ( isset( $_POST['friends_keyword_notification'] ) && $_POST['friends_keyword_notification'] ) {
					delete_user_option( get_current_user_id(), 'friends_no_keyword_notification_' . $friend->ID );
				} else {
					update_user_option( get_current_user_id(), 'friends_no_keyword_notification_' . $friend->ID, 1 );
				}
			}

			if ( isset( $_POST['feeds'] ) ) {
				$existing_feeds = $friend->get_feeds();
				if ( '' === trim( $_POST['feeds']['new']['url'] ) ) {
					unset( $_POST['feeds']['new'] );
				} else {
					foreach ( $existing_feeds as $term_id => $feed ) {
						if ( $feed->get_url() === trim( $_POST['feeds']['new']['url'] ) ) {
							// Let a newly entered feed overrule an existing one.
							$_POST['feeds'][ $term_id ] = array_merge( $_POST['feeds'][ $term_id ], $_POST['feeds']['new'] );
							$_POST['feeds'][ $term_id ]['active'] = 1;
							unset( $_POST['feeds']['new'] );
							break;
						}
					}
				}
				foreach ( $_POST['feeds'] as $term_id => $feed ) {
					if ( 'new' === $term_id ) {
						if ( '' === trim( $feed['url'] ) ) {
							continue;
						}

						$feed['active'] = true;
						User_Feed::save(
							$friend,
							$feed['url'],
							$feed
						);

						continue;
					}

					if ( ! isset( $existing_feeds[ $term_id ] ) ) {
						continue;
					}
					$user_feed = $existing_feeds[ $term_id ];

					if ( $user_feed->get_url() !== $feed['url'] ) {
						if ( ! isset( $feed['mime-type'] ) ) {
							$feed['mime-type'] = $user_feed->get_mime_type();
						}

						if ( $feed['active'] ) {
							$friend->subscribe( $feed['url'], $feed );
						} else {
							$friend->save_feed( $feed['url'], $feed );
						}

						// Since the URL has changed, the above will create a new feed, therefore we need to delete the old one.
						$user_feed->delete();
						continue;
					}

					$user_feed->update_metadata( 'active', isset( $feed['active'] ) && $feed['active'] );

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

					do_action( 'friends_process_feed_item_submit', $user_feed, $feed, $term_id );
				}

				do_action( 'friends_edit_friend_after_form_submit', $friend );
			}
		} else {
			return;
		}

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( $arg, $arg_value, remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
		}
		exit;
	}

	/**
	 * Render the Friends Edit User page
	 */
	public function render_admin_edit_friend() {
		$friend = $this->check_admin_edit_friend();
		$friend_posts = new \WP_Query(
			array(
				'post_type'   => Friends::CPT,
				'post_status' => array( 'publish', 'private' ),
				'author'      => $friend->ID,
				'nopaging'    => true,
			)
		);
		$total_size = 0;
		if ( $friend_posts->have_posts() ) {
			while ( $friend_posts->have_posts() ) {
				$friend_posts->the_post();
				$total_size += strlen( serialize( array_values( (array) $friend_posts->post ) ) );
			}
		}
		wp_reset_postdata();

		$args = array(
			'friend'                 => $friend,
			'friend_posts'           => $friend_posts,
			'total_size'             => $total_size,
			'rules'                  => $friend->get_feed_rules(),
			'post_formats'           => array_merge( array( 'autodetect' => __( 'Autodetect Post Format', 'friends' ) ), get_post_format_strings() ),
			'friends_settings_url'   => add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=friends-settings' ) ),
			'registered_parsers'     => $this->friends->feed->get_registered_parsers(),
			'hide_from_friends_page' => get_user_option( 'friends_hide_from_friends_page' ),
		);
		if ( ! $args['hide_from_friends_page'] ) {
			$args['hide_from_friends_page'] = array();
		}

		?>
		<h1><?php echo esc_html( $friend->user_login ); ?></h1>
		<?php

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
			<div id="message" class="updated error is-dismissible"><p><?php esc_html_e( 'An error occurred.', 'friends' ); ?></p></div>
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

		Friends::template_loader()->get_template_part( 'admin/edit-friend', null, $args );
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
				$error = unserialize( $error_data->error );
				if ( is_wp_error( $error ) ) {
					?>
					<pre>
						<?php
						print_r( $error );
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

	/**
	 * Previous process the Add Friend form. Todo: re-integrate.
	 *
	 * @param      User  $friend_user  The Friend user.
	 * @param      array $vars         The variables from the admin
	 *                                 submission.
	 *
	 * @return     boolean      true when there was no error.
	 */
	public function process_admin_add_friend_response( $friend_user, $vars ) {
		if ( is_wp_error( $friend_user ) ) {
			$this->display_errors( $friend_user );
			return false;
		}

		if ( ! $friend_user instanceof User ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
				<?php
				// translators: %s is a username.
				esc_html_e( 'Unknown error', 'friends' );
				?>
			</p></div>
			<?php
			return false;
		}

		$feed_options = array();
		if ( ! isset( $vars['feeds'] ) ) {
			$vars['feeds'] = array();
		}
		foreach ( $vars['feeds'] as $feed ) {
			if ( isset( $feed['type'] ) ) {
				$feed['mime-type'] = $feed['type'];
				unset( $feed['type'] );
			}
			$feed_options[ $feed['url'] ] = $feed;
		}

		// Save the all feeds for possible later activation.
		$friend_user->save_feeds( $feed_options );

		if ( ! isset( $vars['subscribe'] ) ) {
			$vars['subscribe'] = array();
		}

		$count = 0;
		foreach ( $vars['subscribe'] as $feed_url ) {
			if ( ! isset( $feed_options[ $feed_url ] ) ) {
				continue;
			}
			$friend_user->subscribe( $feed_url, $feed_options[ $feed_url ] );
			$count += 1;
		}

		add_filter( 'notify_about_new_friend_post', '__return_false', 999 );

		$friend_user->retrieve_posts();

		if ( isset( $vars['errors'] ) ) {
			$this->display_errors( $vars['errors'] );
		}

		$friend_link = '<a href="' . esc_url( $this->admin_edit_user_link( $friend_user->get_local_friends_page_url(), $friend_user ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $friend_user->display_name ) . '</a>';
		if ( $friend_user->has_cap( 'pending_friend_request' ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
				<?php
				// translators: %s is a Site URL.
				echo wp_kses( sprintf( __( 'Friendship requested for site %s.', 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
				// translators: %s is a Site URL.
				echo ' ', wp_kses( sprintf( __( 'Until they respond, we have already subscribed you to their updates.', 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
				// translators: %s is the friends page URL.
				echo ' ', wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> to view their posts.', 'friends' ), '"' . esc_url( $friend_user->get_local_friends_page_url() ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
				?>
			</p></div>
			<?php
			return true;
		}

		if ( $friend_user->has_cap( 'friend' ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
				<?php
				// translators: %s is a Site URL.
				echo wp_kses( sprintf( __( "You're now a friend of site %s.", 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
				// translators: %s is the friends page URL.
				echo ' ', wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> to view their posts.', 'friends' ), '"' . home_url( '/friends/' . $friend_user->user_login . '/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
				?>
			</p></div>
			<?php
			return true;
		}

		if ( $friend_user->has_cap( 'subscription' ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p>
				<?php
				if ( isset( $vars['friendship'] ) ) {
					// translators: %s is a Site URL.
					echo wp_kses( sprintf( __( 'No friends plugin installed at %s.', 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
					echo ' ';
				} else {
					// translators: %s is a Site URL.
					echo wp_kses( sprintf( __( "You're now subscribed to %s.", 'friends' ), $friend_link ), array( 'a' => array( 'href' => array() ) ) );
					echo ' ';
				}
				esc_html_e( 'We subscribed you to their updates.', 'friends' );
				// translators: %s is the friends page URL.
				echo ' ', wp_kses( sprintf( __( 'Go to your <a href=%s>friends page</a> to view their posts.', 'friends' ), '"' . home_url( '/friends/' . $friend_user->user_login . '/' ) . '"' ), array( 'a' => array( 'href' => array() ) ) );
				?>
			</p></div>
			<?php
			return true;
		}
		?>
		<div id="message" class="updated notice is-dismissible"><p>
			<?php
			// translators: %s is a username.
			echo esc_html( sprintf( __( 'User %s could not be assigned the appropriate role.', 'friends' ), $friend_user->display_name ) );
			?>
		</p></div>
		<?php
		return false;
	}

	/**
	 * Process the Add Friend form.
	 *
	 * @param      array $vars   The POST or GET variables.
	 *
	 * @return     boolean A \WP_Error or void.
	 */
	public function process_admin_add_friend( $vars ) {
		$errors = new \WP_Error();
		$args = array();

		$friend_url = isset( $vars['friend_url'] ) ? trim( $vars['friend_url'] ) : '';
		$codeword = isset( $vars['codeword'] ) ? trim( $vars['codeword'] ) : '';
		$message = isset( $vars['message'] ) ? trim( $vars['message'] ) : '';

		$friends_plugin = false;
		$friend_user = false;

		$protocol = wp_parse_url( $friend_url, PHP_URL_SCHEME );
		if ( ! $protocol ) {
			// Allow adding a friend by username.
			if ( is_multisite() ) {
				$friend_user = get_user_by( 'login', $friend_url );
				if ( $friend_user ) {
					$site = get_active_blog_for_user( $friend_user->ID );
					// Ensure we're using the same URL protocol.
					$friend_url = set_url_scheme( $site->siteurl );
				}
			}

			// If unsuccessful, then the protocol was forgotten.
			if ( ! $friend_user ) {
				$friend_url = 'https://' . $friend_url;
			}
		}
		$friend_user_login = User::get_user_login_for_url( $friend_url );
		$friend_display_name = User::get_display_name_for_url( $friend_url );

		$friend_user = get_user_by( 'login', $friend_user_login );

		if ( $friend_user ) {
			$args['friends_multisite_user_login'] = $friend_user_login;
			$args['friends_multisite_display_name'] = $friend_display_name;
		}
		$rest_url = false;

		if ( ( isset( $vars['step2'] ) && isset( $vars['feeds'] ) && is_array( $vars['feeds'] ) ) || isset( $vars['step3'] ) ) {
			$friend_user_login = sanitize_user( $vars['user_login'] );
			$friend_display_name = sanitize_text_field( $vars['display_name'] );
			if ( ! $friend_user_login ) {
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				$errors->add( 'user_login', __( '<strong>Error</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
			} elseif ( ! is_multisite() && username_exists( $friend_user_login ) ) {
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				$errors->add( 'user_login', __( '<strong>Error</strong>: This username is already registered. Please choose another one.' ) );
			}

			if ( empty( $vars['subscribe'] ) && empty( $vars['friendship'] ) ) {
				// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				$errors->add( 'no_action', __( '<strong>Error</strong>: Nothing to subscribe selected.', 'friends' ) );
			}

			$feeds = $vars['feeds'];
			if ( ! $errors->has_errors() ) {
				$friend_user = false;
				if ( isset( $vars['friendship'] ) ) {
					$friend_user = $this->send_friend_request( $vars['friendship'], $friend_user_login, $friend_url, $friend_display_name, $codeword, $message );
					if ( $friend_user->has_errors() ) {
						$vars['errors'] = $friend_user;
					}
				}

				if ( ! $friend_user || is_wp_error( $friend_user ) ) {
					$friend_user = User::create( $friend_user_login, 'subscription', $friend_url, $friend_display_name );
				}

				return $this->process_admin_add_friend_response( $friend_user, $vars );
			}

			if ( isset( $vars['friendship'] ) ) {
				$rest_url = $vars['friendship'];
			} else {
				$rest_url = $this->friends->rest->get_friends_rest_url( $feeds );
			}
		} else {
			if ( home_url() === trailingslashit( $friend_url ) ) {
				return new \WP_Error( 'friend-yourself', __( 'It seems like you sent a friend request to yourself.', 'friends' ) );
			}

			$friend_user = User::get_user( $friend_user_login );
			if ( $friend_user && ! is_wp_error( $friend_user ) ) {
				if ( $friend_user->is_valid_friend() ) {
					return new \WP_Error( 'already-friend', __( 'You are already friends with this site.', 'friends' ) );
				}

				// translators: %s is the name of a friend / site.
				return new \WP_Error( 'already-subscribed', sprintf( __( 'You are already subscribed to this site: %s', 'friends' ), '<a href="' . esc_url( $this->admin_edit_user_link( $friend_user->get_local_friends_page_url(), $friend_user ) ) . '">' . esc_html( $friend_user->display_name ) . '</a>' ) );
			}

			$feeds = $this->friends->feed->discover_available_feeds( $friend_url );
			if ( is_wp_error( $feeds ) ) {
				return $feeds;
			}
			if ( ! $feeds ) {
				return new \WP_Error( 'no-feed-found', __( 'No suitable feed was found at the provided address.', 'friends' ) );
			}
			$better_display_name = User::get_display_name_from_feeds( $feeds );
			if ( $better_display_name ) {
				$friend_display_name = $better_display_name;
				$friend_user_login = User::sanitize_username( $better_display_name );
			}

			$rest_url = $this->friends->rest->get_friends_rest_url( $feeds );
		}

		if ( $rest_url ) {
			$friends_plugin = $rest_url;
			unset( $feeds[ $rest_url ] );
		}

		if ( 1 === count( $feeds ) && isset( $vars['quick-subscribe'] ) ) {
			$vars['feeds'] = $feeds;
			$vars['subscribe'] = array_keys( $feeds );

			$friend_user = false;
			if ( isset( $rest_url ) ) {
				$friend_user = $this->send_friend_request( $rest_url, $friend_user_login, $friend_url, $friend_display_name, $codeword, $message );
			}

			if ( ! $friend_user || is_wp_error( $friend_user ) ) {
				$friend_user = User::create( $friend_user_login, 'subscription', $friend_url, $friend_display_name );
			}

			return $this->process_admin_add_friend_response( $friend_user, $vars );
		}

		if ( $errors->has_errors() ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php echo wp_kses( $errors->get_error_message(), array( 'strong' => array() ) ); ?></p>
			</div>
			<?php
		}

		Friends::template_loader()->get_template_part(
			'admin/select-feeds',
			null,
			array_merge(
				$args,
				array(
					'friends_plugin'      => $friends_plugin,
					'friend_url'          => $friend_url,
					'friend_user_login'   => $friend_user_login,
					'friend_display_name' => $friend_display_name,
					'friend_roles'        => $this->get_friend_roles(),
					'default_role'        => get_option( 'friends_default_friend_role', 'friend' ),
					'codeword'            => $codeword,
					'message'             => $message,
					'post_formats'        => array_merge( array( 'autodetect' => __( 'Autodetect Post Format', 'friends' ) ), get_post_format_strings() ),
					'registered_parsers'  => $this->friends->feed->get_registered_parsers(),
					'feeds'               => $feeds,
				)
			)
		);
	}

	/**
	 * Render the admin form for sending a friend request.
	 */
	public function render_admin_add_friend() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to add friends.', 'friends' ) );
		}

		if ( ! empty( $_GET['preview'] ) ) {
			$url = $_GET['preview'];

			?>
			<h1>
				<?php
				// translators: %s is a URL.
				echo esc_html( sprintf( __( 'Preview for %s', 'friends' ), $url ) );
				?>
			</h1>
			<?php

			if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'preview-feed' ) ) {
				?>
				<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'For security reasons, this preview is not available.', 'friends' ); ?></p>
				</div>
				<?php
				exit;
			}

			$parser_name = $this->friends->feed->get_registered_parser( $_GET['parser'] );
			if ( ! $parser_name ) {
				?>
				<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'An unknown parser name was supplied.', 'friends' ); ?></p>
				</div>
				<?php
				exit;
			}
			?>
			<h3><?php esc_html_e( 'Parser Details', 'friends' ); ?></h3>
			<ul id="parser">
				<li>
					<?php
					echo wp_kses(
						// translators: %s is the name of a parser, e.g. simplepie.
						sprintf( __( 'Parser: %s', 'friends' ), $parser_name ),
						array(
							'a' => array(
								'href'   => array(),
								'rel'    => array(),
								'target' => array(),
							),
						)
					);
					?>
				</li>
			</ul>
			<h3><?php esc_html_e( 'Items in the Feed', 'friends' ); ?></h3>

			<?php

			$items = $this->friends->feed->preview( $_GET['parser'], $url, isset( $_GET['feed'] ) ? intval( $_GET['feed'] ) : null );
			if ( is_wp_error( $items ) ) {
				?>
				<div id="message" class="updated notice is-dismissible"><p><?php echo esc_html( $items->get_error_message() ); ?></p>
				</div>
				<?php
				exit;
			}
			?>

			<ul>
				<?php
				foreach ( $items as $item ) {
					$title = $item->title;
					if ( 'status' === $item->post_format ) {
						$title = $item->content;
					}
					?>
					<li><a href="<?php echo esc_url( $item->permalink ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item->date ); ?></a> (author: <?php echo esc_html( $item->author ); ?>, type: <?php echo esc_html( $item->post_format ); ?>):
						<?php if ( $title ) : ?>
							<a href="<?php echo esc_url( $item->permalink ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $title ); ?></a>
							<?php else : ?>
								<p>
									<?php
									echo wp_kses(
										wp_trim_excerpt( $item->content ),
										array(
											'a'   => array( 'href' => array() ),
											'img' => array( 'src' => array() ),
										)
									);
									?>
								</p>
							<?php endif; ?>
						</li>
						<?php
				}
				?>
				</ul>
				<?php
				exit;
		}

		if ( apply_filters( 'friends_debug', false ) && isset( $_GET['next'] ) ) {
			$_POST = $_REQUEST;
			$_POST['_wpnonce'] = wp_create_nonce( 'add-friend' );
			if ( ! empty( $_POST['url'] ) && ! isset( $_POST['friend_url'] ) ) {
				$_POST['friend_url'] = $_POST['url'];
				$parsed_url = parse_url( $_POST['friend_url'] );
				if ( isset( $parsed_url['host'] ) ) {
					if ( ! isset( $parsed_url['scheme'] ) ) {
						$_POST['friend_url'] = 'https://' . ltrim( $_POST['friend_url'], '/' );
					}
				}
			}
		}

		?>
			<h1><?php esc_html_e( 'Add New Friend', 'friends' ); ?></h1>
			<?php
			$response = null;
			$postdata = apply_filters( 'friends_add_friend_postdata', $_POST );
			if ( ! empty( $postdata ) ) {
				if ( ! wp_verify_nonce( $postdata['_wpnonce'], 'add-friend' ) ) {
					$response = new \WP_Error( 'invalid-nonce', __( 'For security reasons, please verify the URL and click next if you want to proceed.', 'friends' ) );
				} else {
					$response = $this->process_admin_add_friend( $postdata );
				}
				if ( is_wp_error( $response ) ) {
					?>
					<div id="message" class="updated notice is-dismissible"><p>
						<?php
						$message = $response->get_error_message();
						if ( $response->get_error_data() ) {
							$message .= ' (' . $response->get_error_data() . ')';
						}
						echo wp_kses(
							$message,
							array(
								'strong' => array(),
								'a'      => array(
									'href'   => array(),
									'rel'    => array(),
									'target' => array(),
								),
							)
						);
						?>
					</p>
				</div>
					<?php
				}
				if ( is_null( $response ) ) {
					return;
				}
			}

			$args = array(
				'friend_url' => '',
			);
			if ( ! empty( $_GET['url'] ) || ! empty( $_POST['url'] ) ) {
				$friend_url = isset( $_GET['url'] ) ? $_GET['url'] : $_POST['url'];
				$parsed_url = parse_url( $friend_url );
				if ( isset( $parsed_url['host'] ) ) {
					if ( ! isset( $parsed_url['scheme'] ) ) {
						$args['friend_url'] = 'https://' . ltrim( $friend_url, '/' );
					} else {
						$args['friend_url'] = $friend_url;
					}
				}
			}

			Friends::template_loader()->get_template_part( 'admin/add-friend', null, $args );

			$friend_requests = new User_Query(
				array(
					'role__in' => array( 'friend', 'acquaintance', 'pending_friend_request', 'friend_request', 'subscription' ),
					'orderby'  => 'registered',
					'order'    => 'DESC',
				)
			);

		Friends::template_loader()->get_template_part(
			'admin/latest-friends',
			null,
			array(
				'friend_requests' => $friend_requests->get_results(),
			)
		);
	}

	/**
	 * Process the admin notification manager form submission.
	 */
	public function process_admin_notification_manager() {
		$this->check_admin_settings();

		if ( empty( $_POST ) || empty( $_POST['friend_listed'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'notification-manager' ) ) {
			return;
		}
		$friend_ids = $_POST['friend_listed'];
		$current_user_id = get_current_user_id();
		$hide_from_friends_page = array();

		foreach ( $friend_ids as $friend_id ) {
			if ( ! isset( $_POST['show_on_friends_page'][ $friend_id ] ) ) {
				$hide_from_friends_page[] = $friend_id;
			}

			$no_new_post_notification = ! isset( $_POST['new_post_notification'][ $friend_id ] );
			if ( get_user_option( 'friends_no_new_post_notification_' . $friend_id ) !== $no_new_post_notification ) {
				update_user_option( $current_user_id, 'friends_no_new_post_notification_' . $friend_id, $no_new_post_notification );
			}

			$no_keyword_notification = ! isset( $_POST['keyword_notification'][ $friend_id ] );
			if ( get_user_option( 'friends_no_keyword_notification_' . $friend_id ) !== $no_keyword_notification ) {
				update_user_option( $current_user_id, 'friends_no_keyword_notification_' . $friend_id, $no_keyword_notification );
			}
		}

		update_user_option( $current_user_id, 'friends_hide_from_friends_page', $hide_from_friends_page );

		do_action( 'friends_notification_manager_after_form_submit', $friend_ids );

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( 'updated', '1', remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
		}
		exit;

	}

	/**
	 * Render the admin notification manager.
	 */
	public function render_admin_notification_manager() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to add friends.', 'friends' ) );
		}

		?>
		<h1><?php esc_html_e( 'Notification Manager', 'friends' ); ?></h1>
		<?php

		$friend_users = new User_Query(
			array(
				'role__in' => array( 'friend', 'acquaintance', 'pending_friend_request', 'friend_request', 'subscription' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);

		$hide_from_friends_page = get_user_option( 'friends_hide_from_friends_page' );
		if ( ! $hide_from_friends_page ) {
			$hide_from_friends_page = array();
		}

		Friends::template_loader()->get_template_part(
			'admin/notification-manager',
			null,
			array(
				'friend_users'             => $friend_users->get_results(),
				'friends_settings_url'     => add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=friends-settings' ) ),
				'hide_from_friends_page'   => $hide_from_friends_page,
				'no_new_post_notification' => get_user_option( 'friends_no_new_post_notification' ),
				'no_keyword_notification'  => get_user_option( 'friends_no_keyword_notification' ),
				'active_keywords'          => Feed::get_active_notification_keywords(),
			)
		);
	}

	/**
	 * Gets the friend roles.
	 *
	 * @return     array  The friend roles.
	 */
	public function get_friend_roles() {
		return array(
			'friend'       => _x( 'Friend', 'User role', 'friends' ),
			'acquaintance' => _x( 'Acquaintance', 'User role', 'friends' ),
		);
	}

	/**
	 * Gets the roles associated with the Friends plugin.
	 *
	 * @return     array  The associated roles.
	 */
	public function get_associated_roles() {
		return apply_filters(
			'friends_associated_roles',
			array_merge(
				$this->get_friend_roles(),
				array(
					'friend_request'         => _x( 'Friend Request', 'User role', 'friends' ),
					'pending_friend_request' => _x( 'Pending Friend Request', 'User role', 'friends' ),
					'subscription'           => _x( 'Subscription', 'User role', 'friends' ),
				)
			)
		);
	}

	public function get_users_url() {
		return 'users.php?role=' . implode( ',', array_keys( $this->get_associated_roles() ) );
	}

	/**
	 * Add actions to the user rows
	 *
	 * @param  array    $actions The existing actions.
	 * @param  \WP_User $user    The user in question.
	 * @return array The extended actions.
	 */
	public function user_row_actions( array $actions, \WP_User $user ) {
		if (
			! current_user_can( Friends::REQUIRED_ROLE ) ||
			(
				! $user->has_cap( 'friend_request' ) &&
				! $user->has_cap( 'pending_friend_request' ) &&
				! $user->has_cap( 'friend' ) &&
				! $user->has_cap( 'subscription' )
			)
		) {
			return $actions;
		}

		if ( is_multisite() ) {
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			$actions = array_merge( array( 'edit' => '<a href="' . esc_url( self_admin_url( 'admin.php?page=edit-friend&user=' . $user->ID ) ) . '">' . __( 'Edit' ) . '</a>' ), $actions );
		}

		// Ensuire we have a friends user here.
		$user = new User( $user );

		$actions['view'] = $this->friends->frontend->get_link(
			$user->user_url,
			sprintf(
			// translators: %s: Author’s display name.
				__( 'Visit %s&#8217;s website' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				$user->display_name
			),
			array(),
			$user
		);
		unset( $actions['resetpassword'] );

		if ( $user->has_cap( 'friend_request' ) ) {
			$link = self_admin_url( wp_nonce_url( 'users.php?action=accept_friend_request&users[]=' . $user->ID ) );

			$actions['user_accept_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Accept Friend Request', 'friends' ) . '</a>';
			$message = get_user_option( 'friends_request_message', $user->ID );
			$actions['friends friends_request_date'] = '<br/><span class="nonessential">' . esc_html(
				sprintf(
				// translators: %s is a date.
					__( 'Requested on %s', 'friends' ),
					date_i18n( __( 'F j, Y g:i a' ), strtotime( $user->user_registered ) ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				)
			) . '</span>';
			if ( $message ) {
				// translators: %s is a message text.
				$actions['friends friend_request_message'] = '<br/><span class="nonessential">' . esc_html( sprintf( __( 'Message: %s', 'friends' ), $message ) ) . '</span>';
			}
		}

		if ( $user->has_cap( 'pending_friend_request' ) || $user->has_cap( 'subscription' ) ) {
			$link = wp_nonce_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=edit-friend&user=' . $user->ID ) ), 'add-friend-' . $user->ID, 'add-friend' );
			if ( $user->has_cap( 'pending_friend_request' ) ) {
				$actions['user_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Resend Friend Request', 'friends' ) . '</a>';
			} elseif ( $user->has_cap( 'subscription' ) ) {
				$actions['user_friend_request'] = '<a href="' . esc_url( $link ) . '">' . __( 'Send Friend Request', 'friends' ) . '</a>';
			}
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
			$user = new User( $user_id );
			if ( ! $user || is_wp_error( $user ) ) {
				continue;
			}

			if ( ! $user->has_cap( 'friend_request' ) ) {
				continue;
			}

			if ( $user->has_cap( 'friend' ) ) {
				continue;
			}

			$user->set_role( get_option( 'friends_default_friend_role', 'friend' ) );
			$accepted++;
		}

		if ( ! $sendback ) {
			return array(
				'accepted' => $accepted,
			);
		}

		$sendback = add_query_arg( 'accepted', $accepted, $sendback );
		$sendback = remove_query_arg( 'role', $sendback );
		wp_safe_redirect( $sendback );
	}

	/**
	 * Add options to the Bulk dropdown on the users page
	 *
	 * @param array $actions The existing bulk options.
	 * @return array The extended bulk options.
	 */
	public function add_user_bulk_options( $actions ) {
		$friends = User_Query::all_friend_requests();
		$friends->get_results();

		if ( ! empty( $friends ) ) {
			$actions['accept_friend_request'] = __( 'Accept Friend Request', 'friends' );
		}

		$friends = User_Query::all_subscriptions();
		$friends->get_results();

		if ( ! empty( $friends ) ) {
			$actions['friend_request'] = __( 'Send Friend Request', 'friends' );
		}

		return $actions;
	}

	/**
	 * Add a column "Posts" (that emcompasses both user andfriend posts.)
	 *
	 * @param      array $columns  The columns.
	 *
	 * @return     array  The columns extended by the friends_posts.
	 */
	public function user_list_columns( $columns ) {
		$columns['friends_posts'] = __( 'Friend Posts', 'friends' );
		unset( $columns['email'] );
		return $columns;
	}

	/**
	 * Return the results for the friends_posts column.
	 *
	 * @param string $output      Custom column output. Default empty.
	 * @param string $column_name Column name.
	 * @param int    $user_id     ID of the currently-listed user.
	 *
	 * @return     string  The column contents.
	 */
	public function user_list_custom_column( $output, $column_name, $user_id ) {
		if ( 'friends_posts' !== $column_name ) {
			return $output;
		}
		$numposts = count_user_posts( $user_id, array_merge( array( 'post' ), Friends::get_frontend_post_types() ) );
		$user = User::get_user_by_id( $user_id );
		return sprintf(
			'<a href="%s" class="edit"><span aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
			$user ? $user->get_local_friends_page_url() : "edit.php?author={$user_id}",
			$numposts,
			sprintf(
				/* translators: %s: Number of posts. */
				_n( '%s post by this author', '%s posts by this author', $numposts, 'friends' ),
				number_format_i18n( $numposts )
			)
		);
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
		if ( empty( $title ) && is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && 'edit-post' === $screen->id ) {
				if ( 'status' === get_post_format() ) {
					return get_the_excerpt();
				}
			}
		}
		return $title;
	}

	/**
	 * Adds the friend requests to the unread count.
	 *
	 * @param      int $unread  The unread count.
	 *
	 * @return     int   Unread count + friend requests.
	 */
	public function friends_unread_friend_request_count( $unread ) {
		$friend_requests = User_Query::all_friend_requests();
		return $unread + $friend_requests->get_total();
	}

	/**
	 * Add open friend requests to the menu.
	 *
	 * @param      \WP_Menu $wp_menu  The wp menu.
	 * @param      string   $my_url   My url.
	 */
	public function friends_add_menu_open_friend_request( $wp_menu, $my_url ) {
		$friend_request_count = $this->friends_unread_friend_request_count( 0 );
		if ( $friend_request_count > 0 ) {
			$wp_menu->add_menu(
				array(
					'id'     => 'open-friend-requests',
					'parent' => 'friends',
					// translators: %s is the number of open friend requests.
					'title'  => esc_html( sprintf( _n( 'Review %s Friend Request', 'Review %s Friends Request', $friend_request_count, 'friends' ), $friend_request_count ) ),
					'href'   => $my_url . '/wp-admin/users.php?role=friend_request',
				)
			);
		}
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
		$my_url = false;
		$my_own_site = false;
		$on_my_own_site = false;
		$we_requested_friendship = false;
		$they_requested_friendship = false;

		if ( is_multisite() ) {
			$site = get_active_blog_for_user( get_current_user_id() );
			if ( ! $site ) {
				// If we cannot find a site, we shouldn't show the admin bar entry.
				return;
			}

			$my_url = set_url_scheme( $site->siteurl );
			$my_own_site = $site;
			$on_my_own_site = get_current_blog_id() === intval( $site->blog_id );
			if ( is_user_member_of_blog( get_current_user_id(), get_current_blog_id() ) ) {
				if ( current_user_can( 'pending_friend_request' ) ) {
					$they_requested_friendship = true;
				} elseif ( current_user_can( 'friend_request' ) ) {
					$we_requested_friendship = true;
				}
			}
		} elseif ( current_user_can( 'friend' ) ) {
			$current_user = wp_get_current_user();
			if ( ! $current_user->user_url ) {
				return;
			}

			$my_url = $current_user->user_url;
		} elseif ( current_user_can( Friends::REQUIRED_ROLE ) ) {
			$my_url = home_url();
			$on_my_own_site = true;
		}

		if ( ! $on_my_own_site && $my_own_site ) {
			switch_to_blog( $my_own_site->blog_id );
		}

		$wp_menu->add_node(
			array(
				'id'     => 'friends',
				'parent' => '',
				'title'  => '<span class="ab-icon dashicons dashicons-groups"></span> <span class="ab-label">' . esc_html( __( 'Friends', 'friends' ) ) . $this->get_unread_badge() . '</span>',
				'href'   => $my_url . '/friends/',
			)
		);

		do_action( 'friends_own_site_menu_top', $wp_menu, $my_url );

		if ( ! $on_my_own_site && $my_own_site ) {
			restore_current_blog();
		}

		do_action( 'friends_current_site_menu_top', $wp_menu, $my_url );

		$wp_menu->add_menu(
			array(
				'id'     => 'your-feed',
				'parent' => 'friends',
				'title'  => esc_html__( 'My Friends Feed', 'friends' ),
				'href'   => $my_url . '/friends/',
			)
		);

		if ( $they_requested_friendship ) {
			$wp_menu->add_menu(
				array(
					'id'     => 'add-friend',
					'parent' => 'friends',
					'title'  => esc_html(
						sprintf(
							// translators: %s is a site title.
							__( "Respond to %s's friend request", 'friends' ),
							get_bloginfo( 'name' )
						)
					),
					'href'   => $my_url . '/wp-admin/' . $this->get_users_url(),
				)
			);
		}

		if ( $on_my_own_site ) {
			$wp_menu->add_menu(
				array(
					'id'     => 'your-profile',
					'parent' => 'friends',
					'title'  => esc_html__( 'My Public Friends Profile', 'friends' ),
					'href'   => $my_url . '/friends/?public',
				)
			);
			$wp_menu->add_menu(
				array(
					'id'     => 'friends-requests',
					'parent' => 'friends',
					'title'  => esc_html__( 'My Friends & Requests', 'friends' ),
					'href'   => $my_url . '/wp-admin/' . $this->get_users_url(),
				)
			);
			$wp_menu->add_menu(
				array(
					'id'     => 'friends-settings',
					'parent' => 'friends',
					'title'  => esc_html__( 'Settings' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'href'   => $my_url . '/wp-admin/admin.php?page=friends-settings',
				)
			);
		} else {
			if ( ! current_user_can( 'friend' ) ) {
				if ( $we_requested_friendship ) {
					$wp_menu->add_menu(
						array(
							'id'     => 'add-friend',
							'parent' => 'friends',
							'title'  => esc_html__( 'Friendship Already Requested', 'friends' ),
							'href'   => $my_url . '/wp-admin/' . $this->get_users_url(),
						)
					);
				} elseif ( ! $they_requested_friendship ) {
					$wp_menu->add_menu(
						array(
							'id'     => 'add-friend',
							'parent' => 'friends',
							'title'  => esc_html(
								sprintf(
									// translators: %s is a site title.
									__( 'Add %s as a friend', 'friends' ),
									get_bloginfo( 'name' )
								)
							),
							'href'   => $my_url . '/?add-friend=' . urlencode( home_url() ),
						)
					);
				}
			}

			$wp_menu->add_menu(
				array(
					'id'     => 'profile',
					'parent' => 'friends',
					'title'  => esc_html(
						sprintf(
						// translators: %s is a site title.
							__( "%s's Profile", 'friends' ),
							get_bloginfo( 'name' )
						)
					),
					'href'   => home_url( '/friends/' ),
				)
			);
		}
	}

	/**
	 * Add Friend entries to the New Content admin section
	 *
	 * @param  \WP_Admin_Bar $wp_menu The admin bar to modify.
	 */
	public function admin_bar_new_content( \WP_Admin_Bar $wp_menu ) {
		if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
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
		User_Feed::delete_user_terms( $user_id );
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
		$count_users = count_users();
		$count = array_merge(
			array(
				'friend'         => 0,
				'acquaintance'   => 0,
				'friend_request' => 0,
				'subscription'   => 0,
			),
			$count_users['avail_roles']
		);
		$friend_count = $count['friend'] + $count['acquaintance'];
		$friend_request_count = $count['friend_request'];
		$subscription_count = $count['subscription'];
		$friend_post_count = wp_count_posts( Friends::CPT );
		$friend_post_count = $friend_post_count->publish + $friend_post_count->private;

		$items[] = '<a class="friends" href="' . self_admin_url( 'users.php?role=friend' ) . '">' . sprintf(
			// translators: %s is the number of your friends.
			_n( '%s Friend', '%s Friends', $friend_count, 'friends' ),
			$friend_count
		) . '</a>';
		if ( $friend_request_count ) {
			// translators: %s is the number of friend requests.
			$items[] = '<a class="friend-requests" href="' . self_admin_url( 'users.php?role=friend_request' ) . '">' . sprintf( _n( '%s Friend Request', '%s Friend Requests', $friend_request_count, 'friends' ), $friend_request_count ) . '</a>';
		}
		if ( $subscription_count ) {
			// translators: %s is the number of subscriptions.
			$items[] = '<a class="subscriptions" href="' . self_admin_url( 'users.php?role=subscription' ) . '">' . sprintf( _n( '%s Subscription', '%s Subscriptions', $subscription_count, 'friends' ), $subscription_count ) . '</a>';
		}

		if ( $friend_post_count ) {
			// translators: %s is the number of friend posts.
			$items[] = '<a class="friend-posts" href="' . home_url( '/friends/' ) . '">' . sprintf( _n( '%s Post by Friends', '%s Posts by Friends', $friend_post_count, 'friends' ), $friend_post_count ) . '</a>';
		}
		return $items;
	}

	public function site_status_tests( $tests ) {
		$tests['direct']['friends-roles'] = array(
			'label' => __( 'Friend roles were created', 'friends' ),
			'test'  => array( $this, 'friend_roles_test' ),
		);
		return $tests;
	}

	public function get_missing_friends_plugin_roles() {
		$missing = array();
		foreach ( Friends::get_friends_plugin_roles() as $role ) {
			if ( ! get_role( $role ) ) {
				$missing[] = $role;
			}
		}
		return $missing;
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
					esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), self_admin_url( 'admin.php?page=friends-settings&rerun-activate' ) ), 'friends-settings' ) )
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
					'value' => strip_tags( implode( ', ', $this->friends->feed->get_registered_parsers() ) ),
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

}
