<?php
/**
 * Friend User Query
 *
 * This wraps \WP_User_Query and so that it generates instances of User.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the User part of the Friends Plugin.
 *
 * @since 0.21
 *
 * @package Friends
 * @author Alex Kirk
 */
class User_Query extends \WP_User_Query {
	/**
	 * Whether to cache the retrieved users
	 *
	 * @var boolean
	 */
	public static $cache = true;

	/**
	 * List of found User objects.
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * Execute the query and ensure that we populate User objects
	 */
	public function query() {
		parent::query();
		foreach ( parent::get_results() as $k => $user ) {
			$this->results[ $k ] = new User( $user );
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
	 *
	 * @return     User_Query  The requested users.
	 */
	public static function all_friends() {
		static $all_friends = array();
		if ( ! self::$cache || ! isset( $all_friends[ get_current_blog_id() ] ) ) {
			$all_friends[ get_current_blog_id() ] = new self(
				array(
					'role__in' => array( 'friend', 'acquaintance' ),
					'order'    => 'ASC',
					'orderby'  => 'display_name',
				)
			);
		}
		return $all_friends[ get_current_blog_id() ];
	}

	/**
	 * Gets all friends.
	 *
	 * @return     User_Query  The requested users.
	 */
	public static function all_friends_subscriptions() {
		static $all_friends_subscriptions = array();
		if ( ! self::$cache || ! isset( $all_friends_subscriptions[ get_current_blog_id() ] ) ) {
			$all_friends_subscriptions[ get_current_blog_id() ] = new self(
				array(
					'role__in' => array( 'friend', 'acquaintance', 'pending_friend_request', 'subscription' ),
					'order'    => 'ASC',
					'orderby'  => 'display_name',
				)
			);
		}
		return $all_friends_subscriptions[ get_current_blog_id() ];
	}

	/**
	 * Gets Starred friends.
	 *
	 * @return     User_Query  The requested users.
	 */
	public static function starred_friends_subscriptions() {
		static $starred_friends_subscriptions = array();
		if ( ! self::$cache || ! isset( $starred_friends_subscriptions[ get_current_blog_id() ] ) ) {
			global $wpdb;
			$starred_friends_subscriptions[ get_current_blog_id() ] = new self(
				array(
					'role__in'     => Friends::get_friends_plugin_roles(),
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_key'     => $wpdb->get_blog_prefix() . 'friends_starred',
					// Using a meta_key EXISTS query is not slow, see https://github.com/WordPress/WordPress-Coding-Standards/issues/1871.
					'meta_compare' => 'EXISTS',
					'order'        => 'ASC',
					'orderby'      => 'display_name',
				)
			);
		}
		return $starred_friends_subscriptions[ get_current_blog_id() ];
	}

	/**
	 * Gets the recent friends.
	 *
	 * @param      int $limit  The limit.
	 *
	 * @return     User_Query  The requested users.
	 */
	public static function recent_friends_subscriptions( $limit = 5 ) {
		static $recent_friends_subscriptions = array();
		if ( ! self::$cache || ! isset( $recent_friends_subscriptions[ get_current_blog_id() ] ) ) {
			$recent_friends_subscriptions[ get_current_blog_id() . '_' . $limit ] = new self(
				array(
					'role__in' => array( 'friend', 'acquaintance', 'pending_friend_request', 'subscription' ),
					'number'   => $limit,
					'orderby'  => 'registered',
					'order'    => 'DESC',
				)
			);
		}
		return $recent_friends_subscriptions[ get_current_blog_id() . '_' . $limit ];
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
				'search_columns' => array( 'display_name' ),
			)
		);
	}

	/**
	 * Gets all friend requests.
	 */
	public static function all_friend_requests() {
		static $all_friend_requests = array();
		if ( ! self::$cache || ! isset( $all_friend_requests[ get_current_blog_id() ] ) ) {
			$all_friend_requests[ get_current_blog_id() ] = new self(
				array(
					'role'    => 'friend_request',
					'order'   => 'ASC',
					'orderby' => 'display_name',
				)
			);
		}
		return $all_friend_requests[ get_current_blog_id() ];
	}

	/**
	 * Gets all subscriptions.
	 */
	public static function all_subscriptions() {
		static $all_subscriptions = array();
		if ( ! self::$cache || ! isset( $all_subscriptions[ get_current_blog_id() ] ) ) {
			$all_subscriptions[ get_current_blog_id() ] = new self(
				array(
					'role__in' => array( 'pending_friend_request', 'subscription' ),
					'order'    => 'ASC',
					'orderby'  => 'display_name',
				)
			);
		}
		return $all_subscriptions[ get_current_blog_id() ];
	}

	/**
	 * Gets all admin users.
	 */
	public static function all_admin_users() {
		static $all_admin_users;
		if ( ! self::$cache || ! isset( $all_admin_users ) ) {
			$all_admin_users = new self(
				array(
					'capability' => 'edit_private_posts',
					'order'      => 'ASC',
					'orderby'    => 'display_name',
				)
			);
		}
		return $all_admin_users;
	}

}
