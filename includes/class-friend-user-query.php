<?php
/**
 * Friend User Query
 *
 * This wraps WP_User_Query and so that it generates instances of Friend_User.
 *
 * @package Friends
 */

/**
 * This is the class for the User part of the Friends Plugin.
 *
 * @since 0.21
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friend_User_Query extends WP_User_Query {
	/**
	 * Whether to cache the retrieved users
	 *
	 * @var boolean
	 */
	public static $cache = true;

	/**
	 * List of found Friend_User objects.
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * Execute the query and ensure that we populate Friend_User objects
	 */
	public function query() {
		parent::query();
		foreach ( parent::get_results() as $k => $user ) {
			$this->results[ $k ] = new Friend_User( $user );
		}
	}

	/**
	 * Return the list of users.
	 *
	 * @return array Array of results.
	 */
	public function get_results() {
		return $this->results;
	}

	/**
	 * Gets all friends.
	 */
	public static function all_friends() {
		static $all_friends;
		if ( ! self::$cache || ! isset( $all_friends ) ) {
			$all_friends = new self(
				array(
					'role__in' => array( 'friend', 'acquaintance' ),
					'order'    => 'ASC',
					'orderby'  => 'display_name',
				)
			);
		}
		return $all_friends;
	}

	/**
	 * Gets all friends.
	 */
	public static function all_friends_subscriptions() {
		static $all_friends;
		if ( ! self::$cache || ! isset( $all_friends ) ) {
			$all_friends = new self(
				array(
					'role__in' => array( 'friend', 'acquaintance', 'pending_friend_request', 'subscription' ),
					'order'    => 'ASC',
					'orderby'  => 'display_name',
				)
			);
		}
		return $all_friends;
	}

	/**
	 * Search friends.
	 *
	 * @param      string $query  The query.
	 *
	 * @return     self    The search result.
	 */
	public static function search( $query ) {
		return new self(
			array(
				'role__in'       => array( 'friend', 'acquaintance', 'pending_friend_request', 'subscription' ),
				'order'          => 'ASC',
				'orderby'        => 'display_name',
				'search'         => $query,
				'search_columns' => 'display_name',
			)
		);
	}

	/**
	 * Gets all friend requests.
	 */
	public static function all_friend_requests() {
		static $all_friend_requests;
		if ( ! self::$cache || ! isset( $all_friend_requests ) ) {
			$all_friend_requests = new self(
				array(
					'role'    => 'friend_request',
					'order'   => 'ASC',
					'orderby' => 'display_name',
				)
			);
		}
		return $all_friend_requests;
	}

	/**
	 * Gets all subscriptions.
	 */
	public static function all_subscriptions() {
		static $all_subscriptions;
		if ( ! self::$cache || ! isset( $all_subscriptions ) ) {
			$all_subscriptions = new self(
				array(
					'role__in' => array( 'pending_friend_request', 'subscription' ),
					'order'    => 'ASC',
					'orderby'  => 'display_name',
				)
			);
		}
		return $all_subscriptions;
	}

	/**
	 * Gets all admin users.
	 */
	public static function all_admin_users() {
		static $all_admin_users;
		if ( ! self::$cache || ! isset( $all_admin_users ) ) {
			$all_admin_users = new self(
				array(
					'role'    => Friends::REQUIRED_ROLE,
					'order'   => 'ASC',
					'orderby' => 'display_name',
				)
			);
		}
		return $all_admin_users;
	}

}
