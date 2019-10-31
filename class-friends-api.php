<?php
/**
 * Friends
 *
 * A plugin to connect WordPresses and communicate privately with your friends.
 *
 * @package Friends
 */

/**
 * This is the class for the Friends Plugin.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_API {
	/**
	 * Register a post type with the Friends plugin
	 *
	 * @param  string $post_type The name of a post type.
	 */
	public static function register_post_type( $post_type ) {
		$friends = Friends::get_instance();
		if ( $friends->post_types->is_known( $post_type ) ) {
			// Already registered.
			return false;
		}
		$friends->post_types->register( $post_type );
	}

	/**
	 * Unregister a post type from the Friends plugin
	 *
	 * @param  string $post_type The name of a post type.
	 */
	public static function unregister_post_type( $post_type ) {
		$friends = Friends::get_instance();
		if ( ! $friends->post_types->is_known( $post_type ) ) {
			// Already registered.
			return false;
		}
		$friends->post_types->unregister( $post_type );
	}
}
