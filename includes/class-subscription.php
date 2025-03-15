<?php
/**
 * Friend Subscription
 *
 * This is a virtual user to allow subscriptions without a WordPress user
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the User part of the Friends Plugin.
 *
 * @since 3.0
 *
 * @package Friends
 * @author Alex Kirk
 */
class Subscription extends User {
	const TAXONOMY = 'friends-virtual-user';
	private $term;
	const MIGRATE_USER_OPTIONS = array(
		'friends_retention_number',
		'friends_enable_retention_days',
		'friends_retention_days',
		'friends_feed_rules',
		'friends_feed_catch_all',
		'friends_in_token',
		'friends_out_token',
		'friends_starred',
		'friends_rest_url',
		'friends_user_icon_url',
	);

	public function __construct( \WP_Term $term ) {
		$this->term = $term;
		$this->ID = 1e10 + $term->term_id;

		$this->caps = array_fill_keys( get_metadata( 'term', $term->term_id, 'roles' ), true );
		$this->caps['subscription'] = true;
		$this->get_role_caps();

		$this->roles = array_values( $this->roles );

		$this->data = (object) array(
			'ID'              => $this->ID,
			'user_login'      => $term->name,
			'display_name'    => $this->get_user_option( 'display_name' ),
			'user_url'        => $this->get_user_option( 'user_url' ),
			'description'     => $this->get_user_option( 'description' ),
			'user_registered' => $this->get_user_option( 'created' ),
		);
	}

	public function get_term_id() {
		return $this->term->term_id;
	}

	public function get_object_id() {
		return $this->get_term_id();
	}

	public function save() {
		foreach ( array(
			'first_name',
			'display_name',
			'description',
			'user_url',
		) as $key ) {
			$this->update_user_option( $key, $this->$key );
		}
		return $this->get_object_id();
	}

	/**
	 * Registers the taxonomy
	 */
	public static function register_taxonomy() {
		$args = array(
			'labels'            => array(
				'name'          => _x( 'Virtual User', 'taxonomy general name', 'friends' ),
				'singular_name' => _x( 'Virtual User', 'taxonomy singular name', 'friends' ),
				'menu_name'     => __( 'Virtual User', 'friends' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => false,
			'public'            => false,
		);
		register_taxonomy( self::TAXONOMY, 'None', $args );
	}

	public static function get_by_username( $username ) {
		$term_query = new \WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'name'       => $username,
				'hide_empty' => false,
			)
		);

		foreach ( $term_query->get_terms() as $term ) {
			return new self( $term );
		}

		return new \WP_Error( 'user-not-found' );
	}

	public function insert_post( array $postarr, $wp_error = false, $fire_after_hooks = true ) {
		if ( ! isset( $postarr['post_author'] ) ) {
			$postarr['post_author'] = 0;
		}
		$post_id = wp_insert_post( $postarr, $wp_error, $fire_after_hooks );
		if ( ! is_wp_error( $post_id ) ) {
			wp_set_object_terms( $post_id, $this->get_term_id(), self::TAXONOMY );
		}

		return $post_id;
	}

	public function modify_query_by_author( \WP_Query $query ) {
		$tax_query = $query->get( 'tax_query' );
		if ( ! $tax_query ) {
			$tax_query = array();
		} else {
			$tax_query['relation'] = 'AND';
		}
		$tax_query[] =
			array(
				'taxonomy' => self::TAXONOMY,
				'field'    => 'term_id',
				'terms'    => $this->get_term_id(),
			);
		$query->set( 'tax_query', $tax_query );
		return $query;
	}

	public function modify_get_posts_args_by_author( $args ) {
		if ( isset( $args['author'] ) ) {
			unset( $args['author'] );
		}
		if ( ! isset( $args['tax_query'] ) ) {
			$args['tax_query'] = array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		} else {
			$args['tax_query']['relation'] = 'AND';
		}
		$args['tax_query'][] =
			array(
				'taxonomy' => self::TAXONOMY,
				'field'    => 'term_id',
				'terms'    => $this->get_term_id(),
			);
		return $args;
	}


