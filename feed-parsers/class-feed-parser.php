<?php
/**
 * Friends Feed Parser
 *
 * This contains the reference implementation for a parser.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This class describes a friends feed parser.
 */
abstract class Feed_Parser {
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
	public function feed_support_confidence( $url, $mime_type, $title, $content = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return 0;
	}

	/**
	 * Allow augmenting or modifying the details of a feed.
	 *
	 * The incoming $feed_details array looks like this:
	 *
	 *  $feed_details = array(
	 *      'url'         => 'https://url.of/the/feed',
	 *      'title'       => 'Title from the <link> tag if any',
	 *      'mime-type'   => 'mime-type from the <link> tag if any',
	 *      // You can add these fields in the response:
	 *      'autoselect'  => true|false,
	 *      'post-format' => 'standard', // or 'aside', etc. see get_post_format_strings() of WordPress core
	 *  );
	 *
	 * @param      array $feed_details  The feed details.
	 *
	 * @return     array  The (potentially) modified feed details.
	 */
	public function update_feed_details( $feed_details ) {
		return $feed_details;
	}

	/**
	 * Discover the feeds available at the URL specified.
	 *
	 * The content for the URL has already been fetched for you which can be analyzed.
	 *
	 * Return an array of supported feeds in the format of the $feed_details above:
	 *
	 *  return array(
	 *      array(
	 *          'url'       => 'https://url.of/the/feed',
	 *          'title'     => 'Title for the feed',
	 *          'mime-type' => 'mime-type for the feed',
	 *          'rel'       => 'e.g. alternate',
	 *      ),
	 *  );
	 *
	 * @param      string $content  The content for the URL is already provided here.
	 * @param      string $url      The url to search.
	 *
	 * @return     array  A list of supported feeds at the URL.
	 */
	public function discover_available_feeds( $content, $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return array();
	}

	/**
	 * Convert relative URLs to absolute ones in incoming content.
	 *
	 * @param      string $html   The html.
	 * @param      string $permalink  The permalink of the feed.
	 *
	 * @return     string  The HTML with URLs replaced to their absolute represenation.
	 */
	public function convert_relative_urls_to_absolute_urls( $html, $permalink ) {
		if ( ! $html ) {
			$html = '';
		}

		// Strip off the hash.
		$permalink = strtok( $permalink, '#' );

		// For now this only converts links and image srcs.
		return preg_replace_callback(
			'~(src|href)=(?:"([^"]+)|\'([^\']+))~i',
			function ( $m ) use ( $permalink ) {
				// Don't update hash-only links.
				if ( str_starts_with( $m[2], '#' ) ) {
					return $m[0];
				}

				// Remove absolute URL from hashes so that it can become relative.
				if ( str_starts_with( $m[2], $permalink . '#' ) ) {
					return str_replace( $permalink, '', $m[0] );
				}

				// Don't convert content URLs like data:image/png;base64, etc.
				if ( str_starts_with( $m[2], 'data:' ) ) {
					return $m[0];
				}

				// Convert relative URLs to absolute ones.
				return str_replace( $m[2], Mf2\resolveUrl( $permalink, $m[2] ), $m[0] );
			},
			$html
		);
	}
}
