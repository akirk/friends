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
use Enable_Mastodon_Apps\Entity\Account as Entity_Account;

/**
 * This is the class for integrating ActivityPub into the Friends Plugin.
 */
class Feed_Parser_ActivityPub extends Feed_Parser_V2 {
	const SLUG = 'activitypub';
	const NAME = 'ActivityPub';
	const URL = 'https://www.w3.org/TR/activitypub/';
	const ACTIVITYPUB_USERNAME_REGEXP = '(?:([A-Za-z0-9_.-]+)@((?:[A-Za-z0-9_-]+\.)+[A-Za-z]+))';
	const EXTERNAL_USERNAME = 'external';

	private $activitypub_already_handled = array();
	private $mapped_usernames = array();
	private $friends_feed;

	/**
	 * Constructor.
	 *
	 * @param      Feed $friends_feed  The friends feed.
	 */
	public function __construct( Feed $friends_feed ) {
		$this->friends_feed = $friends_feed;

		\add_action( 'init', array( $this, 'register_post_meta' ) );
		\add_filter( 'feed_item_allow_set_metadata', array( $this, 'feed_item_allow_set_metadata' ), 10, 3 );
		\add_filter( 'friends_add_friends_input_placeholder', array( $this, 'friends_add_friends_input_placeholder' ) );
		\add_action( 'friends_add_friend_form_top', array( $this, 'friends_add_friend_form_top' ) );

		\add_action( 'activitypub_inbox_create', array( $this, 'handle_received_create' ), 15, 2 );
		\add_filter( 'friends_feed_badge', array( $this, 'friends_feed_badge' ), 10, 3 );
		\add_filter( 'friends_feed_list_title', array( $this, 'friends_feed_list_title' ), 10, 3 );
		\add_action( 'friends_edit_feed_content_top', array( $this, 'render_feed_edit_content' ), 10, 3 );
		\add_action( 'activitypub_inbox_delete', array( $this, 'handle_received_delete' ), 15, 2 );
		\add_action( 'activitypub_inbox_announce', array( $this, 'handle_received_announce' ), 15, 2 );
		\add_action( 'activitypub_inbox_like', array( $this, 'handle_received_like' ), 15, 2 );
		\add_action( 'activitypub_inbox_undo', array( $this, 'handle_received_undo' ), 15, 2 );
		\add_action( 'activitypub_inbox_move', array( $this, 'handle_received_move' ), 15, 2 );
		\add_action( 'activitypub_inbox_update', array( $this, 'handle_received_update' ), 15, 2 );
		\add_action( 'activitypub_handled_create', array( $this, 'activitypub_handled_create' ), 10, 4 );
		\add_action( 'activitypub_interactions_follow_url', array( $this, 'activitypub_interactions_follow_url' ), 10, 2 );
		\add_filter( 'activitypub_comment_post_id', array( $this, 'activitypub_comment_post_id' ), 10, 3 );

		\add_action( 'friends_user_feed_activated', array( $this, 'queue_follow_user' ), 10 );
		\add_action( 'friends_user_feed_deactivated', array( $this, 'queue_unfollow_user' ), 10 );
		\add_action( 'friends_suggest_display_name', array( $this, 'suggest_display_name' ), 10, 2 );
		\add_action( 'friends_feed_parser_activitypub_like', array( $this, 'activitypub_like_post' ), 10, 3 );
		\add_action( 'friends_feed_parser_activitypub_unlike', array( $this, 'activitypub_unlike_post' ), 10, 3 );
		\add_action( 'friends_feed_parser_activitypub_announce', array( $this, 'activitypub_announce' ), 10, 2 );
		\add_action( 'friends_feed_parser_activitypub_unannounce', array( $this, 'activitypub_unannounce' ), 10, 2 );
		\add_filter( 'friends_rewrite_incoming_url', array( get_called_class(), 'friends_webfinger_resolve' ), 10, 2 );

		\add_filter( 'friends_edit_feeds_table_end', array( $this, 'activitypub_settings' ), 10 );
		\add_filter( 'friends_edit_feeds_after_form_submit', array( $this, 'activitypub_save_settings' ), 10 );
		\add_filter( 'friends_modify_feed_item', array( $this, 'modify_incoming_item' ), 9, 3 );
		\add_filter( 'friends_potential_avatars', array( $this, 'friends_potential_avatars' ), 10, 2 );
		\add_filter( 'friends_suggest_user_login', array( $this, 'suggest_user_login_from_url' ), 10, 2 );
		\add_filter( 'friends_author_avatar_url', array( $this, 'author_avatar_url' ), 10, 3 );
		\add_filter( 'friends_author_url', array( $this, 'author_url' ), 10, 3 );

		\add_action( 'template_redirect', array( $this, 'cache_reply_to_boost' ) );
		\add_filter( 'the_content', array( $this, 'the_content' ), 99, 2 );
		\add_filter( 'activitypub_extract_mentions', array( $this, 'activitypub_extract_mentions' ), 10, 2 );
		\add_filter( 'activitypub_extract_mentions', array( $this, 'activitypub_extract_in_reply_to_mentions' ), 10, 3 );
		\add_filter( 'mastodon_api_external_mentions_user', array( $this, 'get_external_user' ) );
		\add_filter( 'activitypub_rest_following', array( $this, 'activitypub_rest_following' ), 10, 2 );

		\add_action( 'friends_user_post_reaction', array( $this, 'post_reaction' ) );
		\add_action( 'friends_user_post_undo_reaction', array( $this, 'undo_post_reaction' ) );
		\add_action( 'mastodon_api_react', array( $this, 'mastodon_api_react' ), 10, 2 );
		\add_action( 'mastodon_api_unreact', array( $this, 'mastodon_api_unreact' ), 10, 2 );
		\add_action( 'friends_get_reaction_display_name', array( $this, 'get_reaction_display_name' ), 10, 2 );

		\add_filter( 'pre_comment_approved', array( $this, 'pre_comment_approved' ), 10, 2 );
		\add_action( 'comment_post', array( $this, 'comment_post' ), 10, 3 );
		\add_action( 'trashed_comment', array( $this, 'trashed_comment' ), 10, 2 );
		\add_filter( 'friends_get_comments', array( $this, 'get_remote_comments' ), 10, 4 );
		\add_filter( 'friends_comments_content', array( $this, 'append_comment_form' ), 10, 4 );
		add_filter( 'comment_post_redirect', array( $this, 'comment_post_redirect' ), 10, 2 );

		add_action( 'friends_post_footer_first', array( $this, 'boost_button' ) );
		\add_filter( 'friends_search_autocomplete', array( $this, 'friends_search_autocomplete' ), 10, 2 );

		add_action( 'wp_ajax_friends-boost', array( $this, 'ajax_boost' ) );
		\add_action( 'mastodon_api_reblog', array( $this, 'mastodon_api_reblog' ) );
		\add_action( 'mastodon_api_unreblog', array( $this, 'mastodon_api_unreblog' ) );

		\add_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'disable_webfinger_for_example_domains' ), 9, 2 );

		add_filter( 'friends_get_feed_metadata', array( $this, 'friends_get_feed_metadata' ), 10, 2 );
		add_filter( 'friends_get_activitypub_metadata', array( $this, 'friends_activitypub_metadata' ), 10, 2 );

		add_filter( 'mastodon_api_mapback_user_id', array( $this, 'mastodon_api_mapback_user_id' ), 30, 4 );
		add_filter( 'friends_mastodon_api_username', array( $this, 'friends_mastodon_api_username' ) );
		add_filter( 'mastodon_api_status', array( $this, 'mastodon_api_status_add_reblogs' ), 40, 3 );
		add_filter( 'mastodon_api_canonical_user_id', array( $this, 'mastodon_api_canonical_user_id' ), 20, 3 );
		add_filter( 'mastodon_api_valid_user', array( $this, 'mastodon_api_valid_user' ), 15, 2 );
		add_filter( 'mastodon_api_comment_parent_post_id', array( $this, 'mastodon_api_in_reply_to_id' ), 25 );
		add_filter( 'mastodon_api_in_reply_to_id', array( $this, 'mastodon_api_in_reply_to_id' ), 25 );
		add_filter( 'friends_cache_url_post_id', array( $this, 'check_url_to_postid' ), 10, 2 );

		add_action( 'friends_post_author_meta', array( self::class, 'friends_post_author_meta' ) );
		add_action( 'friends_get_template_part_frontend/parts/header-menu', array( self::class, 'header_menu' ) );
		add_action( 'friends_comments_form', array( self::class, 'comment_form' ) );
		add_action( 'comments_open', array( self::class, 'enable_comments_form' ), 10, 2 );
		add_action( 'wp_ajax_friends-preview-activitypub', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_friends-delete-follower', array( $this, 'ajax_delete_follower' ) );

		add_action( 'mastodon_api_account_following', array( $this, 'mastodon_api_account_following' ), 10, 2 );
		add_action( 'mastodon_api_account', array( $this, 'mastodon_api_account' ), 9, 2 );
		add_filter( 'mastodon_api_account', array( $this, 'mastodon_api_account_external_user' ), 15, 4 );
		add_action( 'friends_message_form_accounts', array( $this, 'friends_message_form_accounts' ), 10, 2 );
		add_action( 'friends_send_direct_message', array( $this, 'friends_send_direct_message' ), 20, 6 );

		// Auto-create Friend subscription when following via ActivityPub plugin.
		add_action( 'post_activitypub_add_to_outbox', array( $this, 'handle_outbox_follow' ), 10, 4 );
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


	public static function determine_mastodon_api_user( $user_id ) {
		static $user_id_map = array();
		if ( null === $user_id ) {
			return false;
		}
		$cache_key = is_scalar( $user_id ) ? $user_id : '';
		if ( $cache_key && isset( $user_id_map[ $cache_key ] ) ) {
			return $user_id_map[ $cache_key ];
		}
		$user = false;
		if ( is_string( $user_id ) && ! is_numeric( $user_id ) ) {
			$user = User::get_by_username( $user_id );
			if ( ! $user ) {
				$url = self::friends_webfinger_resolve( $user_id, $user_id );
				$user_feed = User_Feed::get_by_url( $url );
				if ( $user_feed && ! is_wp_error( $user_feed ) ) {
					$user = $user_feed->get_friend_user();
				}
			}
		} elseif ( ! is_wp_error( $user_id ) ) {
			$user = User::get_user_by_id( $user_id );
			if ( ! $user ) {
				$user = User::get_user_by_id( 1e10 + $user_id );
			}
		}
		if ( $cache_key ) {
			$user_id_map[ $cache_key ] = $user;
		}
		return $user;
	}


	public function mastodon_api_mapback_user_id( $user_id ) {
		if ( ! is_string( $user_id ) && $user_id < 1e10 ) {
			return $user_id;
		}

		$user = self::determine_mastodon_api_user( $user_id );
		if ( $user ) {
			return $user->ID;
		}
		return $user_id;
	}

	public function mastodon_api_status_add_reblogs( $status, $post_id, $request = null ) {
		if ( Friends::CPT !== get_post_type( $post_id ) ) {
			return $status;
		}

		$meta = get_post_meta( $post_id, self::SLUG, true );

		// Only process reblogs with attributedTo.
		if ( ! isset( $meta['reblog'] ) || ! $meta['reblog'] || ! isset( $meta['attributedTo'] ) ) {
			return $status;
		}

		$attributed_to_url = self::get_actor_url_from_attributed_to( $meta['attributedTo'] );
		if ( ! $attributed_to_url ) {
			return $status;
		}

		$status->reblog = clone $status;
		$status->reblog->account = clone $status->account;
		$status->reblog->id = \Enable_Mastodon_Apps\Mastodon_API::remap_reblog_id( $status->reblog->id );

		$account = false;
		$friend_user = User::get_post_author( get_post( $post_id ) );
		$external_user = $this->get_external_user();
		$is_external_user = get_class( $external_user ) === get_class( $friend_user ) && $external_user->get_object_id() === $friend_user->get_object_id();

		if ( $is_external_user ) {
			$feed_url = get_post_meta( $post_id, 'feed_url', true );
			if ( $feed_url ) {
				$actor = self::convert_actor_to_mastodon_handle( $feed_url );
				$account = new Entity_Account();
				$account->id             = $feed_url;
				$account->username       = strtok( $actor, '@' );
				$account->acct           = $actor;
				$account->display_name   = $friend_user->display_name;
				$account->url            = $feed_url;
				$account->note = $friend_user->description;
				if ( ! $account->note ) {
					$account->note = '';
				}

				$account->avatar = $friend_user->avatar;
				if ( ! $account->avatar ) {
					$account->avatar = '';
				}

				$account->avatar_static = $account->avatar;
				$account->header = 'https://files.mastodon.social/media_attachments/files/003/134/405/original/04060b07ddf7bb0b.png';
				$actor_metadata = self::get_actor_metadata_from_attributed_to( $meta['attributedTo'] );
				if ( ! empty( $actor_metadata['header'] ) ) {
					$account->header = $actor_metadata['header'];
				}
				$account->header_static = $account->header;
				$account->created_at = $status->created_at;
			}
		} elseif ( $friend_user instanceof User ) {
			$account = apply_filters( 'mastodon_api_account', null, $friend_user->ID, null, $post_id );
		}

		if ( $account instanceof Entity_Account ) {
			$status->account = $account;
			$reblog_account = apply_filters( 'mastodon_api_account', null, $attributed_to_url );
			if ( $reblog_account instanceof Entity_Account ) {
				$status->reblog->account = $reblog_account;
			} else {
				// Build account from attributedTo metadata.
				$actor_metadata = self::get_actor_metadata_from_attributed_to( $meta['attributedTo'] );
				$status->reblog->account->id = $attributed_to_url;
				if ( ! empty( $actor_metadata['preferredUsername'] ) ) {
					$status->reblog->account->username = $actor_metadata['preferredUsername'];
					$status->reblog->account->acct = self::convert_actor_to_mastodon_handle( $attributed_to_url );
				}
				if ( ! empty( $actor_metadata['name'] ) ) {
					$status->reblog->account->display_name = $actor_metadata['name'];
				}
				if ( ! empty( $actor_metadata['summary'] ) ) {
					$status->reblog->account->note = $actor_metadata['summary'];
				}
				if ( ! empty( $actor_metadata['icon'] ) ) {
					$status->reblog->account->avatar = $actor_metadata['icon'];
					$status->reblog->account->avatar_static = $actor_metadata['icon'];
				}
				if ( ! empty( $actor_metadata['header'] ) ) {
					$status->reblog->account->header = $actor_metadata['header'];
					$status->reblog->account->header_static = $actor_metadata['header'];
				}
				$status->reblog->account->url = $attributed_to_url;
			}
		}

		return $status;
	}

	/**
	 * Get the badge for ActivityPub feeds.
	 *
	 * @return array Badge info.
	 */
	public function get_badge() {
		return array(
			'label' => 'AP',
			'color' => '#f6ad55',
			'title' => __( 'ActivityPub', 'friends' ),
		);
	}

	/**
	 * Filter the feed title to use actor name as fallback for ActivityPub feeds.
	 *
	 * @param string    $title  The feed title.
	 * @param User_Feed $feed   The feed object.
	 * @param string    $parser The parser slug.
	 * @return string The feed title.
	 */
	public function friends_feed_list_title( $title, $feed, $parser ) {
		if ( 'activitypub' !== $parser || $title ) {
			return $title;
		}

		if ( ! \class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			return $title;
		}

		// Try to get actor by post ID first.
		$ap_actor_id = $feed->get_ap_actor_id();
		$ap_actor_post = $ap_actor_id ? \get_post( $ap_actor_id ) : null;

		// Fallback: look up by URL.
		if ( ! $ap_actor_post || 'ap_actor' !== $ap_actor_post->post_type ) {
			$ap_actor_post = \Activitypub\Collection\Remote_Actors::get_by_uri( $feed->get_url() );
		}

		if ( $ap_actor_post && ! is_wp_error( $ap_actor_post ) ) {
			return $ap_actor_post->post_title;
		}

		return $title;
	}

	/**
	 * Filter the feed badge for ActivityPub feeds to add extra info.
	 *
	 * @param array|null $badge     The badge array.
	 * @param User_Feed  $user_feed The user feed.
	 * @param string     $parser    The parser slug.
	 * @return array|null The badge array.
	 */
	public function friends_feed_badge( $badge, $user_feed, $parser ) {
		if ( 'activitypub' !== $parser ) {
			return $badge;
		}

		if ( $user_feed && $user_feed->get_ap_actor_id() ) {
			$badge['title'] = __( 'Linked to ActivityPub plugin', 'friends' );
		}

		return $badge;
	}

	/**
	 * Render extra content in the feed edit section for ActivityPub feeds.
	 *
	 * @param User_Feed $feed      The feed being edited.
	 * @param int       $term_id   The term ID of the feed.
	 * @param string    $parser    The parser slug.
	 */
	public function render_feed_edit_content( $feed, $term_id, $parser ) {
		if ( 'activitypub' !== $parser ) {
			return;
		}

		if ( ! \class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			return;
		}

		// Try to get actor by post ID first, then by URL.
		$ap_actor_id = $feed->get_ap_actor_id();
		$ap_actor_post = $ap_actor_id ? \get_post( $ap_actor_id ) : null;
		$ap_actor_source = $ap_actor_id ? 'taxonomy' : null;

		// Fallback: look up by URL if we have the post type but not a valid post.
		if ( ! $ap_actor_post || 'ap_actor' !== $ap_actor_post->post_type ) {
			$ap_actor_post = \Activitypub\Collection\Remote_Actors::get_by_uri( $feed->get_url() );
			if ( $ap_actor_post && ! is_wp_error( $ap_actor_post ) ) {
				$ap_actor_id = $ap_actor_post->ID;
				$ap_actor_source = 'url_lookup';
			}
		}

		if ( ! $ap_actor_post || is_wp_error( $ap_actor_post ) ) {
			return;
		}

		$ap_actor_acct = \Activitypub\Collection\Remote_Actors::get_acct( $ap_actor_id );
		?>
		<div class="activitypub-plugin-data">
			<div class="ap-section-header"><?php \esc_html_e( 'ActivityPub Plugin', 'friends' ); ?></div>
			<input type="hidden" name="feeds[<?php echo \esc_attr( $term_id ); ?>][url]" value="<?php echo \esc_attr( $feed->get_url() ); ?>" />
			<div class="ap-data-grid">
				<span class="ap-data-label"><?php \esc_html_e( 'Actor', 'friends' ); ?></span>
				<span class="ap-data-value">
					<span class="ap-actor-name"><?php echo \esc_html( $ap_actor_post->post_title ); ?></span>
					<?php if ( $ap_actor_acct ) : ?>
						<span class="ap-actor-acct">@<?php echo \esc_html( $ap_actor_acct ); ?></span>
					<?php endif; ?>
				</span>
				<span class="ap-data-label"><?php \esc_html_e( 'Profile', 'friends' ); ?></span>
				<span class="ap-data-value">
					<a href="<?php echo \esc_url( $ap_actor_post->guid ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( $ap_actor_post->guid ); ?></a>
				</span>
				<span class="ap-data-label"><?php \esc_html_e( 'Actor Post', 'friends' ); ?></span>
				<span class="ap-data-value">
					<code>ap_actor</code>
					<a href="<?php echo \esc_url( \admin_url( 'post.php?post=' . $ap_actor_id . '&action=edit' ) ); ?>">ID <?php echo \esc_html( $ap_actor_id ); ?></a>
					<em style="color: <?php echo 'taxonomy' === $ap_actor_source ? 'green' : 'orange'; ?>;">(<?php echo \esc_html( $ap_actor_source ); ?>)</em>
				</span>
			</div>
			<div class="ap-section-footer">
				<?php
				echo \wp_kses(
					\sprintf(
						/* translators: %s is a link to the ActivityPub following list. */
						\__( 'Managed by the <a href="%s">ActivityPub plugin</a>.', 'friends' ),
						\esc_url( \admin_url( 'users.php?page=activitypub-following-list' ) )
					),
					array( 'a' => array( 'href' => array() ) )
				);
				?>
			</div>
		</div>
		<?php
	}

	public function suggest_display_name( $display_name, $url ) {
		$meta = $this->get_metadata( $url );
		if ( is_wp_error( $meta ) ) {
			return $display_name;
		}
		if ( isset( $meta['name'] ) ) {
			return $meta['name'];
		}
		if ( isset( $meta['preferredUsername'] ) ) {
			return $meta['preferredUsername'];
		}
		return $display_name;
	}

	public function friends_mastodon_api_username( $user_id ) {
		if ( ! isset( $this->mapped_usernames[ $user_id ] ) ) {
			$user = User::get_user_by_id( $user_id );
			if ( $user ) {
				foreach ( $user->get_active_feeds() as $user_feed ) {
					if ( 'activitypub' === $user_feed->get_parser() ) {
						$this->mapped_usernames[ $user_id ] = self::convert_actor_to_mastodon_handle( $user_feed->get_url() );
						break;
					}
				}
			}
		}

		if ( ! isset( $this->mapped_usernames[ $user_id ] ) ) {
			$this->mapped_usernames[ $user_id ] = $user_id;
		}

		return $this->mapped_usernames[ $user_id ];
	}

	public function mastodon_api_in_reply_to_id( $in_reply_to_id ) {
		$in_reply_to_id = \Enable_Mastodon_Apps\Mastodon_API::maybe_get_remapped_url( $in_reply_to_id );
		if ( ! is_string( $in_reply_to_id ) ) {
			return $in_reply_to_id;
		}
		if ( filter_var( $in_reply_to_id, FILTER_VALIDATE_URL ) ) {
			$in_reply_to_id = $this->cache_url( $in_reply_to_id );
		}
		return $in_reply_to_id;
	}

	public function mastodon_api_valid_user( $is_valid_user, $user_id ) {
		if ( ! $is_valid_user ) {
			$mapped_user_id = $this->mastodon_api_canonical_user_id( $user_id );
			if ( $mapped_user_id ) {
				return true;
			}
		}
		return $is_valid_user;
	}

	public function mastodon_api_canonical_user_id( $user_id ) {
		static $user_id_map = array();
		if ( ! isset( $user_id_map[ $user_id ] ) && is_string( $user_id ) ) {
			$user_feed = User_Feed::get_by_url( $user_id );
			if ( $user_feed && ! is_wp_error( $user_feed ) ) {
				$user_id_map[ $user_id ] = self::convert_actor_to_mastodon_handle( $user_feed->get_url() );
			}
		}
		if ( ! isset( $user_id_map[ $user_id ] ) ) {
			if ( is_string( $user_id ) && ! is_numeric( $user_id ) ) {
				$user = User::get_by_username( $user_id );
			} else {
				$user = User::get_user_by_id( $user_id );
				if ( ! $user ) {
					$user = User::get_user_by_id( 1e10 + $user_id );
				}
			}
			if ( $user ) {
				foreach ( $user->get_active_feeds() as $user_feed ) {
					if ( 'activitypub' === $user_feed->get_parser() ) {
						$user_id_map[ $user_id ] = self::convert_actor_to_mastodon_handle( $user_feed->get_url() );
						break;
					}
				}
			}
		}

		if ( ! isset( $user_id_map[ $user_id ] ) ) {
			$user_id_map[ $user_id ] = $user_id;
		}

		return $user_id_map[ $user_id ];
	}

	public function mastodon_api_account_following( $following, $user_id ) {
		if ( ! method_exists( '\Friends\User_Feed', 'get_by_parser' ) ) {
			return array();
		}

		foreach ( User_Feed::get_by_parser( self::SLUG ) as $user_feed ) {
			$following[] = apply_filters( 'mastodon_api_account', null, $user_feed->get_friend_user()->ID );
		}

		return $following;
	}

	/**
	 * Add following count to the user data.
	 *
	 * @param Account $user_data The user data.
	 * @param string  $user_id   The user id.
	 *
	 * @return Account The filtered Account.
	 */
	public static function mastodon_api_account( $user_data, $user_id ) {
		if ( get_current_user_id() !== $user_id ) {
			return $user_data;
		}
		if ( ! method_exists( '\Friends\User_Feed', 'get_by_parser' ) ) {
			return $user_data;
		}

		$user_data->following_count = count( User_Feed::get_by_parser( self::SLUG ) );

		return $user_data;
	}

	/**
	 * Filter the Mastodon API account for posts from the External user.
	 *
	 * When a post is from an external user (someone you don't follow who mentioned you),
	 * extract the actual actor information from the feed_url post meta.
	 *
	 * @param Entity_Account|null $account The current account object.
	 * @param int                 $user_id The user ID.
	 * @param mixed               $request The request object.
	 * @param \WP_Post|null       $post    The post object.
	 * @return Entity_Account|null The filtered account.
	 */
	public function mastodon_api_account_external_user( $account, $user_id, $request = null, $post = null ) {
		if ( ! $post instanceof \WP_Post ) {
			return $account;
		}

		if ( Friends::CPT !== $post->post_type ) {
			return $account;
		}

		$meta = get_post_meta( $post->ID, self::SLUG, true );

		// Check if this post has attributedTo metadata (ActivityPub post).
		if ( ! is_array( $meta ) || ! isset( $meta['attributedTo'] ) ) {
			return $account;
		}

		$attributed_to = $meta['attributedTo'];
		if ( is_array( $attributed_to ) && isset( $attributed_to['id'] ) ) {
			$attributed_to = $attributed_to['id'];
		}

		if ( ! is_string( $attributed_to ) || empty( $attributed_to ) ) {
			return $account;
		}

		$actor_metadata = self::get_actor_metadata_from_attributed_to( $meta['attributedTo'] );

		$actor = self::convert_actor_to_mastodon_handle( $attributed_to );
		$account = new Entity_Account();
		$account->id           = $attributed_to;
		$account->username     = strtok( $actor, '@' );
		$account->acct         = $actor;
		$account->url          = $attributed_to;
		$account->created_at   = new \DateTime( $post->post_date_gmt );
		$account->display_name = '';
		$account->note         = '';
		$account->avatar       = '';
		$account->avatar_static = '';

		if ( ! empty( $actor_metadata['name'] ) ) {
			$account->display_name = $actor_metadata['name'];
		}
		if ( ! empty( $actor_metadata['icon'] ) ) {
			$account->avatar = $actor_metadata['icon'];
			$account->avatar_static = $actor_metadata['icon'];
		}
		if ( ! empty( $actor_metadata['summary'] ) ) {
			$account->note = $actor_metadata['summary'];
		}

		return $account;
	}

	public function friends_message_form_accounts( $accounts, User $friend_user ) {
		foreach ( $friend_user->get_feeds() as $user_feed ) {
			if ( 'activitypub' === $user_feed->get_parser() ) {
				// translators: %s is the user's handle.
				$accounts[ $user_feed->get_url() ] = sprintf( __( '%s (via ActivityPub)', 'friends' ), '@' . $this->convert_actor_to_mastodon_handle( $user_feed->get_url() ) );
			}
		}

		return $accounts;
	}

	public function friends_send_direct_message( $post_id, User $friend_user, $to, $message, $subject = null, $reply_to_post_id = null ) {
		if ( is_wp_error( $post_id ) || ! is_int( $post_id ) ) {
			return $post_id;
		}
		$send_to = null;
		foreach ( $friend_user->get_active_feeds() as $user_feed ) {
			if ( 'activitypub' === $user_feed->get_parser() && $user_feed->get_url() === $to ) {
				$send_to = $to;
				break;
			}
		}

		if ( ! $send_to ) {
			// We don't know this user.
			return $post_id;
		}

		update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
		$inbox = \Activitypub\Http::get_remote_object( $send_to );

		if ( ! $inbox || \is_wp_error( $inbox ) ) {
			return new \WP_Error( 'friends_activitypub_inbox_error', __( 'Cannot get Activitypub inbox.', 'friends' ), compact( 'post_id', 'to', 'send_to' ) );
		}
		$inboxes = array();
		if ( ! empty( $inbox['endpoints']['sharedInbox'] ) ) {
			$inboxes[] = $inbox['endpoints']['sharedInbox'];
		} elseif ( ! empty( $inbox['inbox'] ) ) {
			$inboxes[] = $inbox['inbox'];
		}

		require_once __DIR__ . '/activitypub/class-activitypub-transformer-message.php';

		$user_id = $this->get_activitypub_actor_id( get_current_user_id() );
		$actor = $this->get_activitypub_actor( $user_id );
		if ( ! $actor ) {
			return $post_id;
		}

		$transformer = new ActivityPub_Transformer_Message( get_post( $post_id ) );
		if ( is_wp_error( $transformer ) ) {
			return $transformer;
		}
		$transformer->set_content_visibility( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
		$transformer->to = array( $send_to );
		if ( $reply_to_post_id ) {
			$reply_to = get_post( $reply_to_post_id );
			if ( ! is_wp_error( $reply_to ) ) {
				$transformer->in_reply_to = $reply_to->guid;
			}
		}
		$object = $transformer->to_object();

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Create' );
		$activity->set_id( home_url( '?p=' . $post_id ) . '#direct-message' );
		$activity->set_actor( $actor->get_id() );
		$activity->set_object( $object );
		$activity->set_to( $send_to );

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			$result = \Activitypub\safe_remote_post( $inbox, $json, $actor->get__id() );
		}

		return $post_id;
	}

	public function handle_received_direct_message( $activity, $user_id ) {
		$actor_url = $activity['actor'];
		$user_feed = false;
		if ( Friends::check_url( $actor_url ) ) {
			// Let's check if we follow this actor. If not it might be a different URL representation.
			$user_feed = $this->friends_feed->get_user_feed_by_url( $actor_url );
		}

		$object = $activity['object'];
		$remote_url = $object['id'];
		$reply_to = null;
		if ( isset( $object['inReplyTo'] ) ) {
			$reply_to = $object['inReplyTo'];
		}
		$message = $object['content'];
		$subject = null;

		if ( ! $user_feed || is_wp_error( $user_feed ) ) {
			$actor = apply_filters( 'friends_get_activitypub_metadata', array(), $actor_url );
			if ( ! $actor || is_wp_error( $actor ) ) {
				return;
			}

			$sender_name = false;
			if ( ! empty( $actor['name'] ) ) {
				$sender_name = $actor['name'];
			} elseif ( ! empty( $actor['preferredUsername'] ) ) {
				$sender_name = $actor['preferredUsername'];
			}

			if ( isset( $actor['id'] ) ) {
				if ( $sender_name ) {
					$sender_name .= ' (@' . self::convert_actor_to_mastodon_handle( $actor['id'] ) . ')';
				} else {
					$sender_name = '@' . self::convert_actor_to_mastodon_handle( $actor['id'] );
				}
			} elseif ( ! $sender_name ) {
				$sender_name = '@' . self::convert_actor_to_mastodon_handle( $actor_url );
			}

			do_action( 'notify_unknown_friend_message_received', $sender_name, $message, $subject, $actor_url, $remote_url, $reply_to );
			return;
		}

		$friend_user = $user_feed->get_friend_user();

		do_action( 'notify_friend_message_received', $friend_user, $message, $subject, $actor_url, $remote_url, $reply_to );
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

		$feed_details['type'] = 'application/activity+json';
		$feed_details['autoselect'] = true;
		$actor = $this->get_activitypub_actor( get_current_user_id() );
		if ( $actor ) {
			$feed_details['additional-info'] = 'You will follow as <tt>' . $actor->get_webfinger() . '</tt>';
		}

		$feed_details['suggested-username'] = str_replace( ' ', '-', sanitize_user( $meta['name'] ) );

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
	public static function friends_webfinger_resolve( $url, $incoming_url ) {
		$pre = apply_filters( 'pre_friends_webfinger_resolve', false, $url, $incoming_url );
		if ( $pre ) {
			return $pre;
		}

		static $cache = array();
		if ( isset( $cache[ $incoming_url ] ) ) {
			return $cache[ $incoming_url ];
		}

		if ( preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $incoming_url ) ) {
			$resolved_url = \Activitypub\Webfinger::resolve( $incoming_url );
			if ( ! is_wp_error( $resolved_url ) ) {
				$cache[ $incoming_url ] = $resolved_url;
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
		}
		return \Activitypub\get_remote_metadata_by_actor( $url );
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
	public function fetch_feed( $url, ?User_Feed $user_feed = null ) {
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

		$response = \Activitypub\safe_remote_get( $meta['outbox'] );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'activitypub_could_not_get_outbox_meta', null, compact( 'meta', 'url' ) );
		}

		$outbox = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $outbox['orderedItems'] ) ) {
			if ( ! isset( $outbox['first'] ) ) {
				return new \WP_Error( 'activitypub_could_not_find_outbox_first_page', null, compact( 'url', 'meta', 'outbox' ) );
			}

			$response = \Activitypub\safe_remote_get( $outbox['first'] );
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

			$outbox = $outbox_page;
		}

		$items = array();
		foreach ( $outbox['orderedItems'] as $object ) {
			$type = strtolower( $object['type'] );
			$items[] = $this->process_incoming_activity( $type, $object, get_current_user_id(), $user_feed );
		}

		return $items;
	}

	public function suggest_user_login_from_url( $login, $url ) {
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

	public function get_activitypub_actor( $user_id ) {
		return \Activitypub\Collection\Actors::get_by_id( self::get_activitypub_actor_id( $user_id ) );
	}

	public static function get_activitypub_actor_id( $user_id ) {
		if ( null !== $user_id && ! \Activitypub\user_can_activitypub( $user_id ) ) {
			$user_id = null;
		}
		if ( null === $user_id ) {
			$user_id = Friends::get_main_friend_user_id();
			if ( \defined( 'ACTIVITYPUB_ACTOR_MODE' ) ) {
				$activitypub_actor_mode = \get_option( 'activitypub_actor_mode', \ACTIVITYPUB_ACTOR_MODE );
				if ( \ACTIVITYPUB_BLOG_MODE === $activitypub_actor_mode ) {
					$user_id = \Activitypub\Collection\Actors::BLOG_USER_ID;
				}
			}
		}

		return $user_id;
	}

	/**
	 * Get the actor URL from a remote actor post ID.
	 *
	 * @param int $ap_actor_id The ap_actor post ID.
	 * @return string|null The actor URL, or null if not found.
	 */
	public static function get_actor_url_from_remote_actor_id( $ap_actor_id ) {
		if ( empty( $ap_actor_id ) || ! is_numeric( $ap_actor_id ) ) {
			return null;
		}

		$actor_post = get_post( $ap_actor_id );
		if ( ! $actor_post || 'ap_actor' !== $actor_post->post_type ) {
			return null;
		}

		// The guid contains the canonical actor URL.
		return $actor_post->guid;
	}

	/**
	 * Get the actor URL from attributedTo metadata.
	 *
	 * This method handles both the new format (ap_actor_id) and the legacy format (id/URL)
	 * for backward compatibility.
	 *
	 * @param array $attributed_to The attributedTo metadata array.
	 * @return string|null The actor URL, or null if not found.
	 */
	public static function get_actor_url_from_attributed_to( $attributed_to ) {
		if ( ! is_array( $attributed_to ) ) {
			return null;
		}

		// New format: use ap_actor_id to look up the URL.
		if ( isset( $attributed_to['ap_actor_id'] ) && $attributed_to['ap_actor_id'] ) {
			$url = self::get_actor_url_from_remote_actor_id( $attributed_to['ap_actor_id'] );
			if ( $url ) {
				return $url;
			}
		}

		// Legacy format: the URL is stored directly in 'id'.
		if ( isset( $attributed_to['id'] ) && $attributed_to['id'] ) {
			return $attributed_to['id'];
		}

		return null;
	}

	/**
	 * Get actor metadata from attributedTo.
	 *
	 * Returns actor metadata (name, icon, summary, preferredUsername, header) using the
	 * ActivityPub plugin's Remote_Actors API for the new format, or from legacy inline data.
	 *
	 * @param array $attributed_to The attributedTo metadata array.
	 * @return array Actor metadata with keys: url, name, icon, summary, preferredUsername, header.
	 */
	public static function get_actor_metadata_from_attributed_to( $attributed_to ) {
		$metadata = array(
			'url'               => null,
			'name'              => '',
			'icon'              => '',
			'header'            => '',
			'summary'           => '',
			'preferredUsername' => '',
		);

		if ( ! is_array( $attributed_to ) ) {
			return $metadata;
		}

		// New format: fetch from ap_actor using ActivityPub plugin API.
		if ( isset( $attributed_to['ap_actor_id'] ) && $attributed_to['ap_actor_id'] ) {
			$ap_actor_id = $attributed_to['ap_actor_id'];

			if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
				$actor = \Activitypub\Collection\Remote_Actors::get_actor( $ap_actor_id );

				if ( $actor && ! is_wp_error( $actor ) ) {
					$metadata['url'] = $actor->get_id();
					$metadata['name'] = $actor->get_name() ?? '';
					$metadata['summary'] = $actor->get_summary() ?? '';
					$metadata['preferredUsername'] = $actor->get_preferred_username() ?? '';
					$metadata['icon'] = \Activitypub\Collection\Remote_Actors::get_avatar_url( $ap_actor_id );

					$image = $actor->get_image();
					if ( $image ) {
						$metadata['header'] = \Activitypub\object_to_uri( $image );
					}

					return $metadata;
				}
			}

			// Fallback: try to get URL from the post directly.
			$url = self::get_actor_url_from_remote_actor_id( $ap_actor_id );
			if ( $url ) {
				$metadata['url'] = $url;
			}
		}

		// Legacy format: use inline data.
		if ( isset( $attributed_to['id'] ) ) {
			$metadata['url'] = $attributed_to['id'];
		}
		if ( isset( $attributed_to['name'] ) ) {
			$metadata['name'] = $attributed_to['name'];
		}
		if ( isset( $attributed_to['icon'] ) ) {
			$metadata['icon'] = $attributed_to['icon'];
		}
		if ( isset( $attributed_to['header'] ) ) {
			$metadata['header'] = $attributed_to['header'];
		}
		if ( isset( $attributed_to['summary'] ) ) {
			$metadata['summary'] = $attributed_to['summary'];
		}
		if ( isset( $attributed_to['preferredUsername'] ) ) {
			$metadata['preferredUsername'] = $attributed_to['preferredUsername'];
		}

		return $metadata;
	}

	/**
	 * Gets the external mentions user.
	 *
	 * @return     User  The external mentions user.
	 */
	public function get_external_user() {
		$external_username = apply_filters( 'friends_external_username', self::EXTERNAL_USERNAME );
		$user = User::get_by_username( $external_username );
		if ( ! $user || is_wp_error( $user ) ) {
			$user = Subscription::create( $external_username, 'subscription', home_url(), _x( 'External', 'user name', 'friends' ) );
		}

		if ( $user instanceof \WP_User && ! ( $user instanceof Subscription ) && ! is_user_member_of_blog( $user->ID, get_current_blog_id() ) ) {
			\add_user_to_blog( get_current_blog_id(), $user->ID, 'subscription' );
		}

		return $user;
	}

	private function get_external_mentions_feed() {
		require_once __DIR__ . '/activitypub/class-virtual-user-feed.php';
		$user = $this->get_external_user();
		return new Virtual_User_Feed( $user, __( 'External Mentions', 'friends' ) );
	}

	/**
	 * Set the post author as an implicit mention.
	 *
	 * @param  array  $mentions The already extracted mentions.
	 * @param string $comment_content   The comment content.
	 * @param object $wp_object The object acted upon.
	 * @return array The extracted mentions.
	 */
	public function activitypub_extract_in_reply_to_mentions( $mentions, $comment_content, $wp_object ) {
		if ( ! $wp_object || 'WP_Comment' !== get_class( $wp_object ) ) {
			return $mentions;
		}
		$post_id = $wp_object->comment_post_ID;
		$post = get_post( $post_id );
		if ( Friends::CPT !== $post->post_type ) {
			return $mentions;
		}

		$meta = get_post_meta( $post->ID, self::SLUG, true );
		$attributed_to_url = isset( $meta['attributedTo'] ) ? self::get_actor_url_from_attributed_to( $meta['attributedTo'] ) : null;
		if ( $attributed_to_url ) {
			$mentions[ $attributed_to_url ] = $attributed_to_url;
		}

		return $mentions;
	}

	public static function extract_html_mentions( $content ) {
		$tags = new \WP_HTML_Tag_Processor( $content );
		$mentions = array();
		while ( $tags->next_tag(
			array(
				'tag_name'   => 'a',
				'class_name' => 'mention',
			)
		) ) {
			$href = false;
			if ( ! in_array( 'hashtag', explode( ' ', $tags->get_attribute( 'class' ) ) ) ) {
				$href = $tags->get_attribute( 'href' );
			}
			if ( $href ) {
				$mentions[ $href ] = true;
			}
		}

		return $mentions;
	}

	public function activitypub_handled_create( $activity, $user_id, $state, $reaction ) {
		$this->log( 'ActivityPub handled create', compact( 'activity', 'user_id', 'state', 'reaction' ) );
		if ( $reaction ) {
			$this->activitypub_already_handled[ $activity['object']['id'] ] = $reaction;
		}
		return $activity;
	}

	public function handle_received_create( $activity, $user_id ) {
		if (
			\Activitypub\is_activity_public( $activity ) ||
			// Only accept messages that have the user in the "to" field.
			empty( $activity['to'] ) ||
			! in_array( \Activitypub\Collection\Actors::get_by_id( $user_id )->get_id(), (array) $activity['to'], true )
		) {
			return $this->handle_received_activity( $activity, $user_id, 'create' );
		}

		$this->handle_received_direct_message( $activity, $user_id );
	}

	public function handle_received_undo( $activity, $user_id ) {
		return $this->handle_received_activity( $activity, $user_id, 'undo' );
	}

	public function handle_received_move( $activity, $user_id ) {
		return $this->handle_received_activity( $activity, $user_id, 'move' );
	}

	public function handle_received_update( $activity, $user_id ) {
		return $this->handle_received_activity( $activity, $user_id, 'update' );
	}

	public function handle_received_delete( $activity, $user_id ) {
		return $this->handle_received_activity( $activity, $user_id, 'delete' );
	}

	public function handle_received_announce( $activity, $user_id ) {
		return $this->handle_received_activity( $activity, $user_id, 'announce' );
	}

	public function handle_received_like( $activity, $user_id ) {
		return $this->handle_received_activity( $activity, $user_id, 'like' );
	}

	/**
	 * Handles incoming ActivityPub requests.
	 *
	 * @param  array  $activity  The activity object.
	 * @param  int    $user_id The id of the local blog user.
	 * @param string $type  The type of the activity.
	 */
	public function handle_received_activity( $activity, $user_id, $type ) {
		if ( isset( $activity['object']['id'] ) && isset( $this->activitypub_already_handled[ $activity['object']['id'] ] ) ) {
			return;
		}

		// Check if this is a reply to an existing Friends post - if so, let the ActivityPub plugin handle it as a comment.
		if ( 'create' === $type && ! empty( $activity['object']['inReplyTo'] ) ) {
			$in_reply_to = $activity['object']['inReplyTo'];
			if ( is_array( $in_reply_to ) ) {
				$in_reply_to = reset( $in_reply_to );
			}
			$reply_to_post_id = Feed::url_to_postid( $in_reply_to );
			if ( $reply_to_post_id ) {
				// This is a reply to an existing Friends post - skip processing and let ActivityPub handle it as a comment.
				return false;
			}
		}

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
				'move',
				'update',
			),
			true
		) ) {
			return false;
		}

		$actor_url = $activity['actor'];
		$user_feed = false;

		// First try to find by ap_actor_id (more robust for URL variations).
		if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) && Friends::check_url( $actor_url ) ) {
			$ap_actor_post = \Activitypub\Collection\Remote_Actors::get_by_uri( $actor_url );
			if ( ! is_wp_error( $ap_actor_post ) ) {
				$user_feed = User_Feed::get_by_ap_actor_id( $ap_actor_post->ID );
			}
		}

		// Fall back to URL-based lookup.
		if ( ( ! $user_feed || is_wp_error( $user_feed ) ) && Friends::check_url( $actor_url ) ) {
			$user_feed = $this->friends_feed->get_user_feed_by_url( $actor_url );
		}

		// If both failed and URL might need resolution, try metadata lookup.
		if ( is_wp_error( $user_feed ) || ! Friends::check_url( $actor_url ) ) {
			$meta = $this->get_metadata( $actor_url );
			if ( ! $meta || is_wp_error( $meta ) || ! isset( $meta['url'] ) ) {
				$error = 'No URL found';
				if ( is_wp_error( $meta ) ) {
					$error = $meta->get_error_message();
					$error .= ' ' . print_r( $meta->get_error_data(), true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				}
				$this->log( 'Received invalid meta for ' . $actor_url . ' ' . $error, $meta );
				return false;
			}

			$actor_url = $meta['url'];
			if ( ! Friends::check_url( $actor_url ) ) {
				$this->log( 'Received invalid meta url for ' . $actor_url );
				return false;
			}

			// Try ap_actor_id lookup with resolved URL first.
			if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
				$ap_actor_post = \Activitypub\Collection\Remote_Actors::get_by_uri( $actor_url );
				if ( ! is_wp_error( $ap_actor_post ) ) {
					$user_feed = User_Feed::get_by_ap_actor_id( $ap_actor_post->ID );
				}
			}

			// Fall back to URL-based lookup with resolved URL.
			if ( ! $user_feed || is_wp_error( $user_feed ) ) {
				$user_feed = $this->friends_feed->get_user_feed_by_url( $actor_url );
			}
		}

		if ( ! $user_feed || is_wp_error( $user_feed ) ) {
			if ( isset( $activity['object']['tag'] ) && is_array( $activity['object']['tag'] ) ) {
				$my_activitypub_id = (string) $this->get_activitypub_actor( $user_id );
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
			$i = $this->friends_feed->process_incoming_feed_items( array( $item ), $user_feed );
			return $item;
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
	protected function process_incoming_activity( $type, $activity, $user_id, $user_feed ) {
		$tags = array();
		$mention_tags = array();

		// Extract mention tags from CC field - if the current user is in CC, add their mention tag.
		if ( isset( $activity['cc'] ) && is_array( $activity['cc'] ) ) {
			$my_activitypub_id = (string) $this->get_activitypub_actor( $user_id );
			if ( in_array( $my_activitypub_id, (array) $activity['cc'], true ) ) {
				$local_user = get_userdata( $user_id );
				if ( $local_user ) {
					$mention_tags[] = 'mention-' . $local_user->user_login;
				}
			}
		}

		// Extract hashtags from ActivityPub object tags.
		if ( isset( $activity['object']['tag'] ) && is_array( $activity['object']['tag'] ) ) {
			foreach ( $activity['object']['tag'] as $tag ) {
				if ( isset( $tag['type'] ) && 'Hashtag' === $tag['type'] && isset( $tag['name'] ) ) {
					$tag_name = ltrim( $tag['name'], '#' );
					$tag_name = sanitize_title( $tag_name );
					if ( ! empty( $tag_name ) ) {
						$tags[] = $tag_name;
					}
				}
			}
		}

		switch ( $type ) {
			case 'create':
				return $this->handle_incoming_create( $activity['object'], $tags, $mention_tags );
			case 'update':
				if ( isset( $activity['object']['type'] ) && 'Person' === $activity['object']['type'] ) {
					if ( ! $user_feed instanceof User_Feed ) {
						return null;
					}
					return $this->handle_incoming_update_person( $activity['object'], $user_feed );
				}
				$item = $this->handle_incoming_create( $activity['object'], $tags, $mention_tags );
				if ( isset( $activity['object']['type'] ) && 'Note' === $activity['object']['type'] ) {
					$friend_user = $user_feed->get_friend_user();
					$post_id = Feed::url_to_postid( $item->permalink );
					$message = sprintf(
						// translators: %1$s is the post URL, %2$s is the linked user display name.
						__( 'Received <a href="%1$s">post update</a> for %2$s', 'friends' ),
						$friend_user->get_local_friends_page_url( $post_id ),
						'<a href="' . esc_url( $friend_user->get_local_friends_page_url() ) . '">' . esc_html( $friend_user->display_name ) . '</a>'
					);
					$details = array();
					if ( $post_id ) {
						$_post = get_post( $post_id );
						if ( ! class_exists( 'WP_Text_Diff_Renderer_inline', false ) ) {
							require ABSPATH . WPINC . '/wp-diff.php';
						}
						$diff = new \Text_Diff( explode( 'PHP_EOL', wp_strip_all_tags( $_post->post_content ) ), explode( 'PHP_EOL', wp_strip_all_tags( $item->content ) ) );
						$renderer = new \WP_Text_Diff_Renderer_inline();
						$details['content'] = $renderer->render( $diff );
						if ( empty( $details['content'] ) ) {
							unset( $details['content'] );
						}
					}

					if ( ! empty( $details ) ) {
						$details['post_id'] = $post_id;
						$details['activity'] = $activity;
						Logging::log( 'post-update', $message, $details, self::SLUG, 0, $friend_user->ID );
					}
				}
				return $item;
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
			case 'move':
				return $this->handle_incoming_move( $activity, $user_feed );
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
	 * @param      array $tags         Optional hashtags to attach to the item.
	 * @param      array $mention_tags Optional mention tags to attach to the item.
	 */
	private function handle_incoming_create( $activity, $tags = array(), $mention_tags = array() ) {
		$permalink = $activity['id'];
		if ( isset( $activity['url'] ) ) {
			if ( is_array( $activity['url'] ) ) {
				if ( empty( $activity['attachment'] ) ) {
					$activity['attachment'] = $activity['url'];
				}
			} elseif ( is_string( $activity['url'] ) ) {
				$permalink = $activity['url'];
			}
		}

		$data = array(
			'permalink'    => $permalink,
			'content'      => $activity['content'],
			'post_format'  => $this->map_type_to_post_format( $activity['type'] ),
			'date'         => $activity['published'],
			'_external_id' => $activity['id'],
			self::SLUG     => array(),
		);

		// Set author for all posts from attributedTo.
		if ( isset( $activity['attributedTo'] ) ) {
			$meta = $this->get_metadata( $activity['attributedTo'] );
			$this->log( 'Attributed to ' . $activity['attributedTo'], compact( 'meta' ) );

			if ( $meta && ! is_wp_error( $meta ) ) {
				if ( isset( $meta['name'] ) ) {
					$data['author'] = $meta['name'];
				} elseif ( isset( $meta['preferredUsername'] ) ) {
					$data['author'] = $meta['preferredUsername'];
				}

				// Store attributedTo for avatar/author URL display.
				$actor_url = isset( $meta['id'] ) ? $meta['id'] : $activity['attributedTo'];

				if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
					$actor_post = \Activitypub\Collection\Remote_Actors::fetch_by_uri( $actor_url );
					if ( ! is_wp_error( $actor_post ) && $actor_post instanceof \WP_Post ) {
						$data[ self::SLUG ]['attributedTo'] = array( 'ap_actor_id' => $actor_post->ID );
					} else {
						$data[ self::SLUG ]['attributedTo'] = array( 'id' => $actor_url );
					}
				} else {
					$data[ self::SLUG ]['attributedTo'] = array( 'id' => $actor_url );
				}
			}
		}

		if ( isset( $activity['reblog'] ) && $activity['reblog'] ) {
			$data[ self::SLUG ]['reblog'] = $activity['reblog'];
		}

		if ( isset( $activity['application'] ) && $activity['application'] ) {
			$data[ self::SLUG ]['application'] = $activity['application'];
		}

		if ( ! empty( $activity['attachment'] ) ) {
			$attachments = array();
			foreach ( $activity['attachment'] as $attachment ) {
				if ( ! isset( $attachment['type'] ) || ! isset( $attachment['mediaType'] ) ) {
					continue;
				}
				if ( ! in_array( $attachment['type'], array( 'Document', 'Image', 'Link' ), true ) ) {
					continue;
				}

				if ( empty( $attachment['url'] ) ) {
					if ( ! empty( $attachment['href'] ) ) {
						$attachment['url'] = $attachment['href'];
					}
				}
				if ( 'application/x-mpegURL' === $attachment['mediaType'] ) {
					if ( ! empty( $attachment['tag'] ) ) {
						$videos = array();
						foreach ( $attachment['tag'] as $tag ) {
							if ( strpos( $tag['mediaType'], 'video/' ) === false ) {
								continue;
							}
							if ( empty( $tag['rel'] ) ) {
								$videos[ $tag['height'] ] = $tag;
							}
						}

						if ( ! empty( $videos ) ) {
							if ( isset( $videos[720] ) ) {
								$video = $videos[720];
							} elseif ( isset( $videos[480] ) ) {
								$video = $videos[480];
							} elseif ( isset( $videos[360] ) ) {
								$video = $videos[360];
							} else {
								$video = reset( $videos );
							}

							$data['content'] .= PHP_EOL;
							$data['content'] .= '<!-- wp:video -->';
							$data['content'] .= '<figure class="wp-block-video"><video controls="controls"';
							if ( isset( $video['width'] ) && $video['height'] ) {
								$data['content'] .= ' width="' . esc_attr( $video['width'] ) . '"';
								$data['content'] .= ' height="' . esc_attr( $video['height'] ) . '"';
							}
							if ( isset( $activity['icon'] ) && ! empty( $activity['icon'] ) ) {
								if ( is_array( $activity['icon'] ) ) {
									$poster = null;
									// choose the larger icon.
									foreach ( $activity['icon'] as $icon ) {
										if ( isset( $icon['width'] ) && isset( $icon['height'] ) && isset( $icon['url'] ) && ( ! $poster || ( $icon['width'] > $poster['width'] && $icon['height'] > $poster['height'] ) ) ) {
											$poster = $icon;
										}
									}
								} else {
									$poster = $activity['icon'];
								}
								if ( $poster ) {
									$data['content'] .= ' poster="' . esc_url( $poster['url'] ) . '"';
								}
							}
							$data['content'] .= ' src="' . esc_url( $video['href'] ) . '" type="' . esc_attr( $video['mediaType'] ) . '"';
							if ( isset( $activity['duration'] ) ) {
								$data['content'] .= ' duration="' . esc_attr( $activity['duration'] ) . '"';
							}
							$data['content'] .= '/>';
							if ( ! empty( $activity['subtitleLanguage'] ) ) {
								foreach ( $activity['subtitleLanguage'] as $subtitle ) {
									$type = '';
									if ( '.vtt' === substr( $subtitle['url'], -4 ) ) {
										$type = 'text/vtt';
									}
									$data['content'] .= '<track src="' . esc_url( $subtitle['url'] ) . '" kind="subtitles" srclang="' . esc_attr( $subtitle['identifier'] ) . '" type="' . esc_attr( $type ) . '" label="' . esc_attr( $subtitle['name'] ) . '" />';
								}
							}
							$data['content'] .= '</video>';
							if ( ! empty( $attachment['name'] ) ) {
								$data['content'] .= '<figcaption class="wp-element-caption">' . esc_html( $attachment['name'] ) . '</figcaption>';
							}
							$data['content'] .= '</figure>';
							$data['content'] .= '<!-- /wp:video -->';

						}
					}
				} elseif ( strpos( $attachment['mediaType'], 'image/' ) === 0 ) {
					$data['content'] .= PHP_EOL;
					$data['content'] .= '<!-- wp:image -->';
					$data['content'] .= '<p><img src="' . esc_url( $attachment['url'] ) . '"';
					if ( isset( $attachment['width'] ) && $attachment['height'] ) {
						$data['content'] .= ' width="' . esc_attr( $attachment['width'] ) . '"';
						$data['content'] .= ' height="' . esc_attr( $attachment['height'] ) . '"';
					}
					$data['content'] .= ' class="size-full" /></p>';
					$data['content'] .= '<!-- /wp:image -->';
				} elseif ( strpos( $attachment['mediaType'], 'video/' ) === 0 ) {
					$data['content'] .= PHP_EOL;
					$data['content'] .= '<!-- wp:video -->';
					$data['content'] .= '<figure class="wp-block-video"><video src="' . esc_url( $attachment['url'] ) . '" type="' . esc_attr( $attachment['mediaType'] ) . '">';
					if ( ! empty( $attachment['name'] ) ) {
						$data['content'] .= '<figcaption class="wp-element-caption">' . esc_html( $attachment['name'] ) . '</figcaption>';
					}
					$data['content'] .= '</figure>';
					$data['content'] .= '<!-- /wp:video -->';
				}
			}
		}

		if ( ! empty( $tags ) && is_array( $tags ) ) {
			$data['friend_tags'] = $tags;
		}

		// These are separate so that they won't be faked.
		if ( ! empty( $mention_tags ) && is_array( $mention_tags ) ) {
			$data['friend_mention_tags'] = $mention_tags;
		}

		// Store mention URLs for reply filtering in modify_incoming_item.
		if ( ! empty( $activity['tag'] ) && is_array( $activity['tag'] ) ) {
			$mention_urls = array();
			foreach ( $activity['tag'] as $tag ) {
				if ( isset( $tag['type'] ) && 'Mention' === $tag['type'] && isset( $tag['href'] ) ) {
					$mention_urls[] = $tag['href'];
				}
			}
			if ( ! empty( $mention_urls ) ) {
				$data['_mention_urls'] = $mention_urls;
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
	 * We received a update of a person, handle it.
	 *
	 * @param      array     $activity     The object from ActivityPub.
	 * @param User_Feed $user_feed The user feed.
	 */
	private function handle_incoming_update_person( $activity, User_Feed $user_feed ) {
		$friend_user = $user_feed->get_friend_user();
		$this->log( 'Received person update for ' . $friend_user->user_login, compact( 'activity' ) );

		$message = sprintf(
			// translators: %s is the user login.
			__( 'Received person update for %s', 'friends' ),
			'<a href="' . esc_url( $friend_user->get_local_friends_page_url() ) . '">' . esc_html( $friend_user->display_name ) . '</a>'
		);

		$details = array();

		if ( ! empty( $activity['summary'] ) && $friend_user->description !== $activity['summary'] ) {
			if ( ! class_exists( 'WP_Text_Diff_Renderer_inline', false ) ) {
				require ABSPATH . WPINC . '/wp-diff.php';
			}
			$summary = wp_encode_emoji( $activity['summary'] );
			$diff = new \Text_Diff( explode( PHP_EOL, $friend_user->description ), explode( PHP_EOL, $summary ) );
			$renderer = new \WP_Text_Diff_Renderer_inline();
			$details['summary'] = $renderer->render( $diff );
			if ( empty( $details['summary'] ) ) {
				unset( $details['summary'] );
			} else {
				$message .= ' ' . __( 'Updated description.', 'friends' );
			}

			$friend_user->description = $summary;
		}
		if ( ! empty( $activity['icon']['url'] ) && $friend_user->get_avatar_url() !== $activity['icon']['url'] ) {
			$details['old-icon'] = '<img src="' . esc_url( $friend_user->get_avatar_url() ) . '" style="max-height: 32px; max-width: 32px" />';
			$details['new-icon'] = '<img src="' . esc_url( $activity['icon']['url'] ) . '" style="max-height: 32px; max-width: 32px" />';
			$friend_user->update_user_icon_url( $activity['icon']['url'] );
			$message .= ' ' . __( 'Updated icon.', 'friends' );
		}
		$friend_user->save();

		if ( ! empty( $details ) ) {
			$details['object'] = $activity;
			Logging::log( 'user-update', $message, $details, self::SLUG, 0, $friend_user->ID );
		}

		return null; // No feed item to submit.
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
		$response = \Activitypub\safe_remote_get( $url );
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

		return $this->handle_incoming_create( $object, array(), array() );
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
			$host = wp_parse_url( $meta['id'], PHP_URL_HOST );
			return '@' . $meta['preferredUsername'] . '@' . $host;
		}
		return $display_name;
	}

	public function author_avatar_url( $avatar_url, $friend_user, $post_id ) {
		$meta = get_post_meta( $post_id, self::SLUG, true );
		if ( ! $meta || ! isset( $meta['attributedTo'] ) ) {
			return $avatar_url;
		}
		$actor_metadata = self::get_actor_metadata_from_attributed_to( $meta['attributedTo'] );
		if ( ! empty( $actor_metadata['icon'] ) ) {
			return $actor_metadata['icon'];
		}
		return $avatar_url;
	}

	/**
	 * Filter the author URL for a post.
	 *
	 * @param string $author_url The author URL.
	 * @param User   $friend_user The friend user.
	 * @param int    $post_id The post ID.
	 * @return string The filtered author URL.
	 */
	public function author_url( $author_url, $friend_user, $post_id ) {
		// Only override the URL for the External user.
		// Regular subscriptions should link to their local friends page.
		if ( self::EXTERNAL_USERNAME !== $friend_user->user_login ) {
			return $author_url;
		}

		$meta = get_post_meta( $post_id, self::SLUG, true );
		if ( ! $meta || ! isset( $meta['attributedTo'] ) ) {
			return $author_url;
		}
		$actor_metadata = self::get_actor_metadata_from_attributed_to( $meta['attributedTo'] );
		if ( ! empty( $actor_metadata['url'] ) ) {
			return $actor_metadata['url'];
		}
		return $author_url;
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

	public function handle_incoming_move( $activity, User_Feed $user_feed ) {
		$old_url = $activity['object'];
		if ( $user_feed->get_url() !== $old_url ) {
			$this->log( 'Could not determine the right feed to be moved. Looking for ' . $old_url . ', got ' . $user_feed->get_url() );
			return false;
		}

		$feed = array(
			'url'         => $activity['target'],
			'mime-type'   => $user_feed->get_mime_type(),
			'title'       => $user_feed->get_title(),
			'parser'      => $user_feed->get_parser(),
			'post-format' => $user_feed->get_post_format(),
			'active'      => $user_feed->is_active(),
		);
		$this->log( 'Received Move from ' . $old_url . ' to ' . $feed['url'] );

		// Similar as in process_admin_edit_friend_feeds.
		if ( $user_feed->get_url() !== $feed['url'] ) {
			$friend = $user_feed->get_friend_user();
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

			// Link the new feed to the ap_actor post for the new URL.
			if ( $new_feed instanceof User_Feed && class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
				$actor_post = \Activitypub\Collection\Remote_Actors::fetch_by_uri( $feed['url'] );
				if ( ! is_wp_error( $actor_post ) && $actor_post instanceof \WP_Post ) {
					$new_feed->set_ap_actor_id( $actor_post->ID );
				}
			}

			// Since the URL has changed, the above will create a new feed, therefore we need to delete the old one.
			$user_feed->delete();

			if ( is_wp_error( $new_feed ) ) {
				do_action( 'friends_process_feed_item_submit_error', $new_feed, $feed );
				return $new_feed;
			}

			do_action( 'friends_process_feed_item_submit', $new_feed, $feed );
			$new_feed->update_last_log(
				sprintf(
					// translators: %s is the old URL.
					__( 'Moved from old URL: %s', 'friends' ),
					$user_feed->get_url()
				)
			);

			$message = sprintf(
				// translators: %s is the new URL.
				__( '%1$s moved to a new URL: %2$s', 'friends' ),
				'<a href="' . esc_url( $old_url ) . '">' . esc_html( $feed['title'] ) . '</a>',
				'<a href="' . esc_url( $new_feed->get_url() ) . '">' . esc_html( $new_feed->get_title() ) . '</a>'
			);

			Logging::log( 'feed-move', $message, $activity, self::SLUG, 0, $friend->ID );

			return $new_feed;
		} else {
			$this->log( 'Move URL didn\'t change, old: ' . $old_url . ', new: ' . $feed['url'] );
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

		$queued = \Activitypub\follow( $user_feed->get_url(), self::get_activitypub_actor_id( null ) );

		if ( $queued ) {
			$user_feed->update_last_log( __( 'Queued follow request.', 'friends' ) );
		}

		return $queued;
	}

	/**
	 * Handle Follow activities added to the ActivityPub outbox.
	 *
	 * When someone follows a user via the ActivityPub plugin (not through Friends),
	 * this method auto-creates a corresponding Friends subscription.
	 *
	 * @param int    $outbox_activity_id The outbox activity post ID.
	 * @param object $activity           The Activity object.
	 * @param int    $user_id            The user ID.
	 * @param string $content_visibility The content visibility.
	 */
	public function handle_outbox_follow( $outbox_activity_id, $activity, $user_id, $content_visibility ) {
		// Only process Follow activities.
		if ( ! $activity || 'Follow' !== $activity->get_type() ) {
			return;
		}

		$actor_url = $activity->get_object();
		if ( empty( $actor_url ) || ! is_string( $actor_url ) ) {
			return;
		}

		// Check if a Friends subscription already exists for this actor.
		$existing_feed = User_Feed::get_by_url( $actor_url );
		if ( $existing_feed instanceof User_Feed ) {
			// Already have a subscription, ensure it's active.
			if ( ! $existing_feed->get_active() ) {
				$existing_feed->update_metadata( 'active', true );
			}
			return;
		}

		// Create a new Friends subscription for this actor.
		$this->create_friend_subscription_from_actor( $actor_url );
	}

	/**
	 * Create a Friends subscription from an ActivityPub actor URL.
	 *
	 * @param string $actor_url The ActivityPub actor URL.
	 * @return Subscription|\WP_Error The created subscription or error.
	 */
	public function create_friend_subscription_from_actor( $actor_url ) {
		$meta = $this->get_metadata( $actor_url );
		if ( is_wp_error( $meta ) ) {
			return $meta;
		}

		if ( ! is_array( $meta ) || empty( $meta['preferredUsername'] ) ) {
			return new \WP_Error( 'invalid_actor', 'Invalid actor metadata' );
		}

		// Generate a unique user login.
		$host = wp_parse_url( $actor_url, PHP_URL_HOST );
		$user_login = sanitize_title( $meta['preferredUsername'] . '.' . $host );

		// Get the ap_actor post ID.
		$ap_actor_id = null;
		if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			$actor_post = \Activitypub\Collection\Remote_Actors::fetch_by_uri( $actor_url );
			if ( ! is_wp_error( $actor_post ) && $actor_post instanceof \WP_Post ) {
				$ap_actor_id = $actor_post->ID;
			}
		}

		// Check if user already exists.
		$existing_user = get_user_by( 'login', $user_login );
		if ( $existing_user ) {
			// User exists, try to get their feeds.
			$user = User::get_user_by_id( $existing_user->ID );
			if ( $user ) {
				// Add the feed if it doesn't exist.
				$feeds = $user->get_active_feeds();
				foreach ( $feeds as $feed ) {
					if ( $feed->get_url() === $actor_url ) {
						// Ensure ap_actor_id is linked.
						if ( $ap_actor_id && ! $feed->get_ap_actor_id() ) {
							$feed->set_ap_actor_id( $ap_actor_id );
						}
						return $user; // Already have this feed.
					}
				}
				// Add the feed.
				$user_feed = $user->save_feed(
					$actor_url,
					array(
						'parser' => self::SLUG,
						'active' => true,
						'title'  => $meta['name'] ?? $meta['preferredUsername'],
					)
				);
				if ( $user_feed instanceof User_Feed && $ap_actor_id ) {
					$user_feed->set_ap_actor_id( $ap_actor_id );
				}
				return $user;
			}
		}

		// Create new subscription.
		$subscription = Subscription::create(
			$user_login,
			'subscription',
			$actor_url,
			$meta['name'] ?? $meta['preferredUsername'],
			isset( $meta['icon']['url'] ) ? $meta['icon']['url'] : null,
			$meta['summary'] ?? null
		);

		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		// Add the feed.
		$user_feed = $subscription->save_feed(
			$actor_url,
			array(
				'parser' => self::SLUG,
				'active' => true,
				'title'  => $meta['name'] ?? $meta['preferredUsername'],
			)
		);

		// Link the feed to the ap_actor post.
		if ( $user_feed instanceof User_Feed && $ap_actor_id ) {
			$user_feed->set_ap_actor_id( $ap_actor_id );
		}

		return $subscription;
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

		$queued = \Activitypub\unfollow( $user_feed->get_url(), self::get_activitypub_actor_id( null ) );

		if ( $queued ) {
			$user_feed->update_last_log( __( 'Queued unfollow request.', 'friends' ) );
		}

		return $queued;
	}

	/**
	 * Extract the mentions from the post_content.
	 *
	 * @param array  $mentions The already found mentions.
	 * @param string $post_content The post content.
	 * @return mixed The discovered mentions.
	 */
	public function activitypub_extract_mentions( $mentions, $post_content ) {
		// Find all @mentions in the content.
		if ( ! preg_match_all( '/@([a-zA-Z0-9_.-]+(?:@[a-zA-Z0-9.-]+)?)\b/i', wp_strip_all_tags( $post_content ), $matches ) ) {
			return $mentions;
		}

		foreach ( array_unique( $matches[1] ) as $handle ) {
			$url = $this->resolve_mention_to_url( $handle );
			if ( $url ) {
				$mentions[ '@' . $handle ] = $url;
			}
		}

		return $mentions;
	}

	/**
	 * Resolve a mention handle to an ActivityPub URL.
	 *
	 * @param string $handle The handle (e.g., "bob" or "bob@mastodon.social").
	 * @return string|null The ActivityPub URL or null if not found.
	 */
	private function resolve_mention_to_url( $handle ) {
		// Check if it's a full handle like user@domain.
		if ( strpos( $handle, '@' ) !== false ) {
			return $this->resolve_full_handle( $handle );
		}

		$sanitized_handle = sanitize_title( $handle );

		// Check Friends/Subscriptions first (they take precedence for outgoing mentions).
		$url = $this->resolve_friend_by_slug( $sanitized_handle );
		if ( $url ) {
			return $url;
		}

		// Check local users.
		$local_user = get_user_by( 'slug', $sanitized_handle );
		if ( ! $local_user ) {
			$local_user = get_user_by( 'login', $handle );
		}
		if ( $local_user ) {
			if ( function_exists( '\Activitypub\get_rest_url_by_path' ) ) {
				return \Activitypub\get_rest_url_by_path( 'actors/' . $local_user->ID );
			}
			return get_author_posts_url( $local_user->ID, $local_user->user_nicename );
		}

		// Check if it's the blog name.
		if ( sanitize_title( get_bloginfo( 'name' ) ) === $sanitized_handle ) {
			if ( function_exists( '\Activitypub\get_rest_url_by_path' ) ) {
				return \Activitypub\get_rest_url_by_path( 'actors/0' );
			}
			return get_bloginfo( 'url' );
		}

		return null;
	}

	/**
	 * Find a friend's ActivityPub URL by their sanitized slug.
	 *
	 * @param string $slug The sanitized slug to search for.
	 * @return string|null The ActivityPub URL or null if not found.
	 */
	private function resolve_friend_by_slug( $slug ) {
		$feeds = User_Feed::get_by_parser( 'activitypub' );
		foreach ( $feeds as $feed ) {
			$user = $feed->get_friend_user();
			if ( ! $user ) {
				continue;
			}

			$user_slug = $user->user_nicename;
			if ( ! $user_slug ) {
				$user_slug = $user->user_login;
			}

			if ( sanitize_title( $user_slug ) === $slug ) {
				return $feed->get_url();
			}
		}

		return null;
	}

	/**
	 * Resolve a full Mastodon handle to an ActivityPub URL.
	 *
	 * @param string $handle The full handle (e.g., "bob@mastodon.social").
	 * @return string|null The ActivityPub URL or null if not found.
	 */
	private function resolve_full_handle( $handle ) {
		if ( ! class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			return null;
		}

		$actor = \Activitypub\Collection\Remote_Actors::fetch_by_acct( $handle );

		if ( ! is_wp_error( $actor ) ) {
			return $actor->guid;
		}

		return null;
	}

	/**
	 * Add the follows for the main user.
	 *
	 * @param array                   $follow_list The array of following urls.
	 * @param \Activitypub\Model\User $user        The user object.
	 *
	 * @return array The array of following urls.
	 */
	public function activitypub_rest_following( $follow_list, $user ) {
		if ( Friends::get_main_friend_user_id() === $user->get__id() ) {
			foreach ( User_Feed::get_by_parser( self::SLUG ) as $user_feed ) {
				$follow_list[] = array(
					'id'   => $user_feed->get_url(),
					'name' => $user_feed->get_title(),
				);
			}
		}

		return $follow_list;
	}

	public function activitypub_interactions_follow_url( $redirect_uri, $uri ) {
		if ( ! $redirect_uri ) {
			$redirect_uri = add_query_arg( 'url', $uri, self_admin_url( 'admin.php?page=add-friend' ) );
		}
		return $redirect_uri;
	}

	private function show_message_on_frontend( $message, $error = null ) {
		if ( is_wp_error( $error ) ) {
			$error = $error->get_error_message();
		}
		add_action(
			'friends_after_header',
			function () use ( $message, $error ) {
				Friends::template_loader()->get_template_part(
					'frontend/error-message',
					null,
					array(
						'message' => $message,
						'error'   => $error,
					)
				);
			}
		);
	}

	public function cache_reply_to_boost() {
		$url = false;
		$append_to_redirect = '';

		// The ignores are not necessary now but when https://github.com/WordPress/WordPress-Coding-Standards/issues/2299 comes into effect.
		$in_reply_to = filter_input( INPUT_GET, 'in_reply_to', FILTER_SANITIZE_URL ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$boost = filter_input( INPUT_GET, 'boost', FILTER_SANITIZE_URL ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $in_reply_to ) {
			$url = $in_reply_to;
			$append_to_redirect .= '#comment';
		} elseif ( $boost ) {
			$url = $boost;
		}

		if ( ! $url ) {
			return;
		}

		$post_id = $this->cache_url( $url );
		if ( is_wp_error( $post_id ) ) {
			// show_message_on_frontend was called inside cache_url.
			return;
		}

		if ( ! $post_id ) {
			$this->show_message_on_frontend(
				sprintf(
					// translators: %s is a URl.
					__( 'Could not retrieve URL %s', 'friends' ),
					'<a href="' . esc_attr( $url ) . '">' . Friends::url_truncate( $url ) . '</a>'
				)
			);
			return;
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}
		$user = User::get_post_author( $post );
		wp_safe_redirect( $user->get_local_friends_page_url( $post_id ) . $append_to_redirect );
		exit;
	}

	public function cache_url( $url ) {
		$post_id = apply_filters( 'friends_cache_url_post_id', false, $url );
		if ( ! $post_id ) {
			$user = $this->get_external_user();
			$user_feed = $this->get_external_mentions_feed();
			$response = \Activitypub\safe_remote_get( $url );
			if ( \is_wp_error( $response ) ) {
				$this->show_message_on_frontend(
					sprintf(
						// translators: %s is a URl.
						__( 'Could not retrieve URL %s', 'friends' ),
						'<a href="' . esc_attr( $url ) . '">' . Friends::url_truncate( $url ) . '</a>'
					),
					$response
				);
				return $response;
			}
			$json = \wp_remote_retrieve_body( $response );
			$object = \json_decode( $json, true );
			if ( ! $object ) {
				$this->log( 'Received invalid json', compact( 'json' ) );
				return false;
			}
			$this->log( 'Received response', compact( 'url', 'object' ) );
			$item = $this->handle_incoming_create( $object, array(), array() );
			if ( $item instanceof Feed_Item ) {
				$new_posts = $this->friends_feed->process_incoming_feed_items( array( $item ), $user_feed );
				if ( 1 === count( $new_posts ) ) {
					$post_id = reset( $new_posts );
				} else {
					$post_id = Feed::url_to_postid( $url );
					if ( is_null( $post_id ) ) {
						$post_id = Feed::url_to_postid( $item->permalink );
					}
				}
			}
		}
		return $post_id;
	}

	public function check_url_to_postid( $post_id, $url ) {
		if ( ! $post_id ) {
			$post_id = \Friends\Feed::url_to_postid( $url );
		}
		return $post_id;
	}

	/**
	 * Resolve a URL to a Friends post ID for ActivityPub comment handling.
	 *
	 * This allows the ActivityPub plugin to create comments on friend_post_cache posts
	 * instead of the Friends plugin creating new posts for replies.
	 *
	 * @param int    $post_id  The post ID (0 if not found).
	 * @param string $url      The target URL being resolved.
	 * @param array  $activity The activity object.
	 * @return int The post ID.
	 */
	public function activitypub_comment_post_id( $post_id, $url, $activity ) {
		if ( ! $post_id ) {
			$post_id = \Friends\Feed::url_to_postid( $url );
		}
		return $post_id;
	}

	public function the_content( $the_content ) {
		if ( ! Friends::on_frontend() ) {
			return $the_content;
		}

		// replace all links in <a href="mention hashtag"> with /friends/tag/tagname using the WP_HTML_Tag_Processor.
		$processor = new \WP_HTML_Tag_Processor( $the_content );
		while ( $processor->next_tag( array( 'tag_name' => 'a' ) ) ) {
			if ( ! $processor->get_attribute( 'href' ) ) {
				continue;
			}
			if ( ! $processor->get_attribute( 'class' ) || false === strpos( $processor->get_attribute( 'class' ), 'tag' ) ) {
				// Also consider URLs that contain the word hashtag like https://twitter.com/hashtag/WordPress.
				if ( false === strpos( $processor->get_attribute( 'href' ), '/hashtag/' ) ) {
					continue;
				}
			}
			$href = $processor->get_attribute( 'href' );
			$path_parts = explode( '/', rtrim( wp_parse_url( $href, PHP_URL_PATH ), '/' ) );
			$tag = array_pop( $path_parts );
			$processor->set_attribute( 'href', '/friends/tag/' . sanitize_title_with_dashes( $tag ) . '/' );
			$processor->set_attribute( 'original-href', $href );
		}

		$the_content = $processor->get_updated_html();

		return $the_content;
	}

	public function activitypub_save_settings( User $friend ) {
		if ( ! isset( $_POST['_wpnonce'] ) || wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'edit-friend-feeds-' . $friend->user_login ) ) {
			return;
		}

		if ( isset( $_POST['friends_show_replies'] ) && boolval( $_POST['friends_show_replies'] ) ) {
			$friend->update_user_option( 'activitypub_friends_show_replies', '1' );
		} else {
			$friend->delete_user_option( 'activitypub_friends_show_replies' );
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
	public function modify_incoming_item( Feed_Item $item, ?User_Feed $feed = null, ?User $friend_user = null ) {
		if ( ! $feed || 'activitypub' !== $feed->get_parser() || ! $friend_user ) {
			return $item;
		}

		$external_user = $this->get_external_user();
		$is_external_user = get_class( $external_user ) === get_class( $friend_user ) && $external_user->get_object_id() === $friend_user->get_object_id();
		if (
			! $is_external_user &&
			// Don't hide mentions for the external mentions user.
			! $friend_user->get_user_option( 'activitypub_friends_show_replies' )
		) {
			$plain_text_content = \wp_strip_all_tags( $item->post_content );

			// Check if post starts with a mention (it's a reply).
			if ( ! preg_match( '/^@[a-zA-Z0-9_.-]+/', $plain_text_content ) ) {
				// Not a reply, let it through.
				return $item;
			}

			// Check if any mentioned actor is known.
			$mention_urls = $item->_mention_urls ?? array();
			if ( ! $this->has_known_mention( $mention_urls, $friend_user ) ) {
				$item->_feed_rule_transform = array(
					'post_status' => 'trash',
				);
			}
		}

		return $item;
	}

	/**
	 * Check if any mention URL is a known actor.
	 *
	 * @param array $mention_urls The mention URLs from the activity.
	 * @param User  $friend_user  The friend user.
	 * @return bool True if any mention is known.
	 */
	private function has_known_mention( array $mention_urls, User $friend_user ) {
		foreach ( $mention_urls as $mention_url ) {
			// Check if it's a local user.
			if ( $this->is_local_actor_url( $mention_url ) ) {
				return true;
			}

			// Check Friends user feeds.
			$user_feed = $this->friends_feed->get_user_feed_by_url( $mention_url );
			if ( $user_feed && ! is_wp_error( $user_feed ) ) {
				return true;
			}

			// Check ActivityPub Remote_Actors.
			if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
				$remote_actor = \Activitypub\Collection\Remote_Actors::get_by_uri( $mention_url );
				if ( ! is_wp_error( $remote_actor ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a URL is a local actor (local WordPress user or blog).
	 *
	 * @param string $url The URL to check.
	 * @return bool True if it's a local actor.
	 */
	private function is_local_actor_url( $url ) {
		$site_url = get_bloginfo( 'url' );

		// Must be on this site.
		if ( strpos( $url, $site_url ) !== 0 ) {
			return false;
		}

		// Check if it matches the blog actor.
		if ( function_exists( '\Activitypub\get_rest_url_by_path' ) ) {
			$blog_actor = \Activitypub\get_rest_url_by_path( 'actors/0' );
			if ( $url === $blog_actor ) {
				return true;
			}
		}

		// Check local users.
		$local_users = get_users( array( 'fields' => array( 'ID' ) ) );
		foreach ( $local_users as $local_user ) {
			if ( function_exists( '\Activitypub\get_rest_url_by_path' ) ) {
				$actor_url = \Activitypub\get_rest_url_by_path( 'actors/' . $local_user->ID );
				if ( $url === $actor_url ) {
					return true;
				}
			}
			// Also check author posts URL.
			if ( get_author_posts_url( $local_user->ID ) === $url ) {
				return true;
			}
		}

		return false;
	}

	public function friends_potential_avatars( $avatars, User $friend_user ) {
		foreach ( $friend_user->get_active_feeds() as $user_feed ) {
			if ( 'activitypub' === $user_feed->get_parser() ) {
				// Try to get avatar from locally cached ap_actor data to avoid network requests.
				$ap_actor_id = $user_feed->get_ap_actor_id();
				$avatar_url = null;
				$title = $user_feed->get_title();

				if ( $ap_actor_id && class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
					$avatar_url = \Activitypub\Collection\Remote_Actors::get_avatar_url( $ap_actor_id );
					$actor_post = get_post( $ap_actor_id );
					if ( $actor_post && $actor_post->post_title ) {
						$title = $actor_post->post_title;
					}
				}

				if ( $avatar_url ) {
					$avatars[ $avatar_url ] = sprintf(
						// translators: %s is a username.
						__( 'Avatar of %s', 'friends' ),
						$title
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
		$attributed_to_url = isset( $meta['attributedTo'] ) ? self::get_actor_url_from_attributed_to( $meta['attributedTo'] ) : null;
		if ( $attributed_to_url ) {
			return $attributed_to_url;
		}

		$feed_url = get_post_meta( $post->ID, 'feed_url', true );
		if ( $feed_url ) {
			return $feed_url;
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
	 * Prepare to like the post via a scheduled event.
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
		$user_feed = User_Feed::get_by_url( $author_url );
		if ( version_compare( ACTIVITYPUB_PLUGIN_VERSION, '5.3.0', '>=' ) ) {
			$outbox_activity_id = \Activitypub\add_to_outbox( $external_post_id, 'Like', get_current_user_id(), ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );
			if ( ! $outbox_activity_id ) {
				if ( $user_feed instanceof User_Feed ) {
					$user_feed->update_last_log( __( 'Like failed.', 'friends' ) );
				}
				return false;
			}
			if ( $user_feed instanceof User_Feed ) {
				$user_feed->update_last_log( __( 'Sent like.', 'friends' ) );
			}
			update_post_meta( $post->ID, 'ap_outbox_like_id', $outbox_activity_id );
			return true;
		}

		$queued = $this->queue(
			'friends_feed_parser_activitypub_like',
			array( $author_url, $external_post_id, get_current_user_id() ),
			'friends_feed_parser_activitypub_unlike'
		);

		if ( $queued && $user_feed instanceof User_Feed ) {
			$user_feed->update_last_log( __( 'Queued like request.', 'friends' ) );
		}

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
		$user_id = $this->get_activitypub_actor_id( $user_id );
		$actor = $this->get_activitypub_actor( $user_id );

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( $type );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( $actor );
		$activity->set_object( $external_post_id );
		$activity->set_id( $actor . '#like-' . \preg_replace( '~^https?://~', '', $external_post_id ) );
		$activity->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', time() ) );

		$json = $activity->to_json();

		if ( version_compare( ACTIVITYPUB_PLUGIN_VERSION, '5.2.0', '>=' ) ) {
			$inboxes = \Activitypub\Collection\Followers::get_inboxes_for_activity( $json, $actor->get__id(), 500 );
		} else {
			$inboxes = apply_filters( 'activitypub_send_to_inboxes', array(), $user_id, $activity );
			$inboxes = array_unique( $inboxes );
		}

		if ( empty( $inboxes ) ) {
			$message = sprintf(
				// translators: %s is the URL of the post.
				__( 'Like failed for %s', 'friends' ),
				'<a href="' . esc_url( $url ) . '">' . $url . '</a>'
			);

			$details = array(
				'url'   => $url,
				'error' => __( 'No inboxes to send to.', 'friends' ),
			);

			Logging::log( 'like-failed', $message, $details, self::SLUG, $user_id );
			return;
		}

		$report = array();
		foreach ( $inboxes as $inbox ) {
			$response = \Activitypub\safe_remote_post( $inbox, $json, $user_id );
			$report[ $inbox ] = wp_remote_retrieve_response_message( $response );
		}

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
		$type = 'like';
		$message = sprintf(
			// translators: %s is the URL of the post.
			__( 'Liked %s', 'friends' ),
			'<a href="' . esc_url( $external_post_id ) . '">' . $external_post_id . '</a>'
		);
		$details = array(
			'actor'   => $actor,
			'url'     => $external_post_id,
			'inboxes' => $report,
		);

		Logging::log( 'like', $message, $details, self::SLUG, $user_id );
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
		$user_feed = User_Feed::get_by_url( $author_url );
		if ( version_compare( ACTIVITYPUB_PLUGIN_VERSION, '5.3.0', '>=' ) ) {
			$outbox_activity_id = get_post_meta( $post->ID, 'ap_outbox_like_id', true );
			if ( ! $outbox_activity_id ) {
				if ( $user_feed instanceof User_Feed ) {
					$user_feed->update_last_log( __( 'Unlike failed.', 'friends' ) );
				}
				return false;
			}
			if ( $user_feed instanceof User_Feed ) {
				$user_feed->update_last_log( __( 'Sent unlike request.', 'friends' ) );
			}
			\Activitypub\Collection\Outbox::undo( $outbox_activity_id );
			return true;
		}
		$queued = $this->queue(
			'friends_feed_parser_activitypub_unlike',
			array( $author_url, $external_post_id, get_current_user_id() ),
			'friends_feed_parser_activitypub_like'
		);

		if ( $queued && $user_feed instanceof User_Feed ) {
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
		$user_id = $this->get_activitypub_actor_id( $user_id );
		$actor = $this->get_activitypub_actor( $user_id );

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

		$json = $activity->to_json();
		if ( version_compare( ACTIVITYPUB_PLUGIN_VERSION, '5.2.0', '>=' ) ) {
			$inboxes = \Activitypub\Collection\Followers::get_inboxes_for_activity( $json, $actor->get__id(), 500 );
		} else {
			$inboxes = apply_filters( 'activitypub_send_to_inboxes', array(), $user_id, $activity );
			$inboxes = array_unique( $inboxes );
		}

		if ( empty( $inboxes ) ) {
			$message = sprintf(
				// translators: %s is the URL of the post.
				__( 'Unlike failed for %s', 'friends' ),
				'<a href="' . esc_url( $url ) . '">' . $url . '</a>'
			);

			$details = array(
				'url'   => $url,
				'error' => __( 'No inboxes to send to.', 'friends' ),
			);

			Logging::log( 'unlike-failed', $message, $details, self::SLUG, $user_id );
			return;
		}

		$report = array();
		foreach ( $inboxes as $inbox ) {
			$response = \Activitypub\safe_remote_post( $inbox, $json, $user_id );
			$report[ $inbox ] = wp_remote_retrieve_response_message( $response );
		}

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
		$type = 'unlike';
		$message = sprintf(
			// translators: %s is the URL of the post.
			__( 'Unliked %s', 'friends' ),
			'<a href="' . esc_url( $external_post_id ) . '">' . $external_post_id . '</a>'
		);
		$details = array(
			'actor'   => $actor,
			'url'     => $external_post_id,
			'inboxes' => $report,
		);

		Logging::log( 'unlike', $message, $details, self::SLUG, $user_id );
	}

	public function boost_button() {
		Friends::template_loader()->get_template_part(
			'frontend/parts/activitypub/boost-button',
			null,
			array()
		);
	}

	public function friends_search_autocomplete( $results, $q ) {
		$url = preg_match( '#^(https?:\/\/)?(?:w{3}\.)?[\w-]+(?:\.[\w-]+)+((?:\/[^\s\/]*)*)#i', $q, $m );
		$url_with_path = isset( $m[2] ) && $m[2];

		$already_added = false;
		foreach ( $results as $result ) {
			if ( strpos( $result, $q ) !== false ) {
				$already_added = true;
			}
		}

		if ( ! $already_added ) {
			if ( preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $q ) ) {
				$result = '<a href="' . esc_url( add_query_arg( 'url', $q, admin_url( 'admin.php?page=add-friend' ) ) ) . '" class="has-icon-left">';
				$result .= '<span class="ab-icon dashicons dashicons-businessperson"><span class="dashicons dashicons-plus"></span></span>';
				$result .= 'Follow ';
				$result .= ' <small>';
				$result .= esc_html( $q );
				$result .= '</small></a>';
				$results[] = $result;
			} elseif ( ( $url && ! $url_with_path ) || preg_match( '/^@?' . self::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $q ) ) {
				$result = '<a href="' . esc_url( add_query_arg( 'url', $m[1] ? $q : 'https://' . $q, admin_url( 'admin.php?page=add-friend' ) ) ) . '" class="has-icon-left">';
				$result .= '<span class="ab-icon dashicons dashicons-businessperson"><span class="dashicons dashicons-plus"></span></span>';
				$result .= 'Follow ';
				$result .= ' <small>';
				$result .= esc_html( $q );
				$result .= '</small></a>';
				$results[] = $result;
			}
		}

		if ( $url_with_path ) {
			$result = '<a href="' . esc_url( add_query_arg( 'boost', $q, home_url( '/friends/' ) ) ) . '" class="has-icon-left">';
			$result .= '<span class="ab-icon dashicons dashicons-controls-repeat"></span>';
			$result .= 'Boost ';
			$result .= ' <small>';
			$result .= esc_html( Friends::url_truncate( $q ) );
			$result .= '</small></a>';
			$results[] = $result;
		}

		if ( $url_with_path ) {
			$result = '<a href="' . esc_url( add_query_arg( 'in_reply_to', $q, home_url( '/friends/' ) ) ) . '" class="has-icon-left">';
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

	public function ajax_boost() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_send_json_error( 'error' );
		}

		check_ajax_referer( 'friends-boost' );

		if ( ! isset( $_POST['post_id'] ) ) {
			wp_send_json_error( 'missing-post-id' );
		}
		$post = get_post( intval( $_POST['post_id'] ) );
		if ( ! $post || ! Friends::check_url( $post->guid ) ) {
			wp_send_json_error( 'unknown-post', array( 'id' => $post->ID ) );
		}

		if ( get_post_meta( $post->ID, 'boosted', true ) ) {
			\delete_post_meta( $post->ID, 'boosted' );
			$this->mastodon_api_unreblog( $post->ID );
			wp_send_json_success( 'unboosted', array( 'id' => $post->ID ) );
			return;
		}
		\update_post_meta( $post->ID, 'boosted', get_current_user_id() );
		$this->mastodon_api_reblog( $post->ID );

		wp_send_json_success( 'boosted', array( 'id' => $post->ID ) );
	}

	public function mastodon_api_reblog( $post_id ) {
		$this->queue_announce( get_post( $post_id ) );
	}

	public function mastodon_api_unreblog( $post_id ) {
		$this->queue_unannounce( get_post( $post_id ) );
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

	public function reblog( $ret, $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $ret;
		}
		if ( User_Feed::get_parser_for_post_id( $post->ID ) === 'activitypub' ) {
			$this->queue_announce( $post );
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
			$this->queue_unannounce( $post );
			\delete_post_meta( $post->ID, 'reblogged', 'activitypub' );
			\delete_post_meta( $post->ID, 'reblogged_by', get_current_user_id() );
			return true;
		}
		return $ret;
	}

	/**
	 * Prepare to announce the post via a scheduled event.
	 *
	 * @param      \WP_Post $post  The post.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_announce( \WP_Post $post ) {
		$url = get_permalink( $post );
		if ( version_compare( ACTIVITYPUB_PLUGIN_VERSION, '5.3.0', '>=' ) ) {
			$outbox_activity_id = \Activitypub\add_to_outbox( $url, 'Announce', get_current_user_id(), ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );
			if ( ! $outbox_activity_id ) {
				return false;
			}
			update_post_meta( $post->ID, 'ap_outbox_announce_id', $outbox_activity_id );
			return true;
		}
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
		$user_id = $this->get_activitypub_actor_id( $user_id );
		$actor = $this->get_activitypub_actor( $user_id );

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Announce' );
		$activity->set_actor( $actor );
		$activity->set_object( $url );
		$activity->set_id( $actor . '#activitypub_announce-' . \preg_replace( '~^https?://~', '', $url ) );
		$activity->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );
		$activity->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', time() ) );

		$json = $activity->to_json();
		if ( version_compare( ACTIVITYPUB_PLUGIN_VERSION, '5.2.0', '>=' ) ) {
			$inboxes = \Activitypub\Collection\Followers::get_inboxes_for_activity( $json, $actor->get__id(), 500 );
		} else {
			$inboxes = apply_filters( 'activitypub_send_to_inboxes', array(), $user_id, $activity );
			$inboxes = array_unique( $inboxes );
		}

		if ( empty( $inboxes ) ) {
			$message = sprintf(
				// translators: %s is the URL of the post.
				__( 'Announce failed for %s', 'friends' ),
				'<a href="' . esc_url( $url ) . '">' . $url . '</a>'
			);

			$details = array(
				'url'   => $url,
				'error' => __( 'No inboxes to send to.', 'friends' ),
			);

			Logging::log( 'announce-failed', $message, $details, self::SLUG, $user_id );
			return;
		}

		$report = array();
		foreach ( $inboxes as $inbox ) {
			$response = \Activitypub\safe_remote_post( $inbox, $json, $user_id );
			$report[ $inbox ] = wp_remote_retrieve_response_message( $response );
		}

		$message = sprintf(
			// translators: %s is the URL of the post.
			__( 'Announced %s', 'friends' ),
			'<a href="' . esc_url( $url ) . '">' . $url . '</a>'
		);

		$details = array(
			'url'     => $url,
			'inboxes' => $report,
		);

		Logging::log( 'announce', $message, $details, self::SLUG, $user_id );
	}

	/**
	 * Prepare to announce the post via a scheduled event.
	 *
	 * @param      \WP_Post $post  The post.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_unannounce( \WP_Post $post ) {
		$url = get_permalink( $post );
		if ( version_compare( ACTIVITYPUB_PLUGIN_VERSION, '5.3.0', '>=' ) ) {
			$outbox_activity_id = get_post_meta( $post->ID, 'ap_outbox_announce_id', true );
			if ( ! $outbox_activity_id ) {
				return false;
			}
			\Activitypub\Collection\Outbox::undo( $outbox_activity_id );
			return true;
		}
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
		$user_id = $this->get_activitypub_actor_id( $user_id );
		$actor = $this->get_activitypub_actor( $user_id );

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
		$activity->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', time() ) );

		$json = $activity->to_json();
		if ( version_compare( ACTIVITYPUB_PLUGIN_VERSION, '5.2.0', '>=' ) ) {
			$inboxes = \Activitypub\Collection\Followers::get_inboxes_for_activity( $json, $actor->get__id(), 500 );
		} else {
			$inboxes = apply_filters( 'activitypub_send_to_inboxes', array(), $user_id, $activity );
			$inboxes = array_unique( $inboxes );
		}

		if ( empty( $inboxes ) ) {
			$message = sprintf(
				// translators: %s is the URL of the post.
				__( 'Unannounce failed for %s', 'friends' ),
				'<a href="' . esc_url( $url ) . '">' . $url . '</a>'
			);

			$details = array(
				'url'   => $url,
				'error' => __( 'No inboxes to send to.', 'friends' ),
			);

			Logging::log( 'unannounce-failed', $message, $details, self::SLUG, $user_id );
			return;
		}

		$report = array();
		foreach ( $inboxes as $inbox ) {
			$response = \Activitypub\safe_remote_post( $inbox, $json, $user_id );
			$report[ $inbox ] = wp_remote_retrieve_response_message( $response );
		}

		$message = sprintf(
			// translators: %s is the URL of the post.
			__( 'Unannounced %s', 'friends' ),
			'<a href="' . esc_url( $url ) . '">' . $url . '</a>'
		);

		$details = array(
			'url'     => $url,
			'inboxes' => $report,
		);

		Logging::log( 'unannounce', $message, $details, self::SLUG, $user_id );
	}

	/**
	 * Approve incoming fediverse comments.
	 *
	 * @param      bool  $approved     Whether the comment is approved.
	 * @param      array $commentdata  The commentdata.
	 *
	 * @return     bool    Whether the comment is approved.
	 */
	public function pre_comment_approved( $approved, $commentdata ) {
		if ( is_string( $approved ) && 'activitypub' === $commentdata['comment_meta']['protocol'] ) {
			// If the author is someone we already follow.
			$user_feed = User_Feed::get_by_url( $commentdata['comment_author_url'] );
			if ( $user_feed instanceof User_Feed ) {
				$approved = true;
			}

			// If the parent post is a Friends::CPT.
			$post = get_post( $commentdata['comment_post_ID'] );
			if ( $post && Friends::CPT === $post->post_type ) {
				$approved = true;
			}
			if ( $post && self::SLUG === User_Feed::get_parser_for_post_id( $post->ID ) ) {
				$approved = true;
			}
		}
		return $approved;
	}

	public function comment_post( $comment_id, $comment_approved, $commentdata ) {
		if ( isset( $commentdata['commentmeta']['protocol'] ) && self::SLUG === $commentdata['commentmeta']['protocol'] ) {
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
	 * Get comments via ActivityPub.
	 *
	 * @param      array     $comments    The comments.
	 * @param      int       $post_id     The post id.
	 * @param      User      $friend_user The friend user.
	 * @param      User_Feed $user_feed   The user feed.
	 *
	 * @return     array  The comments.
	 */
	public function get_remote_comments( $comments, $post_id, ?User $friend_user = null, ?User_Feed $user_feed = null ) {
		if ( User_Feed::get_parser_for_post_id( $post_id ) !== self::SLUG ) {
			return $comments;
		}

		$post = get_post( $post_id );
		if ( Friends::CPT !== $post->post_type ) {
			return $comments;
		}

		// Leverage the Enable Mastodon Apps API to get the comments.
		$context = array(
			'ancestors'   => array(),
			'descendants' => array(),
		);
		$context = apply_filters( 'mastodon_api_status_context', $context, $post_id, $post->guid );
		foreach ( $context['descendants'] as $status ) {

			$comments[] = new \WP_Comment(
				(object) array(
					'comment_ID'           => $status->id,
					'comment_post_ID'      => $post_id,
					'comment_author'       => $status->account->display_name,
					'comment_author_url'   => $status->account->url,
					'comment_author_email' => $status->account->acct,
					'comment_content'      => $status->content,
					'comment_date'         => $status->created_at->format( 'Y-m-d H:i:s' ),
					'comment_date_gmt'     => $status->created_at->format( 'Y-m-d H:i:s' ),
					'comment_approved'     => 1,
					'comment_type'         => 'activitypub',
					'comment_parent'       => $status->in_reply_to_id,

				)
			);
		}

		if ( empty( $comments ) ) {
			add_filter(
				'friends_no_comments_feed_available',
				function () use ( $post ) {
					return __( 'No comments yet.', 'friends' ) . ' <a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank">' . __( 'View this at the source', 'friends' ) . '</a>';
				}
			);
		}
		return $comments;
	}

	public static function friends_post_author_meta( $friend_user ) {
		$meta = get_post_meta( get_the_ID(), self::SLUG, true );
		if ( ! isset( $meta['reblog'] ) || ! $meta['reblog'] ) {
			return;
		}

		if ( ! isset( $meta['attributedTo'] ) ) {
			return;
		}

		$actor_metadata = self::get_actor_metadata_from_attributed_to( $meta['attributedTo'] );
		if ( ! $actor_metadata['url'] ) {
			return;
		}

		Friends::template_loader()->get_template_part(
			'frontend/parts/activitypub/follow-link',
			null,
			array(
				'url'     => $actor_metadata['url'],
				'name'    => $actor_metadata['name'],
				'handle'  => self::convert_actor_to_mastodon_handle( $actor_metadata['url'] ),
				'summary' => wp_strip_all_tags( $actor_metadata['summary'] ),
			)
		);
	}

	public static function header_menu() {
		if ( ! get_post_meta( get_the_ID(), self::SLUG, true ) ) {
			return;
		}

		Friends::template_loader()->get_template_part(
			'frontend/parts/activitypub/header-menu',
			null,
			array()
		);
	}

	public static function enable_comments_form( bool $comments_open, int $post_id ) {
		if ( is_user_logged_in() && User_Feed::get_parser_for_post_id( $post_id ) === self::SLUG ) {
			return true;
		}
		return $comments_open;
	}

	public static function comment_form( $post_id ) {
		$post = get_post( $post_id );
		$mentions = self::extract_html_mentions( $post->post_content );
		$meta = get_post_meta( $post->ID, self::SLUG, true );
		$attributed_to_url = isset( $meta['attributedTo'] ) ? self::get_actor_url_from_attributed_to( $meta['attributedTo'] ) : null;
		if ( $attributed_to_url ) {
			$mentions[ $attributed_to_url ] = $attributed_to_url;
		}

		$comment_content = '';
		if ( $mentions ) {
			$comment_content = '@' . implode( ' @', array_map( array( self::class, 'convert_actor_to_mastodon_handle' ), array_keys( $mentions ) ) ) . ' ';
		}
		$html5 = current_theme_supports( 'html5', 'comment-form' ) ? 'html5' : 'xhtml';
		$required_attribute = ( $html5 ? ' required' : ' required="required"' );
		$required_indicator = ' ' . wp_required_field_indicator();

		\comment_form(
			array(
				'title_reply'          => __( 'Send a Reply via ActivityPub', 'friends' ),
				'title_reply_before'   => '<h5 id="reply-title" class="comment-reply-title">',
				'title_reply_after'    => '</h5>',
				'logged_in_as'         => '',
				'comment_notes_before' => '',
				'comment_field'        => sprintf(
					'<p class="comment-form-comment">%s %s</p>',
					sprintf(
						'<label for="comment">%s%s</label>',
						_x( 'Comment', 'noun' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
						$required_indicator
					),
					'<textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525"' . $required_attribute . '>' . esc_html( $comment_content ) . '</textarea>'
				),
			),
			$post_id
		);
	}

	public function append_comment_form( $content, $post_id, ?User $friend_user = null, ?User_Feed $user_feed = null ) {
		$meta = get_post_meta( $post_id, self::SLUG, true );
		if ( ! $meta ) {
			if ( User_Feed::get_parser_for_post_id( $post_id ) !== self::SLUG ) {
				return $content;
			}
		}

		ob_start();
		self::comment_form( $post_id );
		$comment_form = ob_get_contents();
		ob_end_clean();

		return $content . $comment_form;
	}

	public function comment_post_redirect( $location, $comment ) {
		if ( get_comment_meta( $comment->comment_ID, 'protocol', true ) === 'activitypub' ) {
			// Don't act on comments that came via ActivityPub.
			return $location;
		}

		if ( empty( $comment->user_id ) ) {
			// Don't act on other people's comments.
			return $location;
		}
		$post = get_post( $comment->comment_post_ID );
		$user = User::get_post_author( $post );

		return $user->get_local_friends_page_url( $post->ID );
	}

	private static function convert_actor_to_mastodon_handle( $actor ) {
		// Try to get from ActivityPub plugin's stored actors first (no network request).
		if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			$remote_actor = \Activitypub\Collection\Remote_Actors::get_by_uri( $actor );
			if ( ! is_wp_error( $remote_actor ) ) {
				// Only use stored meta, don't trigger Webfinger fallback in get_acct().
				$acct = get_post_meta( $remote_actor->ID, '_activitypub_acct', true );
				if ( $acct ) {
					return $acct;
				}
			}
		}

		// Construct from the URL as fallback (no network request).
		$p = wp_parse_url( $actor );
		if ( $p ) {
			if ( isset( $p['host'] ) ) {
				$domain = $p['host'];
			}
			if ( isset( $p['path'] ) ) {
				$path_parts = explode( '/', trim( $p['path'], '/' ) );
				$username = ltrim( array_pop( $path_parts ), '@' );
			}
			return $username . '@' . $domain;
		}

		return $actor;
	}

	/**
	 * Disable Webfinger for example domains
	 *
	 * @param mixed $metadata Already retrieved metadata.
	 * @param mixed $actor The actor as URL or username.
	 * @return mixed The potentially added metadata for example domains.
	 */
	public function disable_webfinger_for_example_domains( $metadata, $actor ) {
		if ( ! is_string( $actor ) ) {
			return $metadata;
		}
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

	public function ajax_delete_follower() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_send_json_error( 'error' );
		}
		check_ajax_referer( 'friends-followers' );

		if ( ! isset( $_POST['id'] ) ) {
			return wp_send_json_error( 'missing-id' );
		}

		if ( ! \Activitypub\Collection\Followers::remove_follower( get_current_user_id(), sanitize_text_field( wp_unslash( $_POST['id'] ) ) ) ) {
			return wp_send_json_error( 'Follower not found' );
		}
		wp_send_json_success();
	}

	public function ajax_preview() {
		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_send_json_error( 'error' );
		}

		check_ajax_referer( 'friends-preview' );

		if ( ! isset( $_POST['url'] ) || ! filter_input( INPUT_POST, 'url', FILTER_VALIDATE_URL ) ) {
			wp_send_json_error( 'missing-url' );
		}

		$items = $this->friends_feed->preview( self::SLUG, sanitize_text_field( wp_unslash( $_POST['url'] ) ) );
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( $items );
		}

		$followers = __( '? followers', 'friends' );
		if ( isset( $_POST['followers'] ) && filter_input( INPUT_POST, 'followers', FILTER_VALIDATE_URL ) ) {
			$data = \Activitypub\safe_remote_get( sanitize_text_field( wp_unslash( $_POST['followers'] ) ) );
			if ( ! is_wp_error( $data ) ) {
				$body = json_decode( wp_remote_retrieve_body( $data ), true );
				if ( isset( $body['totalItems'] ) ) {
					$followers = sprintf(
						// translators: %s is the number of followers.
						_n( '%s follower', '%s followers', $body['totalItems'], 'friends' ),
						$body['totalItems']
					);
				}
			}
		}
		$following = __( '? following', 'friends' );
		if ( isset( $_POST['following'] ) && filter_input( INPUT_POST, 'following', FILTER_VALIDATE_URL ) ) {
			$data = \Activitypub\safe_remote_get( sanitize_text_field( wp_unslash( $_POST['following'] ) ) );
			if ( ! is_wp_error( $data ) ) {
				$body = json_decode( wp_remote_retrieve_body( $data ), true );
				if ( isset( $body['totalItems'] ) ) {
					$following = sprintf(
						// translators: %s is the number of followings.
						_n( '%s following', '%s following', $body['totalItems'], 'friends' ),
						$body['totalItems']
					);
				}
			}
		}

		$posts = '<div class="posts">';
		foreach ( $items as $item ) {
			$posts .= '<div class="card">';
			$posts .= '<header class="card-header">';
			$posts .= '<div class="post-meta">';
			$posts .= '<div class="permalink">';
			if ( $item->permalink ) {
				$posts .= '<a href="' . esc_url( $item->permalink ) . '">';
			}
			if ( $item->date ) {
				$posts .= esc_html( $item->date );
			}
			if ( $item->permalink ) {
				$posts .= '</a>';
			}
			$posts .= '</div>';
			$posts .= '</div>';
			$posts .= '</header>';
			$posts .= '<div class="card-body">';
			$posts .= wp_kses_post( $item->content );
			$posts .= '</div>';
			$posts .= '</div>';
		}

		wp_send_json_success( compact( 'posts', 'followers', 'following' ) );
	}
}
