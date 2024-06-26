<?php
/**
 * Friends Automatic Status
 *
 * This contains the functions for the Automatic Status.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Friends Plugin Automatic Status.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Automatic_Status {
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
		add_action( 'friends_admin_menu_settings', array( $this, 'friends_admin_menu_settings' ), 20 );
		add_filter( 'friends_admin_settings_slugs', array( $this, 'friends_admin_settings_slugs' ), 20 );
		add_filter( 'friends_admin_tabs', array( $this, 'friends_admin_tabs' ) );
		add_filter( 'handle_bulk_actions-edit-post', array( $this, 'bulk_publish' ), 10, 3 );

		if ( ! get_option( 'friends_automatic_status_disabled' ) ) {
			return;
		}
		add_action( 'friends_user_post_reaction', array( $this, 'post_reaction' ), 10, 2 );
		add_action( 'set_user_role', array( $this, 'new_friend_user' ), 20, 2 );
		add_action( 'set', array( $this, 'new_friend_user' ), 10, 2 );
	}

	/**
	 * Add the admin menu to the sidebar.
	 *
	 * @param      string $page_type  The page type.
	 */
	public function friends_admin_menu_settings( $page_type ) {
		add_submenu_page(
			'friends',
			__( 'Automatic Status', 'friends' ),
			'- ' . __( 'Automatic Status', 'friends' ),
			Friends::required_menu_role(),
			'friends-auto-status',
			array( $this, 'validate_drafts' )
		);

		add_action( 'load-' . $page_type . '_page_friends-auto-status', array( $this, 'redirect_to_post_format_url' ) );
		add_action( 'load-' . $page_type . '_page_friends-auto-status', array( $this, 'process_settings' ) );
	}

	public function friends_admin_settings_slugs( $slugs ) {
		$slugs[] = 'friends-auto-status';
		return $slugs;
	}

	public function friends_admin_tabs( $menu ) {
		$menu[ __( 'Automatic Status', 'friends' ) ] = 'friends-auto-status';
		return $menu;
	}

	/**
	 * Add the admin menu to the tabs.
	 *
	 * @param      array $menu   The menu.
	 *
	 * @return     array  The modified menu.
	 */
	public function admin_tabs( array $menu ) {
		$menu[ __( 'Automatic Status', 'friends' ) ] = 'friends-auto-status';
		return $menu;
	}

	public function redirect_to_post_format_url() {
		if ( empty( $_GET['post_format'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect(
				add_query_arg(
					array(
						'post_format' => 'status',
						'post_status' => 'draft',
						'post_author' => get_current_user_id(),
					),
					self_admin_url( 'admin.php?page=friends-auto-status' )
				)
			);
			exit;
		}
	}

	public function process_settings() {
		if ( empty( $_REQUEST ) || ! isset( $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'friends-automatic-status' ) ) {
			return;
		}

		if ( isset( $_POST['enabled'] ) && boolval( $_POST['enabled'] ) ) {
			delete_option( 'friends_automatic_status_disabled' );
		} else {
			update_option( 'friends_automatic_status_disabled', 1 );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( isset( $_GET['_wp_http_referer'] ) ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( add_query_arg( 'updated', '1', remove_query_arg( array( '_wp_http_referer', '_wpnonce' ) ) ) );
		}
	}

	/**
	 * This displays the Automatically Generated Statuses admin page.
	 */
	public function validate_drafts() {
		add_filter(
			'manage_edit-post_columns',
			function ( $columns ) {
				unset( $columns['categories'] );
				unset( $columns['tags'] );
				unset( $columns['comments'] );
				return $columns;
			},
			30,
			3
		);

		add_filter(
			'post_row_actions',
			function ( $actions, $post ) {
				unset( $actions['view'], $actions['inline hide-if-no-js'] );
				if ( 'publish' === $post->post_status ) {
					$actions['publish'] = '<em>' . __( 'Published' ) . '</em>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				} else {
					$actions['publish'] = sprintf(
						'<a href="%s" aria-label="%s">%s</a>',
						esc_url(
							add_query_arg(
								array(
									'post_status'      => 'draft',
									'post_format'      => 'status',
									'post_type'        => 'post',
									'_wpnonce'         => wp_create_nonce( 'bulk-posts' ),
									'_wp_http_referer' => self_admin_url( 'admin.php?page=friends-auto-status' ),
									'action'           => 'publish',
									'post[]'           => $post->ID,
								),
								self_admin_url( 'edit.php' )
							)
						),
						/* translators: %s: Post title. */
						esc_attr( sprintf( __( 'Publish &#8220;%s&#8221;' ), $post->title ) ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
						__( 'Publish' ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					);
				}
				if ( 'private' === $post->post_status ) {
					$actions['publish-private'] = '<em>' . __( 'Privately Published' ) . '</em>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				} else {
					$actions['publish-private'] = sprintf(
						'<a href="%s" aria-label="%s">%s</a>',
						esc_url(
							add_query_arg(
								array(
									'post_status'      => 'draft',
									'post_format'      => 'status',
									'post_type'        => 'post',
									'_wpnonce'         => wp_create_nonce( 'bulk-posts' ),
									'_wp_http_referer' => self_admin_url( 'admin.php?page=friends-auto-status' ),
									'action'           => 'publish-private',
									'post[]'           => $post->ID,
								),
								self_admin_url( 'edit.php' )
							)
						),
						/* translators: %s: Post title. */
						esc_attr( sprintf( __( 'Privately Publish &#8220;%s&#8221;', 'friends' ), $post->title ) ),
						__( 'Publish Privately', 'friends' )
					);
				}
				return $actions;
			},
			10,
			2
		);
		_get_list_table( 'WP_Posts_List_Table' );
		require_once __DIR__ . '/class-automatic-status-list-table.php';

		$post_type = 'post';
		$post_type_object = get_post_type_object( $post_type );

		$wp_list_table = new Automatic_Status_List_Table();
		$wp_list_table->prepare_items();

		wp_enqueue_script( 'inline-edit-post' );
		wp_enqueue_script( 'heartbeat' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$bulk_counts = array(
			'updated'   => isset( $_REQUEST['updated'] ) ? absint( $_REQUEST['updated'] ) : 0,
			'locked'    => isset( $_REQUEST['locked'] ) ? absint( $_REQUEST['locked'] ) : 0,
			'deleted'   => isset( $_REQUEST['deleted'] ) ? absint( $_REQUEST['deleted'] ) : 0,
			'trashed'   => isset( $_REQUEST['trashed'] ) ? absint( $_REQUEST['trashed'] ) : 0,
			'untrashed' => isset( $_REQUEST['untrashed'] ) ? absint( $_REQUEST['untrashed'] ) : 0,
		);

		$bulk_messages             = array();
		$bulk_messages['post']     = array(
			/* translators: %s: Number of posts. */
			'updated'   => _n( '%s post updated.', '%s posts updated.', $bulk_counts['updated'] ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'locked'    => ( 1 === $bulk_counts['locked'] ) ? __( '1 post not updated, somebody is editing it.' ) : // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
							/* translators: %s: Number of posts. */
							_n( '%s post not updated, somebody is editing it.', '%s posts not updated, somebody is editing them.', $bulk_counts['locked'] ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			/* translators: %s: Number of posts. */
			'deleted'   => _n( '%s post permanently deleted.', '%s posts permanently deleted.', $bulk_counts['deleted'] ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			/* translators: %s: Number of posts. */
			'trashed'   => _n( '%s post moved to the Trash.', '%s posts moved to the Trash.', $bulk_counts['trashed'] ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			/* translators: %s: Number of posts. */
			'untrashed' => _n( '%s post restored from the Trash.', '%s posts restored from the Trash.', $bulk_counts['untrashed'] ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		);
		$bulk_messages = apply_filters( 'bulk_post_updated_messages', $bulk_messages, $bulk_counts );
		$bulk_counts   = array_filter( $bulk_counts );

		Friends::template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'active' => 'friends-auto-status',
				'title'  => __( 'Friends', 'friends' ),
			)
		);

		if ( isset( $_GET['updated'] ) ) {
			?>
			<div id="message" class="updated notice is-dismissible"><p><?php esc_html_e( 'Settings were updated.', 'friends' ); ?></p></div>
			<?php
		} elseif ( isset( $_GET['error'] ) ) {
			?>
			<div id="message" class="updated error is-dismissible"><p><?php esc_html_e( 'An error occurred.', 'friends' ); ?></p></div>
			<?php
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		Friends::template_loader()->get_template_part( 'admin/automatic-status-list-table', false, compact( 'wp_list_table', 'post_type' ) );
		Friends::template_loader()->get_template_part( 'admin/settings-footer' );
	}

	/**
	 * This implements bulk publishing for the Automatically Generated Statuses page.
	 *
	 * @param string $sendback The redirect URL.
	 * @param string $doaction The action being taken.
	 * @param array  $post_ids The items to take the action on.
	 *
	 * @return     string  A potentially modified redirect URL.
	 */
	public function bulk_publish( $sendback, $doaction, $post_ids ) {
		if ( 'publish' === $doaction ) {
			$done = array(
				'published' => 0,
			);
			foreach ( $post_ids as $post_id ) {
				wp_publish_post( $post_id );
				++$done['published'];
			}

			$sendback = add_query_arg( $done, $sendback );
		} elseif ( 'publish-private' === $doaction ) {
			$done = array(
				'privately-published' => 0,
			);
			foreach ( $post_ids as $post_id ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'private',
					)
				);
				++$done['privately-published'];
			}

			$sendback = add_query_arg( $done, $sendback );
		}
		return $sendback;
	}

	/**
	 * Adds a status post.
	 *
	 * @param      string $text   The text.
	 *
	 * @return     int|\WP_Error  The post ID or a \WP_Error.
	 */
	private function add_status( $text ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$user_id = Friends::get_main_friend_user_id();
		}

		$new_post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_author'  => $user_id,
				'tax_input'    => array( 'post_format' => 'post-format-status' ),
				'post_content' => '<!-- wp:paragraph -->' . PHP_EOL . '<p>' . $text . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->',
			)
		);
		if ( ! is_wp_error( $new_post_id ) ) {
			wp_set_post_terms( $new_post_id, 'post-format-status', 'post_format' );
		}
		return $new_post_id;
	}

	/**
	 * Create a status based on a post reaction.
	 *
	 * @param      int    $post_id  The post ID.
	 * @param      string $emoji    The emoji.
	 */
	public function post_reaction( $post_id, $emoji ) {
		$post = get_post( $post_id );
		$title = get_the_title( $post );
		if ( empty( $title ) ) {
			$title = get_the_excerpt( $post );
		}
		$this->add_status(
			sprintf(
				// translators: %1$s is an emoji, %2$s is a linked post title.
				__( 'I just reacted with %1$s on %2$s.', 'friends' ),
				$emoji,
				'<a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( $title ) . '</a>'
			)
		);
	}

	/**
	 * Create a status based on a friend user status change.
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 */
	public function new_friend_user( $user_id, $new_role ) {
		$friend_user = new User( $user_id );
		$link = '<a href="' . esc_url( $friend_user->user_url ) . '">' . esc_html( $friend_user->display_name ) . '</a>';

		if ( 'friend' === $new_role || 'acquaintance' === $new_role ) {
			$this->add_status(
				sprintf(
					// translators: %s is a new friend.
					__( "I'm now friends with %s.", 'friends' ),
					$link
				)
			);
			return;
		}

		if ( 'subscription' === $new_role ) {
			$this->add_status(
				sprintf(
					// translators: %s is a new friend.
					__( "I've just subscribed %s.", 'friends' ),
					$link
				)
			);
			return;
		}
	}
}
