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
	private $emojis;

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
	 * Unregister the hooks that attach reactions to content
	 */
	public function unregister_content_hooks() {
		remove_action( 'the_content', array( $this, 'post_reactions' ), 20 );
	}

	/**
	 * Register the taxonomies necessary
	 */
	public function register_taxonomies() {
		$this->register_user_taxonomy( get_current_user_id() );
	}

	/**
	 * Register the taxonomy for a certain user
	 *
	 * @param  int $user_id The user id.
	 */
	public function register_user_taxonomy( $user_id ) {
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
		register_taxonomy( 'friend-reaction-' . $user_id, $this->friends->get_all_post_types(), $args );
	}

	/**
	 * Get my reactions for a post.
	 *
	 * @param  int $post_id The post ID.
	 * @return array The users' reactions.
	 */
	public function get_my_reactions( $post_id ) {
		$my_reactions = wp_get_object_terms( $post_id, 'friend-reaction-' . get_current_user_id() );
		if ( is_wp_error( $my_reactions ) ) {
			return array();
		}
		$reactions = array();
		foreach ( $my_reactions as $term ) {
			$reactions[] = $term->slug;
		}

		return $reactions;
	}

	/**
	 * Get the reactions for a post.
	 *
	 * @param  int       $post_id The post ID.
	 * @param  int|false $exclude_user_id Whether to exclude a certain user_id.
	 * @return array The users' reactions.
	 */
	public function get_reactions( $post_id, $exclude_user_id = false ) {
		$reactions  = array();
		$term_query = new WP_Term_Query(
			array(
				'object_ids' => $post_id,
			)
		);

		if ( false !== $exclude_user_id ) {
			$excluded_user = new WP_User( $exclude_user_id );
		} else {
			$excluded_user = wp_get_current_user();
		}

		foreach ( $term_query->get_terms() as $term ) {
			if ( substr( $term->taxonomy, 0, 16 ) !== 'friend-reaction-' ) {
				continue;
			}
			if ( ! isset( $reactions[ $term->slug ] ) ) {
				$reactions[ $term->slug ] = array();
			}

			$user_id = intval( substr( $term->taxonomy, 16 ) );
			if ( $exclude_user_id === $user_id || ( false === $exclude_user_id && get_current_user_id() === $user_id ) ) {
				$user_reactions[ $term->slug ] = true;
				continue;
			}

			$user = new WP_User( $user_id );
			if ( ! $user || is_wp_error( $user ) ) {
				continue;
			}

			if ( ! isset( $reactions[ $term->slug ] ) ) {
				$reactions[ $term->slug ] = array();
			}
			$reactions[ $term->slug ][ $user_id ] = $user->display_name;
		}

		$remote_reactions = maybe_unserialize( get_post_meta( $post_id, 'remote_reactions', true ) );
		foreach ( $reactions as $slug => $reacting_usernames ) {
			$user_reacted = isset( $user_reactions[ $slug ] );

			$count = count( $reacting_usernames );
			if ( false === $exclude_user_id && $user_reacted ) {
				$count += 1;
			}

			$usernames = array_values( $reacting_usernames );
			if ( false === $exclude_user_id && $user_reacted ) {
				$usernames[] = $excluded_user->display_name;
			}

			if ( is_array( $remote_reactions ) && isset( $remote_reactions[ $slug ] ) ) {
				$count      += $remote_reactions[ $slug ]->count;
				$usernames[] = $remote_reactions[ $slug ]->usernames;
				unset( $remote_reactions[ $slug ] );
			}

			$usernames = array_filter( $usernames );
			if ( empty( $usernames ) ) {
				unset( $reactions[ $slug ] );
				continue;
			}

			$reactions[ $slug ] = (object) array(
				'count'        => intval( $count ),
				'html_entity'  => $this->get_emoji_html( $slug ),
				'usernames'    => implode( ', ', $usernames ),
				'user_reacted' => isset( $user_reactions[ $slug ] ),
			);
		}

		if ( is_array( $remote_reactions ) ) {
			foreach ( $remote_reactions as $slug => $reaction ) {
				$reaction->user_reacted = false;
				$reaction->html_entity  = $this->get_emoji_html( $slug );
				$reactions[ $slug ]     = $reaction;
			}
		}

		ksort( $reactions );

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
			include apply_filters( 'friends_template_path', 'friends/post-reactions.php' );
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
		check_ajax_referer( 'friends-reaction' );

		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'unauthorized', 'You are not authorized to send a reaction.' );
		}

		if ( ! isset( $_POST['post_id'] ) || ! isset( $_POST['reaction'] ) ) {
			wp_send_json_error(
				array(
					'result' => false,
				)
			);
		}

		if ( ! is_numeric( $_POST['post_id'] ) || $_POST['post_id'] <= 0 ) {
			wp_send_json_error(
				array(
					'result' => false,
				)
			);
		}

		$post_id = intval( $_POST['post_id'] );

		if ( ! $this->get_emoji_html( $_POST['reaction'] ) ) {
			// This emoji is not defined in emoji.json.
			return new WP_Error( 'invalid-emoji', 'This emoji is unknown.' );
		}

		$term = false;
		foreach ( wp_get_object_terms( $post_id, 'friend-reaction-' . get_current_user_id() ) as $t ) {
			if ( $t->slug === $_POST['reaction'] ) {
				$term = $t;
				break;
			}
		}

		if ( ! $term ) {
			wp_set_object_terms( $post_id, $_POST['reaction'], 'friend-reaction-' . get_current_user_id(), true );
		} else {
			wp_remove_object_terms( $post_id, $term->term_id, 'friend-reaction-' . get_current_user_id() );
		}

		do_action( 'friends_user_post_reaction', $post_id );

		wp_send_json_success(
			array(
				'result' => true,
			)
		);
	}

	/**
	 * Fetches the Emojis from the JSON file.
	 *
	 * @return array The emojis.
	 */
	private function get_emojis() {
		if ( ! $this->emojis ) {
			$this->emojis = json_decode( file_get_contents( __DIR__ . '/emojis.json' ), true );
		}
		return $this->emojis;
	}

	/**
	 * Get the HTML code for an emoji
	 *
	 * @param  string $slug The emoji shortname to look up.
	 * @return string|false The emoji HTML or false if it doesn't exist.
	 */
	public function get_emoji_html( $slug ) {
		$emojis = $this->get_emojis();

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
	public function update_remote_feed_reactions( $post_id, array $feed_data ) {
		$reactions = array();

		foreach ( $feed_data as $feed_reaction ) {
			$attribs = $feed_reaction['attribs'][ Friends_Feed::XMLNS ];
			$slug    = $attribs['slug'];
			if ( ! preg_match( '/^[a-z0-9_-]+$/', $slug ) ) {
				continue;
			}

			$reactions[ $slug ] = (object) array(
				'count'        => $attribs['count'],
				'usernames'    => $feed_reaction['data'],
				'user_reacted' => isset( $attribs['you-reacted'] ) && $attribs['you-reacted'],
			);
		}

		return $this->update_remote_reactions( $post_id, $reactions );
	}

	/**
	 * Store remote reactions in post_meta and update the main user taxonomy.
	 *
	 * @param  int   $post_id   The post id.
	 * @param  array $reactions The reactions data to be updated.
	 * @return array The parsed reactions.
	 */
	public function update_remote_reactions( $post_id, array $reactions ) {
		$main_user_id = $this->friends->get_main_friend_user_id();
		$this->register_user_taxonomy( $main_user_id );
		$main_user_reactions = wp_get_object_terms( $post_id, 'friend-reaction-' . $main_user_id );
		if ( is_wp_error( $main_user_reactions ) ) {
			$main_user_reactions = array();
		}
		$changed = false;

		foreach ( $reactions as $slug => $reaction ) {
			if ( is_array( $reaction ) ) {
				$reaction = (object) $reaction;
			}

			if (
				! preg_match( '/^[a-z0-9_-]+$/', $slug )
				|| ! isset( $reaction->count )
				|| $reaction->count < 0
				|| ! isset( $reaction->usernames )
			) {
				unset( $reactions[ $slug ] );
				continue;
			}

			$term = false;
			foreach ( $main_user_reactions as $k => $t ) {
				if ( $t->slug === $slug ) {
					$term = $t;
					unset( $main_user_reactions[ $k ] );
					break;
				}
			}

			if ( $reaction->user_reacted && ! $term ) {
				// Someone reacted on the remote site which hasn't been recorded here yet.
				wp_set_object_terms( $post_id, $slug, 'friend-reaction-' . $main_user_id, true );
				$changed = true;
			} elseif ( ! $reaction->user_reacted && $term ) {
				// Someone removed our reaction on the remote site so we need to delete it here.
				wp_remove_object_terms( $post_id, $term->term_id, 'friend-reaction-' . $main_user_id );
				$changed = true;
			}

			unset( $reaction->user_reacted );
			$reactions[ $slug ] = $reaction;

			if ( ! $reaction->count ) {
				unset( $reactions[ $slug ] );
			}
		}

		// Remove all remaining reactions as they have not been reported by remote.
		foreach ( $main_user_reactions as $term ) {
			wp_remove_object_terms( $post_id, $term->term_id, 'friend-reaction-' . $main_user_id );
		}

		update_post_meta( $post_id, 'remote_reactions', $reactions );
		return $reactions;
	}

	/**
	 * Store reactions of a friend.
	 *
	 * @param  int   $post_id   The post id.
	 * @param  int   $friend_user_id The friend who reacted.
	 * @param  array $reactions The reactions data to be updated.
	 * @return array The parsed reactions.
	 */
	public function update_friend_reactions( $post_id, $friend_user_id, array $reactions ) {
		$this->register_user_taxonomy( $friend_user_id );
		$friend_user_reactions = wp_get_object_terms( $post_id, 'friend-reaction-' . $friend_user_id );

		if ( is_wp_error( $friend_user_reactions ) ) {
			return false;
		}
		foreach ( $reactions as $slug ) {
			if ( ! preg_match( '/^[a-z0-9_-]+$/', $slug ) ) {
				continue;
			}

			$term = false;
			foreach ( $friend_user_reactions as $k => $t ) {
				if ( $t->slug === $slug ) {
					$term = $t;
					unset( $friend_user_reactions[ $k ] );
					break;
				}
			}

			if ( ! $term ) {
				wp_set_object_terms( $post_id, $slug, 'friend-reaction-' . $friend_user_id, true );
			}
		}

		// Remove all remaining reactions as they have not been reported by remote.
		foreach ( $friend_user_reactions as $term ) {
			wp_remove_object_terms( $post_id, $term->term_id, 'friend-reaction-' . $friend_user_id );
		}

		return $reactions;
	}
}
