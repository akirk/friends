<?php
/**
 * Friends ActivityPub Parser
 *
 * With this parser, we can talk to ActivityPub.
 *
 * @since @2.1.4
 *
 * @package Friends
 * @author Alex Kirk
 */

namespace Friends;

use WP_Error;

/**
 * This is the class for integrating ActivityPub into the Friends Plugin.
 */
class Feed_Parser_ActivityPub extends Feed_Parser_V2 {
	const SLUG = 'activitypub';
	const NAME = 'ActivityPub';
	const URL = 'https://www.w3.org/TR/activitypub/';
	const ACTIVITYPUB_USERNAME_REGEXP = '(?:([A-Za-z0-9_-]+)@((?:[A-Za-z0-9_-]+\.)+[A-Za-z]+))';
	const EXTERNAL_MENTIONS_USERNAME = 'external-mentions';

	private $friends_feed;

	/**
	 * Constructor.
	 *
	 * @param      Feed $friends_feed  The friends feed.
	 */
	public function __construct( Feed $friends_feed ) {
		$this->friends_feed = $friends_feed;

		\add_action( 'init', array( $this, 'register_post_meta' ) );
		\add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		\add_filter( 'feed_item_allow_set_metadata', array( $this, 'feed_item_allow_set_metadata' ), 10, 3 );
		\add_filter( 'friends_add_friends_input_placeholder', array( $this, 'friends_add_friends_input_placeholder' ) );
		\add_action( 'friends_add_friend_form_top', array( $this, 'friends_add_friend_form_top' ) );

		\add_action( 'activitypub_inbox', array( $this, 'handle_received_activity' ), 10, 3 );
		\add_action( 'friends_user_feed_activated', array( $this, 'queue_follow_user' ), 10 );
		\add_action( 'friends_user_feed_deactivated', array( $this, 'queue_unfollow_user' ), 10 );
		\add_action( 'friends_feed_parser_activitypub_follow', array( $this, 'activitypub_follow_user' ), 10, 2 );
		\add_action( 'friends_feed_parser_activitypub_unfollow', array( $this, 'activitypub_unfollow_user' ), 10, 2 );
		\add_action( 'friends_feed_parser_activitypub_like', array( $this, 'activitypub_like_post' ), 10, 3 );
		\add_action( 'friends_feed_parser_activitypub_unlike', array( $this, 'activitypub_unlike_post' ), 10, 3 );
		\add_action( 'friends_feed_parser_activitypub_announce', array( $this, 'activitypub_announce' ), 10, 2 );
		\add_action( 'friends_feed_parser_activitypub_unannounce', array( $this, 'activitypub_unannounce' ), 10, 2 );
		\add_filter( 'friends_rewrite_incoming_url', array( $this, 'friends_webfinger_resolve' ), 10, 2 );

		\add_filter( 'friends_edit_feeds_table_end', array( $this, 'activitypub_settings' ), 10 );
		\add_filter( 'friends_edit_feeds_after_form_submit', array( $this, 'activitypub_save_settings' ), 10 );
		\add_filter( 'friends_modify_feed_item', array( $this, 'modify_incoming_item' ), 9, 3 );
		\add_filter( 'friends_potential_avatars', array( $this, 'friends_potential_avatars' ), 10, 2 );
		\add_filter( 'friends_suggest_user_login', array( $this, 'suggest_user_login' ), 10, 2 );

		\add_filter( 'the_content', array( $this, 'the_content' ), 99, 2 );
		\add_filter( 'activitypub_extract_mentions', array( $this, 'activitypub_extract_mentions' ), 10, 2 );
		\add_filter( 'mastodon_api_external_mentions_user', array( $this, 'get_external_mentions_user' ) );
		\add_filter( 'activitypub_post', array( $this, 'activitypub_post_in_reply_to' ), 10, 2 );
		\add_filter( 'activitypub_activity_object_array', array( $this, 'activitypub_activity_object_array_in_reply_to' ), 10, 3 );

		\add_action( 'friends_user_post_reaction', array( $this, 'post_reaction' ) );
		\add_action( 'friends_user_post_undo_reaction', array( $this, 'undo_post_reaction' ) );
		\add_action( 'mastodon_api_react', array( $this, 'mastodon_api_react' ), 10, 2 );
		\add_action( 'mastodon_api_unreact', array( $this, 'mastodon_api_unreact' ), 10, 2 );
		\add_action( 'friends_get_reaction_display_name', array( $this, 'get_reaction_display_name' ), 10, 2 );

		\add_filter( 'pre_comment_approved', array( $this, 'pre_comment_approved' ), 10, 2 );
		\add_action( 'comment_post', array( $this, 'comment_post' ), 10, 3 );
		\add_action( 'trashed_comment', array( $this, 'trashed_comment' ), 10, 2 );

		\add_filter( 'friends_reblog_button_label', array( $this, 'friends_reblog_button_label' ), 10, 2 );
		\add_filter( 'friends_search_autocomplete', array( $this, 'friends_search_autocomplete' ), 10, 2 );
		\add_action( 'friends_after_header', array( $this, 'frontend_reply_form' ) );
		\add_action( 'friends_after_header', array( $this, 'frontend_boost_form' ) );
		\add_action( 'wp_ajax_friends-in-reply-to-preview', array( $this, 'ajax_in_reply_to_preview' ) );

		\add_filter( 'friends_reblog', array( $this, 'unqueue_activitypub_create' ), 9 );
		\add_action( 'mastodon_api_reblog', array( $this, 'mastodon_api_reblog' ) );
		\add_action( 'mastodon_api_unreblog', array( $this, 'mastodon_api_unreblog' ) );
		\add_filter( 'friends_activitypub_announce_any_url', array( $this, 'queue_announce' ) );
		\add_filter( 'friends_reblog', array( $this, 'reblog' ), 20, 2 );
		\add_filter( 'friends_unreblog', array( $this, 'unreblog' ), 20, 2 );
		\add_filter( 'friends_reblog', array( $this, 'maybe_unqueue_friends_reblog_post' ), 9, 2 );

		\add_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'disable_webfinger_for_example_domains' ), 9, 2 );

		add_filter( 'friends_get_feed_metadata', array( $this, 'friends_get_feed_metadata' ), 10, 2 );
		add_filter( 'friends_get_activitypub_metadata', array( $this, 'friends_activitypub_metadata' ), 10, 2 );
	}

	/**
	 * Add the admin menu to the sidebar.
	 */
	public function admin_menu() {
		$friends = Friends::get_instance();
		$unread_badge = $friends->admin->get_unread_badge();

		$menu_title = __( 'Friends', 'friends' ) . $unread_badge;
		$page_type = sanitize_title( $menu_title );

		add_submenu_page(
			'friends',
			__( 'ActivityPub', 'friends' ),
			__( 'ActivityPub', 'friends' ),
			Friends::required_menu_role(),
			'friends-activitypub-settings',
			array( $this, 'settings' )
		);

		add_action( 'load-' . $page_type . '_page_friends-activitypub-settings', array( $this, 'process_settings' ) );
	}

	public function settings() {
		Friends::template_loader()->get_template_part(
			'admin/settings-header',
			null,
			array(
				'menu'   => array(
					__( 'ActivityPub Settings', 'friends' ) => 'friends-activitypub-settings',
				),
				'active' => 'friends-activitypub-settings',
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

		Friends::template_loader()->get_template_part(
			'admin/activitypub-settings',
			null,
			array(
				'reblog' => ! get_user_option( 'friends_activitypub_dont_reblog' ),
			)
		);

		Friends::template_loader()->get_template_part( 'admin/settings-footer' );
	}

	public function process_settings() {
		if ( empty( $_POST ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'friends-activitypub-settings' ) ) {
			return;
		}

		if ( isset( $_POST['activitypub_reblog'] ) ) {
			delete_user_option( get_current_user_id(), 'friends_activitypub_dont_reblog' );
		} else {
			update_user_option( get_current_user_id(), 'friends_activitypub_dont_reblog', true );
		}
		wp_safe_redirect( add_query_arg( 'updated', 'true', admin_url( 'admin.php?page=friends-activitypub-settings' ) ) );
		exit;
	}

	public function friends_add_friends_input_placeholder() {
		return __( 'Enter URL or @activitypub@handle.domain', 'friends' );
	}

	public function friends_add_friend_form_top() {
		?>
		<p>
			<?php
			echo wp_kses(
				sprintf(
					// translators: %1$s and %2$s are links to the respective services.
					__( '<strong>Note:</strong> Because you have the ActivityPub plugin installed, you can also follow people over that protocol, for example <a href=%1$s>Mastodon</a> or <a href=%2$s>Pixelfed</a>.', 'friends' ),
					'"https://joinmastodon.org/"',
					'"https://pixelfed.social/"'
				),
				array(
					'a'      => array( 'href' => array() ),
					'strong' => array(),
				)
			);
			?>
		</p>
		<?php
	}

	public function friends_get_feed_metadata( $meta, $feed ) {
		if ( self::SLUG === $feed->get_parser() ) {
			return $this->friends_activitypub_metadata( $meta, $feed->get_url() );
		}
		return $meta;
	}

	public function friends_activitypub_metadata( $ret, $url ) {
		$meta = self::get_metadata( $url );
		if ( is_null( $meta ) ) {
			return $ret;
		}
		if ( is_wp_error( $meta ) ) {
			if ( ! empty( $ret ) ) {
				return $ret;
			}
			return $meta;
		}
		return array_merge( $ret, $meta );
	}

	public function register_post_meta() {
		register_post_meta(
			Friends::CPT,
			self::SLUG,
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'object',
			)
		);
	}

	public function feed_item_allow_set_metadata( $verdict, $key, $value ) {
		if ( self::SLUG === $key && ! empty( $value ) ) {
			// We don't want to insert empty post meta.
			return true;
		}
		return $verdict;
	}

	/**
	 * Allow logging a message via an action.
	 *
	 * @param string $message The message to log.
	 * @param array  $objects Optional objects as meta data.
	 * @return void
	 */
	private function log( $message, $objects = array() ) {
		// Receive debug messages from the ActivityPub feed parser.
		do_action( 'friends_activitypub_log', $message, $objects );
	}

	/**
	 * Determines if this is a supported feed and to what degree we feel it's supported.
	 *
	 * @param      string      $url        The url.
	 * @param      string      $mime_type  The mime type.
	 * @param      string      $title      The title.
	 * @param      string|null $content    The content, it can't be assumed that it's always available.
	 *
	 * @return     int  Return 0 if unsupported, a positive value representing the confidence for the feed, use 10 if you're reasonably confident.
	 */
	public function feed_support_confidence( $url, $mime_type, $title, $content = null ) {
		if ( preg_match( '/^@?[^@]+@((?:[a-z0-9-]+\.)+[a-z]+)$/i', $url ) ) {
			return 10;
		}

		if ( 'application/activity+json' === $mime_type ) {
			return 10;
		}

		if ( preg_match( '#^https?://[^/]+/@[a-z0-9-]+$#i', $url ) ) {
			return 9;
		}

		if ( preg_match( '#^https?://[^/]+/(users|author)/[a-z0-9-]+$#i', $url ) ) {
			return 8;
		}

		return 0;
	}

	/**
	 * Format the feed title and autoselect the posts feed.
	 *
	 * @param      array $feed_details  The feed details.
	 *
	 * @return     array  The (potentially) modified feed details.
	 */
	public function update_feed_details( $feed_details ) {
		$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $feed_details['url'] );
		if ( ! $meta || is_wp_error( $meta ) ) {
			return $feed_details;
		}
		if ( isset( $meta['name'] ) ) {
			$feed_details['title'] = $meta['name'];
		} elseif ( isset( $meta['preferredUsername'] ) ) {
			$feed_details['title'] = $meta['preferredUsername'];
		}

		if ( isset( $meta['id'] ) ) {
			$feed_details['url'] = $meta['id'];
		}

		if ( isset( $meta['icon']['type'] ) && 'image' === strtolower( $meta['icon']['type'] ) ) {
			$feed_details['avatar'] = $meta['icon']['url'];
		}

		if ( ! empty( $meta['summary'] ) ) {
			$feed_details['description'] = $meta['summary'];
		}

		// Disable polling.
		$feed_details['interval'] = YEAR_IN_SECONDS;
		$feed_details['next-poll'] = gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS );
		$feed_details['post-format'] = 'status';

		return $feed_details;
	}

	/**
	 * Rewrite a Mastodon style URL @username@server to a URL via webfinger.
	 *
	 * @param      string $url           The URL to filter.
	 * @param      string $incoming_url  Potentially a mastodon identifier.
	 *
	 * @return     string  The rewritten URL.
	 */
	public function friends_webfinger_resolve( $url, $incoming_url ) {
		if ( preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $incoming_url ) ) {
			$resolved_url = \Activitypub\Webfinger::resolve( $incoming_url );
			if ( ! is_wp_error( $resolved_url ) ) {
				return $resolved_url;
			}
		}
		return $url;
	}

	/**
	 * Get the inbox URL for an actor
	 *
	 * @param string $url The URL of the actor.
	 * @param string $type The type of the activity.
	 * @return string|WP_Error
	 */
	public static function get_inbox_by_actor( $url, $type ) {
		$metadata = self::get_metadata( $url );
		if ( \is_wp_error( $metadata ) ) {
			return $metadata;
		}

		if ( ! in_array( $type, array( 'Follow' ) ) && isset( $metadata['endpoints'] ) && isset( $metadata['endpoints']['sharedInbox'] ) ) {
			return $metadata['endpoints']['sharedInbox'];
		}

		if ( \array_key_exists( 'inbox', $metadata ) ) {
			return $metadata['inbox'];
		}

		return new WP_Error( 'activitypub_no_inbox', \__( 'No "ActivityPub Inbox" found', 'friends' ), $metadata );
	}

	public static function get_metadata( $url ) {
		if ( ! is_string( $url ) ) {
			return array();
		}

		if ( false !== strpos( $url, '@' ) ) {
			if ( false === strpos( $url, '/' ) && preg_match( '#^https?://#', $url, $m ) ) {
				$url = substr( $url, strlen( $m[0] ) );
			}
			return \Activitypub\get_remote_metadata_by_actor( $url );
		}

		$transient_key = 'friends_activitypub_' . crc32( $url );

		$response = \get_transient( $transient_key );
		if ( $response ) {
			return $response;
		}

		$response = \wp_remote_get(
			$url,
			array(
				'headers'     => array( 'Accept' => 'application/activity+json' ),
				'redirection' => 2,
				'timeout'     => 5,
			)
		);

		if ( \is_wp_error( $response ) ) {
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $response;
		}

		if ( \is_wp_error( $response ) ) {
			\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
			return $response;
		}

		$body = \wp_remote_retrieve_body( $response );
		$body = \json_decode( $body, true );

		\set_transient( $transient_key, $body, HOUR_IN_SECONDS ); // Cache the error for a shorter period.

		return $body;
	}
	/**
	 * Discover the feeds available at the URL specified.
	 *
	 * @param      string $content  The content for the URL is already provided here.
	 * @param      string $url      The url to search.
	 *
	 * @return     array  A list of supported feeds at the URL.
	 */
	public function discover_available_feeds( $content, $url ) {
		$discovered_feeds = array();

		$meta = self::get_metadata( $url );
		if ( $meta && ! is_wp_error( $meta ) && isset( $meta['id'] ) ) {
			$discovered_feeds[ $meta['id'] ] = array(
				'type'        => 'application/activity+json',
				'rel'         => 'self',
				'post-format' => 'status',
				'parser'      => self::SLUG,
				'autoselect'  => true,
			);
		}

		return $discovered_feeds;
	}

	/**
	 * Fetches a feed and returns the processed items.
	 *
	 * @param      string    $url        The url.
	 * @param      User_Feed $user_feed The user feed.
	 *
	 * @return     array            An array of feed items.
	 */
	public function fetch_feed( $url, User_Feed $user_feed = null ) {
		if ( $user_feed ) {
			$this->disable_polling( $user_feed );
		}

		// This is only for previwing.
		if ( wp_doing_cron() ) {
			return array();
		}

		$meta = self::get_metadata( $url );
		if ( is_wp_error( $meta ) || ! isset( $meta['outbox'] ) ) {
			return array();
		}

		$response = \Activitypub\safe_remote_get( $meta['outbox'], Friends::get_main_friend_user_id() );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'activitypub_could_not_get_outbox_meta', null, compact( 'meta', 'url' ) );
		}

		$outbox = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $outbox['first'] ) ) {
			return new \WP_Error( 'activitypub_could_not_find_outbox_first_page', null, compact( 'url', 'meta', 'outbox' ) );
		}

		$response = \Activitypub\safe_remote_get( $outbox['first'], Friends::get_main_friend_user_id() );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'activitypub_could_not_get_outbox',
				null,
				array(
					'meta' => $outbox,
					$url   => $outbox['first'],
				)
			);
		}
		$outbox_page = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $outbox_page['type'] ) || 'OrderedCollectionPage' !== $outbox_page['type'] ) {
			return new \WP_Error(
				'activitypub_outbox_page_invalid_type',
				null,
				array(
					'outbox_page' => $outbox_page,
					$url          => $outbox['first'],
				)
			);
		}

		$items = array();
		foreach ( $outbox_page['orderedItems'] as $object ) {
			$type = strtolower( $object['type'] );
			$items[] = $this->process_incoming_activity( $type, $object, get_current_user_id(), $user_feed );
		}

		return $items;
	}

	public function suggest_user_login( $login, $url ) {
		if ( preg_match( '#^https?://([^/]+)/users/([^/]+)#', $url, $m ) ) {
			return $m[2] . '-' . $m[1];
		}
		return $login;
	}
	/**
	 * Disable polling for this feed.
	 *
	 * @param User_Feed $user_feed The user feed.
	 * @return void
	 */
	private function disable_polling( User_Feed $user_feed ) {
		$user_feed->update_metadata( 'interval', YEAR_IN_SECONDS );
		$user_feed->update_metadata( 'next-poll', gmdate( 'Y-m-d H:i:s', time() + YEAR_IN_SECONDS ) );
	}

	/**
	 * Gets the external mentions user.
	 *
	 * @return     User  The external mentions user.
	 */
	public function get_external_mentions_user() {
		$external_mentions_username = apply_filters( 'friends_external_mentions_username', self::EXTERNAL_MENTIONS_USERNAME );
		$user = get_user_by( 'login', $external_mentions_username );
		if ( ! $user ) {
			$user = Subscription::create( $external_mentions_username, 'subscription', home_url(), __( 'External Mentions', 'friends' ) );
		} else {
			$user = User::get_by_username( $external_mentions_username );
		}

		if ( $user instanceof \WP_User && ! ( $user instanceof Subscription ) && ! is_user_member_of_blog( $user->ID, get_current_blog_id() ) ) {
			\add_user_to_blog( get_current_blog_id(), $user->ID, 'subscription' );
		}

		return $user;
	}

	private function get_external_mentions_feed() {
		require_once __DIR__ . '/activitypub/class-virtual-user-feed.php';
		$user = $this->get_external_mentions_user();
		return new Virtual_User_Feed( $user, __( 'External Mentions', 'friends' ) );
	}

	/**
	 * Set the inReplyTo property for the outgoing activity (object).
	 *
	 * @param  array   $ret The activity from the ActivityPub plugin.
	 * @param WP_Post $post The post object.
	 * @return mixed
	 */
	public function activitypub_post_in_reply_to( $ret, $post ) {
		if ( get_post_meta( $post->ID, 'activitypub_in_reply_to', true ) ) {
			$ret['inReplyTo'] = get_post_meta( $post->ID, 'activitypub_in_reply_to', true );
			$ret['object']['inReplyTo'] = $ret['inReplyTo'];
		}
		return $ret;
	}

	/**
	 * Set the inReplyTo property for the outgoing activity object.
	 *
	 * @param  array  $ret The activity object from the ActivityPub plugin.
	 * @param object $c   The class.
	 * @param string $url The URL of the post.
	 * @return array The modified activity object.
	 */
	public function activitypub_activity_object_array_in_reply_to( $ret, $c, $url ) {
		$post_id = url_to_postid( $url );
		if ( get_post_meta( $post_id, 'activitypub_in_reply_to', true ) ) {
			$ret['inReplyTo'] = get_post_meta( $post_id, 'activitypub_in_reply_to', true );
		}
		return $ret;
	}

	/**
	 * Handles "Create" requests
	 *
	 * @param  array  $activity  The activity object.
	 * @param  int    $user_id The id of the local blog user.
	 * @param string $type  The type of the activity.
	 */
	public function handle_received_activity( $activity, $user_id, $type ) {
		if ( 'undo' === $type ) {
			if ( ! isset( $activity['object'] ) ) {
				return false;
			}
			$activity = $activity['object'];
			switch ( strtolower( $activity['type'] ) ) {
				case 'like':
					$type = 'unlike';
					break;
				case 'announce':
					$type = 'unannounce';
					break;
				default:
					return false;
			}
		}
		if ( ! in_array(
			$type,
			array(
				// We don't need to handle 'Accept' types since it's handled by the ActivityPub plugin itself.
				'create',
				'delete',
				'announce',
				'unannounce',
				'like',
				'unlike',
			),
			true
		) ) {
			return false;
		}
		$actor_url = $activity['actor'];
		$user_feed = false;
		if ( Friends::check_url( $actor_url ) ) {
			// Let's check if we follow this actor. If not it might be a different URL representation.
			$user_feed = $this->friends_feed->get_user_feed_by_url( $actor_url );
		}

		if ( is_wp_error( $user_feed ) || ! Friends::check_url( $actor_url ) ) {
			$meta = $this->get_metadata( $actor_url );
			if ( ! $meta || is_wp_error( $meta ) || ! isset( $meta['url'] ) ) {
				$this->log( 'Received invalid meta for ' . $actor_url );
				return false;
			}

			$actor_url = $meta['url'];
			if ( ! Friends::check_url( $actor_url ) ) {
				$this->log( 'Received invalid meta url for ' . $actor_url );
				return false;
			}
		}

		$user_feed = $this->friends_feed->get_user_feed_by_url( $actor_url );
		if ( ! $user_feed || is_wp_error( $user_feed ) ) {
			if ( isset( $activity['object']['tag'] ) && is_array( $activity['object']['tag'] ) ) {
				$my_activitypub_id = \get_author_posts_url( $user_id );
				foreach ( $activity['object']['tag'] as $tag ) {
					if ( isset( $tag['type'] ) && 'Mention' === $tag['type'] && isset( $tag['href'] ) && $tag['href'] === $my_activitypub_id ) {
						// It was a mention.
						$user_feed = $this->get_external_mentions_feed();
						break;
					}
				}
			}
		}

		if ( ! $user_feed || is_wp_error( $user_feed ) ) {
			// We're not following this user.
			return false;
		}

		$item = $this->process_incoming_activity( $type, $activity, $user_id, $user_feed );

		if ( $item instanceof Feed_Item ) {
			$this->friends_feed->process_incoming_feed_items( array( $item ), $user_feed );
			return true;
		}

		return false;
	}

	/**
	 * Process an incoming activity.
	 *
	 * @param string    $type The type of the activity.
	 * @param array     $activity The activity object.
	 * @param int       $user_id The id of the local user if any.
	 * @param User_Feed $user_feed The user feed.
	 * @return Feed_Item|null The feed item or null if it's not a valid activity.
	 */
	private function process_incoming_activity( $type, $activity, $user_id, $user_feed ) {
		switch ( $type ) {
			case 'create':
				return $this->handle_incoming_create( $activity['object'] );
			case 'delete':
				return $this->handle_incoming_delete( $activity['object'] );
			case 'announce':
				return $this->handle_incoming_announce( $activity['object'], $user_id, $activity['published'] );
			case 'unannounce':
				return $this->handle_incoming_unannounce( $activity['object'], $user_feed );
			case 'like':
				return $this->handle_incoming_like( $activity, $user_id );
			case 'unlike':
				return $this->handle_incoming_unlike( $activity, $user_id );
		}
		return null;
	}

	/**
	 * Map the Activity type to a post fomat.
	 *
	 * @param      string $type   The type.
	 *
	 * @return     string  The determined post format.
	 */
	private function map_type_to_post_format( $type ) {
		return 'status';
	}

	/**
	 * We received a post for a feed, handle it.
	 *
	 * @param      array $activity     The object from ActivityPub.
	 */
	private function handle_incoming_create( $activity ) {
		$permalink = $activity['id'];
		if ( isset( $activity['url'] ) ) {
			$permalink = $activity['url'];
		}

		$data = array(
			'permalink'    => $permalink,
			'content'      => $activity['content'],
			'post_format'  => $this->map_type_to_post_format( $activity['type'] ),
			'date'         => $activity['published'],
			'_external_id' => $activity['id'],
			self::SLUG     => array(),
		);

		if ( isset( $activity['reblog'] ) && $activity['reblog'] ) {
			$data[ self::SLUG ]['reblog'] = $activity['reblog'];
		}

		if ( isset( $activity['attributedTo'] ) ) {
			$meta = $this->get_metadata( $activity['attributedTo'] );
			$this->log( 'Attributed to ' . $activity['attributedTo'], compact( 'meta' ) );

			if ( $meta && ! is_wp_error( $meta ) ) {
				if ( isset( $meta['name'] ) ) {
					$data['author'] = $meta['name'];
				} elseif ( isset( $meta['preferredUsername'] ) ) {
					$data['author'] = $meta['preferredUsername'];
				}

				$data[ self::SLUG ]['attributedTo'] = array(
					'id' => $meta['id'],
				);
				if ( ! empty( $meta['icon']['url'] ) ) {
					$data[ self::SLUG ]['attributedTo']['icon'] = $meta['icon']['url'];
				}

				if ( ! empty( $meta['summary'] ) ) {
					$data[ self::SLUG ]['attributedTo']['summary'] = $meta['summary'];
				}

				if ( ! empty( $meta['preferredUsername'] ) ) {
					$data[ self::SLUG ]['attributedTo']['preferredUsername'] = $meta['preferredUsername'];
				}

				if ( ! empty( $meta['name'] ) ) {
					$data[ self::SLUG ]['attributedTo']['name'] = $meta['name'];
				}
			}
		}

		if ( isset( $activity['application'] ) && $activity['application'] ) {
			$data[ self::SLUG ]['application'] = $activity['application'];
		}

		if ( ! empty( $activity['attachment'] ) ) {
			foreach ( $activity['attachment'] as $attachment ) {
				if ( ! isset( $attachment['type'] ) || ! isset( $attachment['mediaType'] ) ) {
					continue;
				}
				if ( 'Document' !== $attachment['type'] ) {
					continue;
				}

				if ( strpos( $attachment['mediaType'], 'image/' ) === 0 ) {
					$data['content'] .= PHP_EOL;
					$data['content'] .= '<!-- wp:image -->';
					$data['content'] .= '<p><img src="' . esc_url( $attachment['url'] ) . '" width="' . esc_attr( $attachment['width'] ) . '"  height="' . esc_attr( $attachment['height'] ) . '" class="size-full" /></p>';
					$data['content'] .= '<!-- /wp:image -->';
				} elseif ( strpos( $attachment['mediaType'], 'video/' ) === 0 ) {
					$data['content'] .= PHP_EOL;
					$data['content'] .= '<!-- wp:video -->';
					$data['content'] .= '<figure class="wp-block-video"><video controls src="' . esc_url( $attachment['url'] ) . '" />';
					if ( ! empty( $attachment['name'] ) ) {
						$data['content'] .= '<figcaption class="wp-element-caption">' . esc_html( $attachment['name'] ) . '</figcaption>';
					}
					$data['content'] .= '</figure>';
					$data['content'] .= '<!-- /wp:video -->';
				}
			}
		}

		$this->log(
			'Received feed item',
			array(
				'url'  => $permalink,
				'data' => $data,
			)
		);
		return new Feed_Item( $data );
	}


	/**
	 * We received a delete of a post, handle it.
	 *
	 * @param      array $activity     The object from ActivityPub.
	 */
	private function handle_incoming_delete( $activity ) {
		$permalink = $activity['id'];
		if ( isset( $activity['url'] ) ) {
			$permalink = $activity['url'];
		}

		$this->log( 'Received delete for ' . $permalink );

		$comment_id = null;
		if ( preg_match( '/#comment-(\d+)$/i', $permalink, $matches ) ) {
			$comment_id = $matches[1];
			$permalink = substr( $permalink, 0, - strlen( $matches[0] ) );
		}

		$post_id = Feed::url_to_postid( $permalink );
		if ( $post_id ) {
			if ( $comment_id ) {
				$comment = get_comment( $comment_id );
				if ( $comment ) {
					wp_delete_comment( $comment_id );
					return true;
				}
			} else {
				$meta = get_post_meta( $post_id, self::SLUG, true );
				if ( ! empty( $meta ) ) {
					wp_trash_post( $post_id );
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * We received an announced URL (boost) for a feed, handle it.
	 *
	 * @param      array  $url     The announced URL.
	 * @param      int    $user_id  The user id (for retrieving the keys).
	 * @param      string $published_date  The published date.
	 */
	private function handle_incoming_announce( $url, $user_id, $published_date = null ) {
		if ( ! Friends::check_url( $url ) ) {
			$this->log( 'Received invalid announce', compact( 'url' ) );
			return false;
		}
		$this->log( 'Received announce for ' . $url );
		if ( null === $user_id ) {
			$user_id = Friends::get_main_friend_user_id();
		}
		$response = \Activitypub\safe_remote_get( $url, $user_id );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}
		$json = \wp_remote_retrieve_body( $response );
		$object = \json_decode( $json, true );
		if ( ! $object ) {
			$this->log( 'Received invalid json', compact( 'json' ) );
			return false;
		}
		$this->log( 'Received response', compact( 'url', 'object' ) );
		if ( ! $published_date ) {
			$published_date = 'now';
		}
		$object['published'] = gmdate( 'Y-m-d H:i:s', strtotime( $published_date ) );
		$object['reblog'] = true;

		return $this->handle_incoming_create( $object );
	}

	/**
	 * We received an announced URL (boost) for a feed, handle it.
	 *
	 * @param      array     $url     The announced URL.
	 * @param      User_Feed $user_feed The user feed.
	 */
	private function handle_incoming_unannounce( $url, $user_feed ) {
		if ( ! Friends::check_url( $url ) ) {
			$this->log( 'Received invalid unannounce', compact( 'url' ) );
			return false;
		}
		$this->log( 'Received unannounce for ' . $url );

		$post_id = Feed::url_to_postid( $url, $user_feed->get_friend_user()->ID );
		if ( $post_id ) {
			$meta = get_post_meta( $post_id, self::SLUG, true );
			if ( isset( $meta['reblog'] ) ) {
				wp_trash_post( $post_id );
				return true;
			}
		}
		return false;
	}

	public function get_reaction_display_name( $display_name, $term ) {
		$url = get_term_meta( $term->term_id, 'url', true );
		$meta = \Activitypub\get_remote_metadata_by_actor( $url );
		if ( $meta && ! is_wp_error( $meta ) && ! empty( $meta['preferredUsername'] ) ) {
			$host = parse_url( $meta['id'], PHP_URL_HOST );
			return '@' . $meta['preferredUsername'] . '@' . $host;
		}
		return $display_name;
	}

	public function handle_incoming_like( $activity, $user_id ) {
		$post_id = Feed::url_to_postid( $activity['object'] );
		if ( ! $post_id ) {
			return false;
		}
		$taxonomy_username = 'ap-' . crc32( $activity['actor'] );

		// Allow setting the term with its meta.
		$taxonomy = Reactions::register_user_taxonomy( $taxonomy_username );

		register_term_meta(
			$taxonomy,
			'url',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// 2b50 = star.
		// 2764 = heart.
		$term_id = apply_filters( 'friends_react', null, $post_id, '2b50', $taxonomy_username );
		if ( ! $term_id || is_wp_error( $term_id ) ) {
			return false;
		}

		update_term_meta( $term_id, 'url', $activity['actor'] );

		return $term_id;
	}

	public function handle_incoming_unlike( $activity, $user_id ) {
		$post_id = Feed::url_to_postid( $activity['object'] );
		if ( ! $post_id ) {
			return false;
		}

		$taxonomy_username = 'ap-' . crc32( $activity['actor'] );

		// Allow removing the term.
		Reactions::register_user_taxonomy( $taxonomy_username );

		// 2b50 = star.
		// 2764 = heart.
		$ret = apply_filters( 'friends_unreact', null, $post_id, '2b50', $taxonomy_username );
		if ( ! $ret || is_wp_error( $ret ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Queue a hook to run async.
	 *
	 * @param string $hook The hook name.
	 * @param array  $args The arguments to pass to the hook.
	 * @param string $unqueue_hook Optional a hook to unschedule before queuing.
	 * @return void|bool Whether the hook was queued.
	 */
	public function queue( $hook, $args, $unqueue_hook = null ) {
		if ( $unqueue_hook ) {
			$hook_timestamp = wp_next_scheduled( $unqueue_hook, $args );
			if ( $hook_timestamp ) {
				wp_unschedule_event( $hook_timestamp, $unqueue_hook, $args );
			}
		}

		if ( wp_next_scheduled( $hook, $args ) ) {
			return;
		}

		return \wp_schedule_single_event( \time(), $hook, $args );
	}

	/**
	 * Prepare to follow the user via a scheduled event.
	 *
	 * @param      User_Feed $user_feed  The user feed.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_follow_user( User_Feed $user_feed ) {
		if ( self::SLUG !== $user_feed->get_parser() ) {
			return;
		}

		$queued = $this->queue(
			'friends_feed_parser_activitypub_follow',
			array( $user_feed->get_url() ),
			'friends_feed_parser_activitypub_unfollow'
		);

		if ( $queued ) {
			$user_feed->update_last_log( __( 'Queued follow request.', 'friends' ) );
		}

		return $queued;
	}

	/**
	 * Follow a user via ActivityPub at a URL.
	 *
	 * @param      string $url    The url.
	 * @param      int    $user_id   The current user id.
	 */
	public function activitypub_follow_user( $url, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = Friends::get_main_friend_user_id();
		}

		$meta = $this->get_metadata( $url );
		$user_feed = User_Feed::get_by_url( $url );
		if ( is_wp_error( $meta ) ) {
			if ( $user_feed instanceof User_Feed ) {
				$user_feed->update_last_log(
					sprintf(
						// translators: %s an error message.
						__( 'Error: %s', 'friends' ),
						$meta->get_error_code() . ' ' . $meta->get_error_message()
					)
				);
			}
			return $meta;
		}
		$to = $meta['id'];
		$type = 'Follow';
		$inbox = self::get_inbox_by_actor( $to, $type );
		if ( is_wp_error( $inbox ) ) {
			return $inbox;
		}
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( $type );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( $actor );
		$activity->set_object( $to );
		$activity->set_id( $actor . '#follow-' . \preg_replace( '~^https?://~', '', $to ) );
		$activity = $activity->to_json();
		$response = \Activitypub\safe_remote_post( $inbox, $activity, $user_id );

		if ( $user_feed instanceof User_Feed ) {
			$user_feed->update_last_log(
				sprintf(
				// translators: %s is the response from the remote server.
					__( 'Sent follow request with response: %s', 'friends' ),
					wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_response_message( $response )
				)
			);
		}
	}

	/**
	 * Prepare to unfollow the user via a scheduled event.
	 *
	 * @param      User_Feed $user_feed  The user feed.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_unfollow_user( User_Feed $user_feed ) {
		if ( self::SLUG !== $user_feed->get_parser() ) {
			return false;
		}

		$queued = $this->queue(
			'friends_feed_parser_activitypub_unfollow',
			array( $user_feed->get_url() ),
			'friends_feed_parser_activitypub_follow'
		);

		if ( $queued ) {
			$user_feed->update_last_log( __( 'Queued unfollow request.', 'friends' ) );
		}

		return $queued;
	}

	/**
	 * Unfllow a user via ActivityPub at a URL.
	 *
	 * @param      string $url    The url.
	 * @param      int    $user_id   The current user id.
	 */
	public function activitypub_unfollow_user( $url, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = Friends::get_main_friend_user_id();
		}
		$meta = $this->get_metadata( $url );
		$user_feed = User_Feed::get_by_url( $url );
		if ( is_wp_error( $meta ) ) {
			if ( $user_feed instanceof User_Feed ) {
				$user_feed->update_last_log(
					sprintf(
						// translators: %s an error message.
						__( 'Error: %s', 'friends' ),
						$meta->get_error_code() . ' ' . $meta->get_error_message()
					)
				);
			}
			return $meta;
		}
		$to = $meta['id'];
		$type = 'Follow';
		$inbox = self::get_inbox_by_actor( $to, $type );
		if ( is_wp_error( $inbox ) ) {
			return $inbox;
		}
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Undo' );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( $actor );
		$activity->set_object(
			array(
				'type'   => $type,
				'actor'  => $actor,
				'object' => $to,
				'id'     => $to,
			)
		);
		$activity->set_id( $actor . '#unfollow-' . \preg_replace( '~^https?://~', '', $to ) );
		$activity = $activity->to_json();
		$response = \Activitypub\safe_remote_post( $inbox, $activity, $user_id );

		$user_feed = User_Feed::get_by_url( $url );
		if ( $user_feed instanceof User_Feed ) {
			$user_feed->update_last_log(
				sprintf(
				// translators: %s is the response from the remote server.
					__( 'Sent unfollow request with response: %s', 'friends' ),
					wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_response_message( $response )
				)
			);
		}
	}

	public function get_possible_mentions() {
		static $users = null;
		if ( ! method_exists( '\Friends\User_Feed', 'get_by_parser' ) ) {
			return array();
		}

		if ( is_null( $users ) || ! apply_filters( 'activitypub_cache_possible_friend_mentions', true ) ) {
			$feeds = User_Feed::get_by_parser( 'activitypub' );
			$users = array();
			foreach ( $feeds as $feed ) {
				$user = $feed->get_friend_user();
				$slug = sanitize_title( $user->user_nicename );
				$users[ '@' . $slug ] = $feed->get_url();
			}
		}
		return $users;
	}

	/**
	 * Extract the mentions from the post_content.
	 *
	 * @param array  $mentions The already found mentions.
	 * @param string $post_content The post content.
	 * @return mixed The discovered mentions.
	 */
	public function activitypub_extract_mentions( $mentions, $post_content ) {
		$users = $this->get_possible_mentions();
		preg_match_all( '/@(?:[a-zA-Z0-9_-]+)/', $post_content, $matches );
		foreach ( $matches[0] as $match ) {
			if ( isset( $users[ $match ] ) ) {
				$mentions[ $match ] = $users[ $match ];
			}
		}
		return $mentions;
	}

	public function the_content( $the_content ) {
		$protected_tags = array();
		$the_content = preg_replace_callback(
			'#<a.*?href=[^>]+>.*?</a>#i',
			function ( $m ) use ( &$protected_tags ) {
				$c = count( $protected_tags );
				$protect = '!#!#PROTECT' . $c . '#!#!';
				$protected_tags[ $protect ] = $m[0];
				return $protect;
			},
			$the_content
		);

		$the_content = \preg_replace_callback( '/@(?:[a-zA-Z0-9_-]+)/', array( $this, 'replace_with_links' ), $the_content );

		$the_content = str_replace( array_keys( $protected_tags ), array_values( $protected_tags ), $the_content );

		return $the_content;
	}

	/**
	 * Replace the mention with a link to the user.
	 *
	 * @param array $result The matched username.
	 *
	 * @return string The replaced username.
	 */
	public function replace_with_links( array $result ) {
		$users = $this->get_possible_mentions();
		if ( ! isset( $users[ $result[0] ] ) ) {
			return $result[0];
		}

		$metadata = $this->get_metadata( $users[ $result[0] ] );
		if ( is_wp_error( $metadata ) || empty( $metadata['url'] ) ) {
			return $result[0];
		}

		$username = ltrim( $users[ $result[0] ], '@' );
		if ( ! empty( $metadata['name'] ) ) {
			$username = $metadata['name'];
		}
		if ( ! empty( $metadata['preferredUsername'] ) ) {
			$username = $metadata['preferredUsername'];
		}
		$username = '@<span>' . $username . '</span>';
		return \sprintf( '<a rel="mention" class="u-url mention" href="%s">%s</a>', $metadata['url'], $username );
	}

	public function activitypub_save_settings( User $friend ) {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'edit-friend-feeds-' . $friend->user_login ) ) {

			if ( isset( $_POST['friends_show_replies'] ) && $_POST['friends_show_replies'] ) {
				$friend->update_user_option( 'activitypub_friends_show_replies', '1' );
			} else {
				$friend->delete_user_option( 'activitypub_friends_show_replies' );
			}
		}
	}

	public function activitypub_settings( User $friend ) {
		$has_activitypub_feed = false;
		foreach ( $friend->get_active_feeds() as $feed ) {
			if ( 'activitypub' === $feed->get_parser() ) {
				$has_activitypub_feed = true;
			}
		}

		if ( ! $has_activitypub_feed ) {
			return;
		}

		?>
		<tr>
			<th>ActivityPub</th>
			<td>
				<fieldset>
					<div>
						<input type="checkbox" name="friends_show_replies" id="friends_show_replies" value="1" <?php checked( '1', $friend->get_user_option( 'activitypub_friends_show_replies' ) ); ?> />
						<label for="friends_show_replies"><?php esc_html_e( "Don't hide @mentions of feeds you don't follow", 'friends' ); ?></label>
						</div>
				</fieldset>
				<p class="description">
				<?php
				esc_html_e( "Show ActivityPub '@mention' posts even when you do not follow the feed being mentioned.", 'friends' );
				?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Apply the feed rules
	 *
	 * @param  Feed_Item $item         The feed item.
	 * @param  User_Feed $feed         The feed object.
	 * @param  User      $friend_user The friend user.
	 * @return Feed_Item The modified feed item.
	 */
	public function modify_incoming_item( Feed_Item $item, User_Feed $feed = null, User $friend_user = null ) {
		if ( ! $feed || 'activitypub' !== $feed->get_parser() ) {
			return $item;
		}

		if ( ! $friend_user->get_user_option( 'activitypub_friends_show_replies' ) ) {
			$plain_text_content = \wp_strip_all_tags( $item->post_content );
			$possible_mentions = $this->get_possible_mentions();

			$no_known_user_found = true;
			if ( preg_match( '/^@(?:[a-zA-Z0-9_.-]+)/i', $plain_text_content, $m ) ) {
				if ( ! isset( $possible_mentions[ $m[0] ] ) ) {
					$no_known_user_found = false;
				}
			}

			if ( $no_known_user_found ) {
				if ( $friend_user && false !== strpos( $item->post_content, \get_author_posts_url( $friend_user->ID ) ) ) {
					$no_known_user_found = false;
				}
			}

			if ( $no_known_user_found ) {
				foreach ( $possible_mentions as $username => $mention_url ) {
					if ( false !== strpos( $item->post_content, $mention_url ) ) {
						$no_known_user_found = false;
						break;
					}
					if ( false !== strpos( $username, $mention_url ) ) {
						$no_known_user_found = false;
						break;
					}
				}
			}

			if ( ! $no_known_user_found ) {
				$item->_feed_rule_transform = array(
					'post_status' => 'trash',
				);
			}
		}

		return $item;
	}

	public function friends_potential_avatars( $avatars, User $friend_user ) {
		foreach ( $friend_user->get_feeds() as $user_feed ) {
			if ( 'activitypub' === $user_feed->get_parser() ) {
				$details = $this->update_feed_details(
					array(
						'url' => $user_feed->get_url(),
					)
				);
				if ( isset( $details['avatar'] ) ) {
					$avatars[ $details['avatar'] ] = sprintf(
						// translators: %s is a username.
						__( 'Avatar of %s', 'friends' ),
						$details['title']
					);
				}
			}
		}
		return $avatars;
	}

	private function get_author_of_post( \WP_Post $post ) {
		$friend = User::get_post_author( $post );
		if ( ! $friend || is_wp_error( $friend ) ) {
			return $friend;
		}

		$meta = get_post_meta( $post->ID, self::SLUG, true );
		if ( isset( $meta['attributedTo']['id'] ) ) {
			return $meta['attributedTo']['id'];
		}

		$host = wp_parse_url( $post->guid, PHP_URL_HOST );

		foreach ( $friend->get_active_feeds() as $feed ) {
			if ( 'activitypub' !== $feed->get_parser() ) {
				continue;
			}

			$feed_host = wp_parse_url( $feed->get_url(), PHP_URL_HOST );
			if ( $feed_host !== $host ) {
				continue;
			}

			return $feed->get_url();
		}

		return null;
	}

	/**
	 * Create a status based on a post reaction.
	 *
	 * @param      int $post_id  The post ID.
	 */
	public function post_reaction( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$author_url = $this->get_author_of_post( $post );
		if ( ! $author_url || is_wp_error( $author_url ) ) {
			return;
		}
		return $this->queue_like_post( $post, $author_url );
	}

	/**
	 * Prepare to follow the user via a scheduled event.
	 *
	 * @param      \WP_Post $post       The post.
	 * @param      string   $author_url  The author url.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_like_post( \WP_Post $post, $author_url ) {
		$external_post_id = get_post_meta( $post->ID, 'external-id', true );
		if ( ! $external_post_id ) {
			$external_post_id = $post->guid;
		}

		$queued = $this->queue(
			'friends_feed_parser_activitypub_like',
			array( $author_url, $external_post_id, get_current_user_id() ),
			'friends_feed_parser_activitypub_unlike'
		);

		return $queued;
	}

	/**
	 * Like a post.
	 *
	 * @param mixed $url The URL of the user.
	 * @param mixed $external_post_id The post to like.
	 * @param mixed $user_id The current user id.
	 * @return void|WP_Error
	 */
	public function activitypub_like_post( $url, $external_post_id, $user_id ) {
		$type = 'Like';
		$inbox = self::get_inbox_by_actor( $url, $type );
		if ( is_wp_error( $inbox ) ) {
			return $inbox;
		}
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( $type );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( $actor );
		$activity->set_object( $external_post_id );
		$activity->set_id( $actor . '#like-' . \preg_replace( '~^https?://~', '', $external_post_id ) );
		$activity = $activity->to_json();
		$response = \Activitypub\safe_remote_post( $inbox, $activity, $user_id );

		$user_feed = User_Feed::get_by_url( $url );
		if ( $user_feed instanceof User_Feed ) {
			$user_feed->update_last_log(
				sprintf(
				// translators: %s is the response from the remote server.
					__( 'Sent like request with response: %s', 'friends' ),
					wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_response_message( $response )
				)
			);
		}
	}

	/**
	 * Create a status based on a post reaction.
	 *
	 * @param      int $post_id  The post ID.
	 */
	public function undo_post_reaction( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$author_url = $this->get_author_of_post( $post );
		if ( ! $author_url || is_wp_error( $author_url ) ) {
			return;
		}
		return $this->queue_unlike_post( $post, $author_url );
	}

	/**
	 * Prepare to follow the user via a scheduled event.
	 *
	 * @param      \WP_Post $post       The post.
	 * @param      string   $author_url  The author url.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_unlike_post( \WP_Post $post, $author_url ) {
		$external_post_id = get_post_meta( $post->ID, 'external-id', true );
		if ( ! $external_post_id ) {
			$external_post_id = $post->guid;
		}

		$queued = $this->queue(
			'friends_feed_parser_activitypub_unlike',
			array( $author_url, $external_post_id, get_current_user_id() ),
			'friends_feed_parser_activitypub_like'
		);

		if ( $queued ) {
			$user_feed->update_last_log( __( 'Queued unlike request.', 'friends' ) );
		}

		return $queued;
	}

	/**
	 * Unlike a post.
	 *
	 * @param mixed $url The URL of the user.
	 * @param mixed $external_post_id The post to like.
	 * @param mixed $user_id The current user id.
	 * @return void|WP_Error
	 */
	public function activitypub_unlike_post( $url, $external_post_id, $user_id ) {
		$type = 'Like';
		$inbox = self::get_inbox_by_actor( $url, $type );
		if ( is_wp_error( $inbox ) ) {
			return $inbox;
		}
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Undo' );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( $actor );
		$activity->set_object(
			array(
				'type'   => $type,
				'actor'  => $actor,
				'object' => $external_post_id,
				'id'     => $actor . '#like-' . \preg_replace( '~^https?://~', '', $external_post_id ),
			)
		);
		$activity->set_id( $actor . '#unlike-' . \preg_replace( '~^https?://~', '', $external_post_id ) );
		$activity = $activity->to_json();
		$response = \Activitypub\safe_remote_post( $inbox, $activity, $user_id );

		$user_feed = User_Feed::get_by_url( $url );
		if ( $user_feed instanceof User_Feed ) {
			$user_feed->update_last_log(
				sprintf(
				// translators: %s is the response from the remote server.
					__( 'Sent unlike request with response: %s', 'friends' ),
					wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_response_message( $response )
				)
			);
		}
	}

	public function friends_reblog_button_label( $button_label ) {
		if ( User_Feed::get_parser_for_post_id( get_the_ID() ) === 'activitypub' ) {
			if ( get_user_option( 'friends_activitypub_dont_reblog' ) ) {
				$button_label = _x( 'Boost', 'button', 'friends' );
			} else {
				$button_label = _x( 'Reblog & Boost', 'button', 'friends' );
			}
		}
		return $button_label;
	}


	/**
	 * Get metadata for in_reply_to_preview.
	 *
	 * @param      string $url    The url.
	 *
	 * @return    array|WP_Error  The in reply to metadata.
	 */
	public function get_activitypub_ajax_metadata( $url ) {
		$meta = apply_filters( 'friends_get_activitypub_metadata', array(), $url );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		if ( ! $meta || ! isset( $meta['attributedTo'] ) ) {
			return new \WP_Error( 'no-activitypub', 'No ActivityPub metadata found.' );
		}

		$html = force_balance_tags( wp_kses_post( $meta['content'] ) );

		$webfinger = apply_filters( 'friends_get_activitypub_metadata', array(), $meta['attributedTo'] );
		$mention = '';
		if ( $webfinger && ! is_wp_error( $webfinger ) ) {
			$mention = '@' . $webfinger['preferredUsername'] . '@' . parse_url( $url, PHP_URL_HOST );
		}

		return array(
			'url'     => $url,
			'html'    => $html,
			'author'  => $meta['attributedTo'],
			'mention' => $mention,
		);
	}

	/**
	 * The Ajax function to fill the in-reply-to-preview.
	 */
	public function ajax_in_reply_to_preview() {
		$url = wp_unslash( $_POST['url'] );

		if ( ! wp_parse_url( $url ) ) {
			wp_send_json_error();
			exit;
		}

		$meta = $this->get_activitypub_ajax_metadata( $_POST['url'] );

		if ( is_wp_error( $meta ) ) {
			wp_send_json_error( $meta->get_error_message() );
			exit;
		}
		wp_send_json_success( $meta );
	}

	public function frontend_reply_form( $args ) {
		if ( isset( $_GET['in_reply_to'] ) && wp_parse_url( $_GET['in_reply_to'] ) ) {
			$args['in_reply_to'] = $this->get_activitypub_ajax_metadata( $_GET['in_reply_to'] );
			$args['in_reply_to']['html'] = '<figcaption>' . make_clickable( $_GET['in_reply_to'] ) . '</figcaption><blockquote>' . $args['in_reply_to']['html'] . '</blockquote>';
			$args['form_class'] = 'open';
			Friends::template_loader()->get_template_part( 'frontend/activitypub/reply', true, $args );
		}
	}

	public function frontend_boost_form( $args ) {
		if ( isset( $_GET['boost'] ) && wp_parse_url( $_GET['boost'] ) ) {
			$args['boost'] = $this->get_activitypub_ajax_metadata( $_GET['boost'] );
			$args['boost']['html'] = '<figcaption>' . make_clickable( $_GET['boost'] ) . '</figcaption><blockquote>' . $args['boost']['html'] . '</blockquote>';
			$args['form_class'] = 'open';
			Friends::template_loader()->get_template_part( 'frontend/activitypub/boost', true, $args );
		}
	}

	public function friends_search_autocomplete( $results, $q ) {
		$url = preg_match( '#^(?:https?:\/\/)?(?:w{3}\.)?[\w-]+(?:\.[\w-]+)+((?:\/[^\s\/]*)*)#i', $q, $m );
		$url_with_path = isset( $m[1] ) && $m[1];

		if ( ( $url && ! $url_with_path ) || preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $q ) ) {
			$result = '<a href="' . esc_url( add_query_arg( 'url', $q, admin_url( 'admin.php?page=add-friend' ) ) ) . '" class="has-icon-left">';
			$result .= '<span class="ab-icon dashicons dashicons-businessperson"><span class="dashicons dashicons-plus"></span></span>';
			$result .= 'Follow ';
			$result .= ' <small>';
			$result .= esc_html( $q );
			$result .= '</small></a>';
			$results[] = $result;
		}

		if ( $url_with_path ) {
			$result = '<a href="' . esc_url( add_query_arg( 'boost', $q, home_url( '/friends/type/status/' ) ) ) . '" class="has-icon-left">';
			$result .= '<span class="ab-icon dashicons dashicons-controls-repeat"></span>';
			$result .= 'Boost ';
			$result .= ' <small>';
			$result .= esc_html( Friends::url_truncate( $q ) );
			$result .= '</small></a>';
			$results[] = $result;
		}

		if ( $url_with_path ) {
			$result = '<a href="' . esc_url( add_query_arg( 'in_reply_to', $q, home_url( '/friends/type/status/' ) ) ) . '" class="has-icon-left">';
			$result .= '<span class="ab-icon dashicons dashicons-admin-comments"></span>';
			$result .= 'Reply to ';
			$result .= ' <small>';
			$result .= esc_html( Friends::url_truncate( $q ) );
			$result .= '</small></a>';
			$results[] = $result;
		}

		return $results;
	}

	public function mastodon_api_react( $post_id, $reaction ) {
		apply_filters( 'friends_react', null, $post_id, $reaction );
	}

	public function mastodon_api_unreact( $post_id, $reaction ) {
		apply_filters( 'friends_unreact', null, $post_id, $reaction );
	}

	public function mastodon_api_reblog( $post_id ) {
		apply_filters( 'friends_reblog', null, $post_id );
	}

	public function mastodon_api_unreblog( $post_id ) {
		apply_filters( 'friends_unreblog', null, $post_id );
	}

	/**
	 * Don't create a note via ActivityPub since we'll use announce there.
	 *
	 * @param      bool $ret  The return value.
	 *
	 * @return     bool  The return value.
	 */
	public function unqueue_activitypub_create( $ret ) {
		$hook = 'transition_post_status';
		$filter = array( '\Activitypub\Activitypub', 'schedule_post_activity' );
		$priority = has_filter( $hook, $filter );
		if ( $priority ) {
			remove_filter( $hook, $filter, $priority );
		}
		return $ret;
	}

	public function maybe_unqueue_friends_reblog_post( $ret, $post ) {
		if ( ! get_user_option( 'friends_activitypub_dont_reblog' ) ) {
			return $ret;
		}

		if ( User_Feed::get_parser_for_post_id( $post->ID ) !== 'activitypub' ) {
			return $ret;
		}

		remove_filter( 'friends_reblog', array( 'Friends\Frontend', 'reblog' ), 10, 2 );
		return $ret;
	}

	public function reblog( $ret, $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $ret;
		}
		if ( User_Feed::get_parser_for_post_id( $post->ID ) === 'activitypub' ) {
			$this->queue_announce( $post->guid );
			\update_post_meta( $post->ID, 'reblogged', 'activitypub' );
			\update_post_meta( $post->ID, 'reblogged_by', get_current_user_id() );
			return true;
		}
		return $ret;
	}

	public function unreblog( $ret, $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $ret;
		}
		if ( get_post_meta( $post->ID, 'reblogged', true ) === 'activitypub' ) {
			$this->queue_unannounce( $post->guid );
			\delete_post_meta( $post->ID, 'reblogged', 'activitypub' );
			\delete_post_meta( $post->ID, 'reblogged_by', get_current_user_id() );
			return true;
		}
		return $ret;
	}

	/**
	 * Prepare to announce the post via a scheduled event.
	 *
	 * @param      string $url  The url to announce.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_announce( $url ) {
		$queued = $this->queue(
			'friends_feed_parser_activitypub_announce',
			array( $url, get_current_user_id() ),
			'friends_feed_parser_activitypub_unannounce'
		);

		return $queued;
	}

	/**
	 * Announce the post via ActivityPub.
	 *
	 * @param      string $url      The url.
	 * @param      id     $user_id  The user id.
	 */
	public function activitypub_announce( $url, $user_id ) {
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Announce' );
		$activity->set_actor( $actor );
		$activity->set_object( $url );
		$activity->set_id( $actor . '#activitypub_announce-' . \preg_replace( '~^https?://~', '', $url ) );

		$follower_inboxes  = \Activitypub\Collection\Followers::get_inboxes( $user_id );
		$mentioned_inboxes = \Activitypub\Mention::get_inboxes( $activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			\Activitypub\safe_remote_post( $inbox, $json, $user_id );
		}
	}

	/**
	 * Prepare to announce the post via a scheduled event.
	 *
	 * @param      string $url  The url to announce.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_unannounce( $url ) {
		$queued = $this->queue(
			'friends_feed_parser_activitypub_unannounce',
			array( $url, get_current_user_id() ),
			'friends_feed_parser_activitypub_announce'
		);

		return $queued;
	}

	/**
	 * Unannounce the post via ActivityPub.
	 *
	 * @param      string $url      The url.
	 * @param      id     $user_id  The user id.
	 */
	public function activitypub_unannounce( $url, $user_id ) {
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Undo' );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( $actor );
		$activity->set_id( $actor . '#activitypub_unannounce-' . \preg_replace( '~^https?://~', '', $url ) );
		$activity->set_object(
			array(
				'type'   => 'Announce',
				'actor'  => $actor,
				'object' => $url,
				'id'     => $actor . '#activitypub_announce-' . \preg_replace( '~^https?://~', '', $url ),
			)
		);

		$follower_inboxes  = \Activitypub\Collection\Followers::get_inboxes( $user_id );
		$mentioned_inboxes = \Activitypub\Mention::get_inboxes( $activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			\Activitypub\safe_remote_post( $inbox, $json, $user_id );
		}
	}

	/**
	 * Approve comments that
	 *
	 * @param      bool  $approved     Whether the comment is approved.
	 * @param      array $commentdata  The commentdata.
	 *
	 * @return     bool    Whether the comment is approved.
	 */
	public function pre_comment_approved( $approved, $commentdata ) {
		if ( ! $approved || is_string( $approved ) && 'activitypub' === $commentdata['comment_meta']['protocol'] ) {
			$user_feed = User_Feed::get_by_url( $commentdata['comment_author_url'] );
			if ( $user_feed instanceof User_Feed ) {
				$approved = true;
			}
		}
		return $approved;
	}

	public function comment_post( $comment_id, $comment_approved, $commentdata ) {
		if ( isset( $commentdata['commentmeta']['protocol'] ) && 'activitypub' === $commentdata['commentmeta']['protocol'] ) {
			// Don't act upon incoming comments via ActivityPub.
			return;
		}

		if ( empty( $commentdata['user_id'] ) ) {
			// Don't act on other people's comments.
			return;
		}

		$user = new \WP_User( $commentdata['user_id'] );
		if ( User::is_friends_plugin_user( $user ) ) {
			// Don't act on non-local comments.
			return;
		}

		// TODO: in the ActivityPub plugin, we should be able to use the comment_post hook to send out the comment.
	}

	public function trashed_comment( $comment_id, $comment ) {
		if ( get_comment_meta( $comment_id, 'protocol', true ) === 'activitypub' ) {
			// Don't act on comments that came via ActivityPub.
			return;
		}

		if ( empty( $comment->user_id ) ) {
			// Don't act on other people's comments.
			return;
		}

		$user = new \WP_User( $comment->user_id );
		if ( User::is_friends_plugin_user( $user ) ) {
			// Don't act on non-local comments.
			return;
		}

		// TODO: in the ActivityPub plugin, we should be able to use the trashed_comment hook to send out the comment deletion.
	}

	/**
	 * Disable Webfinger for example domains
	 *
	 * @param mixed $metadata Already retrieved metadata.
	 * @param mixed $actor The actor as URL or username.
	 * @return mixed The potentially added metadata for example domains.
	 */
	public function disable_webfinger_for_example_domains( $metadata, $actor ) {
		if ( ! $metadata ) {
			$username = null;
			$domain = null;
			if ( preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $actor, $m ) ) {
				$username = $m[1];
				$domain = $m[2];
			} else {
				$p = wp_parse_url( $actor );
				if ( $p ) {
					if ( isset( $p['host'] ) ) {
						$domain = $p['host'];
					}
					if ( isset( $p['path'] ) ) {
						$path_parts = explode( '/', trim( $p['path'], '/' ) );
						$username = ltrim( array_pop( $path_parts ), '@' );
					}
				}
			}
			if ( preg_match( '/[^a-zA-Z0-9.-]/', $domain ) ) { // Has invalid characters.
				return $metadata;
			}

			if (
				rtrim( strtok( $domain, '.' ), '0123456789' ) === 'example' // A classic example.org domain.
				|| preg_match( '/(my|your|our)-(domain)/', $domain )
				|| preg_match( '/(test)/', $domain )
				|| in_array( $username, array( 'example' ), true )
			) {
				$metadata = array(
					'url'  => sprintf( 'https://%s/users/%s/', $domain, $username ),
					'name' => $username,
				);
			}
		}
		return $metadata;
	}
}
