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
abstract class Feed_Parser_V2 extends Feed_Parser {

	/**
	 * Fetches a feed and returns the processed items.
	 *
	 * Return an array of objects:
	 *
	 *  return array(
	 *      new Feed_Item array(
	 *          'permalink' => 'https://url.of/the/feed/item',
	 *          'title'     => 'Title for the feed item',
	 *          'content'   => 'Content for the feed item',
	 *          'date'      => gmdate( 'Y-m-d H:i:s' ),
	 *          // Optional fields:
	 *          'comment_count' => 0,
	 *          'gravatar' => 'https://url/icon.png',
	 *          'post_status' => 'publish', // A WordPress post status (e.g. publish or private).
	 *          'post_id' => 123, // the id of the post for better update/duplicate detection.
	 *          'updated_date' => gmdate( 'Y-m-d H:i:s' ),
	 *      ),
	 *  );
	 *
	 * @param      string    $url        The url.
	 * @param      User_Feed $user_feed  The user feed.
	 *
	 * @return     array            An array of feed items.
	 */
	public function fetch_feed( $url, User_Feed $user_feed = null ) {
		return array();
	}
}
