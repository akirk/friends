<?php
/**
 * Friends jsonfeed Parser
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
class Feed_Parser_JSON_Feed extends Feed_Parser_V2 {
	const NAME = 'JSON Feed';
	const URL = 'https://www.jsonfeed.org/';
	const SLUG = 'jsonfeed';

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
		$confidence = 10;

		$rewritten = $this->rewrite_known_url( $url );
		if ( $rewritten ) {
			$mime_type = $rewritten['type'];
			$confidence *= 2;
		}

		switch ( $mime_type ) {
			case 'application/feed+json':
				return $confidence;
			case 'application/json':
				if ( $content ) {
					return $confidence;

				}
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
			case 'micro.blog':
				if ( preg_match( '#/([^/]+)$#', $url, $m ) ) {
					return array(
						'title' => 'Micro.blog: ' . $m[1],
						'rel'   => 'alternate',
						'type'  => 'application/feed+json',
						'url'   => 'https://micro.blog/posts/' . $m[1],
					);
				}
				return array();
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
		return array();
	}

	/**
	 * Fetches a feed and returns the processed items.
	 *
	 * @param      string    $url        The url.
	 * @param      User_Feed $user_feed  The user feed.
	 *
	 * @return     array            An array of feed items.
	 */
	public function fetch_feed( $url, ?User_Feed $user_feed = null ) {
		$args = array();
		$res = wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$body = wp_remote_retrieve_body( $res );
		$json = json_decode( $body );

		return $this->parse_json( $json );
	}

	/**
	 * Parse the JSON object as a JSON Feed.
	 *
	 * @param      object $json   The parsed JSON.
	 *
	 * @return     array   An array of Feed items.
	 */
	public function parse_json( $json ) {
		if ( ! isset( $json->items ) ) {
			return null;
		}

		$feed_items = array();
		foreach ( $json->items as $item ) {
			$feed_item = new Feed_Item(
				array(
					'permalink' => $item->url,
				)
			);

			if ( isset( $item->content_html ) ) {
				$feed_item->content = $item->content_html;
			} elseif ( isset( $item->content_text ) ) {
				$feed_item->content = $item->content_text;
			}

			if ( isset( $item->title ) ) {
				$feed_item->title = $item->title;
			}
			if ( isset( $item->authors ) && isset( $item->authors[0] ) && is_object( $item->authors[0] ) ) {
				$feed_item->author = $item->authors[0]->name;
			}
			if ( isset( $item->date_published ) ) {
				$feed_item->date = $item->date_published;
			}
			if ( isset( $item->date_modified ) ) {
				$feed_item->updated_date = $item->date_modified;
				if ( ! isset( $feed_item->date ) ) {
					$feed_item->date = $feed_item->updated_date;
				}
			}
			$feed_items[] = $feed_item;
		}

		return $feed_items;
	}
}
