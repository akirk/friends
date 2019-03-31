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
		add_action( 'friends_retrieve_friends_error', array( $this, 'log_feed_error' ), 10, 3 );
	}

	/**
	 * Log that a feed was sucessfully fetched.
	 *
	 * @param array   $new_posts   The new posts that were fetched (potentially empty array).
	 * @param WP_User $friend_user The user for which the feed was fetched.
	 */
	public function log_feed_successfully_fetched( $new_posts, $friend_user ) {
		update_user_option( $friend_user->ID, 'friends_last_feed_retrieval', date( 'Y-m-d H:i:s' ) );
		update_user_option(
			$friend_user->ID,
			'friends_last_feed_retrieval_message',
			// translators: %s is the number of new posts found.
			sprintf( _n( 'Found %d new post.', 'Found %d new posts.', count( $new_posts ), 'friends' ), count( $new_posts ) )
		);

	}

	/**
	 * Log that an error occurred when fetching a feed.
	 *
	 * @param string   $feed_url    The feed url that was fetched.
	 * @param WP_Error $error       The error that occurred when fetching the feed.
	 * @param WP_User  $friend_user The user for which the feed was fetched.
	 */
	public function log_feed_error( $feed_url, $error, $friend_user ) {
		update_user_option( $friend_user->ID, 'friends_last_feed_retrieval', date( 'Y-m-d H:i:s' ) );
		update_user_option(
			$friend_user->ID,
			'friends_last_feed_retrieval_message',
			$error->get_error_message()
		);
	}
}
