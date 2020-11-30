<?php
/**
 * Friends Logging
 *
 * This contains the functions for Logging.
 *
 * @package Friends
 */

/**
 * This is the class for the Logging part of the Friends Plugin.
 *
 * @since 0.21
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Logging {
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
		add_action( 'friends_retrieved_new_posts', array( $this, 'log_feed_successfully_fetched' ), 10, 2 );
		add_action( 'friends_retrieve_friends_error', array( $this, 'log_feed_error' ), 10, 2 );
	}

	/**
	 * Log that a feed was sucessfully fetched.
	 *
	 * @param      Friend_User_Feed $user_feed  The user feed.
	 * @param      array            $new_posts  The new posts that were fetched
	 *                                          (potentially empty array).
	 */
	public function log_feed_successfully_fetched( Friend_User_Feed $user_feed, $new_posts ) {
		$count = 0;
		if ( is_array( $new_posts ) ) {
			foreach ( $new_posts as $post_type => $posts ) {
				$count += count( $posts );
			}
		}
		// translators: %s is the number of new posts found.
		$user_feed->update_last_log( sprintf( _n( 'Found %d new post.', 'Found %d new posts.', $count, 'friends' ), $count ) );
	}

	/**
	 * Log that an error occurred when fetching a feed.
	 *
	 * @param      Friend_User_Feed $user_feed  The user feed.
	 * @param      WP_Error         $error      The error that occurred when
	 *                                          fetching the feed.
	 */
	public function log_feed_error( Friend_User_Feed $user_feed, $error ) {
		$user_feed->update_last_log( $error->get_error_message() );
	}
}
