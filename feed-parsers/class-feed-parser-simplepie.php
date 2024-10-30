<?php
/**
 * Friends SimplePie Parser Wrapper
 *
 * With this parser, we can import RSS and Atom Feeds for a friend.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the feed part of the Friends Plugin.
 *
 * @since 1.0
 *
 * @package Friends
 * @author Alex Kirk
 */
class Feed_Parser_SimplePie extends Feed_Parser_V2 {
	const NAME = 'SimplePie';
	const URL = 'http://simplepie.org';
	const SLUG = 'simplepie';

	private $friends_feed;

	/**
	 * Constructor.
	 *
	 * @param      Feed $friends_feed  The friends feed.
	 */
	public function __construct( Feed $friends_feed ) {
		$this->friends_feed = $friends_feed;

		\add_filter( 'friends_get_comments', array( $this, 'get_comments' ), 10, 4 );
		\add_filter( 'friends_no_comments_feed_available', array( $this, 'no_comments_feed_available' ), 10, 2 );
		\add_filter( 'friends_modify_feed_item', array( $this, 'podcast_support' ), 10, 4 );
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
		$rewritten = $this->rewrite_known_url( $url );
		if ( $rewritten ) {
			$mime_type = $rewritten['type'];
		}

		switch ( $mime_type ) {
			case 'application/rss+xml':
			case 'application/atom+xml':
				return 10;
		}

		switch ( $mime_type ) {
			case 'application/xml':
				return 5;
		}

		return 0;
	}

	/**
	 * Rewrite known URLs to their RSS feeds.
	 *
	 * @param      string $url    The url.
	 *
	 * @return     array  An equivalent link array.
	 */
	public function rewrite_known_url( $url ) {
		$host = wp_parse_url( strtolower( $url ), PHP_URL_HOST );

		switch ( $host ) {
			case 'www.youtube.com':
			case 'youtube.com':
				if ( preg_match( '#/channel/([^?&$]+)#i', $url, $m ) ) {
					return array(
						'title'       => 'Youtube',
						'rel'         => 'alternate',
						'type'        => 'application/rss+xml',
						'url'         => 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1],
						'post-format' => 'video',
					);
				}
				if ( preg_match( '#/user/([^?&$]+)#i', $url, $m ) ) {
					return array(
						'title'       => 'Youtube',
						'rel'         => 'alternate',
						'type'        => 'application/rss+xml',
						'url'         => 'https://www.youtube.com/feeds/videos.xml?user=' . $m[1],
						'post-format' => 'video',
					);
				}
				return array();
			case 'github.com':
				return array(
					'rel'         => 'alternate',
					'type'        => 'application/atom+xml',
					'url'         => $url,
					'post-format' => 'aside',
				);
		}

