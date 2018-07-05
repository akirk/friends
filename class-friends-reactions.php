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
	 * Holds the supported Emojis array.
	 *
	 * @var array
	 */
	private static $emojis;

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
		add_action( 'wp_ajax_friends_toggle_react', array( $this, 'toggle_react' ) );
		add_action( 'the_content', array( $this, 'post_reactions' ), 20 );
		add_action( 'wp_footer', array( $this, 'reactions_picker' ), 20 );
	}

	/**
	 * Register the hooks that attach reactions to content
	 */
	public function unregister_content_hooks() {
		remove_action( 'the_content', array( $this, 'post_reactions' ), 20 );
	}

	/**
	 * Register the taxonomies necessary
	 */
	public function register_taxonomies() {
		$args = array(
			'labels'            => array(
				'name'          => _x( 'Reactions', 'taxonomy general name' ),
				'singular_name' => _x( 'Reaction', 'taxonomy singular name' ),
				'menu_name'     => __( 'Reaction' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
		);
		register_taxonomy( 'friend-reaction-' . get_current_user_id(), array( 'post', Friends::FRIEND_POST_CACHE ), $args );
	}

	/**
	 * Gets the usernames of reactions (including remote reactions).
	 *
	 * @param  array $reaction_users The users returned from get_reactions.
	 * @return string The usernames of the reactions.
	 */
	public static function get_count( array $reaction_users ) {
		$count = count( $reaction_users );
		if ( isset( $reaction_users['remote'] ) ) {
			$count += $reaction_users['remote']->count - 1;
		}

		return $count;
	}

	/**
	 * Determine the count of reactions (including remote reactions).
	 *
	 * @param  array $reaction_users The users returned from get_reactions.
	 * @return int The number of reactions.
	 */
	public static function get_usernames( array $reaction_users ) {
		$count = count( $reaction_users );
		if ( isset( $reaction_users['remote'] ) ) {
			$reaction_users['remote'] = $reaction_users['remote']->usernames;
		}

		return implode( ', ', $reaction_users );

	}

	/**
	 * Get the reactions for a post.
	 *
	 * @param  int $post_id The post ID.
	 * @return array The users' reactions.
	 */
	public function get_reactions( $post_id ) {
		$reactions        = array();
		$term_query       = new WP_Term_Query(
			array(
				'object_ids' => $post_id,
			)
		);
		$remote_reactions = maybe_unserialize( get_post_meta( $post_id, 'remote_reactions', true ) );
		if ( is_array( $remote_reactions ) ) {
			foreach ( $remote_reactions as $slug => $reaction ) {
				if ( ! isset( $reactions[ $slug ] ) ) {
					$reactions[ $slug ] = array();
				}
				$reactions[ $slug ]['remote'] = $reaction;
			}
		}

		foreach ( $term_query->get_terms() as $term ) {
			if ( substr( $term->taxonomy, 0, 16 ) !== 'friend-reaction-' ) {
				continue;
			}
			if ( ! isset( $reactions[ $term->slug ] ) ) {
				$reactions[ $term->slug ] = array();
			}

			$user_id = substr( $term->taxonomy, 16 );
			$user    = new WP_User( $user_id );
			if ( ! $user || is_wp_error( $user ) ) {
				continue;
			}

			if ( ! isset( $reactions[ $term->slug ] ) ) {
				$reactions[ $term->slug ] = array();
			}
			$reactions[ $term->slug ][ $user_id ] = $user->display_name;
		}

		return $reactions;
	}

	/**
	 * Display Post reactions under a post.
	 *
	 * @param  string  $text The post content.
	 * @param  boolean $echo Whether the content should be echoed.
	 * @return string        The post content with buttons or nothing if echoed.
	 */
	public function post_reactions( $text = '', $echo = false ) {
		if ( is_user_logged_in() ) {
			$reactions = $this->get_reactions( get_the_ID() );

			ob_start();
			include apply_filters( 'friends_template_path', 'friends/reactions.php' );
			$reactions_text = ob_get_contents();
			ob_end_clean();

			$text .= $reactions_text;
		}

		if ( ! $echo ) {
			return $text;
		}

		echo $text;
	}

	/**
	 * Output the reactions picker.
	 */
	public function reactions_picker() {
		if ( is_user_logged_in() ) {
			include apply_filters( 'friends_template_path', 'friends/reactions-picker.php' );
		}
	}

	/**
	 * Store a reaction.
	 */
	public function toggle_react() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'unauthorized', 'You are not authorized to send a reaction.' );
		}

		if ( is_numeric( $_POST['post_id'] ) && is_string( $_POST['reaction'] ) ) {
			// TODO: Whitelist Emojis.
			$term = false;
			foreach ( wp_get_object_terms( $_POST['post_id'], 'friend-reaction-' . get_current_user_id() ) as $t ) {
				if ( $t->slug === $_POST['reaction'] ) {
					$term = $t;
					break;
				}
			}
			if ( ! $term ) {
				wp_set_object_terms( $_POST['post_id'], $_POST['reaction'], 'friend-reaction-' . get_current_user_id(), true );
			} else {
				wp_remove_object_terms( $_POST['post_id'], $term->term_id, 'friend-reaction-' . get_current_user_id() );
			}
			return true;
		}
	}

	/**
	 * Fetches the Emojis from the JSON file.
	 *
	 * @return array The emojis.
	 */
	private static function get_emojis() {
		if ( ! self::$emojis ) {
			self::$emojis = json_decode( file_get_contents( __DIR__ . '/emojis.json' ), true );
		}
		return self::$emojis;
	}

	/**
	 * Get the HTML code for an emoji
	 *
	 * @param  string $slug The emoji shortname to look up.
	 * @return string|false The emoji HTML or false if it doesn't exist.
	 */
	public static function get_emoji_html( $slug ) {
		$emojis = self::get_emojis();

		if ( ! isset( $emojis[ $slug ] ) ) {
			return;
		}

		return $emojis[ $slug ];
	}

	/**
	 * Store remote reactions in post_meta.
	 *
	 * @param  int   $post_id   The post id.
	 * @param  array $feed_data The feed data as delivered by SimplePie.
	 * @return array The parsed reactions.
	 */
	public function update_remote_reactions( $post_id, array $feed_data ) {
		$reactions = array();

		foreach ( $feed_data as $feed_reaction ) {
			$attribs = $feed_reaction['attribs'][ Friends_Feed::XMLNS ];
			$slug    = $attribs['slug'];
			if ( ! preg_match( '/^[a-z0-9_-]+$/', $slug ) ) {
				continue;
			}

			$reactions[ $slug ] = (object) array(
				'slug'      => $slug,
				'count'     => $attribs['count'],
				'usernames' => $feed_reaction['data'],
			);
		}

		update_post_meta( $post_id, 'remote_reactions', $reactions );
		return $reactions;
	}

}
