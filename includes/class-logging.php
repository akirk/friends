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
	 * The custom post type for logging.
	 *
	 * @var string
	 */
	const CPT = 'friends_log';

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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'friends_retrieved_new_posts', array( $this, 'log_feed_successfully_fetched' ), 10, 3 );
		add_action( 'friends_retrieve_friends_error', array( $this, 'log_feed_error' ), 10, 2 );
		add_action( 'friends_log', array( $this, 'log_entry' ), 10, 2 );
	}

	/**
	 * Register the custom post type for logging.
	 */
	public function register_post_type() {
		$args = array(
			'labels'       => array(
				'name'          => __( 'Friends Logs', 'friends' ),
				'singular_name' => __( 'Friends Log', 'friends' ),
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_menu' => false,
			'supports'     => array( 'title', 'editor' ),
		);
		register_post_type( self::CPT, $args );

		register_post_meta(
			self::CPT,
			'type',
			array(
				'type'   => 'string',
				'single' => true,
			)
		);
		register_post_meta(
			self::CPT,
			'module',
			array(
				'type'   => 'string',
				'single' => true,
			)
		);
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
			$last_log .= ' ' . sprintf( _n( '%d post was modified.', '%d posts were modified.', count( $modified_posts ), 'friends' ), count( $modified_posts ) ) . ' (' . implode( ', ', array_slice( $modified_posts, 0, 5 ) ) . ')';
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

	/**
	 * Save a log message.
	 *
	 * @param     string $type    The type of log message.
	 * @param     string $message The message.
	 * @param     array  $details The details of the log message.
	 * @param     string $module  The module that generated the log message.
	 * @param     int    $user_id The ID of the user that generated the log message.
	 * @return    int    The ID of the log post.
	 */
	public static function log( $type, $message, $details, $module, $user_id ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => self::CPT,
				'post_title'   => $message,
				'post_content' => str_replace( '\\', '\\\\', wp_json_encode( $details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ),
				'post_author'  => $user_id,
				'post_status'  => 'publish',
			)
		);

		add_post_meta( $post_id, 'type', $type );
		add_post_meta( $post_id, 'module', $module );

		return $post_id;
	}

	public static function get_logs() {
		$logs = get_posts(
			array(
				'post_type'      => self::CPT,
				'posts_per_page' => 100,
			)
		);
		return $logs;
	}
}
