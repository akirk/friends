<?php
/**
 * Friends Feed
 *
 * This contains the feed functions.
 *
 * @package Friends
 */

/**
 * This is the class for the feed part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Feed {
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
		add_filter( 'friends_modify_feed_item', array( $this, 'apply_feed_rules' ), 10, 3 );

		add_action( 'rss_item', array( $this, 'feed_additional_fields' ) );
		add_action( 'rss2_item', array( $this, 'feed_additional_fields' ) );
		add_action( 'rss_ns', array( $this, 'additional_feed_namespaces' ) );
		add_action( 'rss2_ns', array( $this, 'additional_feed_namespaces' ) );

		add_action( 'cron_friends_refresh_feeds', array( $this, 'cron_friends_refresh_feeds' ) );
		add_action( 'set_user_role', array( $this, 'retrieve_new_friends_posts' ), 999, 3 );

		add_action( 'wp_loaded', array( $this, 'friends_opml' ), 100 );
		add_action( 'wp_loaded', array( $this, 'friends_add_friend_redirect' ), 100 );
		add_action( 'wp_feed_options', array( $this, 'wp_feed_options' ), 90, 2 );
	}

	/**
	 * Allow registering a parser
	 *
	 * @param      string              $slug    The slug.
	 * @param      Friends_Feed_Parser $parser  The parser that extends the Friends_Feed_Parser class.
	 */
	public function register_parser( $slug, Friends_Feed_Parser $parser ) {
		if ( in_array( $slug, $this->reservered_parser_slugs, true ) ) {
			// translators: %s is the slug of a parser.
			return new WP_Error( 'resevered-slug', sprintf( __( 'The slug "%s" cannot be used.', 'friends' ), $slug ) );
		}
		if ( isset( $this->parsers[ $slug ] ) ) {
			// translators: %s is the slug of a parser.
			return new WP_Error( 'parser-already-registered', sprintf( __( 'There is already a parser registered with the slug "%s".', 'friends' ), $slug ) );
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
	 * @param      string $parser  The parser slug.
	 * @param      string $url     The url.
	 *
	 * @return     array  The feed items.
	 */
	public function preview( $parser, $url ) {
		if ( ! isset( $this->parsers[ $parser ] ) ) {
			return new WP_Error( 'unknown-parser', __( 'An unknown parser name was supplied.', 'friends' ) );
		}

		$items = $this->parsers[ $parser ]->fetch_feed( $url );

		if ( ! is_wp_error( $items ) && empty( $items ) ) {
			$items = new WP_Error( 'empty-feed', __( "This feed doesn't contain any entries. There might be a problem parsing the feed.", 'friends' ) );
		}

		return $items;
	}

	/**
	 * Retrieve posts from a remote WordPress for a friend.
	 *
	 * @param  Friend_User $friend_user A single user to fetch.
	 */
	public function retrieve_single_friend_posts( Friend_User $friend_user ) {
		foreach ( $friend_user->get_active_feeds() as $user_feed ) {
			$parser = $user_feed->get_parser();
			if ( ! isset( $this->parsers[ $parser ] ) ) {
				do_action( 'friends_retrieve_friends_error', $user_feed, new WP_Error( 'unknown-parser', __( 'An unknown parser name was supplied.', 'friends' ) ), $friend_user );
					continue;
			}
			$items = $this->parsers[ $parser ]->fetch_feed( $user_feed->get_private_url(), $user_feed );

			if ( is_wp_error( $items ) ) {
				do_action( 'friends_retrieve_friends_error', $user_feed, $items, $friend_user );
				continue;
			}

			$post_type = $user_feed->get_post_type();
			$cache_post_type = $this->friends->post_types->get_cache_post_type( $post_type );
			if ( ! isset( $new_posts[ $post_type ] ) ) {
				$new_posts[ $post_type ] = array();
			}

			$posts = $this->process_incoming_feed_items( $items, $user_feed, $cache_post_type );
			$new_posts[ $post_type ] = array_merge( $new_posts[ $post_type ], $posts );
			$this->notify_about_new_friend_posts( $friend_user, $user_feed, $new_posts );

			do_action( 'friends_retrieved_new_posts', $user_feed, $new_posts, $friend_user );
		}
		return $new_posts;
	}

	/**
	 * Notify users about new posts of this friend
	 *
	 * @param      Friend_User      $friend_user  The friend.
	 * @param      Friend_User_Feed $user_feed    The user feed.
	 * @param      array            $new_posts    The new posts of this friend.
	 */
	public function notify_about_new_friend_posts( Friend_User $friend_user, Friend_User_Feed $user_feed, $new_posts ) {
		if ( $friend_user->is_new() ) {
			$friend_user->set_not_new();
		} else {
			foreach ( $new_posts as $post_type => $posts ) {
				foreach ( $posts as $post_id ) {
					$notify_users = apply_filters( 'notify_about_new_friend_post', true, $friend_user, $post_id );
					if ( $notify_users ) {
						do_action( 'notify_new_friend_post', get_post( intval( $post_id ) ) );
					}
				}
			}
		}

	}

	/**
	 * Retrieve posts from all friend.
	 */
	public function retrieve_friend_posts() {
		$friends = new Friend_User_Query( array( 'role__in' => array( 'friend', 'acquaintance', 'pending_friend_request', 'subscription' ) ) );
		$friends = $friends->get_results();

		if ( empty( $friends ) ) {
			return;
		}

		foreach ( $friends as $friend_user ) {
			$this->retrieve_single_friend_posts( $friend_user );
		}
	}

	/**
	 * Apply the feed rules
	 *
	 * @param  object      $item         The feed item.
	 * @param  object      $feed         The feed object.
	 * @param  Friend_User $friend_user The friend user.
	 * @return object The modified feed item.
	 */
	public function apply_feed_rules( $item, $feed, Friend_User $friend_user ) {
		$rules  = $friend_user->get_feed_rules();
		$action = $friend_user->get_feed_catch_all();

		foreach ( $rules as $rule ) {
			$field = $this->get_feed_rule_field( $rule['field'], $item );

			if ( 'title' === $rule['field'] && ! isset( $item->$field ) ) {
				if ( ! ( $item instanceof WP_Post ) ) {
					$item->$field = $item->get_title();
				}
			}

			if ( 'author' === $rule['field'] && ! isset( $item->$field ) ) {
				if ( $item instanceof WP_Post ) {
					$item->$field = get_post_meta( get_the_ID( $item ), 'author', true );
				} else {
					$item->$field = $item->get_author()->name;
				}
			}

			if ( preg_match( '/' . $rule['regex'] . '/iu', $item->$field ) ) {
				if ( 'replace' === $rule['action'] ) {
					$item->$field = preg_replace( '/' . $rule['regex'] . '/iu', $rule['replace'], $item->$field );
					continue;
				}
				$action = $rule['action'];
				break;
			}
		}

		switch ( $action ) {
			case 'delete':
				$item->feed_rule_delete = true;
				return $item;

			case 'trash':
				$item->feed_rule_transform = array(
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
	 * @param  object $item  The feed item.
	 * @return string        The adapted field name.
	 */
	private function get_feed_rule_field( $field, $item ) {
		if ( $item instanceof WP_Post ) {
			switch ( $field ) {
				case 'title':
					return 'post_title';
				case 'permalink':
					return 'guid';
				case 'content':
					return 'post_content';
			}
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
						$rule[ $part ] = $keys[ $key ];
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
	 * @param  array            $items           The incoming items.
	 * @param  Friend_User_Feed $user_feed       The feed to which these items belong.
	 * @param  string           $cache_post_type The post type to be used for caching the feed items.
	 * @return array                             The post ids of the new posts.
	 */
	public function process_incoming_feed_items( array $items, Friend_User_Feed $user_feed, $cache_post_type ) {
		$friend_user     = $user_feed->get_friend_user();
		$remote_post_ids = $friend_user->get_remote_post_ids();
		$rules           = $friend_user->get_feed_rules();
		$post_formats    = get_post_format_strings();
		$feed_post_format = $user_feed->get_post_format();

		$new_posts = array();
		foreach ( $items as $item ) {
			if ( ! isset( $item->permalink ) ) {
				continue;
			}
			$permalink = str_replace( array( '&#38;', '&#038;' ), '&', ent2ncr( wp_kses_normalize_entities( $item->permalink ) ) );

			$title = '';
			if ( isset( $item->title ) ) {
				$title = $item->title;
			}
			$title = trim( $title );

			$content = '';
			if ( isset( $item->content ) ) {
				$content = $item->content;
			}
			$content = wp_kses_post( trim( $content ) );

			$item = apply_filters( 'friends_modify_feed_item', $item, $user_feed, $friend_user );
			if ( ! $item || ( isset( $item->feed_rule_delete ) && $item->feed_rule_delete ) ) {
				continue;
			}

			// Fallback, when no friends plugin is installed.
			$item->{'post-id'}     = $permalink;
			$item->{'post-status'} = 'publish';
			if ( ! isset( $item->comment_count ) ) {
				$item->comment_count = 0;
			}
			if ( ( ! $content && ! $title ) || ! $permalink ) {
				continue;
			}

			foreach ( array( 'gravatar', 'comments', 'post-status', 'post-format', 'post-id', 'reaction' ) as $key ) {
				if ( ! isset( $item->{$key} ) ) {
					$item->{$key} = false;
				}
			}

			$post_id = null;
			if ( isset( $remote_post_ids[ $item->{'post-id'} ] ) ) {
				$post_id = $remote_post_ids[ $item->{'post-id'} ];
			}
			if ( is_null( $post_id ) && isset( $remote_post_ids[ $permalink ] ) ) {
				$post_id = $remote_post_ids[ $permalink ];
			}

			if ( is_null( $post_id ) ) {
				$post_id = $this->url_to_postid( $permalink, $friend_user->ID );
			}

			$updated_date = $item->date;
			if ( isset( $item->updated_date ) ) {
				$updated_date = $item->updated_date;
			}

			$post_data = array(
				'post_title'        => $title,
				'post_content'      => $content,
				'post_modified_gmt' => $updated_date,
				'post_status'       => $item->{'post-status'},
				'guid'              => $permalink,
			);

			// Modified via feed rules.
			if ( isset( $item->feed_rule_transform ) ) {
				$post_data = array_merge( $post_data, $item->feed_rule_transform );
			}
			if ( ! is_null( $post_id ) ) {
				$post_data['ID'] = $post_id;
				wp_update_post( $post_data );
			} else {
				$post_data['post_author']   = $friend_user->ID;
				$post_data['post_type']     = $cache_post_type;
				$post_data['post_date_gmt'] = $item->date;
				$post_data['comment_count'] = $item->comment_count;
				$post_id                    = wp_insert_post( $post_data, true );
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

			if ( $item->reaction ) {
				$this->friends->reactions->update_remote_feed_reactions( $post_id, $item->reaction );
			}

			if ( is_numeric( $item->{'post-id'} ) ) {
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
		echo 'xmlns:friends="' . esc_attr( self::XMLNS ) . '"';
	}

	/**
	 * Additional fields for the friends feed.
	 */
	public function feed_additional_fields() {
		global $post;
		echo '<friends:post-format>' . esc_html( get_post_format( $post ) ) . '</friends:post-format>' . PHP_EOL;

		$authenticated_user_id = $this->friends->access_control->feed_is_authenticated();
		if ( ! $authenticated_user_id ) {
			return;
		}

		echo '<friends:gravatar>' . esc_html( get_avatar_url( $post->post_author ) ) . '</friends:gravatar>' . PHP_EOL;
		echo '<friends:post-status>' . esc_html( $post->post_status ) . '</friends:post-status>' . PHP_EOL;
		echo '<friends:post-id>' . esc_html( $post->ID ) . '</friends:post-id>' . PHP_EOL;

		$reactions = $this->friends->reactions->get_reactions( $post->ID, $authenticated_user_id );
		foreach ( $reactions as $slug => $reaction ) {
			echo '<friends:reaction';
			echo ' friends:slug="' . esc_attr( $slug ) . '"';
			echo ' friends:count="' . esc_attr( $reaction->count ) . '"';
			if ( $reaction->user_reacted ) {
				echo ' friends:you-reacted="1"';
			}
			echo '>' . esc_html( $reaction->usernames ) . '</friends:reaction>' . PHP_EOL;
		}
	}

	/**
	 * Offers the OPML file for download.
	 */
	public function friends_opml() {
		if ( ! isset( $_GET['friends'] ) || 'opml' !== $_GET['friends'] ) {
			return;
		}

		if ( ! isset( $_GET['auth'] ) || get_option( 'friends_private_rss_key' ) !== $_GET['auth'] ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'friends' ) );
		}

		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'friends' ) );
		}

		$friends = new Friend_User_Query( array( 'role__in' => array( 'friend', 'acquaintance', 'friend_request', 'subscription' ) ) );
		$feed    = $this->friends->feed;

		include apply_filters( 'friends_template_path', 'admin/opml.php' );
		exit;
	}

	/**
	 * Redirect
	 */
	public function friends_add_friend_redirect() {
		if ( ! isset( $_GET['add-friend'] ) ) {
			return;
		}

		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'friends' ) );
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

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$doc = new DOMDocument();
			if ( is_wp_error( $response ) ) {
				return array();
			}
			$content = wp_remote_retrieve_body( $response );
			$headers = wp_remote_retrieve_headers( $response );

			// We'll determine the obvious feeds ourself.
			$available_feeds = $this->discover_link_rel_feeds( $content, $url, $headers );
			$content_type = $headers->{'content-type'};
		}

		if ( empty( $available_feeds ) ) {
			$available_feeds[ $url ] = array(
				'url'  => $url,
				'type' => $content_type,
			);
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
				$confidence = $parser->feed_support_confidence( $link_url, $feed['type'], $feed['rel'] );
				if ( $available_feeds[ $link_url ]['parser_confidence'] < $confidence ) {
					$available_feeds[ $link_url ]['parser_confidence'] = $confidence;
					$available_feeds[ $link_url ]['parser'] = $slug;
				}
			}
		}

		foreach ( $available_feeds as $link_url => $feed ) {
			if ( ! isset( $this->parsers[ $feed['parser'] ] ) ) {
				continue;
			}
			$parser = $this->parsers[ $feed['parser'] ];
			$available_feeds[ $link_url ] = array_merge( $feed, $parser->update_feed_details( $feed ) );
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
			if ( $has_friends_plugin ) {
				// Prefer the main RSS feed.
				if ( 'feed' === trim( $path, '/' ) ) {
					$available_feeds[ $link_url ]['post-format'] = 'autodetect';
					$autoselected = true;
				}
			} elseif ( isset( $feed['rel'] ) && 'self' === $feed['rel'] ) {
				$autoselected = true;
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
		$mf = Friends_Mf2\parse( $content, $url );
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
	 * @param  WP_Query $query The main query.
	 * @return WP_Query The modified main query.
	 */
	public function private_feed_query( WP_Query $query ) {
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
			$friend = new Friend_User( $user_id );
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
