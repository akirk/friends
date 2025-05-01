<?php
/**
 * Friends Shortcodes
 *
 * This contains the functions for shortcodes.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Friends Plugin shortcodes.
 *
 * @since 0.8
 *
 * @package Friends
 * @author Alex Kirk
 */
class Shortcodes {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends = null;

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_shortcodes();
	}

	/**
	 * Register the WordPress shortcodes
	 */
	private function register_shortcodes() {
		add_shortcode( 'only-friends', '__return_empty_string' ); // deprecated after removal of friendships.
		add_shortcode( 'not-friends', '__return_empty_string' ); // deprecated after removal of friendships.
		add_shortcode( 'friends-list', '__return_empty_string' ); // deprecated after removal of friendships.
		add_shortcode( 'friends-count', array( $this, 'friends_count_shortcode' ) );
	}

	/**
	 * Display the number of your friends.
	 *
	 * @return string The content to be output.
	 */
	public function friends_count_shortcode() {
		$friends = User_Query::all_subscriptions();
		return $friends->get_total();
	}
}
