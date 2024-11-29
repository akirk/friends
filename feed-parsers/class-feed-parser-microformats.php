<?php
/**
 * Friends microformats Parser Wrapper
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
class Feed_Parser_Microformats extends Feed_Parser_V2 {
	const NAME = 'Microformats';
	const URL = 'https://www.microformats.org/';
	const SLUG = 'microformats';

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
		if ( ! $content ) {
			return 0;
		}
		$feeds = self::discover_available_feeds( $content, $url );
		if ( isset( $feeds[ $url ] ) ) {
			return 10;
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
		$feed_details['title'] = trim( str_replace( array( '&raquo; Feed', '» Feed' ), '', $feed_details['title'] ) );

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
		$discovered_feeds = array();
		$mf = Mf2\parse( $content, $url );
		if ( isset( $mf['rel-urls'] ) ) {
			foreach ( $mf['rel-urls'] as $feed_url => $link ) {
				foreach ( array( 'me', 'alternate' ) as $rel ) {
					if ( in_array( $rel, $link['rels'] ) ) {
						$discovered_feeds[ $feed_url ] = array(
							'rel' => $rel,
						);
					}
				}

				if ( ! isset( $discovered_feeds[ $feed_url ] ) ) {
					continue;
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

		if ( isset( $mf['items'] ) && ! isset( $discovered_feeds[ $url ] ) ) {
			$feed_items = $this->parse_hfeed( $mf );
			if ( count( $feed_items ) > 0 ) {
				$discovered_feeds[ $url ] = array(
					'type'        => 'text/html',
					'rel'         => 'self',
					'post-format' => 'autodetect',
					'parser'      => 'microformats',
				);
			}
		}

		return $discovered_feeds;
	}

	/**
	 * Parse an h-card.
	 *
	 * @param      array $data      The data.
	 * @param      bool  $category  It is a category.
	 *
	 * @return     string  The discovered value.
	 */
	private function parse_hcard( $data, $category = false ) {
		$name = '';
		$link = '';
		// Check if h-card is set and pass that information on in the link.
		if ( isset( $data['type'] ) && in_array( 'h-card', $data['type'] ) ) {
			if ( isset( $data['properties']['name'][0] ) ) {
				$name = $data['properties']['name'][0];
			}
			if ( isset( $data['properties']['url'][0] ) ) {
				$link = $data['properties']['url'][0];
				if ( '' === $name ) {
					$name = $link;
				} else {
					// can't have commas in categories.
					$name = str_replace( ',', '', $name );
				}
				$person_tag = $category ? '<span class="person-tag"></span>' : '';
				return '<a class="h-card" href="' . $link . '">' . $person_tag . $name . '</a>';
			}
		}
		return isset( $data['value'] ) ? $data['value'] : '';
	}

	/**
	 * Parse an h-feed.
	 *
	 * @param      array $mf     The microfeeds2 parser response.
	 *
	 * @return     array            An array of feed items.
	 */
	public function parse_hfeed( $mf ) {
		if ( ! is_array( $mf ) ) {
			return array();
		}
		$feed_items = array();
		$entries = array();

		// The following section is adapted from the SimplePie source.
		// 2020-11-30: Modifications: set post format, handle videos.
		// This should implement the https://www.w3.org/TR/post-type-discovery/ algorithm.

		$feed_author = null;
		$h_feed = array();
		foreach ( $mf['items'] as $mf_item ) {
			if ( in_array( 'h-feed', $mf_item['type'] ) ) {
				$h_feed = $mf_item;
				break;
			}
			// Also look for h-feed or h-entry in the children of each top level item.
			if ( ! isset( $mf_item['children'][0]['type'] ) ) {
				continue;
			}
			if ( in_array( 'h-feed', $mf_item['children'][0]['type'] ) ) {
				$h_feed = $mf_item['children'][0];
				// In this case the parent of the h-feed may be an h-card, so use it as
				// the feed_author.
				if ( in_array( 'h-card', $mf_item['type'] ) ) {
					$feed_author = $mf_item;
				}
				break;
			} elseif ( in_array( 'h-entry', $mf_item['children'][0]['type'] ) ) {
				$entries = $mf_item['children'];
				// In this case the parent of the h-entry list may be an h-card, so use
				// it as the feed_author.
				if ( in_array( 'h-card', $mf_item['type'] ) ) {
					$feed_author = $mf_item;
				}
				break;
			}
		}

		if ( isset( $h_feed['children'] ) ) {
			$entries = $h_feed['children'];
		} elseif ( empty( $entries ) ) {
			$entries = $mf['items'];
		}

		foreach ( $entries as $entry ) {
			if ( isset( $entry['properties']['deleted'][0] ) || ! isset( $entry['properties']['published'][0] ) ) {
				continue;
			}
			$item = new Feed_Item();
			$item->date = $entry['properties']['published'][0];
			$item->author = $feed_author;

			if ( isset( $entry['properties']['url'][0] ) ) {
				$link = $entry['properties']['url'][0];
				if ( isset( $link['value'] ) ) {
					$link = $link['value'];
				}
				$item->permalink = $link;
			}

			if ( isset( $entry['properties']['uid'][0] ) ) {
				$guid = $entry['properties']['uid'][0];
				if ( isset( $guid['value'] ) ) {
					$guid = $guid['value'];
				}
				$item->permalink = $guid;
			}

			if ( isset( $entry['properties']['name'][0] ) ) {
				$title = $entry['properties']['name'][0];
				if ( isset( $title['value'] ) ) {
					$title = $title['value'];
				}
				$item->title = $title;
			}

			$content = '';
			foreach ( array( 'photo', 'video' ) as $media ) {
				if ( ! isset( $entry['properties'][ $media ][0] ) ) {
					continue;
				}

				// If a $media is also in content, don't need to add it again here.
				if ( isset( $entry['properties']['content'][0]['html'] ) ) {
					$content = $entry['properties']['content'][0]['html'];
				}
				$el_list = array();
				for ( $j = 0, $l = count( $entry['properties'][ $media ] ); $j < $l; $j++ ) {
					$el = $entry['properties'][ $media ][ $j ];
					if ( ! empty( $el ) && strpos( $content, $el ) === false ) {
						$el_list[] = $el;
					}
				}

				if ( count( $el_list ) > 0 ) {
					$set_id = preg_replace( '/[[:^alnum:]]/', '', $el_list[0] );
					$content = '<p>';
					foreach ( $el_list as $j => $el ) {
						$hidden = 0 === $j ? '' : 'class="hidden" ';
						$content .= '<a href="' . $el . '" ' . $hidden .
						'data-lightbox="set-' . $set_id . '">';
						switch ( $media ) {
							case 'photo':
								$content .= '<img src="' . esc_url( $el ) . '" />';
								break;
							case 'video':
								$content .= '<video src="' . esc_url( $el ) . '" />';
								break;
						}
						$content .= '</a>';
					}
					$content .= '<br><b>';
					switch ( $media ) {
						case 'photo':
							// translators: %s is the number of photos.
							$content .= sprintf( _n( '%d photo', '%d photos', count( $el_list ), 'friends' ), count( $el_list ) );
							break;
						case 'video':
							// translators: %s is the number of videos.
							$content .= sprintf( _n( '%d video', '%d videos', count( $el_list ), 'friends' ), count( $el_list ) );
							break;
					}
					$content .= '</b></p>';
				}
				$item->format = $media;
			}

			if ( isset( $entry['properties']['content'][0]['html'] ) ) {
				$content .= $entry['properties']['content'][0]['html'];
				if ( isset( $entry['properties']['in-reply-to'][0] ) ) {
					$in_reply_to = '';
					if ( is_string( $entry['properties']['in-reply-to'][0] ) ) {
						$in_reply_to = $entry['properties']['in-reply-to'][0];
					} elseif ( isset( $entry['properties']['in-reply-to'][0]['value'] ) ) {
						$in_reply_to = $entry['properties']['in-reply-to'][0]['value'];
					}
					if ( '' !== $in_reply_to ) {
						$content .= '<p><span class="in-reply-to"></span> ' .
							'<a href="' . $in_reply_to . '">' . $in_reply_to . '</a><p>';
					}
				}
				$item->content = $content;
			}

			if ( isset( $entry['properties']['category'] ) ) {
				$category_csv = '';
				// Categories can also contain h-cards.
				foreach ( $entry['properties']['category'] as $category ) {
					if ( '' !== $category_csv ) {
						$category_csv .= ', ';
					}
					if ( is_string( $category ) ) {
						// Can't have commas in categories.
						$category_csv .= str_replace( ',', '', $category );
					} else {
						$category_csv .= $this->parse_hcard( $category, true );
					}
				}
				$item->category = $category_csv;
			}

			if ( ! $item->post_format && in_array( 'h-as-note', $entry['type'] ) ) {
				$item->post_format = 'status';
				$item->title = '';
			}

			$feed_items[] = $item;
		}

		return $feed_items;
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
		$mf = Mf2\fetch( $url );
		if ( ! $mf ) {
			// translators: %s is a URL.
			return new \WP_Error( 'microformats Parser', sprintf( __( 'Could not parse %s.', 'friends' ), $url ) );
		}

		return $this->parse_hfeed( $mf );
	}
}
