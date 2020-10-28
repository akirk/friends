<?php
/**
 * Friends Post Types
 *
 * This contains the functions for post types.
 *
 * @package Friends
 */

/**
 * This is the class for the post types part of the Friends Plugin.
 *
 * @since 0.21
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Post_Types {
	/**
	 * Post types registered by other plugins.
	 *
	 * @var array
	 */
	private $registered_post_types = array();

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
	}

	/**
	 * Get all the post types that have been registered to be handled by the Friends plugin.
	 *
	 * @return array The array of cache post types.
	 */
	public function get_all_registered() {
		return array_keys( $this->registered_post_types );
	}
	/**
	 * Get all the post types that cache posts for friends.
	 *
	 * @return array The array of cache post types.
	 */
	public function get_all_cached() {
		return array_values( $this->registered_post_types );
	}

	/**
	 * Get all the cached and original post types.
	 *
	 * @return array The array of post types.
	 */
	public function get_all() {
		return array_merge( array_keys( $this->registered_post_types ), array_values( $this->registered_post_types ) );
	}

	/**
	 * Check whether a post type is a cached post type.
	 *
	 * @param  string $cached_post_type The post type to check.
	 * @return boolean                  Whether the post type is a cached post type.
	 */
	public function is_cached_post_type( $cached_post_type ) {
		return in_array( $cached_post_type, $this->registered_post_types, true );
	}

	/**
	 * Check whether a post type has been registered with the Friends plugin.
	 * Register this through Friends_API::register_post_type( $post_type );
	 *
	 * @param  string $post_type The post type to check.
	 * @return boolean           Whether the post type has been registered.
	 */
	public function is_known( $post_type ) {
		return isset( $this->registered_post_types[ $post_type ] );
	}

	/**
	 * Get the corresponding cached post type for the given post type.
	 *
	 * @param  string $post_type The post type to check.
	 * @return string            The post type to be used for caching.
	 */
	public function get_cache_post_type( $post_type ) {
		return $this->registered_post_types[ $post_type ];
	}

	/**
	 * Registers a custom post type to be handled by the friends plugin.
	 *
	 * @param string $post_type The post type to be registered.
	 * @return string|false
	 */
	public function register( $post_type ) {
		if ( ! post_type_exists( $post_type ) ) {
			return new WP_Error( 'inexistant-post-type', "You're trying to register a post type that is not known to WordPress." );
		}

		$cache_post_type = Friends::CPT_PREFIX . $post_type;
		if ( 'post' === $post_type ) {
			$cache_post_type = Friends::CPT;
		}
		$this->registered_post_types[ $post_type ] = $cache_post_type;
		do_action( 'friends_register_post_type', $post_type, $cache_post_type );

		return $post_type;
	}

	/**
	 * Unregisters the custom post type from being handled by the friends plugin.
	 *
	 * @param string $post_type The post type to be unregistered.
	 * @return boolean
	 */
	public function unregister( $post_type ) {
		if ( ! isset( $this->registered_post_types[ $post_type ] ) ) {
			return false;
		}
		unset( $this->registered_post_types[ $post_type ] );
		return true;
	}

}
