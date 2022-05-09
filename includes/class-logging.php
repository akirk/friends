<?php
/**
 * Friends Logging
 *
 * This contains the functions for Logging.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Logging part of the Friends Plugin.
 *
 * @since 0.21
 *
 * @package Friends
 * @author Alex Kirk
 */
class Logging {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

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
		add_action( 'friends_retrieved_new_posts', array( $this, 'log_feed_successfully_fetched' ), 10, 3 );
		add_action( 'friends_retrieve_friends_error', array( $this, 'log_feed_error' ), 10, 2 );
	}

	/**
	 * Log that a feed was sucessfully fetched.
	 *
	 * @param      User_Feed $user_feed  The user feed.
	 * @param      array     $new_posts  The new posts that were fetched
	 *                                   (potentially empty array).
	 * @param      array     $modified_posts  Posts in the feed that weere modified
	 *                                   (potentially empty array).
	 */
	public function log_feed_successfully_fetched( User_Feed $user_feed, $new_posts, $modified_posts ) {
		// translators: %s is the number of new posts found.
		$last_log = sprintf( _n( 'Found %d new post.', 'Found %d new posts.', count( $new_posts ), 'friends' ), count( $new_posts ) );
		if ( $modified_posts ) {
			// translators: %s is the number of new posts found.
			$last_log .= ' ' . sprintf( _n( '%d post was modified.', '%d posts were modified.', count( $modified_posts ), 'friends' ), count( $modified_posts ) );
		}
		$user_feed->update_last_log( $last_log );
	}

	/**
	 * Log that an error occurred when fetching a feed.
	 *
	 * @param      User_Feed $user_feed  The user feed.
	 * @param      \WP_Error $error      The error that occurred when
	 *                                  fetching the feed.
	 */
	public function log_feed_error( User_Feed $user_feed, $error ) {
		$user_feed->update_last_log( $error->get_error_message() );
	}
}
