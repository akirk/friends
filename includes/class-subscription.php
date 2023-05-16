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

	public function __construct( \WP_Term $term ) {
		$this->term = $term;
		$this->ID = 'friends-virtual-user-' . $term->term_id;
		$this->allcaps = array_fill_keys( get_metadata( 'term', $term->term_id, 'roles' ), true );
		$this->data = (object) array(
			'ID'              => $this->ID,
			'user_login'      => $term->name,
			'display_name'    => get_metadata( 'term', $term->term_id, 'display_name', true ),
			'user_url'        => get_metadata( 'term', $term->term_id, 'user_url', true ),
			'icon_url'        => get_metadata( 'term', $term->term_id, 'icon_url', true ),
			'description'     => get_metadata( 'term', $term->term_id, 'description', true ),
			'user_registered' => get_metadata( 'term', $term->term_id, 'created', true ),
		);
	}

	public function get_term_id() {
		return $this->term->term_id;
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

	public function save_post( array $postarr, $wp_error = false, $fire_after_hooks = true ) {
		if ( ! isset( $postarr['tax_input'] ) ) {
			$postarr['tax_input'] = array();
		}
		$postarr['tax_input'][ self::TAXONOMY ] = array( $this->term );
		$post = wp_insert_post( $postarr, $wp_error, $fire_after_hooks );

		return $post;
	}

	public function modify_query_by_author( \WP_Query $query ) {
		$query->set( 'author', get_current_user_id() );
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
					$authordata = new Subscription( \get_term( $term_id ) );
					return;
				}
			}
		}
	}


	/**
	 * Gets the post counts by post format.
	 *
	 * @return     array  The post counts.
	 */
	public function get_post_count_by_post_format() {
		$cache_key = 'friends_post_count_by_post_format_author_' . $this->ID;

		$counts = get_transient( $cache_key );
		if ( false === $counts ) {
			$counts = array();
			$post_types = apply_filters( 'friends_frontend_post_types', array() );
			$post_formats_term_ids = array();
			foreach ( get_post_format_slugs() as $post_format ) {
				$term = get_term_by( 'slug', 'post-format-' . $post_format, 'post_format' );
				if ( $term ) {
					$post_formats_term_ids[ $term->term_id ] = $post_format;
				}
			}

			global $wpdb;

			$counts = array();
			$post_format_counts = $wpdb->get_results(
				$wpdb->prepare(
					sprintf(
						"SELECT relationships_post_format.term_taxonomy_id AS post_format_id, COUNT(relationships_post_format.term_taxonomy_id) AS count
						FROM %s AS posts
						JOIN %s AS relationships_post_format
						JOIN %s AS relationships_author

						WHERE posts.post_author = %s
						AND posts.post_status IN ( 'publish', 'private' )
						AND posts.post_type IN ( %s )
						AND relationships_post_format.object_id = posts.ID
						AND relationships_post_format.term_taxonomy_id IN ( %s )
						AND relationships_author.object_id = posts.ID
						AND relationships_author.term_taxonomy_id = %s
						GROUP BY relationships_post_format.term_taxonomy_id",
						$wpdb->posts,
						$wpdb->term_relationships,
						$wpdb->term_relationships,
						'%d',
						implode( ',', array_fill( 0, count( $post_types ), '%s' ) ),
						implode( ',', array_fill( 0, count( $post_formats_term_ids ), '%d' ) ),
						'%d'
					),
					array_merge(
						array( get_current_user_id() ),
						$post_types,
						array_keys( $post_formats_term_ids ),
						array( $this->get_term_id() )
					)
				)
			);

			foreach ( $post_format_counts as $row ) {
				$counts[ $post_formats_term_ids[ $row->post_format_id ] ] = $row->count;
			}

			$counts['standard'] = $wpdb->get_var(
				$wpdb->prepare(
					sprintf(
						"SELECT COUNT(*)
						FROM %s AS posts
						JOIN %s AS relationships_post_format
						JOIN %s AS relationships_author

						WHERE posts.post_author = %s
						AND posts.post_status IN ( 'publish', 'private' )
						AND posts.post_type IN ( %s )
						AND relationships_post_format.object_id = posts.ID
						AND relationships_post_format.term_taxonomy_id NOT IN ( %s )
						AND relationships_author.object_id = posts.ID
						AND relationships_author.term_taxonomy_id = %s",
						$wpdb->posts,
						$wpdb->term_relationships,
						$wpdb->term_relationships,
						'%d',
						implode( ',', array_fill( 0, count( $post_types ), '%s' ) ),
						implode( ',', array_fill( 0, count( $post_formats_term_ids ), '%d' ) ),
						'%d'
					),
					array_merge(
						array( get_current_user_id() ),
						$post_types,
						$post_formats_term_ids,
						array( $this->get_term_id() )
					)
				)
			);

			$counts = array_filter( $counts );

			set_transient( $cache_key, $counts, HOUR_IN_SECONDS );
		}

		return $counts;
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

	public static function convert_from_user( User $user ) {
		if ( $user instanceof Subscription ) {
			return $user;
		}

		$subscription = self::create( $user->user_login, $user->roles[0], $user->user_url, $user->display_name, $user->get_user_option( 'friends_user_icon_url' ), $user->description, $user->user_registered );

		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		$query = new \WP_Query();
		$query->set( 'post_type', Friends::CPT );
		$query->set( 'posts_per_page', -1 );
		$query = $user->modify_query_by_author( $query );

		foreach ( $query->get_posts() as $post ) {
			$post->post_author = get_current_user_id();
			wp_update_post( $post );
			wp_remove_object_terms( $post->ID, $subscription->ID, self::TAXONOMY );
			wp_set_object_terms( $post->ID, $subscription->get_term_id(), self::TAXONOMY );
		}
		// \wp_delete_user( $user->ID );
		return $user;
	}

	public static function convert_to_user( Subscription $subscription ) {
		$user = User::create( $subscription->user_login, $subscription->role, $subscription->user_url, $subscription->display_name, $subscription->get_user_option( 'friends_user_icon_url' ), $subscription->description, $subscription->user_registered );

		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$query = new \WP_Query();
		$query->set( 'post_type', Friends::CPT );
		$query->set( 'posts_per_page', -1 );
		$query = $subscription->modify_query_by_author( $query );

		foreach ( $query->get_posts() as $post ) {
			$post->post_author = $user->user_login;
			wp_update_post( $post );
			wp_remove_object_terms( $post->ID, $subscription->get_term_id(), self::TAXONOMY );
		}

		wp_delete_term( $subscription->ID, self::TAXONOMY );

		return $user;
	}

	public static function create( $user_login, $role, $user_url, $display_name = null, $icon_url = null, $description = null, $created = null ) {
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
		add_metadata( 'term', $term_id, 'display_name', $display_name, true );
		delete_metadata( 'term', $term_id, 'icon_url' );
		add_metadata( 'term', $term_id, 'icon_url', $icon_url, true );
		delete_metadata( 'term', $term_id, 'description' );
		add_metadata( 'term', $term_id, 'description', $description, true );
		delete_metadata( 'term', $term_id, 'created' );
		add_metadata( 'term', $term_id, 'created', $created ? $created : gmdate( 'Y-m-d H:i:s' ), true );

		$term = get_term( $term['term_id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		return new self( $term );
	}
}
