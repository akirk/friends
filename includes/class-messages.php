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
	const TAXONOMY = 'friend_message_user';

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
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 2 );
		add_filter( 'friends_unread_count', array( $this, 'friends_unread_messages_count' ) );
		add_action( 'friends_own_site_menu_top', array( $this, 'friends_add_menu_unread_messages' ) );
		add_action( 'wp_ajax_friends-mark-read', array( $this, 'mark_message_read' ) );
		add_action( 'rest_api_init', array( $this, 'add_rest_routes' ) );
		add_action( 'friends_author_header', array( $this, 'friends_author_header' ), 10, 2 );
		add_action( 'friends_after_header', array( $this, 'friends_display_messages' ), 10, 2 );
		add_action( 'friends_after_header', array( $this, 'friends_message_form' ), 11, 2 );
		add_filter( 'template_redirect', array( $this, 'handle_message_send' ), 10, 2 );
		add_filter( 'friends_message_form_accounts', array( $this, 'friends_message_form_accounts' ), 10, 2 );
		add_filter( 'friends_send_direct_message', array( $this, 'friends_send_direct_message' ), 20, 5 );
		add_filter( 'friends_send_direct_message', array( $this, 'save_outgoing_message' ), 10, 6 );
		add_filter( 'notify_friend_message_received', array( $this, 'save_incoming_message' ), 5, 6 );
		add_filter( 'mastodon_api_conversation', array( $this, 'mastodon_api_conversation' ), 10, 2 );
		add_filter( 'mastodon_api_conversations', array( $this, 'mastodon_api_conversations' ), 10, 3 );
		add_filter( 'mastodon_api_status_context_post_types', array( $this, 'api_status_context_post_types' ), 10, 2 );
		add_filter( 'mastodon_api_status_context_post_statuses', array( $this, 'api_status_context_post_statuses' ), 10, 2 );
		add_filter( 'api_status_context_post_types', array( $this, 'api_status_context_post_types' ), 10, 2 ); // legacy filter.
		add_filter( 'api_status_context_post_statuses', array( $this, 'api_status_context_post_statuses' ), 10, 2 ); // legacy filter.
		add_filter( 'mastodon_api_submit_status', array( $this, 'mastodon_api_submit_status' ), 9, 6 );
		add_filter( 'mastodon_api_conversation_mark_read', array( $this, 'mastodon_api_conversation_mark_read' ), 10 );
		add_filter( 'mastodon_api_conversation_delete', array( $this, 'delete_conversation' ), 10 );
		add_filter( 'mastodon_api_status', array( $this, 'mastodon_api_status' ), 20, 2 );
		add_filter( 'mastodon_api_get_notifications_query_args', array( $this, 'mastodon_api_get_notifications_query_args' ), 20, 2 );
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

	public function register_taxonomy() {
		$args = array(
			'labels'            => array(
				'name'          => _x( 'Friend Message User', 'taxonomy general name', 'friends' ),
				'singular_name' => _x( 'Friend Message User', 'taxonomy singular name', 'friends' ),
				'menu_name'     => __( 'Friend Message User', 'friends' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => false,
			'public'            => false,
		);
		register_taxonomy( self::TAXONOMY, self::CPT, $args );
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
				'permission_callback' => array( $this, 'permission_check_receive_message' ),
			)
		);
	}

	public function permission_check_receive_message( \WP_REST_Request $request ) {
		if ( Friends::authenticated_for_posts() ) {
			return true;
		}

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
		if ( ! apply_filters( 'friends_message_form_accounts', array(), $friend_user ) ) {
			return new \WP_Error(
				'friends_request_failed',
				__( 'Could not respond to the request.', 'friends' ),
				array(
					'status' => 403,
				)
			);
		}

		return true;
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
		if ( ! apply_filters( 'friends_message_form_accounts', array(), $friend_user ) ) {
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
		$remote_url = wp_unslash( $request->get_param( 'remote_url' ) );
		$reply_to = wp_unslash( $request->get_param( 'reply_to' ) );

		do_action( 'notify_friend_message_received', $friend_user, $message, $subject, $friend_user->get_rest_url(), $remote_url, $reply_to );

		return array(
			'status' => 'message-received',
		);
	}

	public function friends_frontend_post_types_only_messages( $post_types ) {
		$post_types = array( self::CPT );
		return $post_types;
	}


	public function save_incoming_message( User $friend_user, $message, $subject = null, $feed_url = null, $remote_url = null, $reply_to = null ) {
		$post_data = array(
			'post_type'    => self::CPT,
			'post_title'   => $subject,
			'post_content' => $message,
			'post_status'  => 'friends_unread',
			'guid'         => $remote_url,
		);

		if ( $reply_to ) {
			add_filter( 'friends_frontend_post_types', array( $this, 'friends_frontend_post_types_only_messages' ), 999 );
			$reply_to_post_id = Feed::url_to_postid( $reply_to );
			remove_filter( 'friends_frontend_post_types', array( $this, 'friends_frontend_post_types_only_messages' ), 999 );
			if ( $reply_to_post_id ) {
				$topmost_post = get_post( $reply_to_post_id );
				while ( $topmost_post->post_parent ) {
					$topmost_post = get_post( $topmost_post->post_parent );
				}

				$post_data['post_parent'] = $topmost_post->ID;
			}
		}

		$post_id = $friend_user->insert_post( $post_data );
		wp_set_post_terms( $post_id, strval( $friend_user->ID ), self::TAXONOMY );
		if ( $feed_url ) {
			update_post_meta( $post_id, 'friends_feed_url', $feed_url );
		}

		return $post_id;
	}

	/**
	 * Adds a message to friends_message post.
	 *
	 * @param      int    $post_id        The return value that might be a post id.
	 * @param      User   $friend_user  The friend user to which this should be associated.
	 * @param      string $to           The recipient as URL.
	 * @param      string $message      The message.
	 * @param      string $subject      The subject.
	 * @param      int    $reply_to_post_id  The reply to post ID.
	 *
	 * @return     int     The post ID.
	 */
	public function save_outgoing_message( $post_id, User $friend_user, $to, $message, $subject, $reply_to_post_id = null ) {
		$content = \wpautop( $message );
		$content = \preg_replace( '/[\n\r\t]/', '', $content );
		$content = \trim( $content );

		$post_data = array(
			'post_type'    => self::CPT,
			'post_title'   => $subject,
			'post_content' => $content,
			'post_status'  => 'friends_read',
			'post_parent'  => $reply_to_post_id,
		);

		$post_id = wp_insert_post( $post_data );
		wp_set_post_terms( $post_id, strval( $friend_user->ID ), self::TAXONOMY );
		if ( $to ) {
			update_post_meta( $post_id, 'friends_feed_url', $to );
		}

		return $post_id;
	}

	public function post_type_link( $post_link, \WP_Post $post ) {
		if ( $post && self::CPT === $post->post_type ) {
			if ( str_starts_with( $post->guid, home_url() ) || empty( $post->guid ) ) {
				return home_url( '?p=' . $post->ID );
			}

			return $post->guid;
		}

		return $post_link;
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
			$friend_user = User::get_post_author( $post );
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
		$result = array();
		$post_id = intval( $_POST['post_id'] );
		if ( get_post_status( $post_id ) !== 'friends_read' ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'friends_read',
				)
			);
			$result[] = $post_id;
		}
		$child_posts = get_children(
			array(
				'post_parent' => $post_id,
				'post_type'   => self::CPT,
				'post_status' => array( 'friends_unread' ),
			)
		);
		foreach ( $child_posts as $child ) {
			wp_update_post(
				array(
					'ID'          => $child->ID,
					'post_status' => 'friends_read',
				)
			);
			$result[] = $child->ID;
		}

		do_action( 'friends_message_read', $post_id );

		wp_send_json_success(
			array(
				'result' => $result,
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
		if ( apply_filters( 'friends_message_form_accounts', array(), $friend_user ) ) {
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
		if ( ! isset( $args['friend_user'] ) || ! $args['friend_user'] ) {
			return;
		}

		if ( apply_filters( 'friends_message_form_accounts', array(), $args['friend_user'] ) ) {
			$args['existing_messages'] = new \WP_Query();
			$args['existing_messages']->set( 'post_type', self::CPT );
			$args['existing_messages']->set( 'post_parent', '0' );
			$args['existing_messages']->set( 'post_status', array( 'friends_read', 'friends_unread' ) );
			$tax_query = array();
			$tax_query[] =
				array(
					'taxonomy' => self::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $args['friend_user']->ID,
				);
			$args['existing_messages']->set( 'tax_query', $tax_query );
			$args['existing_messages']->set( 'posts_per_page', -1 );
			if ( ! $args['existing_messages']->get_posts() ) {
				return;
			}
			$args['accounts'] = apply_filters( 'friends_message_form_accounts', array(), $args['friend_user'] );

			add_filter( 'excerpt_length', array( $this, 'friends_message_excerpt_length' ) );

			Friends::template_loader()->get_template_part(
				'frontend/messages/friend',
				null,
				$args
			);

			remove_filter( 'excerpt_length', array( $this, 'friends_message_excerpt_length' ) );

		}
	}

	public function friends_message_excerpt_length() {
		return 10;
	}

	/**
	 * Embed the message form for the friend user.
	 *
	 * @param      array $args         The arguments.
	 */
	public function friends_message_form( $args ) {
		if ( ! isset( $args['friend_user'] ) || ! $args['friend_user'] ) {
			return;
		}

		$args['blocks-everywhere'] = false;
		$args['accounts'] = apply_filters( 'friends_message_form_accounts', array(), $args['friend_user'] );

		if ( $args['accounts'] ) {
			Friends::template_loader()->get_template_part(
				'frontend/messages/message-form',
				null,
				$args
			);

		}
	}

	public function friends_message_form_accounts( $accounts, User $friend_user ) {
		if ( $friend_user->has_cap( 'friend' ) ) {
			// translators: %s is the user's URL.
			$accounts[ $friend_user->get_rest_url() ] = sprintf( __( 'Friends connection (%s)', 'friends' ), $friend_user->user_url );
		}

		return $accounts;
	}

	/**
	 * Sends a message to a friend.
	 *
	 * @param      User   $friend_user  The friend user.
	 * @param      string $to           The recipient as URL.
	 * @param      string $message      The message.
	 * @param      string $subject      The subject.
	 * @param      int    $reply_to_post_id  The reply to post ID.
	 *
	 * @return     \WP_Error|int  An error or the message post id.
	 */
	public function send_message( User $friend_user, $to, $message, $subject = null, $reply_to_post_id = null ) {
		$tos = apply_filters( 'friends_message_form_accounts', array(), $friend_user );
		if ( ! isset( $tos[ $to ] ) ) {
			return new \WP_Error( 'not-a-friend', __( 'You cannot send messages to this user.', 'friends' ) );
		}
		if ( ! $message || ! trim( $message ) ) {
			return new \WP_Error( 'empty-message', __( 'You cannot send empty messages.', 'friends' ) );
		}

		$post_id = apply_filters( 'friends_send_direct_message', null, $friend_user, $to, $message, $subject, $reply_to_post_id );

		return $post_id;
	}

	public function friends_send_direct_message( $post_id, User $friend_user, $to, $message, $subject = null, $reply_to_post_id = null ) {
		if ( $post_id || $to !== $friend_user->get_rest_url() ) {
			return $post_id;
		}

		$body = array(
			'subject' => $subject,
			'message' => $message,
			'auth'    => $friend_user->get_friend_auth(),
		);

		if ( $reply_to_post_id ) {
			$body['reply_to'] = get_permalink( $reply_to_post_id );
		}

		if ( $post_id ) {
			$body['remote_url'] = get_permalink( $post_id );
		}

		$response = wp_safe_remote_post(
			$friend_user->get_rest_url() . '/message',
			array(
				'body'        => $body,
				'timeout'     => 20,
				'redirection' => 5,
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'invalid-response', __( 'We received an unexpected response to our message.', 'friends' ) );
		}

		return true;
	}

	/**
	 * Delete a conversation
	 *
	 * @param      int $parent_post_id      The parent post id.
	 *
	 * @return     \WP_Error|\WP_Post|bool  The post or false.
	 */
	public function delete_conversation( $parent_post_id ) {
		if ( ! current_user_can( 'delete_post', $parent_post_id ) ) {
			return new \WP_Error( 'cannot_delete', __( 'You cannot delete converations.', 'friends' ) );
		}

		$post = get_post( $parent_post_id );
		if ( ! $post ) {
			return new \WP_Error( 'friends_messages_delete_conversation', 'Record not found', array( 'status' => 404 ) );
		}

		$child_posts = get_children(
			array(
				'post_parent' => $parent_post_id,
				'post_type'   => self::CPT,
				'post_status' => array( 'friends_read', 'friends_unread' ),
			)
		);

		foreach ( $child_posts as $child_post ) {
			wp_delete_post( $child_post->ID );
		}

		wp_delete_post( $parent_post_id );

		return true;
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

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'friends_send_message' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'friends' ) );
		}

		$friend_user = User::get_by_username( sanitize_text_field( wp_unslash( $_REQUEST['friends_message_recipient'] ) ) );

		$subject = '';
		if ( isset( $_REQUEST['friends_message_subject'] ) ) {
			$subject = sanitize_text_field( wp_unslash( $_REQUEST['friends_message_subject'] ) );
		}
		$message = '';
		if ( isset( $_REQUEST['friends_message_message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_REQUEST['friends_message_message'] ) );
		}
		if ( ! trim( $message ) ) {
			wp_die( esc_html__( 'You cannot send an empty message.', 'friends' ) );
		}

		$reply_to_post_id = null;
		if ( isset( $_REQUEST['friends_message_reply_to'] ) ) {
			$reply_to_post_id = intval( $_REQUEST['friends_message_reply_to'] );
		}

		$to = null;
		if ( isset( $_REQUEST['friends_message_account'] ) ) {
			$to = sanitize_text_field( wp_unslash( $_REQUEST['friends_message_account'] ) );
		}

		if ( ! $to ) {
			wp_die( esc_html__( 'You cannot send a message without a recipient.', 'friends' ) );
		}

		$error = $this->send_message( $friend_user, $to, $message, $subject, $reply_to_post_id );

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
		if ( ! isset( $_REQUEST['friends_message_delete_conversation'] ) || ! isset( $_REQUEST['friends_message_recipient'] ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'friends_send_message' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'friends' ) );
		}

		$friend_user = User::get_by_username( sanitize_text_field( wp_unslash( $_REQUEST['friends_message_recipient'] ) ) );

		$post_id = '';
		if ( isset( $_REQUEST['friends_message_reply_to'] ) ) {
			$post_id = sanitize_text_field( wp_unslash( $_REQUEST['friends_message_reply_to'] ) );
		}
		if ( ! $post_id ) {
			wp_die( esc_html__( 'You cannot delete a conversation without a post id.', 'friends' ) );
		}
		$error = $this->delete_conversation( $post_id );

		if ( is_wp_error( $error ) ) {
			wp_die( esc_html( $error->get_error_message() ) );
		}

		wp_safe_redirect( $friend_user->get_local_friends_page_url() );
		exit;
	}

	public function mastodon_api_conversation( $conversation, $post_id ) {
		$message = get_post( $post_id );
		if ( ! $message ) {
			return new \WP_Error( 'friends_messages_mastodon_api_conversation', 'Record not found', array( 'status' => 404 ) );
		}

		$last_status = get_posts(
			array(
				'post_type'   => self::CPT,
				'post_parent' => $message->ID,
				'post_status' => array( 'friends_read', 'friends_unread' ),
				'orderby'     => 'date',
				'order'       => 'DESC',
				'numberposts' => 1,
			)
		);
		if ( ! $last_status ) {
			$last_status = $message;
		} else {
			$last_status = $last_status[0];
		}

		$unread = 'friends_unread' === $message->post_status;
		if ( ! $unread ) {
			$unread_posts = get_children(
				array(
					'post_parent' => $message->ID,
					'post_type'   => self::CPT,
					'post_status' => array( 'friends_unread' ),
				)
			);
			if ( $unread_posts ) {
				$unread = true;
			}
		}
		$conversation = new \Enable_Mastodon_Apps\Entity\Conversation();
		$conversation->id = $message->ID;
		$conversation->unread = $unread;
		$conversation->last_status = apply_filters( 'mastodon_api_status', null, $last_status->ID );
		$conversation->accounts = array();
		// TODO: include virtual users.
		$conversation->accounts[] = apply_filters( 'mastodon_api_account', null, $message->post_author );

		return $conversation;
	}

	public function mastodon_api_conversations( $conversations, $user_id, $limit = 20 ) {
		$messages = new \WP_Query();
		$messages->set( 'post_type', self::CPT );
		$messages->set( 'post_parent', '0' );
		$messages->set( 'post_status', array( 'friends_read', 'friends_unread' ) );
		$messages->set( 'posts_per_page', $limit );
		$messages->set( 'order', 'DESC' );

		foreach ( $messages->get_posts() as $message ) {
			$conversation = $this->mastodon_api_conversation( null, $message->ID );
			if ( $conversation && ! is_wp_error( $conversation ) ) {
				$conversations[] = $conversation;
			}
		}

		return $conversations;
	}

	public function api_status_context_post_types( $post_types, $post_id ) {
		if ( self::CPT === get_post_type( $post_id ) ) {
			return array( self::CPT );
		}
		return $post_types;
	}

	public function api_status_context_post_statuses( $post_statuses, $post_id ) {
		if ( self::CPT === get_post_type( $post_id ) ) {
			return array( 'friends_read', 'friends_unread' );
		}
		return $post_statuses;
	}

	public function mastodon_api_submit_status( $status, $status_text, $in_reply_to_id, $media_ids, $post_format, $visibility ) {
		if ( $status instanceof \WP_Error || $status instanceof \Enable_Mastodon_Apps\Entity\Status || 'direct' !== $visibility ) {
			return $status;
		}

		$mentions = apply_filters(
			'activitypub_extract_mentions',
			array(),
			$status_text,
			null
		);

		/**
		 * Documented in Enable_Mastodon_Apps\Handler\Status\prepare_post_data()
		 */
		$status_text = apply_filters( 'mastodon_api_submit_status_text', $status_text, $in_reply_to_id, $visibility );

		$friend_user = false;
		foreach ( $mentions as $mention ) {
			$user_feed = User_Feed::get_by_url( $mention );
			if ( $user_feed ) {
				$friend_user = $user_feed->get_friend_user();
				break;
			}
			if ( class_exists( Feed_Parser_ActivityPub::class ) ) {
				$user_feed = User_Feed::get_by_url( $mention );
				$url = Feed_Parser_ActivityPub::friends_webfinger_resolve( $mention, $mention );
				$user_feed = User_Feed::get_by_url( $url );
				if ( $user_feed ) {
					$friend_user = $user_feed->get_friend_user();
					break;
				}
			}
		}

		if ( ! $friend_user ) {
			// we cannot find the mentioned user, we need to stop this.
			// TODO: handle the non-ActivityPub case.
			return new \WP_Error( 'friends_messages_mastodon_api_submit_status', 'Direct message without a known mentioned user', array( 'status' => 400 ) );

		}

		$app = \Enable_Mastodon_Apps\Mastodon_App::get_current_app();
		if ( ! $app->get_disable_blocks() ) {
			$status_text = \Enable_Mastodon_Apps\Handler\Status::convert_to_blocks( $status_text );
		}

		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				$media = get_post( $media_id );
				if ( ! $media ) {
					return new \WP_Error( 'friends_messages_mastodon_api_submit_status', 'Media not found', array( 'status' => 400 ) );
				}
				if ( 'attachment' !== $media->post_type ) {
					return new \WP_Error( 'friends_messages_mastodon_api_submit_status', 'Media not found', array( 'status' => 400 ) );
				}
				$attachment = \wp_get_attachment_metadata( $media_id );
				if ( \wp_attachment_is( 'image', $media_id ) ) {
					$status_text .= PHP_EOL;
					$meta_json                  = array(
						'id'       => intval( $media_id ),
						'sizeSlug' => 'large',
					);
					$status_text .= '<!-- wp:image ' . wp_json_encode( $meta_json ) . ' -->' . PHP_EOL;
					$status_text .= '<figure class="wp-block-image"><img src="' . esc_url( wp_get_attachment_url( $media_id ) ) . '" alt="" class="wp-image-' . esc_attr( $media_id ) . '"/></figure>' . PHP_EOL;
					$status_text .= '<!-- /wp:image -->' . PHP_EOL;
				} elseif ( \wp_attachment_is( 'video', $media_id ) ) {
					$status_text .= PHP_EOL;
					$status_text .= '<!-- wp:video ' . wp_json_encode( array( 'id' => $media_id ) ) . '  -->' . PHP_EOL;
					$status_text .= '<figure class="wp-block-video"><video controls src="' . esc_url( wp_get_attachment_url( $media_id ) ) . '" width="' . esc_attr( $attachment['width'] ) . '" height="' . esc_attr( $attachment['height'] ) . '" /></figure>' . PHP_EOL;
					$status_text .= '<!-- /wp:video -->' . PHP_EOL;
				}
			}
		}

		$post_id = $this->send_message( $friend_user, $user_feed->get_url(), $status_text, null, $in_reply_to_id );

		if ( ! empty( $media_ids ) ) {
			foreach ( $media_ids as $media_id ) {
				wp_update_post(
					array(
						'ID'          => $media_id,
						'post_parent' => $post_id,
					)
				);
			}
		}

		return apply_filters( 'mastodon_api_status', null, $post_id );
	}

	public function mastodon_api_conversation_mark_read( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'friends_messages_mastodon_api_conversation_mark_read', 'Record not found', array( 'status' => 404 ) );
		}

		if ( get_post_status( $post_id ) !== 'friends_read' ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'friends_read',
				)
			);
		}

		$child_posts = get_children(
			array(
				'post_parent' => $post_id,
				'post_type'   => self::CPT,
				'post_status' => array( 'friends_unread' ),
			)
		);
		foreach ( $child_posts as $child ) {
			wp_update_post(
				array(
					'ID'          => $child->ID,
					'post_status' => 'friends_read',
				)
			);
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'friends_read',
			)
		);

		return true;
	}

	public function mastodon_api_status( $status, $post_id ) {
		if ( ! $status instanceof \Enable_Mastodon_Apps\Entity\Status || get_post_type( $post_id ) !== self::CPT ) {
			return $status;
		}
		$status->visibility = 'direct';
		$post = get_post( $post_id );
		if ( $post->post_parent ) {
			$status->in_reply_to_id = $post->post_parent;
			$status->in_reply_to_account_id = strval( get_current_user_id() );
		}
		return $status;
	}

	public function mastodon_api_get_notifications_query_args( $args, $type ) {
		if ( 'mention' !== $type ) {
			return $args;
		}
		if ( ! isset( $args['post_type'] ) ) {
			$args['post_type'] = array();
		} elseif ( ! is_array( $args['post_type'] ) ) {
			$args['post_type'] = array( $args['post_type'] );
		}
		$args['post_type'][] = self::CPT;

		if ( ! isset( $args['post_status'] ) ) {
			$args['post_status'] = array();
		} elseif ( ! is_array( $args['post_status'] ) ) {
			$args['post_status'] = array( $args['post_status'] );
		}
		if ( ! in_array( 'friends_unread', $args['post_status'] ) ) {
			$args['post_status'][] = 'friends_unread';
		}
		if ( ! in_array( 'friends_read', $args['post_status'] ) ) {
			$args['post_status'][] = 'friends_read';
		}

		return $args;
	}
}
