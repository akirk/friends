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
	 * @return     boolean  True if supported feed, False otherwise.
	 */
	public function is_supported_feed( $url, $mime_type, $title ) {
		return false;
	}

	/**
	 * Allow augmenting or modifying the details of a feed.
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
	 * @param      string           $url        The url.
	 * @param      Friend_User_Feed $user_feed  The user feed.
	 *
	 * @return     array            An array of feed items.
	 */
	public function fetch_feed( $url, Friend_User_Feed $user_feed ) {
		return array();
	}
}

