<?php
/**
 * Friend User Query Result
 *
 * Simple result container for filtered subscription queries.
 * Avoids creating a WP_User_Query which would load all WP users.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is a simple result container for filtered subscription queries.
 *
 * @since 4.0
 *
 * @package Friends
 * @author Alex Kirk
 */
class User_Query_Result {
	/**
	 * The results.
	 *
	 * @var array
	 */
	private $results;

	/**
	 * The total count.
	 *
	 * @var int
	 */
	private $total;

	/**
	 * Constructor.
	 *
	 * @param array $results The results.
	 * @param int   $total   The total count.
	 */
	public function __construct( $results, $total ) {
		$this->results = $results;
		$this->total   = $total;
	}

	/**
	 * Get results.
	 *
	 * @return array
	 */
	public function get_results() {
		return $this->results;
	}

	/**
	 * Get total.
	 *
	 * @return int
	 */
	public function get_total() {
		return $this->total;
	}
}
