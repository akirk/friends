<?php
/**
 * Friends Messages
 *
 * This contains the functions for Messages.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Messages part of the Friends Plugin.
 *
 * @since 0.21
 *
 * @package Friends
 * @author Alex Kirk
 */
class Messages {
	const CPT = 'friend_message';

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
		add_action( 'init', array( $this, 'register_custom_post_type' ) );
		add_filter( 'friends_unread_count', array( $this, 'friends_unread_messages_count' ) );
		add_action( 'friends_own_site_menu_top', array( $this, 'friends_add_menu_unread_messages' ) );
		add_action( 'wp_ajax_friends-mark-read', array( $this, 'mark_message_read' ) );
		add_action( 'rest_api_init', array( $this, 'add_rest_routes' ) );
		add_action( 'friends_author_header', array( $this, 'friends_author_header' ), 10, 2 );
		add_action( 'friends_after_header', array( $this, 'friends_display_messages' ), 10, 2 );
		add_action( 'friends_after_header', array( $this, 'friends_message_form' ), 11, 2 );
		add_filter( 'template_redirect', array( $this, 'handle_message_send' ), 10, 2 );
	}


	/**
	 * Registers the custom post type
	 */
	public function register_custom_post_type() {
		$labels = array(
			'name'               => __( 'Friend Messages', 'friends' ),
			'singular_name'      => __( 'Friend Message', 'friends' ),
			'add_new'            => _x( 'Add New', 'cached friend post', 'friends' ),
			'add_new_item'       => __( 'Add New Friend Message', 'friends' ),
			'edit_item'          => __( 'Edit Friend Message', 'friends' ),
			'new_item'           => __( 'New Friend Message', 'friends' ),
			'all_items'          => __( 'All Friend Messages', 'friends' ),
			'view_item'          => __( 'View Friend Message', 'friends' ),
			'search_items'       => __( 'Search Friend Messages', 'friends' ),
			'not_found'          => __( 'No Friend Messages found', 'friends' ),
			'not_found_in_trash' => __( 'No Friend Messages found in the Trash', 'friends' ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Friend Messages', 'friends' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => "A friend's message",
			'publicly_queryable'  => Friends::has_required_privileges(),
			'show_ui'             => true,
			'show_in_menu'        => apply_filters( 'friends_show_cached_posts', false ),
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => Friends::has_required_privileges(),
			'exclude_from_search' => true,
			'public'              => false,
			'delete_with_user'    => true,
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-admin-comments',
			'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
			'taxonomies'          => array( 'friend-reaction-' . get_current_user_id() ),
			'has_archive'         => true,
			'rewrite'             => false,
		);

		register_post_type( self::CPT, $args );

		register_post_status(
			'friends_unread',
			array(
				'label'                     => _x( 'Unread', 'message', 'friends' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
				// translators: %s; number of unread messages.
				'label_count'               => _n_noop( 'Unread (%s)', 'Unread (%s)', 'friends' ),
			)
		);

		register_post_status(
			'friends_read',
			array(
				'label'                     => _x( 'Read', 'message', 'friends' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
				// translators: %s; number of read messages.
				'label_count'               => _n_noop( 'Read (%s)', 'Read (%s)', 'friends' ),
			)
		);

	}

	/**
	 * Add the REST API to send and receive friend requests
	 */
	public function add_rest_routes() {
		register_rest_route(
			REST::PREFIX,
			'message',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_receive_message' ),
				'permission_callback' => Friends::authenticated_for_posts(),
			)
		);
	}

	/**
	 * Receive a message via REST
	 *
	 * @param  \WP_REST_Request $request The incoming request.
	 * @return array The array to be returned via the REST API.
	 */
	public function rest_receive_message( \WP_REST_Request $request ) {
		$tokens = explode( '-', $request->get_param( 'auth' ) );
		$user_id = $this->friends->access_control->verify_token( $tokens[0], isset( $tokens[1] ) ? $tokens[1] : null, isset( $tokens[2] ) ? $tokens[2] : null );
		if ( ! $user_id ) {
			return new \WP_Error(
				'friends_request_failed',
				__( 'Could not respond to the request.', 'friends' ),
				array(
					'status' => 403,
				)
			);
		}

		$friend_user = new User( $user_id );
		if ( ! $friend_user->has_cap( self::get_minimum_cap() ) ) {
			return new \WP_Error(
				'friends_request_failed',
				__( 'Could not respond to the request.', 'friends' ),
				array(
					'status' => 403,
				)
			);
		}

		$subject = wp_unslash( $request->get_param( 'subject' ) );
		$message = wp_unslash( $request->get_param( 'message' ) );
		$this->add_message_to_post( $friend_user, $friend_user, $subject, $message, true );

		do_action( 'notify_friend_message_received', $friend_user, $message, $subject );

		return array(
			'status' => 'message-received',
		);
	}

	/**
	 * Adds a message to friends_message post.
	 *
	 * @param      \WP_User $sender       The sender.
	 * @param      User     $friend_user  The friend user to which this should be associated.
	 * @param      string   $subject      The subject.
	 * @param      string   $message      The message.
	 *
	 * @return     int     The post ID.
	 */
	private function add_message_to_post( \WP_User $sender, User $friend_user, $subject, $message ) {
		$existing_message = new \WP_Query(
			array(
				'post_type'   => self::CPT,
				'author'      => $friend_user->ID,
				'title'       => $subject,
				'post_status' => array( 'friends_read', 'friends_unread' ),
			)
		);
		$mark_unread = $sender->ID === $friend_user->ID;

		$content = '';
		$content .= '<!-- wp:friends/message {"sender":' . $sender->ID . ',"date":' . time() . '} -->' . PHP_EOL;
		$content .= '<div class="wp-block-friends-message">';
		$content .= '<span class="date">' . esc_html( gmdate( 'Y-m-d H:i:s' ) ) . '</span> ';
		$content .= '<strong>' . esc_html( $sender->display_name ) . '</strong>: ';
		if ( false === strpos( $message, '<!-- wp:' ) ) {
			$content .= '<!-- wp:paragraph -->' . PHP_EOL . '<p>' . wp_kses_post( $message );
			$content .= '</p>' . PHP_EOL;
			$content .= '<!-- /wp:paragraph -->';
		} else {
			$content .= wp_kses_post( $message );
		}
		$content .= '</div>';
		$content .= '<!-- /wp:friends/message -->';

		if ( $existing_message->have_posts() ) {
			$post = $existing_message->next_post();
			$post_id = $post->ID;
			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => $post->post_content . PHP_EOL . $content,
					'post_status'  => $mark_unread ? 'friends_unread' : 'friends_read',
				)
			);
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'    => self::CPT,
					'post_author'  => $friend_user->ID,
					'post_title'   => $subject,
					'post_content' => $content,
					'post_status'  => $mark_unread ? 'friends_unread' : 'friends_read',
				)
			);
		}

		return $post_id;
	}

	/**
	 * Add the unread messages to the total.
	 *
	 * @param      int $unread  The unread count.
	 *
	 * @return     int   Unread count + unread messages.
	 */
	public function friends_unread_messages_count( $unread ) {
		$unread_messages = new \WP_Query(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'friends_unread',
			)
		);
		return $unread + $unread_messages->post_count;
	}

	/**
	 * Add entries to the menu for unread messages.
	 *
	 * @param      \WP_Menu $wp_menu  The wp menu.
	 */
	public function friends_add_menu_unread_messages( $wp_menu ) {
		global $post;
		$unread_messages = new \WP_Query(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'friends_unread',
			)
		);

		while ( $unread_messages->have_posts() ) {
			$unread_messages->the_post();
			$friend_user = new User( $post->post_author );
			$wp_menu->add_menu(
				array(
					'id'     => 'friend-message-' . $friend_user->ID,
					'parent' => 'friends-menu',
					// translators: %s is the number of open friend requests.
					'title'  => '<span style="border-left: 2px solid #d63638; padding-left: .5em">' . esc_html( sprintf( __( 'New message from %s', 'friends' ), $friend_user->display_name ) ) . '</span>',
					'href'   => $friend_user->get_local_friends_page_url(),
				)
			);
		}
	}

	/**
	 * Ajax function to mark a message as read.
	 *
	 * @return     \WP_Error  The wp error.
	 */
	public function mark_message_read() {
		check_ajax_referer( 'friends-mark-read' );

		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'unauthorized', 'You are not authorized to send a reaction.' );
		}

		if ( ! isset( $_POST['post_id'] ) ) {
			wp_send_json_error(
				array(
					'result' => false,
				)
			);
		}
		if ( ! is_numeric( $_POST['post_id'] ) || $_POST['post_id'] <= 0 ) {
			wp_send_json_error(
				array(
					'result' => false,
				)
			);
		}

		$post_id = intval( $_POST['post_id'] );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'friends_read',
			)
		);

		do_action( 'friends_message_read', $post_id );

		wp_send_json_success(
			array(
				'result' => true,
			)
		);
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
	 * Extend the author header.
	 *
	 * @param      User  $friend_user  The friend user.
	 * @param      array $args         The arguments.
	 */
	public function friends_author_header( User $friend_user, $args ) {
		if ( $friend_user->has_cap( self::get_minimum_cap() ) ) {
			Friends::template_loader()->get_template_part(
				'frontend/messages/author-header',
				null,
				$args
			);

		}
	}

	/**
	 * Display received messages by the friend user.
	 *
	 * @param      array $args         The arguments.
	 */
	public function friends_display_messages( $args ) {
		if ( ! isset( $args['friend_user'] ) ) {
			return;
		}

		if ( $args['friend_user']->has_cap( self::get_minimum_cap() ) ) {
			$args['existing_messages'] = new \WP_Query(
				array(
					'post_type'   => self::CPT,
					'author'      => $args['friend_user']->ID,
					'post_status' => array( 'friends_read', 'friends_unread' ),
				)
			);

			if ( ! $args['existing_messages']->have_posts() ) {
				return;
			}

			Friends::template_loader()->get_template_part(
				'frontend/messages/friend',
				null,
				$args
			);

		}
	}

	/**
	 * Embed the message form for the friend user.
	 *
	 * @param      array $args         The arguments.
	 */
	public function friends_message_form( $args ) {
		if ( ! isset( $args['friend_user'] ) ) {
			return;
		}

		if ( $args['friend_user']->has_cap( self::get_minimum_cap() ) ) {
			Friends::template_loader()->get_template_part(
				'frontend/messages/message-form',
				null,
				$args
			);

		}
	}

	/**
	 * Gets the minimum capability necessary to use messages.
	 *
	 * @return     string  The minimum capability.
	 */
	public static function get_minimum_cap() {
		return apply_filters( 'friends_message_minimum_cap', 'friend' );
	}

	/**
	 * Sends a message to a friend.
	 *
	 * @param      User   $friend_user  The friend user.
	 * @param      string $message      The message.
	 * @param      string $subject      The subject.
	 *
	 * @return     \WP_Error|int  An error or the message post id.
	 */
	public function send_message( User $friend_user, $message, $subject = null ) {
		if ( ! $friend_user->has_cap( self::get_minimum_cap() ) ) {
			return new \WP_Error( 'not-a-friend', __( 'You cannot send messages to this user.', 'friends' ) );
		}
		if ( ! trim( $message ) ) {
			return new \WP_Error( 'empty-message', __( 'You cannot send empty messages.', 'friends' ) );
		}

		if ( empty( $subject ) ) {
			$subject = sprintf(
				// translators: %1$s is a date, %2$s is a time.
				__( 'Conversation started on %1$s at %2$s', 'friends' ),
				gmdate( 'Y-m-d' ),
				gmdate( 'H:i:s' )
			);
		}

		$post_id = $this->add_message_to_post( wp_get_current_user(), $friend_user, $subject, $message );

		$body = array(
			'subject' => $subject,
			'message' => $message,
			'auth'    => $friend_user->get_friend_auth(),
		);

		$response = wp_safe_remote_post(
			$friend_user->get_rest_url() . '/message',
			array(
				'body'        => $body,
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// TODO find a way to message the user.
			return new \WP_Error( 'invalid-response', __( 'We received an unexpected response to our message.', 'friends' ) );
		}

		return $post_id;
	}

	/**
	 * Delete a conversation
	 *
	 * @param      User   $friend_user  The friend user.
	 * @param      string $subject      The subject.
	 *
	 * @return     \WP_Error|\WP_Post|bool  The post or false.
	 */
	public function delete_conversation( User $friend_user, $subject ) {
		if ( ! $friend_user->has_cap( self::get_minimum_cap() ) ) {
			return new \WP_Error( 'not-a-friend', __( 'You cannot delete converations.', 'friends' ) );
		}

		$existing_message = new \WP_Query(
			array(
				'post_type'   => self::CPT,
				'author'      => $friend_user->ID,
				'title'       => $subject,
				'post_status' => array( 'friends_read', 'friends_unread' ),
			)
		);

		if ( $existing_message->have_posts() ) {
			$post = $existing_message->next_post();
			return wp_trash_post( $post->ID );
		}

		return false;
	}

	/**
	 * Handle the message form.
	 */
	public function handle_message_send() {
		if ( ! isset( $_REQUEST['friends_message_recipient'] ) ) {
			return;
		}

		if ( isset( $_REQUEST['friends_message_delete_conversation'] ) ) {
			return $this->handle_conversation_delete();
		}

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'friends_send_message' ) ) {
			wp_die( esc_html( __( 'Error - unable to verify nonce, please try again.', 'friends' ) ) );
		}

		$friend_user = new User( $_REQUEST['friends_message_recipient'] );

		$subject = wp_unslash( $_REQUEST['friends_message_subject'] );
		$message = wp_unslash( $_REQUEST['friends_message_message'] );

		$error = $this->send_message( $friend_user, $message, $subject );

		if ( is_wp_error( $error ) ) {
			wp_die( esc_html( $error->get_error_message() ) );
		}

		wp_safe_redirect( $friend_user->get_local_friends_page_url() );
		exit;
	}

	/**
	 * Handle the deletion of the conversation.
	 */
	public function handle_conversation_delete() {
		if ( ! isset( $_REQUEST['friends_message_delete_conversation'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'friends_send_message' ) ) {
			wp_die( esc_html( __( 'Error - unable to verify nonce, please try again.', 'friends' ) ) );
		}

		$friend_user = new User( $_REQUEST['friends_message_recipient'] );

		$subject = wp_unslash( $_REQUEST['friends_message_subject'] );
		$error = $this->delete_conversation( $friend_user, $subject );

		if ( is_wp_error( $error ) ) {
			wp_die( esc_html( $error->get_error_message() ) );
		}

		wp_safe_redirect( $friend_user->get_local_friends_page_url() );
		exit;
	}
}
