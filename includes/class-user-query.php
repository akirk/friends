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
	 * Total number of found users for the current query
	 *
	 * @var int
	 */
	private $total_users = 0;

	/**
	 * Execute the query and ensure that we populate User objects
	 */
	public function query() {
		parent::query();
		foreach ( parent::get_results() as $k => $user ) {
			$this->results[ $k ] = new User( $user );
			$this->total_users += 1;
		}
	}

	/**
	 * Return the count users.
	 *
	 * @return array Array of results.
	 */
	public function get_results() {
		return $this->results;
	}

	/**
	 * Returns the total number of users for the current query.
	 *
	 * @return int Number of total users.
	 */
	public function get_total() {
		return $this->total_users;
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
					'capability' => 'friend',
					'order'      => 'ASC',
					'orderby'    => 'display_name',
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
	public static function all_associated_users() {
		static $all = array();
		if ( ! self::$cache || ! isset( $all[ get_current_blog_id() ] ) ) {
			$query = array(
				'capability__in' => array_keys( Admin::get_associated_roles() ),
			);
			$sort = array(
				'order'   => 'ASC',
				'orderby' => 'display_name',
			);
			$all[ get_current_blog_id() ] = new self( array_merge( $query, $sort ) );
			$all[ get_current_blog_id() ]->add_virtual_subscriptions();
			$all[ get_current_blog_id() ]->sort( $sort['orderby'], $sort['order'] );
		}
		return $all[ get_current_blog_id() ];
	}

	public function add_virtual_subscriptions( $args = array() ) {
		if ( isset( $args['meta_key'] ) && substr( $args['meta_key'], -8 ) === '_starred' ) {
			$args['meta_key'] = 'starred'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}
		$searches = array();

		if ( isset( $args['search'] ) ) {
			// WP_Term_Query will add wildcarts before and after.
			$args['search'] = trim( $args['search'], '*' );
			$searches[] = $args;
			$searches[] = array(
				'meta_key'     => 'display_name', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'   => $args['search'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare' => 'LIKE',
			);
		} else {
			$searches[] = $args;
		}

		foreach ( $searches as $args ) {
			$term_query = new \WP_Term_Query(
				array_merge(
					array(
						'taxonomy'   => Subscription::TAXONOMY,
						'hide_empty' => false,
					),
					$args
				)
			);

			foreach ( $term_query->get_terms() as $term ) {
				if ( ! isset( $this->results[ $term->term_id ] ) ) {
					$this->results[ $term->term_id ] = new Subscription( $term );
					$this->total_users += 1;
				}
			}
		}
	}
	public function sort( $field, $direction ) {
		usort(
			$this->results,
			function ( $a, $b ) use ( $field, $direction ) {
				$a = $a->$field;
				$b = $b->$field;
				if ( $a === $b ) {
					return 0;
				}
				if ( 'ASC' === $direction ) {
					return strcasecmp( $a, $b );
				}
				return strcasecmp( $b, $a );
			}
		);
	}

	public function limit( $limit ) {
		$this->results = array_slice( $this->results, 0, $limit );
	}

	/**
	 * Gets Starred friends.
	 *
	 * @return     User_Query  The requested users.
	 */
	public static function starred_friends_subscriptions() {
		static $starred_friends_subscriptions = array();
		$cache_key = get_current_blog_id();

		if ( ! self::$cache || ! isset( $starred_friends_subscriptions[ $cache_key ] ) ) {
			global $wpdb;
			$query = array(
				'capability__in' => Friends::get_friends_plugin_roles(),
			);
			$meta = array(
				'meta_key'     => $wpdb->get_blog_prefix() . 'friends_starred', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				// Using a meta_key EXISTS query is not slow, see https://github.com/WordPress/WordPress-Coding-Standards/issues/1871.
				'meta_compare' => 'EXISTS',
			);
			$sort = array(
				'order'   => 'ASC',
				'orderby' => 'display_name',
			);
			$starred_friends_subscriptions[ $cache_key ] = new self( array_merge( $query, $meta, $sort ) );
			$starred_friends_subscriptions[ $cache_key ]->add_virtual_subscriptions( $meta );
			$starred_friends_subscriptions[ $cache_key ]->sort( $sort['orderby'], $sort['order'] );
		}
		return $starred_friends_subscriptions[ $cache_key ];
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
		$cache_key = get_current_blog_id() . '_' . $limit;
		if ( ! self::$cache || ! isset( $recent_friends_subscriptions[ $cache_key ] ) ) {
			$query = array(
				'capability__in' => array( 'friend', 'acquaintance', 'pending_friend_request', 'subscription' ),
				'number'         => $limit,
			);
			$sort = array(
				'orderby' => 'user_registered',
				'order'   => 'DESC',
			);
			$recent_friends_subscriptions[ $cache_key ] = new self(
				array_merge(
					$query,
					$sort
				)
			);
			$recent_friends_subscriptions[ $cache_key ]->add_virtual_subscriptions();
			$recent_friends_subscriptions[ $cache_key ]->sort( $sort['orderby'], $sort['order'] );
			$recent_friends_subscriptions[ $cache_key ]->limit( $limit );
		}
		return $recent_friends_subscriptions[ $cache_key ];
	}

	/**
	 * Search friends.
	 *
	 * @param      string $query  The query.
	 *
	 * @return     self    The search result.
	 */
	public static function search( $query ) {
		$query = array(
			'search' => $query,
		);
		$sort = array(
			'order'   => 'ASC',
			'orderby' => 'display_name',
		);
		$search = new self(
			array_merge(
				array(
					'capability__in' => Friends::get_friends_plugin_roles(),
					'search_columns' => array( 'display_name' ),
				),
				$query,
				$sort
			)
		);

		$search->add_virtual_subscriptions( $query );
		$search->sort( $sort['orderby'], $sort['order'] );
		return $search;
	}

	/**
	 * Gets all friend requests.
	 */
	public static function all_friend_requests() {
		static $all_friend_requests = array();
		if ( ! self::$cache || ! isset( $all_friend_requests[ get_current_blog_id() ] ) ) {
			$all_friend_requests[ get_current_blog_id() ] = new self(
				array(
					'role'    => 'friexnd_request',
					'order'   => 'ASC',
					'orderby' => 'display_name',
				)
			);
		}
		return $all_friend_requests[ get_current_blog_id() ];
	}

	/**
	 * Gets all friend requests.
	 */
	public static function all_pending_friend_requests() {
		static $all_pending_friend_requests = array();
		if ( ! self::$cache || ! isset( $all_pending_friend_requests[ get_current_blog_id() ] ) ) {

			$all_pending_friend_requests[ get_current_blog_id() ] = new self(
				array(
					'capability__in' => array( 'pending_friend_request', 'friend_request' ),
					'order'          => 'ASC',
					'orderby'        => 'display_name',
				)
			);
		}
		return $all_pending_friend_requests[ get_current_blog_id() ];
	}

	/**
	 * Gets all subscriptions.
	 */
	public static function all_subscriptions() {
		static $all_subscriptions = array();
		if ( ! self::$cache || ! isset( $all_subscriptions[ get_current_blog_id() ] ) ) {
			$query = array(
				'capability__in' => array( 'pending_friend_request', 'subscription' ),
			);
			$sort = array(
				'order'   => 'ASC',
				'orderby' => 'display_name',
			);
			$all_subscriptions[ get_current_blog_id() ] = new self( array_merge( $query, $sort ) );
			$all_subscriptions[ get_current_blog_id() ]->add_virtual_subscriptions();
			$all_subscriptions[ get_current_blog_id() ]->sort( $sort['orderby'], $sort['order'] );
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
