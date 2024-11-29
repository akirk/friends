<?php
/**
 * Friends Parser Tester
 *
 * This is a class that helps with testing feed parsers.
 *
 * @since @2.1.5
 *
 * @package Friends
 * @author Alex Kirk
 */

namespace Friends;

/**
 * This is the class for testing feed parsing.
 */
class Feed_Parser_Local_File extends Feed_Parser_SimplePie {
	const SLUG = 'local';
	/**
	 * Constructor.
	 *
	 * @param      Feed $friends_feed  The friends feed.
	 */
	public function __construct( Feed $friends_feed ) {
	}

	public function fetch_feed( $url, ?User_Feed $user_feed = null ) {
		$file = new \SimplePie_File( $url );
		$feed = new \SimplePie();
		$feed->set_file( $file );
		$feed->init();

		return $this->process_items( $feed->get_items(), $url );
	}
}
