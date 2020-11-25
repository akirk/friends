<?php
/**
 * Friends SimplePie Parser Wrapper
 *
 * With this parser, we can import RSS and Atom Feeds for a friend.
 *
 * @package Friends
 */

/**
 * This is the class for the feed part of the Friends Plugin.
 *
 * @since 1.0
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Feed_Parser_SimplePie extends Friends_Feed_Parser {
	/**
	 * Determines if this is a supported feed.
	 *
	 * @param      string $url        The url.
	 * @param      string $mime_type  The mime type.
	 * @param      string $title      The title.
	 *
	 * @return     boolean  True if supported feed, False otherwise.
	 */
	public function is_supported_feed( $url, $mime_type, $title ) {
		switch ( $mime_type ) {
			case 'application/rss+xml':
			case 'application/atom+xml':
				return true;
		}

		return false;
	}

	/**
	 * Format the feed title and autoselect the posts feed.
	 *
	 * @param      array $feed_details  The feed details.
	 *
	 * @return     array  The (potentially) modified feed details.
	 */
	public function update_feed_details( $feed_details ) {
		$feed_details['title'] = trim( str_replace( array( '&raquo; Feed', '» Feed' ), '', $feed_details['title'] ) );

		$feed_details['autoselect'] = true;
		if ( stripos( $feed_details['url'], 'comments' ) !== false ) {
			$feed_details['autoselect'] = false;
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
	 * Discover the feeds available at the URL specified.
	 *
	 * @param      string $content  The content for the URL is already provided here.
	 * @param      string $url      The url to search.
	 *
	 * @return     array  A list of supported feeds at the URL.
	 */
	public function discover_available_feeds( $content, $url ) {
		if ( ! class_exists( 'SimplePie', false ) ) {
			require_once ABSPATH . WPINC . '/class-simplepie.php';
		}

		require_once ABSPATH . WPINC . '/class-wp-feed-cache.php';
		require_once ABSPATH . WPINC . '/class-wp-feed-cache-transient.php';
		require_once ABSPATH . WPINC . '/class-wp-simplepie-file.php';
		require_once ABSPATH . WPINC . '/class-wp-simplepie-sanitize-kses.php';

		$feed = new SimplePie();

		$feed->set_sanitize_class( 'WP_SimplePie_Sanitize_KSES' );
		// We must manually overwrite $feed->sanitize because SimplePie's
		// constructor sets it before we have a chance to set the sanitization class.
		$feed->sanitize = new WP_SimplePie_Sanitize_KSES();

		$feed->set_cache_class( 'WP_Feed_Cache' );
		$feed->set_file_class( 'WP_SimplePie_File' );

		$feed->set_raw_data( $content );

		$feed->init();
		$feed->set_output_encoding( get_option( 'blog_charset' ) );

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
					'type'  => $mime_type,
					'title' => $feed->get_title(),
					'rel'   => 'alternate',
				);
			}
		}

		return $discovered_feeds;
	}

	/**
	 * Fetches a feed and returns the processed items.
	 *
	 * @param      string $url        The url.
	 *
	 * @return     array            An array of feed items.
	 */
	public function fetch_feed( $url ) {
		// Use SimplePie which is bundled with WordPress.
		$feed = fetch_feed( $url );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		foreach ( $feed->get_items() as $item ) {
			$feed_item = (object) array(
				'permalink' => $item->get_permalink(),
				'title'     => $item->get_title(),
				'content'   => $item->get_content(),
			);

			foreach ( array( 'gravatar', 'comments', 'post-status', 'post-id', 'reaction' ) as $key ) {
				foreach ( array( Friends_Feed::XMLNS, 'com-wordpress:feed-additions:1' ) as $xmlns ) {
					if ( ! isset( $item->data['child'][ $xmlns ][ $key ][0]['data'] ) ) {
						continue;
					}

					if ( 'reaction' === $key ) {
						$feed_item->reaction = $item->data['child'][ $xmlns ][ $key ];
						break;
					}

					$feed_item->{$key} = $item->data['child'][ $xmlns ][ $key ][0]['data'];
					break;
				}
			}

			$feed_item->comments_count = isset( $item->data['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'] ) ? $item->data['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'] : 0;

			$feed_item->date         = $item->get_gmdate( 'Y-m-d H:i:s' );
			$feed_item->updated_date = $item->get_updated_gmdate( 'Y-m-d H:i:s' );

			$feed_items[] = $feed_item;
		}

		return $feed_items;
	}
}
