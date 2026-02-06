<?php
/**
 * Friend Tag Taxonomy
 *
 * Handles the friend_tag taxonomy for categorizing and filtering friend posts.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Friend_Tag class for managing the friend_tag taxonomy.
 */
class Friend_Tag {
	/**
	 * The taxonomy name.
	 */
	const TAXONOMY = 'friend_tag';

	/**
	 * Prefix for mention tags.
	 */
	const MENTION_PREFIX = 'mention-';

	/**
	 * Register the friend_tag taxonomy.
	 */
	public static function register() {
		$labels = array(
			'name'          => __( 'Friend Tags', 'friends' ),
			'singular_name' => __( 'Friend Tag', 'friends' ),
			'search_items'  => __( 'Search Friend Tags', 'friends' ),
			'all_items'     => __( 'All Friend Tags', 'friends' ),
			'edit_item'     => __( 'Edit Friend Tag', 'friends' ),
			'update_item'   => __( 'Update Friend Tag', 'friends' ),
			'add_new_item'  => __( 'Add New Friend Tag', 'friends' ),
			'new_item_name' => __( 'New Friend Tag Name', 'friends' ),
			'menu_name'     => __( 'Friend Tags', 'friends' ),
		);

		$args = array(
			'hierarchical'       => false,
			'labels'             => $labels,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'friend-tag' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_in_rest'       => true,
		);

		register_taxonomy( self::TAXONOMY, Friends::CPT, $args );
	}

	/**
	 * Check if a post has a specific tag.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $tag     The tag slug to check for.
	 * @return bool True if the post has the tag.
	 */
	public static function has_tag( $post_id, $tag ) {
		$terms = wp_get_post_terms( $post_id, self::TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) ) {
			return false;
		}
		return in_array( $tag, $terms, true );
	}

	/**
	 * Check if a post has any tag with a given prefix.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $prefix  The tag prefix to check for.
	 * @return bool True if the post has a tag with the prefix.
	 */
	public static function has_tag_prefix( $post_id, $prefix ) {
		$terms = wp_get_post_terms( $post_id, self::TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) ) {
			return false;
		}
		foreach ( $terms as $slug ) {
			if ( 0 === strpos( $slug, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a post has any mention tag.
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if the post has a mention tag.
	 */
	public static function has_mention( $post_id ) {
		return self::has_tag_prefix( $post_id, self::MENTION_PREFIX );
	}

	/**
	 * Get all tags for a post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $fields  What fields to return ('slugs', 'names', 'ids', 'all').
	 * @return array Array of tags or empty array on error.
	 */
	public static function get_tags( $post_id, $fields = 'slugs' ) {
		$terms = wp_get_post_terms( $post_id, self::TAXONOMY, array( 'fields' => $fields ) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return $terms;
	}

	/**
	 * Get all mention tags for a post.
	 *
	 * @param int $post_id The post ID.
	 * @return array Array of mention tag slugs.
	 */
	public static function get_mention_tags( $post_id ) {
		$terms = self::get_tags( $post_id, 'slugs' );
		return array_filter(
			$terms,
			function ( $slug ) {
				return 0 === strpos( $slug, self::MENTION_PREFIX );
			}
		);
	}

	/**
	 * Add a tag to a post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $tag     The tag slug to add.
	 * @param bool   $append  Whether to append to existing tags (default true).
	 * @return array|false|\WP_Error Array of term IDs on success, false or WP_Error on failure.
	 */
	public static function add_tag( $post_id, $tag, $append = true ) {
		return wp_set_post_terms( $post_id, array( $tag ), self::TAXONOMY, $append );
	}

	/**
	 * Add multiple tags to a post.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $tags    Array of tag slugs to add.
	 * @param bool  $append  Whether to append to existing tags (default true).
	 * @return array|false|\WP_Error Array of term IDs on success, false or WP_Error on failure.
	 */
	public static function add_tags( $post_id, $tags, $append = true ) {
		return wp_set_post_terms( $post_id, $tags, self::TAXONOMY, $append );
	}

	/**
	 * Add a mention tag for a user.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $username The username to create a mention tag for.
	 * @return array|false|\WP_Error Array of term IDs on success, false or WP_Error on failure.
	 */
	public static function add_mention( $post_id, $username ) {
		return self::add_tag( $post_id, self::MENTION_PREFIX . $username );
	}

	/**
	 * Create a mention tag slug for a username.
	 *
	 * @param string $username The username.
	 * @return string The mention tag slug.
	 */
	public static function mention_tag( $username ) {
		return self::MENTION_PREFIX . $username;
	}

	/**
	 * Remove a tag from a post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $tag     The tag slug to remove.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_tag( $post_id, $tag ) {
		$term = get_term_by( 'slug', $tag, self::TAXONOMY );
		if ( ! $term ) {
			return false;
		}
		return wp_remove_object_terms( $post_id, $term->term_id, self::TAXONOMY );
	}

	/**
	 * Clean up orphaned friend tags that have no posts.
	 */
	public static function cleanup_orphaned() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			if ( 0 === $term->count ) {
				wp_delete_term( $term->term_id, self::TAXONOMY );
			}
		}
	}
}
