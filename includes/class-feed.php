<?php
/**
 * Friends Feed
 *
 * This contains the feed functions.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the feed part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Feed extends \Friends_Feed {
	const XMLNS = 'wordpress-plugin-friends:feed-additions:1';

	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * Contains the registered parsers.
	 *
	 * @var array
	 */
	private $parsers = array();

	/**
	 * These parsers cannot be registered.
	 *
	 * @var        array
	 */
	private $reservered_parser_slugs = array( 'friends', 'unsupported' );

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
		add_filter( 'pre_get_posts', array( $this, 'private_feed_query' ), 1 );
		add_filter( 'private_title_format', array( $this, 'private_title_format' ) );
		add_filter( 'pre_option_rss_use_excerpt', array( $this, 'feed_use_excerpt' ), 90 );
		add_filter( 'friends_early_modify_feed_item', array( $this, 'apply_early_feed_rules' ), 10, 3 );
		add_filter( 'friends_modify_feed_item', array( $this, 'apply_feed_rules' ), 10, 3 );

		add_action( 'rss_item', array( $this, 'feed_additional_fields' ) );
		add_action( 'rss2_item', array( $this, 'feed_additional_fields' ) );
		add_action( 'rss_ns', array( $this, 'additional_feed_namespaces' ) );
		add_action( 'rss2_ns', array( $this, 'additional_feed_namespaces' ) );

		add_action( 'cron_friends_refresh_feeds', array( $this, 'cron_friends_refresh_feeds' ) );
		add_action( 'set_user_role', array( $this, 'retrieve_new_friends_posts' ), 999, 3 );

		add_action( 'wp_loaded', array( $this, 'friends_add_friend_redirect' ), 100 );
		add_action( 'wp_feed_options', array( $this, 'wp_feed_options' ), 90, 2 );

		add_action( 'wp_insert_post', array( $this, 'invalidate_post_count_cache' ), 10, 2 );
	}

	/**
	 * Allow registering a parser
	 *
	 * @param      string      $slug    The slug.
	 * @param      Feed_Parser $parser  The parser that extends the Feed_Parser class.
	 */
	public function register_parser( $slug, $parser ) {
		if ( in_array( $slug, $this->reservered_parser_slugs, true ) ) {
			// translators: %s is the slug of a parser.
			return new \WP_Error( 'resevered-slug', sprintf( __( 'The slug "%s" cannot be used.', 'friends' ), $slug ) );
		}
		if ( isset( $this->parsers[ $slug ] ) ) {
			// translators: %s is the slug of a parser.
			return new \WP_Error( 'parser-already-registered', sprintf( __( 'There is already a parser registered with the slug "%s".', 'friends' ), $slug ) );
		}
		$this->parsers[ $slug ] = $parser;

		return true;
	}

	/**
	 * Allow unregistering a parser
	 *
	 * @param      string $slug    The slug.
	 */
	public function unregister_parser( $slug ) {
		if ( ! in_array( $slug, $this->reservered_parser_slugs, true ) ) {
			unset( $this->parsers[ $slug ] );
		}
	}

	/**
	 * Cron function to refresh the feeds of the friends' blogs
	 */
	public function cron_friends_refresh_feeds() {
		$this->retrieve_friend_posts();
	}

	/**
	 * Preview a URL using a parser.
	 *
	 * @param      string $parser   The parser slug.
	 * @param      string $url      The url.
	 * @param      int    $feed_id  The feed id.
	 *
	 * @return     array|\WP_error  The feed items.
	 */
	public function preview( $parser, $url, $feed_id = null ) {
		if ( ! isset( $this->parsers[ $parser ] ) ) {
			return new \WP_Error( 'unknown-parser', __( 'An unknown parser name was supplied.', 'friends' ) );
		}

		$user_feed = null;
		$friend_user = null;
		if ( ! is_null( $feed_id ) ) {
			$user_feed = User_Feed::get_by_id( $feed_id );
			if ( ! is_wp_error( $user_feed ) ) {
				$friend_user = $user_feed->get_friend_user();
			}
		}

		$items = $this->parsers[ $parser ]->fetch_feed( $url );

		if ( ! is_wp_error( $items ) ) {
			if ( empty( $items ) ) {
				$items = new \WP_Error( 'empty-feed', __( "This feed doesn't contain any entries. There might be a problem parsing the feed.", 'friends' ) );
			} else {
				foreach ( $items as $key => $item ) {
					$item = apply_filters( 'friends_modify_feed_item', $item, $user_feed, $friend_user );

					if ( ! $item || $item->_feed_rule_delete ) {
						unset( $items[ $key ] );
						continue;
					}
					if ( ! empty( $item->_feed_rule_transform ) && is_array( $item->_feed_rule_transform ) ) {
						if ( isset( $item->_feed_rule_transform['post_content'] ) ) {
							$items[ $key ]->content = $item->_feed_rule_transform['post_content'];
						}
					}
				}
			}
		}

		return $items;
	}

	/**
	 * Retrieve posts from a remote WordPress for a friend.
	 *
	 * @param  User $friend_user A single user to fetch.
	 */

	/**
	 * Retrieves a user feed.
	 *
	 * @param      User_Feed $user_feed  The user feed.
	 *
	 * @return     array|\WP_Error             The retrieved items.
	 */
	public function retrieve_feed( User_Feed $user_feed ) {
		$friend_user = $user_feed->get_friend_user();
		$parser = $user_feed->get_parser();
		if ( ! isset( $this->parsers[ $parser ] ) ) {
			$error = new \WP_Error( 'unknown-parser', __( 'An unknown parser name was supplied.', 'friends' ) );
			do_action( 'friends_retrieve_friends_error', $user_feed, $error, $friend_user );
			return $error;
		}
		try {
			$items = $this->parsers[ $parser ]->fetch_feed( $user_feed->get_private_url(), $user_feed );
		} catch ( \Exception $e ) {
			$items = new \WP_Error( $parser . '-failed', $e->getMessage() );
		}

		if ( is_wp_error( $items ) ) {
			do_action( 'friends_retrieve_friends_error', $user_feed, $items, $friend_user );
			return $items;
		}

		$new_posts = $this->process_incoming_feed_items( $items, $user_feed );
		$this->notify_about_new_posts( $friend_user, $new_posts );

		do_action( 'friends_retrieved_new_posts', $user_feed, $new_posts, $friend_user );
		return $new_posts;
	}

	/**
	 * Notify users about new posts of this friend
	 *
	 * @param      User  $friend_user  The friend.
	 * @param      array $new_posts    The new posts of this friend.
	 */
	public function notify_about_new_posts( User $friend_user, $new_posts ) {
		$keywords = self::get_active_notification_keywords();
		foreach ( $new_posts as $post_id ) {
			$post = false;

			$keyword_match = false;
			if ( $keywords ) {
				$post = get_post( intval( $post_id ) );
				$fulltext = '';
				foreach ( apply_filters( 'friends_keyword_search_fields', array( 'post_title', 'post_content' ) ) as $field ) {
					$fulltext .= PHP_EOL . $post->$field;
				}
				$fulltext = strip_tags( $fulltext );
				foreach ( $keywords as $keyword ) {
					if ( preg_match( '/' . str_replace( '/', '\\/', $keyword ) . '/ius', $fulltext ) ) {
						$keyword_match = $keyword;
						break;
					}
				}

				if ( $keyword_match ) {
					$notified = apply_filters( 'notify_keyword_match_post', false, $post, $keyword_match );
					if ( $notified ) {
						continue;
					}
				}
			}

			$notify_users = apply_filters( 'notify_about_new_friend_post', true, $friend_user, $post_id );
			if ( $notify_users ) {
				if ( ! $post ) {
					$post = get_post( intval( $post_id ) );
				}
				do_action( 'notify_new_friend_post', $post );
			}
		}
	}

	/**
	 * Retrieve posts from all friends.
	 *
	 * @param      int $max_age     The maximum age of the last retrieval. Default: a bit less than an hour to not block cron jobs.
	 */
	public function retrieve_friend_posts( $max_age = 3000 ) {
		$friends = new User_Query( array( 'role__in' => array( 'friend', 'acquaintance', 'pending_friend_request', 'subscription' ) ) );
		$friends = $friends->get_results();

		if ( empty( $friends ) ) {
			return;
		}

		$feeds = array();
		foreach ( $friends as $friend_user ) {
			$feeds = array_merge( $feeds, $friend_user->get_active_feeds() );
		}

		$max_age = gmdate( 'Y-m-d H:i:s', time() - $max_age );
		$feeds = array_filter(
			$feeds,
			function( $feed ) use ( $max_age ) {
				return strcmp( $feed->get_last_log(), $max_age ) < 0;
			}
		);

		usort(
			$feeds,
			function( $a, $b ) {
				return strcmp( $a->get_last_log(), $b->get_last_log() );
			}
		);

		foreach ( $feeds as $feed ) {
			$this->retrieve_feed( $feed );
			$feed->get_friend_user()->delete_outdated_posts();
		}
	}
	/**
	 * Apply the feed rules that need to be applied early.
	 *
	 * @param  Feed_Item $item         The feed item.
	 * @param      User_Feed $feed         The feed.
	 * @param      User      $friend_user  The friend user.
	 * @return Feed_Item The modified feed item.
	 */
	public function apply_early_feed_rules( $item, User_Feed $feed = null, User $friend_user = null ) {
		$updated_item = $this->apply_feed_rules( $item, $feed, $friend_user );
		if ( $updated_item->_feed_rule_delete ) {
			return $updated_item;
		}
		return $item;
	}

	/**
	 * Apply the feed rules
	 *
	 * @param  Feed_Item $item         The feed item.
	 * @param  User_Feed $feed         The feed object.
	 * @param  User      $friend_user The friend user.
	 * @return Feed_Item The modified feed item.
	 */
	public function apply_feed_rules( $item, User_Feed $feed = null, User $friend_user = null ) {
		if ( is_null( $friend_user ) ) {
			return $item;
		}

		$rules  = $friend_user->get_feed_rules();
		$action = $friend_user->get_feed_catch_all();

		foreach ( $rules as $rule ) {
			if ( $item instanceof \WP_Post ) {
				$field = $this->get_feed_rule_field( $rule['field'] );

				if ( 'author' === $rule['field'] ) {
					$item->$field = get_post_meta( $item->ID, 'author', true );
				}
			} else {
				$field = $rule['field'];
			}

			if ( $item->$field && preg_match( '/' . str_replace( '/', '\\/', $rule['regex'] ) . '/ius', $item->$field ) ) {
				if ( 'replace' === $rule['action'] ) {
					$item->_feed_rule_transform = array(
						$this->get_feed_rule_field( $rule['field'], $item ) => preg_replace( '/' . $rule['regex'] . '/iu', $rule['replace'], $item->$field ),
					);
					continue;
				}
				$action = $rule['action'];
				break;
			}
		}
		switch ( $action ) {
			case 'delete':
				$item->_feed_rule_delete = true;
				return $item;

			case 'trash':
				$item->_feed_rule_transform = array(
					'post_status' => 'trash',
				);
				return $item;

			case 'accept':
				return $item;
		}

		return $item;
	}

	/**
	 * Get the field name for the feed item.
	 *
	 * @param  string $field The field name.
	 * @return string        The adapted field name.
	 */
	private function get_feed_rule_field( $field ) {
		switch ( $field ) {
			case 'title':
				return 'post_title';
			case 'permalink':
				return 'guid';
			case 'content':
				return 'post_content';
		}

		return $field;
	}

	/**
	 * Validate feed item rules
	 *
	 * @param  array $rules The rules to validate.
	 * @return array        The valid rules.
	 */
	public function validate_feed_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return array();
		}

		if ( isset( $rules['field'] ) && is_array( $rules['field'] ) ) {
			// Transform POST values.
			$transformed_rules = array();
			foreach ( $rules['field'] as $key => $field ) {
				$rule = array();
				foreach ( $rules as $part => $keys ) {
					if ( isset( $keys[ $key ] ) ) {
						$rule[ $part ] = stripslashes( $keys[ $key ] );
					}
				}
				$transformed_rules[] = $rule;
			}
			$rules = $transformed_rules;
		}

		foreach ( $rules as $k => $rule ) {
			if ( ! isset( $rule['field'] ) || ! in_array( $rule['field'], array( 'title', 'content', 'permalink', 'author' ), true ) ) {
				unset( $rules[ $k ] );
				continue;
			}

			if ( ! isset( $rule['regex'] ) || ! is_string( $rule['regex'] ) || '' === trim( $rule['regex'] ) ) {
				unset( $rules[ $k ] );
				continue;
			}

			$rules[ $k ]['regex'] = substr( $rule['regex'], 0, 10240 );

			if ( ! isset( $rule['action'] ) || ! in_array( $rule['action'], array( 'accept', 'trash', 'delete', 'replace' ), true ) ) {
				unset( $rules[ $k ] );
				continue;
			}

			if ( 'replace' === $rule['action'] ) {
				if ( ! isset( $rule['replace'] ) || ! is_string( $rule['replace'] ) ) {
					unset( $rules[ $k ] );
					continue;
				}

				$rules[ $k ]['replace'] = substr( $rule['replace'], 0, 10240 );
			} else {
				unset( $rules[ $k ]['replace'] );
			}
		}

		return $rules;
	}

	/**
	 * Validate feed catch_all
	 *
	 * @param  array $catch_all The catch_all value to.
	 * @return array            A valid catch_all
	 */
	public function validate_feed_catch_all( $catch_all ) {
		if ( ! in_array( $catch_all, array( 'accept', 'trash', 'delete' ), true ) ) {
			return 'accept';
		}

		return $catch_all;
	}

	/**
	 * Gets the notification keywords.
	 *
	 * @return     array  The notification keywords.
	 */
	public static function get_active_notification_keywords() {
		$active_keywords = array();
		foreach ( self::get_all_notification_keywords() as $entry ) {
			if ( $entry['enabled'] ) {
				$active_keywords[] = $entry['keyword'];
			}
		}

		return $active_keywords;
	}

	/**
	 * Gets all notification keywords.
	 *
	 * @return     array  All notification keywords.
	 */
	public static function get_all_notification_keywords() {
		$keywords = get_option( 'friends_notification_keywords', false );
		if ( false === $keywords ) {
			// Default keywords.
			$keywords = array();
			$keywords[] = array(
				'enabled' => false,
				'keyword' => trim( preg_replace( '#^https?:#', '', home_url() ), '/' ),
			);
		}
		return $keywords;
	}

	/**
	 * Discover the post format for the item.
	 *
	 * @param      object $item   The feed item.
	 *
	 * @return     string  The discovered post format.
	 */
	public function post_format_discovery( $item ) {
		// Not implemented yet.
		return 'standard';
	}

	/**
	 * Process incoming feed items
	 *
	 * @param  array     $items           The incoming items.
	 * @param  User_Feed $user_feed       The feed to which these items belong.
	 * @return array                             The post ids of the new posts.
	 */
	public function process_incoming_feed_items( array $items, User_Feed $user_feed ) {
		$friend_user     = $user_feed->get_friend_user();
		$remote_post_ids = $friend_user->get_remote_post_ids();
		$rules           = $friend_user->get_feed_rules();
		$post_formats    = get_post_format_strings();
		$feed_post_format = $user_feed->get_post_format();

		$new_posts = array();
		foreach ( $items as $item ) {
			if ( ! $item->permalink ) {
				continue;
			}
			$permalink = str_replace( array( '&#38;', '&#038;' ), '&', ent2ncr( wp_kses_normalize_entities( $item->permalink ) ) );

			$title = $item->title;
			$content = wp_kses_post( trim( $item->post_content ) );

			$item = apply_filters( 'friends_early_modify_feed_item', $item, $user_feed, $friend_user );
			if ( ! $item || $item->_feed_rule_delete ) {
				continue;
			}

			// Fallback, when no friends plugin is installed.
			$item->post_id     = $permalink;
			$item->post_status = 'publish';
			if ( ! isset( $item->comment_count ) ) {
				$item->comment_count = 0;
			}
			if ( ( ! $content && ! $title ) || ! $permalink ) {
				continue;
			}

			$post_id = null;
			if ( isset( $remote_post_ids[ $item->post_id ] ) ) {
				$post_id = $remote_post_ids[ $item->post_id ];
			}
			if ( is_null( $post_id ) && isset( $remote_post_ids[ $permalink ] ) ) {
				$post_id = $remote_post_ids[ $permalink ];
			}

			if ( is_null( $post_id ) ) {
				$post_id = $this->url_to_postid( $permalink, $friend_user->ID );
			}
			$item->_is_new = is_null( $post_id );
			$item = apply_filters( 'friends_modify_feed_item', $item, $user_feed, $friend_user );

			$updated_date = $item->date;
			if ( ! empty( $item->updated_date ) ) {
				$updated_date = $item->updated_date;
			}

			$post_data = array(
				'post_title'        => $title,
				'post_content'      => $content,
				'post_modified_gmt' => $updated_date,
				'post_status'       => $item->post_status,
				'guid'              => $permalink,
			);

			// Modified via feed rules.
			if ( ! empty( $item->_feed_rule_transform ) && is_array( $item->_feed_rule_transform ) ) {
				$post_data = array_merge( $post_data, $item->_feed_rule_transform );
			}

			if ( ! is_null( $post_id ) ) {
				$post_data['ID'] = $post_id;
				$was_modified_by_user = false;
				foreach ( wp_get_post_revisions( $post_id ) as $revision ) {
					if ( intval( $revision->post_author ) ) {
						$was_modified_by_user = true;
						break;
					}
				}
				if ( ! $was_modified_by_user ) {
					wp_update_post( $post_data );
				}
			} else {
				$post_data['post_author']   = $friend_user->ID;
				$post_data['post_type']     = Friends::CPT;
				$post_data['post_date_gmt'] = $item->date;
				$post_data['comment_count'] = $item->comment_count;

				$post_id = wp_insert_post( $post_data, true );
				if ( is_wp_error( $post_id ) ) {
					continue;
				}

				$new_posts[]                   = $post_id;
				$remote_post_ids[ $permalink ] = $post_id;
			}

			$post_format = $feed_post_format;
			if ( 'autodetect' === $post_format ) {
				if ( isset( $item->{'post-format'} ) && isset( $post_formats[ $item->{'post-format'} ] ) ) {
					$post_format = $item->{'post-format'};
				} else {
					$post_format = $this->post_format_discovery( $item );
				}
			}

			if ( $post_format ) {
				set_post_format( $post_id, $post_format );
			}

			if ( $item->author ) {
				update_post_meta( $post_id, 'author', $item->author );
			}

			if ( is_numeric( $item->post_id ) ) {
				update_post_meta( $post_id, 'remote_post_id', $item->{'post-id'} );
			}

			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'comment_count' => $item->comment_count ), array( 'ID' => $post_id ) );
		}

		return $new_posts;
	}

	/**
	 * Remove the Private: when sending a private feed.
	 *
	 * @param  string $title_format The title format for a private post title.
	 * @return string The modified title format for a private post title.
	 */
	public function private_title_format( $title_format ) {
		if ( $this->friends->access_control->feed_is_authenticated() ) {
			return '%s';
		}
		return $title_format;
	}

	/**
	 * Disable excerpted feeds for friend feeds
	 *
	 * @param  boolean $feed_use_excerpt Whether to only have excerpts in feeds.
	 * @return boolean The modified flag whether to have excerpts in feeds.
	 */
	public function feed_use_excerpt( $feed_use_excerpt ) {
		if ( $this->friends->access_control->feed_is_authenticated() ) {
			return 0;
		}

		return $feed_use_excerpt;
	}

	/**
	 * Output an additional XMLNS for the feed.
	 */
	public function additional_feed_namespaces() {
		echo ' xmlns:friends="' . esc_attr( self::XMLNS ) . '" ';
	}

	/**
	 * Additional fields for the friends feed.
	 */
	public function feed_additional_fields() {
		global $post;
		$post_format = get_post_format( $post );
		if ( empty( $post_format ) ) {
			$post_format = 'standard';
		}
		echo '<friends:post-format>' . esc_html( $post_format ) . '</friends:post-format>' . PHP_EOL;

		$authenticated_user_id = $this->friends->access_control->feed_is_authenticated();
		if ( ! $authenticated_user_id ) {
			return;
		}

		echo '<friends:gravatar>' . esc_html( get_avatar_url( $post->post_author ) ) . '</friends:gravatar>' . PHP_EOL;
		echo '<friends:post-status>' . esc_html( $post->post_status ) . '</friends:post-status>' . PHP_EOL;
		echo '<friends:post-id>' . esc_html( $post->ID ) . '</friends:post-id>' . PHP_EOL;
	}

	/**
	 * Redirect
	 */
	public function friends_add_friend_redirect() {
		if ( ! isset( $_GET['add-friend'] ) || isset( $_GET['page'] ) ) {
			return;
		}

		wp_safe_redirect( self_admin_url( 'admin.php?page=add-friend&url=' . urlencode( $_GET['add-friend'] ) ) );
		exit;
	}

	/**
	 * Configure feed downloading options
	 *
	 * @param  SimplePie $feed The SimplePie object.
	 * @param  string    $url  The URL to fetch.
	 */
	public function wp_feed_options( $feed, $url ) {
		$feed->useragent .= ' Friends/' . Friends::VERSION;
		if ( isset( $_GET['page'] ) && 'page=friends-refresh' === $_GET['page'] ) {
			$feed->enable_cache( false );
		} else {
			$feed->set_cache_duration( 3590 );
		}
	}

	/**
	 * Invalidatee Post Count Cache
	 *
	 * @param      int      $post_ID  The post id.
	 * @param      \WP_Post $post     The post.
	 */
	public function invalidate_post_count_cache( $post_ID, \WP_Post $post ) {
		$cache_key = 'friends_post_count_by_post_format';
		delete_transient( $cache_key );
		if ( is_numeric( $post->post_author ) && $post->post_author > 0 ) {
			$author_id = intval( $post->post_author );
			delete_transient( $cache_key . '_author_' . $author_id );
		}
	}

	/**
	 * Discover available feeds.
	 *
	 * @param  string $url The feed URL.
	 * @return array The available feeds.
	 */
	public function discover_available_feeds( $url ) {
		$available_feeds = array();
		$content = null;
		$content_type = 'text/html';

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 1,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$content = wp_remote_retrieve_body( $response );
			$headers = wp_remote_retrieve_headers( $response );

			// We'll determine the obvious feeds ourself.
			$available_feeds = $this->discover_link_rel_feeds( $content, $url, $headers );
			$content_type = strtok( $headers['content-type'], ';' );
		}

		if ( $content ) {
			foreach ( $this->parsers as $slug => $parser ) {
				foreach ( $parser->discover_available_feeds( $content, $url ) as $link_url => $feed ) {
					if ( isset( $available_feeds[ $link_url ] ) ) {
						// If this parser tells us it can parse it right away, allow it to override.
						if ( isset( $available_feeds[ $link_url ]['parser'] ) || ! isset( $feed['parser'] ) ) {
							continue;
						}
					}
					$available_feeds[ $link_url ] = $feed;
					$available_feeds[ $link_url ]['url'] = $link_url;
				}
			}
		}

		$has_friends_plugin = false;
		foreach ( $available_feeds as $link_url => $feed ) {
			$feed = array_merge(
				array(
					'url'               => $link_url,
					'rel'               => 'unknown',
					'type'              => 'unknown',
					'title'             => '',
					'parser'            => 'unsupported',
					'parser_confidence' => 0,
				),
				$feed
			);
			$available_feeds[ $link_url ] = $feed;

			if ( 'friends-base-url' === $feed['rel'] ) {
				$available_feeds[ $link_url ]['parser'] = 'friends';
				$available_feeds[ $link_url ]['parser_confidence'] = 1000;
				$has_friends_plugin = $link_url;
				continue;
			}

			foreach ( $this->parsers as $slug => $parser ) {
				$confidence = intval( $parser->feed_support_confidence( $link_url, $feed['type'], $feed['rel'] ) );
				$confidence = intval( apply_filters( 'friends_parser_confidence', $confidence, $slug, $link_url, $feed ) );
				if ( $available_feeds[ $link_url ]['parser_confidence'] < $confidence ) {
					$available_feeds[ $link_url ]['parser_confidence'] = $confidence;
					$available_feeds[ $link_url ]['parser'] = $slug;
				}
			}
		}

		if ( ! isset( $available_feeds[ $url ] ) ) {
			$feed = array(
				'url'               => $url,
				'type'              => $content_type,
				'rel'               => 'self',
				'title'             => '',
				'parser'            => 'unsupported',
				'parser_confidence' => 0,

			);

			foreach ( $this->parsers as $slug => $parser ) {
				$confidence = $parser->feed_support_confidence( $url, $feed['type'], $feed['rel'], $content );
				$confidence = apply_filters( 'friends_parser_confidence', $confidence, $slug, $url, $feed );
				if ( $feed['parser_confidence'] < $confidence ) {
					$feed['parser_confidence'] = $confidence;
					$feed['parser'] = $slug;
				}
			}

			if ( 'unknown' !== $feed['parser'] ) {
				$available_feeds[ $feed['url'] ] = $feed;
			}
		}

		foreach ( array_keys( $available_feeds ) as $link_url ) {
			if ( ! isset( $this->parsers[ $available_feeds[ $link_url ]['parser'] ] ) ) {
				continue;
			}
			$parser = $this->parsers[ $available_feeds[ $link_url ]['parser'] ];
			$available_feeds[ $link_url ] = array_merge( $available_feeds[ $link_url ], $parser->update_feed_details( $available_feeds[ $link_url ] ) );
			$available_feeds[ $link_url ] = apply_filters( 'friends_update_feed_details', $available_feeds[ $link_url ], $slug );
			if ( $available_feeds[ $link_url ]['url'] !== $link_url ) {
				$new_url = $available_feeds[ $link_url ]['url'];
				$available_feeds[ $new_url ] = $available_feeds[ $link_url ];
				unset( $available_feeds[ $link_url ] );
			}
		}

		$autoselected = false;
		foreach ( $available_feeds as $link_url => $feed ) {
			$path = wp_parse_url( $link_url, PHP_URL_PATH );

			// Backfill titles.
			if ( empty( $feed['title'] ) ) {
				$host = wp_parse_url( $link_url, PHP_URL_HOST );
				if ( trim( $path, '/' ) ) {
					$feed['title'] = trim( preg_replace( '#^www\.#i', '', preg_replace( '#[^a-z0-9.:-]#i', ' ', ucwords( $host . ': ' . $path ) ) ), ': ' );
				} else {
					$feed['title'] = strtolower( $host );
				}
				$available_feeds[ $link_url ]['title'] = $feed['title'];
			}

			// Autoselect heuristic.
			if ( $autoselected ) {
				continue;
			}

			if ( ! $feed['parser_confidence'] ) {
				continue;
			}

			if ( $has_friends_plugin ) {
				// Prefer the main RSS feed.
				if ( 'feed' === trim( $path, '/' ) ) {
					$available_feeds[ $link_url ]['post-format'] = 'autodetect';
					$autoselected = true;
				}
			} elseif ( isset( $feed['rel'] ) ) {
				if ( 'alternate' === $feed['rel'] && 'application/rss+xml' === $feed['type'] && 'feed' === trim( $path, '/' ) ) {
					$autoselected = true;
				}
			}

			if ( $autoselected ) {
				$available_feeds[ $link_url ]['autoselect'] = true;
			}
		}

		$feed_sort_order = array( 'self', 'alternate', 'me' );
		if ( $has_friends_plugin ) {
			// If we have the Friends plugin, we prefer an (augmented) RSS feed over, for example, microformats.
			$feed_sort_order = array( 'alternate', 'self', 'me' );
		}

		uasort(
			$available_feeds,
			function ( $a, $b ) use ( $feed_sort_order ) {
				if ( isset( $a['autoselect'] ) && $a['autoselect'] ) {
					if ( ! isset( $b['autoselect'] ) || ! $b['autoselect'] ) {
						return -1;
					}
				} elseif ( isset( $b['autoselect'] ) && $b['autoselect'] ) {
					return 1;
				}

				foreach ( $feed_sort_order as $rel_sort ) {
					if ( $rel_sort === $a['rel'] ) {
						if ( $rel_sort !== $b['rel'] ) {
							return -1;
						}
						break;
					} elseif ( $rel_sort === $b['rel'] ) {
						return 1;
					}
				}

				return strcmp( $a['title'], $b['title'] );
			}
		);

		return apply_filters( 'friends_available_feeds', $available_feeds, $url );
	}

	/**
	 * Discover feeds specified in the <link> section.
	 *
	 * @param      string $content  The content to search.
	 * @param      string $url      The url.
	 * @param      object $headers  The headers from the request.
	 *
	 * @return     array   ( description_of_the_return_value )
	 */
	private function discover_link_rel_feeds( $content, $url, $headers ) {
		$discovered_feeds = array();
		$has_self = false;
		$mf = Friends\Mf2\parse( $content, $url );
		if ( isset( $mf['rel-urls'] ) ) {
			foreach ( $mf['rel-urls'] as $feed_url => $link ) {
				foreach ( array( 'friends-base-url', 'me', 'alternate', 'self' ) as $rel ) {
					if ( in_array( $rel, $link['rels'] ) ) {
						$discovered_feeds[ $feed_url ] = array(
							'rel' => $rel,
						);
					}
				}

				if ( ! isset( $discovered_feeds[ $feed_url ] ) ) {
					continue;
				}

				if ( 'self' === $rel ) {
					$has_self = true;
				}

				if ( isset( $link['type'] ) ) {
					$discovered_feeds[ $feed_url ]['type'] = $link['type'];
				}

				if ( isset( $link['title'] ) ) {
					$discovered_feeds[ $feed_url ]['title'] = $link['title'];
				} elseif ( isset( $link['text'] ) ) {
					$discovered_feeds[ $feed_url ]['title'] = $link['text'];
				}
			}
		}

		if ( ! $has_self && class_exists( 'DOMXpath' ) ) {
			// Convert to a DomDocument and silence the errors while doing so.
			$doc = new DomDocument;
			set_error_handler( '__return_null' );
			$doc->loadHTML( $content );
			restore_error_handler();

			$xpath = new DOMXpath( $doc );
			if ( $xpath ) {
				$discovered_feeds[ $url ] = array(
					'rel'   => 'self',
					'title' => $xpath->query( '//title' )->item( 0 )->textContent,
				);
			}
		}

		return $discovered_feeds;
	}

	/**
	 * Modify the main query for the friends feed
	 *
	 * @param  \WP_Query $query The main query.
	 * @return \WP_Query The modified main query.
	 */
	public function private_feed_query( \WP_Query $query ) {
		if ( ! $this->friends->access_control->feed_is_authenticated() ) {
			return $query;
		}

		$friend_user = $this->friends->access_control->get_authenticated_feed_user();
		if ( ! $query->is_admin && $query->is_feed && $friend_user->has_cap( 'friend' ) && ! $friend_user->has_cap( 'acquaintance' ) ) {
			$query->set( 'post_status', array( 'publish', 'private' ) );
		}

		return $query;
	}

	/**
	 * Retrieve new friend's posts after changing roles
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  string $old_roles The old roles.
	 */
	public function retrieve_new_friends_posts( $user_id, $new_role, $old_roles ) {
		if ( ( 'friend' === $new_role || 'acquaintance' === $new_role ) && apply_filters( 'friends_immediately_fetch_feed', true ) ) {
			update_user_option( $user_id, 'friends_new_friend', true );
			$friend = new User( $user_id );
			$friend->retrieve_posts();
		}
	}

	/**
	 * More generic version of the native url_to_postid()
	 *
	 * @param string $url       Permalink to check.
	 * @param int    $author_id The id of the author.
	 * @return int Post ID, or 0 on failure.
	 */
	public function url_to_postid( $url, $author_id = false ) {
		global $wpdb;
		if ( $author_id ) {
			$post_id = $wpdb->get_var( $wpdb->prepare( 'SELECT ID from ' . $wpdb->posts . ' WHERE guid IN (%s, %s) AND post_author = %d LIMIT 1', $url, esc_attr( $url ), $author_id ) );
		} else {
			$post_id = $wpdb->get_var( $wpdb->prepare( 'SELECT ID from ' . $wpdb->posts . ' WHERE guid IN (%s, %s) LIMIT 1', $url, esc_attr( $url ) ) );
		}
		return $post_id;
	}

	/**
	 * Gets the registered parser.
	 *
	 * @param      string $parser  The parser slug.
	 *
	 * @return     string  The parser name.
	 */
	public function get_registered_parser( $parser ) {
		if ( ! isset( $this->parsers[ $parser ] ) ) {
			return null;
		}
		$parsers = $this->get_registered_parsers();
		return $parsers[ $parser ];
	}

	/**
	 * The list of currently supported parsers.
	 *
	 * @return array A list of parsers. Key is the slug, value is the parser name.
	 */
	public function get_registered_parsers() {
		$parsers = array();
		foreach ( $this->parsers as $slug => $parser ) {
			$name = $slug;
			if ( defined( get_class( $parser ) . '::NAME' ) ) {
				$name = esc_html( $parser::NAME );
			}
			if ( defined( get_class( $parser ) . '::URL' ) ) {
				$name = '<a href="' . esc_url( $parser::URL ) . '" target="_blank" rel="noopener noreferrer">' . $name . '</a>';
			}
			$parsers[ $slug ] = $name;
		}
		return $parsers;
	}
}
