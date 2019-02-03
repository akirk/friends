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
	 * @param  string $post_type The name of a post_type.
	 */
	public static function register_post_type( $post_type ) {
		if ( ! post_type_exists( $post_type ) ) {
			return false;
		}
		$friends = Friends::get_instance();
		if ( $friends->is_known_post_type( $post_type ) ) {
			// Already registered.
			return false;
		}
		$friends->registered_post_types[ $post_type ] = Friends::CPT_PREFIX . $post_type;
	}
}
