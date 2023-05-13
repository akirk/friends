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
				'taxonomy' => self::TAXONOMY,
				'slug'     => $username,
			)
		);

		foreach ( $term_query->get_terms() as $term ) {
			return new self( $term );
		}

		return new \WP_Error( 'user-not-found' );
	}

	public static function convert_from_user( User $user ) {
		if ( $user instanceof Subscription ) {
			return $user;
		}

		$subscription = self::create( $user->user_login, $user->roles[0], $user->user_url, $user->display_name, $user->get_user_option( 'friends_user_icon_url' ), $user->description );

		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		$query = new \WP_Query( array( 'author' => $user->ID ) );
		while ( $query->have_posts() ) {
			$post = $query->the_post();
			$post->post_author = get_current_user_id();
			wp_update_post( $post );
			wp_set_object_terms( $post->ID, $subscription->ID, self::TAXONOMY );
		}

		wp_delete_user( $user->ID );
		return $subscription;
	}

	public static function convert_to_user( Subscription $subscription ) {
		$user = User::create( $subscription->user_login, $subscription->role, $subscription->user_url, $subscription->display_name, $subscription->get_user_option( 'friends_user_icon_url' ), $subscription->description );

		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$query = new \WP_Query(
			array(
				'post_type' => 'post',
				'tax_query' => array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'slug',
						'terms'    => $user->user_login,
					),
				),
			)
		);

		while ( $query->have_posts() ) {
			$post = $query->the_post();
			$post->post_author = $user->user_login;
			wp_update_post( $post );
			wp_remove_object_terms( $post->ID, $subscription->ID, self::TAXONOMY );
		}

		wp_delete_term( $subscription->ID, self::TAXONOMY );

		return $user;
	}

	public static function create( $user_login, $role, $user_url, $display_name = null, $icon_url = null, $description = null ) {

		$term = wp_insert_term( $user_login, self::TAXONOMY );

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = $term['term_id'];
		add_metadata( 'term', $term_id, 'role', $role, true );
		add_metadata( 'term', $term_id, 'user_url', $user_url, true );
		add_metadata( 'term', $term_id, 'display_name', $display_name, true );
		add_metadata( 'term', $term_id, 'icon_url', $icon_url, true );
		add_metadata( 'term', $term_id, 'description', $description, true );

		$term = get_term( $term['term_id'] );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		return new self( $term );
	}
}