	public static function set_authordata_by_query( $query ) {
		global $authordata;
		if ( $query->get( 'tax_query' ) ) {
			foreach ( $query->get( 'tax_query' ) as $tax_query ) {
				if ( ! is_array( $tax_query ) || ! isset( $tax_query['taxonomy'] ) || self::TAXONOMY !== $tax_query['taxonomy'] ) {
					continue;
				}
				if ( 'term_id' === $tax_query['field'] ) {
					$term_id = $tax_query['terms'];
					if ( is_array( $term_id ) ) {
						$term_id = reset( $term_id );
					}
					$authordata = new Subscription( \get_term( $term_id ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					return;
				}
			}
		}
	}


	/**
	 * Gets the post stats.
	 *
	 * @return     object  The post stats.
	 */
	public function get_post_stats() {
		$cache_key = 'post_stats_author_' . $this->ID;
		$post_stats = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $post_stats ) {
			return $post_stats;
		}
		global $wpdb;
		$post_types = apply_filters( 'friends_frontend_post_types', array() );
		$post_stats = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				sprintf(
					'SELECT SUM(
					LENGTH( ID ) +
					LENGTH( post_author ) +
					LENGTH( post_date ) +
					LENGTH( post_date_gmt ) +
					LENGTH( post_content ) +
					LENGTH( post_title ) +
					LENGTH( post_excerpt ) +
					LENGTH( post_status ) +
					LENGTH( comment_status ) +
					LENGTH( ping_status ) +
					LENGTH( post_password ) +
					LENGTH( post_name ) +
					LENGTH( to_ping ) +
					LENGTH( pinged ) +
					LENGTH( post_modified ) +
					LENGTH( post_modified_gmt ) +
					LENGTH( post_content_filtered ) +
					LENGTH( post_parent ) +
					LENGTH( guid ) +
					LENGTH( menu_order ) +
					LENGTH( post_type ) +
					LENGTH( post_mime_type ) +
					LENGTH( comment_count )
					) AS total_size,
					COUNT(*) as post_count
					FROM %s p, %s t, %s r
					WHERE r.object_id = p.ID
					AND r.term_taxonomy_id = t.term_taxonomy_id
					AND t.term_id = %%d
					AND p.post_type IN ( %s )',
					$wpdb->posts,
					$wpdb->term_taxonomy,
					$wpdb->term_relationships,
					implode( ', ', array_fill( 0, count( $post_types ), '%s' ) )
				),
				array_merge( array( $this->get_term_id() ), $post_types )
			),
			ARRAY_A
		);

		$post_stats['earliest_post_date'] = mysql2date(
			'U',
			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					sprintf(
						'SELECT MIN(post_date)
						FROM %s p, %s t, %s r
						WHERE r.object_id = p.ID
						AND r.term_taxonomy_id = t.term_taxonomy_id
						AND t.term_id = %%d
						AND p.post_status = "publish"
						AND p.post_type IN ( %s )',
						$wpdb->posts,
						$wpdb->term_taxonomy,
						$wpdb->term_relationships,
						implode( ', ', array_fill( 0, count( $post_types ), '%s' ) )
					),
					array_merge( array( $this->get_term_id() ), $post_types )
				)
			)
		);

		wp_cache_set( $cache_key, $post_stats, 'friends', HOUR_IN_SECONDS );
		return $post_stats;
	}

	public function get_all_post_ids() {
		global $wpdb;
		$post_types = apply_filters( 'friends_frontend_post_types', array() );

		$cache_key = 'get_all_post_ids_' . $this->ID . '_' . implode( '_', $post_types );
		$post_ids = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $post_ids ) {
			return $post_ids;
		}

		$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				sprintf(
					'SELECT posts.ID
						FROM %s AS posts
						JOIN %s AS taxonomy_author
						JOIN %s AS relationships_author
						WHERE posts.post_type IN ( %s )
						AND relationships_author.object_id = posts.ID
						AND taxonomy_author.term_taxonomy_id = relationships_author.term_taxonomy_id
						AND taxonomy_author.term_id = %%d
					',
					$wpdb->posts,
					$wpdb->term_taxonomy,
					$wpdb->term_relationships,
					implode( ', ', array_fill( 0, count( $post_types ), '%s' ) )
				),
				array_merge(
					$post_types,
					array( $this->get_term_id() )
				)
			)
		);

		wp_cache_set( $cache_key, $post_ids, 'friends', HOUR_IN_SECONDS - 60 );

		return $post_ids;
	}

	/**
	 * Gets the post counts by post format.
	 *
	 * @return     array  The post counts.
	 */
	public function get_post_count_by_post_format() {
		$cache_key = 'get_post_count_by_post_format_' . $this->ID;

		$counts = wp_cache_get( $cache_key, 'friends' );
		if ( false !== $counts ) {
			return $counts;
		}
		$counts = get_transient( $cache_key );
		if ( false !== $counts ) {
			return $counts;
		}
		$counts = array();
		$post_types = apply_filters( 'friends_frontend_post_types', array() );
		$post_formats_term_ids = array();
		foreach ( get_post_format_slugs() as $post_format ) {
			$term = get_term_by( 'slug', 'post-format-' . $post_format, 'post_format' );
			if ( $term ) {
				$post_formats_term_ids[ $term->term_taxonomy_id ] = $post_format;
			}
		}

		global $wpdb;

		$counts = array();
		$counts['standard'] = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				sprintf(
					"SELECT COUNT(DISTINCT posts.ID)
					FROM %s AS posts
					JOIN %s AS relationships_post_format
					JOIN %s AS taxonomy_author
					JOIN %s AS relationships_author

					WHERE posts.post_status IN ( 'publish', 'private' )
					AND posts.post_type IN ( %s )
					AND relationships_post_format.object_id = posts.ID
					AND relationships_author.object_id = posts.ID
					AND taxonomy_author.term_taxonomy_id = relationships_author.term_taxonomy_id
					AND taxonomy_author.term_id = %%d",
					$wpdb->posts,
					$wpdb->term_relationships,
					$wpdb->term_taxonomy,
					$wpdb->term_relationships,
					implode( ',', array_fill( 0, count( $post_types ), '%s' ) )
				),
				array_merge(
					$post_types,
					array( $this->get_term_id() )
				)
			)
		);

		if ( ! empty( $post_formats_term_ids ) ) {
			$post_format_counts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					sprintf(
						"SELECT relationships_post_format.term_taxonomy_id AS post_format_id, COUNT(relationships_post_format.term_taxonomy_id) AS count
						FROM %s AS posts
						JOIN %s AS relationships_post_format
						JOIN %s AS taxonomy_author
						JOIN %s AS relationships_author

						WHERE posts.post_status IN ( 'publish', 'private' )
						AND posts.post_type IN ( %s )
						AND relationships_post_format.object_id = posts.ID
						AND relationships_post_format.term_taxonomy_id IN ( %s )
						AND relationships_author.object_id = posts.ID
						AND taxonomy_author.term_taxonomy_id = relationships_author.term_taxonomy_id
						AND taxonomy_author.term_id = %%d
						GROUP BY relationships_post_format.term_taxonomy_id",
						$wpdb->posts,
						$wpdb->term_relationships,
						$wpdb->term_taxonomy,
						$wpdb->term_relationships,
						implode( ',', array_fill( 0, count( $post_types ), '%s' ) ),
						implode( ',', array_fill( 0, count( $post_formats_term_ids ), '%d' ) )
					),
					array_merge(
						$post_types,
						array_keys( $post_formats_term_ids ),
						array( $this->get_term_id() )
					)
				)
			);

			foreach ( $post_format_counts as $row ) {
				$counts[ $post_formats_term_ids[ $row->post_format_id ] ] = $row->count;
				$counts['standard'] -= $row->count;
			}
		}

		$counts = array_filter( $counts );

		set_transient( $cache_key, $counts, HOUR_IN_SECONDS );
		wp_cache_set( $cache_key, $counts, 'friends', HOUR_IN_SECONDS );

		return $counts;
	}

	/**
	 * Gets the post counts by post format.
	 *
	 * @return     int  The post count.
	 */
	public function get_post_in_trash_count() {
		global $wpdb;
		$post_types = apply_filters( 'friends_frontend_post_types', array() );

		$cache_key = 'get_post_in_trash_count_' . $this->get_term_id() . '_' . implode( '_', $post_types );
		if ( false !== wp_cache_get( $cache_key, 'friends' ) ) {
			return wp_cache_get( $cache_key, 'friends' );
		}

		$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				sprintf(
					'SELECT COUNT(*)
					FROM %s p, %s t, %s r
					WHERE r.object_id = p.ID
					AND r.term_taxonomy_id = t.term_taxonomy_id
					AND t.term_id = %%d
					AND post_type IN ( %s )
					AND post_status = "trash"',
					$wpdb->posts,
					$wpdb->term_taxonomy,
					$wpdb->term_relationships,
					implode( ', ', array_fill( 0, count( $post_types ), '%s' ) )
				),
				array_merge( array( $this->get_term_id() ), $post_types )
			)
		);

		wp_cache_set( $cache_key, intval( $count ), 'friends', HOUR_IN_SECONDS - 60 );
		return intval( $count );
	}

	/**
	 * Gets the role name (for a specific count).
	 *
	 * @param      bool $group_subscriptions  Whether to group all types of subscriptions into the name "Subscriptions".
	 * @param      int  $count                The count if more than one.
	 *
	 * @return     string  The role name.
	 */
	public function get_role_name( $group_subscriptions = false, $count = 1 ) {
		return _nx( 'Subscription', 'Subscriptions', $count, 'User role', 'friends' );
	}

	public function get_avatar_url() {
		return get_metadata( 'term', $this->get_term_id(), 'avatar_url', true );
	}

	/**
	 * Determines if starred.
	 *
	 * @return     bool  True if starred, False otherwise.
	 */
	public function is_starred() {
		return get_metadata( 'term', $this->get_term_id(), 'starred', true );
	}

	/**
	 * Marks a friend as starred or unstarred.
	 *
	 * @param      bool $starred  Whether to star the friend.
	 *
	 * @return     bool    The new star status.
	 */
	public function set_starred( $starred ) {
		if ( $starred ) {
			update_metadata( 'term', $this->get_term_id(), 'starred', true );
			return true;
		}

		delete_metadata( 'term', $this->get_term_id(), 'starred' );
		return false;
	}

	public function delete() {
		// Allow unsubscribing to all these feeds.
		foreach ( $this->get_active_feeds() as $feed ) {
			do_action( 'friends_user_feed_deactivated', $feed );
			$feed->delete();
		}

		// Delete the rest.
		foreach ( $this->get_feeds() as $feed ) {
			$feed->delete();
		}

		foreach ( $this->get_all_post_ids() as $post_id ) {
			wp_delete_post( $post_id );
		}

		wp_delete_term( $this->get_term_id(), self::TAXONOMY );
		return true;
	}

	public static function convert_from_user( User $user ) {
		if ( $user instanceof Subscription ) {
			return $user;
		}

		$subscription = self::create( $user->user_login, $user->roles[0], $user->user_url, $user->display_name, $user->get_avatar_url(), $user->description, $user->user_registered );

		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		$query = new \WP_Query();
		$query->set( 'post_type', apply_filters( 'friends_frontend_post_types', array() ) );
		$query->set( 'post_status', array( 'publish', 'private', 'draft', 'trash' ) );
		$query->set( 'posts_per_page', -1 );
		$query = $user->modify_query_by_author( $query );

		foreach ( $query->get_posts() as $post ) {
			$post->post_author = 0;
			wp_update_post( $post );
			wp_set_object_terms( $post->ID, $subscription->get_term_id(), self::TAXONOMY );
		}

		global $wpdb;
		// Convert feeds.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				sprintf(
					'UPDATE %s
					JOIN %s
					ON %s.term_taxonomy_id = %s.term_taxonomy_id
					SET object_id = %%d
					WHERE object_id = %%d
					AND %s.taxonomy = %%s',
					$wpdb->term_relationships,
					$wpdb->term_taxonomy,
					$wpdb->term_relationships,
					$wpdb->term_taxonomy,
					$wpdb->term_taxonomy
				),
				$subscription->get_term_id(),
				$user->ID,
				User_Feed::TAXONOMY
			)
		);

		foreach ( self::MIGRATE_USER_OPTIONS as $option_name ) {
			$subscription->update_user_option( $option_name, $user->get_user_option( $option_name ) );
		}

		$user->delete();

		return $subscription;
	}

	public static function convert_to_user( Subscription $subscription ) {
		$user = User::create( $subscription->user_login, $subscription->roles[0], $subscription->user_url, $subscription->display_name, $subscription->get_avatar_url(), $subscription->description, $subscription->user_registered, true );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$query = new \WP_Query();
		$query->set( 'post_type', apply_filters( 'friends_frontend_post_types', array() ) );
		$query->set( 'post_status', array( 'publish', 'private', 'draft', 'trashed' ) );
		$query->set( 'posts_per_page', -1 );
		$query = $subscription->modify_query_by_author( $query );

		foreach ( $query->get_posts() as $post ) {
			$post->post_author = $user->ID;
			wp_update_post( $post );
			wp_remove_object_terms( $post->ID, $subscription->get_term_id(), self::TAXONOMY );
		}

		global $wpdb;
		// Convert feeds.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				sprintf(
					'UPDATE %s
					JOIN %s
					ON %s.term_taxonomy_id = %s.term_taxonomy_id
					SET object_id = %%d
					WHERE object_id = %%d
					AND %s.taxonomy = %%s',
					$wpdb->term_relationships,
					$wpdb->term_taxonomy,
					$wpdb->term_relationships,
					$wpdb->term_taxonomy,
					$wpdb->term_taxonomy
				),
				$user->ID,
				$subscription->get_term_id(),
				User_Feed::TAXONOMY
			)
		);

		foreach ( self::MIGRATE_USER_OPTIONS as $option_name ) {
			$user->update_user_option( $option_name, $subscription->get_user_option( $option_name ) );
		}

		$subscription->delete();

		return $user;
	}

	/**
	 * Create a Subscription (virtual user).
	 *
	 * @param      string $user_login    The user login.
	 * @param      string $role          The role: subscription.
	 * @param      string $user_url      The site URL.
	 * @param      string $display_name  The user's display name.
	 * @param      string $avatar_url      The user_icon_url URL.
	 * @param      string $description   A description for the user.
	 * @param      string $user_registered   When the user was registered.
	 * @param      bool   $subscription_override  Whether to override the automatic creation of a subscription.
	 *
	 * @return     Subscription|\WP_Error  The created subscription or an error.
	 */
	public static function create( $user_login, $role, $user_url, $display_name = null, $avatar_url = null, $description = null, $user_registered = null, $subscription_override = false ) {
		$term = term_exists( $user_login, self::TAXONOMY );

		if ( ! $term || ! isset( $term['term_id'] ) ) {
			$term = wp_insert_term( $user_login, self::TAXONOMY );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
		}

		$term_id = $term['term_id'];

		delete_metadata( 'term', $term_id, 'roles' );
		add_metadata( 'term', $term_id, 'roles', $role, true );
		delete_metadata( 'term', $term_id, 'user_url' );
		add_metadata( 'term', $term_id, 'user_url', $user_url, true );

		delete_metadata( 'term', $term_id, 'display_name' );
		if ( $display_name ) {
			add_metadata( 'term', $term_id, 'display_name', $display_name, true );
		}

		delete_metadata( 'term', $term_id, 'avatar_url' );
		if ( $avatar_url ) {
			add_metadata( 'term', $term_id, 'avatar_url', $avatar_url, true );
		}
		delete_metadata( 'term', $term_id, 'description' );
		if ( $description ) {
			add_metadata( 'term', $term_id, 'description', $description, true );
		}
		delete_metadata( 'term', $term_id, 'created' );
		add_metadata( 'term', $term_id, 'created', $user_registered ? $user_registered : gmdate( 'Y-m-d H:i:s' ), true );

		$term = get_term( $term['term_id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		return new self( $term );
	}

	private function map_option( $option_name ) {
		$option_map = array(
			'friends_user_icon_url' => 'avatar_url',
		);
		if ( isset( $option_map[ $option_name ] ) ) {
			$option_name = $option_map[ $option_name ];
		}

		return $option_name;
	}

	/**
	 * Wrap get_user_option
	 *
	 * @param string $option_name User option name.
	 * @return int|bool User meta ID if the option didn't exist, true on successful update,
	 *                  false on failure.
	 */
	public function get_user_option( $option_name ) {
		$value = get_metadata( 'term', $this->get_term_id(), $option_name, true );
		if ( false === $value ) {
			$value = get_metadata( 'term', $this->get_term_id(), $this->map_option( $option_name ), true );
		}

		return $value;
	}

	/**
	 * Wrap update_user_option
	 *
	 * @param string $option_name    User option name.
	 * @param mixed  $new_value      User option value.
	 * @param bool   $is_global      Optional. Whether option name is global or blog specific.
	 *                               Default false (blog specific).
	 * @return int|bool User meta ID if the option didn't exist, true on successful update,
	 *                  false on failure.
	 */
	public function update_user_option( $option_name, $new_value, $is_global = false ) {
		return update_metadata( 'term', $this->get_term_id(), $this->map_option( $option_name ), $new_value );
	}

	/**
	 * Wrap delete_user_option
	 *
	 * @param string $option_name    User option name.
	 * @param bool   $is_global      Optional. Whether option name is global or blog specific.
	 *                               Default false (blog specific).
	 * @return bool True on success, false on failure.
	 */
	public function delete_user_option( $option_name, $is_global = false ) {
		return delete_metadata( 'term', $this->get_term_id(), $option_name );
	}
}