		return array();
	}

	/**
	 * Format the feed title and autoselect the posts feed.
	 *
	 * @param      array $feed_details  The feed details.
	 *
	 * @return     array  The (potentially) modified feed details.
	 */
	public function update_feed_details( $feed_details ) {
		$rewritten = $this->rewrite_known_url( $feed_details['url'] );
		if ( $rewritten ) {
			$feed_details = $rewritten;
		}

		foreach ( get_post_format_strings() as $format => $title ) {
			if ( preg_match( '/\b' . preg_quote( $format, '/' ) . '\b/i', $feed_details['url'] ) ) {
				$feed_details['post-format'] = $format;
				break;
			}
		}

		return $feed_details;
	}

	/**
	 * Instanciate SimplePie the same way WordPress does it.
	 *
	 * @return     SimplePie  A simplepie instance.
	 */
	private function get_simplepie() {
		if ( ! class_exists( 'SimplePie', false ) ) {
			require_once ABSPATH . WPINC . '/class-simplepie.php';
		}

		require_once ABSPATH . WPINC . '/class-wp-feed-cache-transient.php';
		require_once ABSPATH . WPINC . '/class-wp-simplepie-file.php';
		require_once __DIR__ . '/SimplePie/class-simplepie-file-accept-only-rss.php';
		require_once __DIR__ . '/SimplePie/class-simplepie-misc.php';
		require_once ABSPATH . WPINC . '/class-wp-simplepie-sanitize-kses.php';

		// Workaround for SimplePie assuming that CURL is loaded.
		if ( ! defined( 'CURLOPT_USERAGENT' ) ) {
			define( 'CURLOPT_USERAGENT', 10018 );
		}

		$feed = new \SimplePie();

		$feed->set_sanitize_class( '\WP_SimplePie_Sanitize_KSES' );
		// We must manually overwrite $feed->sanitize because SimplePie's
		// constructor sets it before we have a chance to set the sanitization class.
		$feed->sanitize = new \WP_SimplePie_Sanitize_KSES();

		\SimplePie_Cache::register( 'wp_transient', '\WP_Feed_Cache_Transient' );
		$feed->set_cache_location( 'wp_transient' );

		$registry = $feed->get_registry();
		$registry->register( 'Misc', __NAMESPACE__ . '\SimplePie_Misc' );

		return $feed;
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
		$feed = $this->get_simplepie();
		do_action_ref_array( 'wp_feed_options', array( &$feed, $url ) );

		$feed->set_raw_data( $content );

		$feed->set_output_encoding( get_option( 'blog_charset' ) );

		$feed->init();

		if ( $feed->error() ) {
			return array();
		}

		$discovered_feeds = array();

		$items = $feed->get_items();
		if ( count( $items ) ) {
			// This is a feed.
			$mime_type = false;
			if ( $feed->get_type() & SIMPLEPIE_TYPE_RSS_ALL ) {
				$mime_type = 'application/rss+xml';
			} elseif ( $feed->get_type() & SIMPLEPIE_TYPE_ATOM_ALL ) {
				$mime_type = 'application/atom+xml';
			}

			if ( $mime_type ) {
				$feed_url = $feed->subscribe_url();
				if ( ! $feed_url ) {
					$feed_url = $url;
				}

				$discovered_feeds[ $feed_url ] = array(
					'type'       => $mime_type,
					'title'      => $feed->get_title(),
					'parser'     => 'simplepie',
					'rel'        => 'self',
					'autoselect' => true,
				);
			}
		}

		return $discovered_feeds;
	}

	/**
	 * Fetches a feed and returns the processed items.
	 *
	 * @param      string    $url        The url.
	 * @param      User_Feed $user_feed  The user feed.
	 *
	 * @return     array            An array of feed items.
	 */
	public function fetch_feed( $url, User_Feed $user_feed = null ) {
		// Use SimplePie which is bundled with WordPress.
		$feed = $this->get_simplepie();

		$host = wp_parse_url( strtolower( $url ), PHP_URL_HOST );

		switch ( $host ) {
			case 'github.com':
				$feed->set_file_class( __NAMESPACE__ . '\SimplePie_File_Accept_Only_RSS' );
				break;
			default:
				$feed->set_file_class( '\WP_SimplePie_File' );
		}
		/**
		 * Maybe Rewrite a URL
		 *
		 * Allows modifying the URL before fetching it.
		 *
		 * @param string $url The URL to fetch.
		 * @param Feed_Parser_V2 $parser The parser instance.
		 */
		$feed->set_feed_url( $url );
		$feed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', HOUR_IN_SECONDS - 600, $url ) );

		do_action_ref_array( 'wp_feed_options', array( &$feed, $url ) );

		$feed->init();

		$feed->set_output_encoding( get_option( 'blog_charset' ) );

		if ( $feed->error() ) {
			return new \WP_Error( 'simplepie-error', $feed->error() );
		}

		return $this->process_items( $feed->get_items(), $url );
	}

	/**
	 * Process the feed items.
	 *
	 * @param      array  $items  The items.
	 * @param      string $url    The url.
	 *
	 * @return     array  The processed feed items.
	 */
	public function process_items( $items, $url ) {
		$feed_items = array();
		foreach ( $items as $item ) {
			/* See https://www.rssboard.org/rss-encoding-examples */
			$title = $item->get_title();
			if ( $title ) {
				$title = htmlspecialchars_decode( $title );
				if ( $title ) {
					$title = \html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
				}
			}

			$feed_item = new Feed_Item(
				array(
					'permalink' => $item->get_permalink(),
					'title'     => $title,
					'content'   => $this->convert_relative_urls_to_absolute_urls( $item->get_content(), $url ),
				)
			);

			foreach ( array(
				'gravatar'      => 'gravatar',
				'comment_count' => 'comments',
				'status'        => 'post-status',
				'post_format'   => 'post-format',
				'id'            => 'post-id',
			) as $key => $lookup_key ) {
				foreach ( array( Feed::XMLNS, 'com-wordpress:feed-additions:1' ) as $xmlns ) {
					if ( ! isset( $item->data['child'][ $xmlns ][ $lookup_key ][0]['data'] ) ) {
						continue;
					}

					$feed_item->{$key} = $item->data['child'][ $xmlns ][ $lookup_key ][0]['data'];
					break;
				}
			}

			foreach ( array(
				'http://purl.org/rss/1.0/modules/slash/' => array(
					'comment_count' => 'comments',
				),
				'http://wellformedweb.org/CommentAPI/'   => array(
					'comments_feed' => 'commentRss',
				),
			) as $xmlns => $keys ) {
				foreach ( $keys as $key => $lookup_key ) {
					if ( ! isset( $item->data['child'][ $xmlns ][ $lookup_key ][0]['data'] ) ) {
						continue;
					}

					$feed_item->{$key} = $item->data['child'][ $xmlns ][ $lookup_key ][0]['data'];
					break;
				}
			}

			foreach ( $item->get_enclosures() as $enclosure ) {
				if ( ! isset( $enclosure->link ) ) {
					continue;
				}

				$feed_item->enclosure = array_filter(
					array(
						'url'    => $enclosure->get_link(),
						'type'   => $enclosure->get_type(),
						'length' => $enclosure->get_length(),
					)
				);
			}

			if ( is_object( $item->get_author() ) ) {
				$feed_item->author = \wp_strip_all_tags( $item->get_author()->name );
			}

			$feed_item->date         = $item->get_gmdate( 'U' );
			$feed_item->updated_date = $item->get_updated_gmdate( 'U' );

			$feed_items[] = $feed_item;
		}

		return $feed_items;
	}

	public function no_comments_feed_available( $text, $post_id ) {
		if ( User_Feed::get_parser_for_post_id( $post_id ) !== self::SLUG ) {
			return $text;
		}

		$comments_url = get_post_meta( $post_id, Feed::COMMENTS_FEED_META, true );
		if ( ! $comments_url ) {
			return __( 'We tried to load comments remotely but there was no comments feed available.', 'friends' );
		}
		return $text;
	}

	/**
	 * Get comments from a feed.
	 *
	 * @param      array     $comments    The comments.
	 * @param      int       $post_id     The post id.
	 * @param      User      $friend_user The friend user.
	 * @param      User_Feed $user_feed   The user feed.
	 *
	 * @return     array  The comments.
	 */
	public function get_comments( $comments, $post_id, User $friend_user = null, User_Feed $user_feed = null ) {
		$comments_url = get_post_meta( $post_id, Feed::COMMENTS_FEED_META, true );
		if ( ! $comments_url || ! $friend_user || ! $user_feed ) {
			return $comments;
		}

		if ( ( $friend_user->is_friend_url( $comments_url ) && Friends::has_required_privileges() ) || wp_doing_cron() ) {
			$comments_url = apply_filters( 'friends_friend_private_feed_url', $comments_url, $friend_user );
			$comments_url = Friends::get_instance()->access_control->append_auth( $comments_url, $friend_user, 300 );
		}

		$items = $this->fetch_feed( $comments_url, $user_feed );

		if ( is_wp_error( $items ) ) {
			return $comments;
		}

		foreach ( $items as $key => $item ) {
			if ( is_wp_error( $item ) ) {
				unset( $items[ $key ] );
				continue;
			}
			$item = apply_filters( 'friends_modify_feed_item', $item, $user_feed, $friend_user, null );

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

		return array_merge( $comments, $items );
	}

	public function podcast_support( $item, $user_feed, $friend_user, $post_id ) {
		if (
			$item->enclosure
			&& isset( $item->enclosure['url'] )
			&& false === strpos( $item->post_content, '<!-- wp:audio -->' )
		) {
			$audio_block  = '<!-- wp:audio -->';
			$audio_block .= PHP_EOL;
			$audio_block .= '<figure class="wp-block-audio"><audio controls src="';
			$audio_block .= esc_url( $item->enclosure['url'] );
			$audio_block .= '"></audio></figure>';
			$audio_block .= PHP_EOL;
			$audio_block .= '<!-- /wp:audio -->';
			$audio_block .= PHP_EOL;
			$audio_block .= PHP_EOL;

			$item->post_content = $audio_block . $item->post_content;

		}
		return $item;
	}
}
