<?php
/**
 * Friends Reactions
 *
 * This contains the functions for Reactions.
 *
 * @package Friends
 */

/**
 * This is the class for the Reactions part of the Friends Plugin.
 *
 * @since 0.8
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Reactions {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

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
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'the_content', array( $this, 'post_reactions' ), 20 );
	}

	/**
	 * Register the taxonomies necessary
	 */
	public function register_taxonomies() {
		$args = array(
			'labels'            => array(
				'name'              => _x( 'Reactions', 'taxonomy general name' ),
				'singular_name'     => _x( 'Reaction', 'taxonomy singular name' ),
				'search_items'      => __( 'Search Reactions' ),
				'all_items'         => __( 'All Reactions' ),
				'parent_item'       => __( 'Parent Reaction' ),
				'parent_item_colon' => __( 'Parent Reaction:' ),
				'edit_item'         => __( 'Edit Reaction' ),
				'update_item'       => __( 'Update Reaction' ),
				'add_new_item'      => __( 'Add New Reaction' ),
				'new_item_name'     => __( 'New Reaction Name' ),
				'menu_name'         => __( 'Reaction' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
		);
		register_taxonomy( 'friend-reaction-' . get_current_user_id(), array( 'post', Friends::FRIEND_POST_CACHE ), $args );
	}

	/**
	 * Display Post reactions under a post.
	 *
	 * @param  string  $text The post content.
	 * @param  boolean $echo Whether the content should be echoed.
	 * @return string        The post content with buttons or nothing if echoed.
	 */
	public function post_reactions( $text = '', $echo = false ) {
			$t         = new WP_Term_Query(
				array(
					'object_ids' => get_the_ID(),
				)
			);
			$reactions = array();
		foreach ( $t->get_terms() as $term ) {
			if ( substr( $term->taxonomy, 0, 16 ) !== 'friend-reaction-' ) {
				continue;
			}
			$user_id = substr( $term->taxonomy, 16 );
			if ( ! isset( $reactions[ $term->slug ] ) ) {
				$reactions[ $term->slug ] = array();
			}
			$reactions[ $term->slug ][ $user_id ] = 1;
		}

			ob_start();
			include apply_filters( 'friends_template_path', 'friends/reactions.php' );
			$reactions_text = ob_get_contents();
			ob_end_clean();

			// wp_set_object_terms( get_the_ID(),'smile', 'friend-reaction-' . get_current_user_id(), true );
			$text .= $reactions_text;

		if ( ! $echo ) {
			return $text;
		}

			echo $text;
	}
}
