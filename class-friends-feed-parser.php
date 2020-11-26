<?php
/**
 * Friends Feed Parser
 *
 * This contains the reference implementation for a parser.
 *
 * @package Friends
 */

/**
/**
 * This class describes a friends feed parser.
 */
abstract class Friends_Feed_Parser {
	/**
	 * Determines if this is a supported feed.
	 *
	 * @param      string $url        The url.
	 * @param      string $mime_type  The mime type.
	 * @param      string $title      The title.
	 *
	 * @return     boolean  Return True if supported feed, False otherwise.
	 */
	public function is_supported_feed( $url, $mime_type, $title ) {
		return false;
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
	public function discover_available_feeds( $content, $url ) {
		return array();
	}

	/**
	 * Fetches a feed and returns the processed items.
	 *
	 * Return an array of objects:
	 *
	 *  return array(
	 *      (object) array(
	 *          'permalink' => 'https://url.of/the/feed/item',
	 *          'title'     => 'Title for the feed item',
	 *          'content'   => 'Content for the feed item',
	 *          'date'      => gmdate( 'Y-m-d H:i:s' ),
	 *          // Optional fields:
	 *          'comments_count' => 0,
	 *          'gravatar' => 'https://url/icon.png',
	 *          'post-status' => 'publish', // A WordPress post status (e.g. publish or private).
	 *          'post-id' => 123, // the id of the post for better update/duplicate detection.
	 *          'reaction' => '', // see Friends_Feed::feed_additional_fields()
	 *          'updated_date' => gmdate( 'Y-m-d H:i:s' ),
	 *      ),
	 *  );
	 *
	 * @param      string $url        The url.
	 *
	 * @return     array            An array of feed items.
	 */
	public function fetch_feed( $url ) {
		return array();
	}
}

