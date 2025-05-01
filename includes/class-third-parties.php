<?php
/**
 * Friends 3rd Parties
 *
 * This contains the functions for working with third party plugins.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Friends Plugin 3rd Party handling.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Third_Parties {
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
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_filter( 'wp_sweep_excluded_taxonomies', array( $this, 'wp_sweep_excluded_taxonomies' ) );
	}

	/**
	 * WP Sweep
	 * Prevent WP Sweep from sweeping our taxonomies.
	 *
	 * @since 2.0
	 *
	 * @param array $excluded_taxonomies list of taxonomies excluded from sweeping.
	 * @return array
	 */
	public function wp_sweep_excluded_taxonomies( $excluded_taxonomies ) {
		return array_merge( $excluded_taxonomies, array( User_Feed::TAXONOMY, User_Feed::POST_TAXONOMY, Subscription::TAXONOMY ) );
	}
}
