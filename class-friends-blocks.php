<?php
/**
 * Friends Blocks
 *
 * This contains the functions for blocks.
 *
 * @package Friends
 */

/**
 * This is the class for the Friends Plugin blocks.
 *
 * @since 0.8
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Blocks {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends = null;

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
		if ( function_exists( 'register_block_type' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'language_data' ) );
			add_filter( 'render_block', array( $this, 'render_friends_block_visibility' ), 10, 2 );
			add_action( 'enqueue_block_editor_assets', array( $this, 'register_friends_block_visibility' ) );
			add_action( 'block_attributes', array( $this, 'block_attributes' ) );

			add_action( 'enqueue_block_editor_assets', array( $this, 'register_friends_list' ) );
			register_block_type(
				'friends/friends-list',
				array(
					'render_callback' => array( $this, 'render_block_friends_list' ),
					'attributes'      => array(
						'users_inline' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'user_types'   => array(
							'type' => 'string',
						),
					),
				)
			);

			add_action( 'enqueue_block_editor_assets', array( $this, 'register_friend_posts' ) );
			register_block_type(
				'friends/friend-posts',
				array(
					'render_callback' => array( $this, 'render_block_friend_posts' ),
					'attributes'      => array(
						'author_inline' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'author_name'   => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'author_avatar' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'show_date'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'count'         => array(
							'type'    => 'number',
							'default' => 5,
						),
						'exclude_users' => array(
							'type' => 'string',
						),
						'only_users'    => array(
							'type' => 'string',
						),
						'internal_link' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				)
			);
		}
	}

	/**
	 * Register the Friends List block
	 */
	public function register_friends_list() {
		if ( ! function_exists( 'register_block_type' ) ) {
			// Blocks is not active.
			return;
		}

		wp_enqueue_script(
			'friends-friends-list',
			plugins_url( 'blocks/friends-list.build.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor' )
		);

	}

	/**
	 * Render the Friends List block
	 *
	 * @param  array  $attributes Attributes set by Blocks.
	 * @param  string $content    The JS block content.
	 * @return string The new block content.
	 */
	public function render_block_friends_list( $attributes, $content ) {
		switch ( $attributes['user_types'] ) {
			case 'friend_requests':
				$friends  = Friend_User_Query::all_friend_requests();
				$no_users = __( "You currently don't have any friend requests.", 'friends' );
				break;

			case 'friends_subscriptions':
				$friends  = Friend_User_Query::all_friends_subscriptions();
				$no_users = __( "You don't have any friends or subscriptions yet.", 'friends' );
				break;

			case 'subscriptions':
				$friends  = Friend_User_Query::all_subscriptions();
				$no_users = __( "You don't have any subscriptions yet.", 'friends' );
				break;

			case 'friends':
			default:
				$friends  = Friend_User_Query::all_friends();
				$no_users = __( "You don't have any friends yet.", 'friends' );
		}

		if ( $friends->get_total() === 0 ) {
			return '<span class="wp-block-friends-friends-list no-users">' . $no_users . '</span>';
		}

		if ( $attributes['users_inline'] ) {
			$out   = '';
			$first = true;
		} else {
			$out = '<ul>';
		}
		foreach ( $friends->get_results() as $friend_user ) {
			$count += 1;
			if ( $attributes['users_inline'] ) {
				if ( ! $first ) {
					$out .= ', ';
				}
				$first = false;
			} else {
				$out .= '<li>';
			}

			if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
				$url = site_url( '/friends/' . $friend_user->user_login . '/' );
			} else {
				$url = $friend_user->user_url;
			}

			$out .= sprintf(
				'<a class="wp-block-friends-friends-list wp-user" href="%1$s">%2$s</a></li>',
				esc_url( $url ),
				esc_html( $friend_user->display_name )
			);
			if ( ! $attributes['users_inline'] ) {
				$out .= '</li>';
			}
		}
		if ( ! $attributes['users_inline'] ) {
			$out .= '</ul>';
		}

		return $out;
	}

	/**
	 * Register the Friend Posts block
	 */
	public function register_friend_posts() {
		if ( ! function_exists( 'register_block_type' ) ) {
			// Blocks is not active.
			return;
		}

		wp_enqueue_script(
			'friends-friend-posts',
			plugins_url( 'blocks/friend-posts.build.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor' )
		);
	}

	/**
	 * Render the Friend Posts block
	 *
	 * @param  array  $attributes Attributes set by Blocks.
	 * @param  string $content    The JS block content.
	 * @return string The new block content.
	 */
	public function render_block_friend_posts( $attributes, $content ) {
		$date_formats = array(
			'Y m d H' => 'human',
			'Y m d'   => 'H:i',
			'Y w'     => 'D j, H:i',
			'Y'       => 'M j',
			''        => 'M j, Y',
		);

		$last_author = false;

		$only_users    = array_flip( array_filter( preg_split( '/[, ]+/', $attributes['only_users'] ) ) );
		$exclude_users = array_flip( array_filter( preg_split( '/[, ]+/', $attributes['exclude_users'] ) ) );

		$count     = max( 1, min( 100, $attributes['count'] ) );
		$remaining = $count;
		$offset    = 0;
		$out       = '<ul class="friend-posts">';
		while ( $remaining > 0 ) {
			$recent_posts = wp_get_recent_posts(
				array(
					'numberposts' => $count,
					'offset'      => $offset,
					'post_type'   => $this->friends->post_types->get_all_cached(),
				)
			);
			if ( count( $recent_posts ) === 0 ) {
				break;
			}
			$offset += $count;

			foreach ( $recent_posts as $post ) {
				$author = get_the_author_meta( 'display_name', $post['post_author'] );

				if ( ! empty( $only_users ) && ! isset( $only_users[ $author ] ) ) {
					continue;
				}

				if ( ! empty( $exclude_users ) && isset( $exclude_users[ $author ] ) ) {
					continue;
				}

				if ( $remaining <= 0 ) {
					break 2;
				}
				$remaining -= 1;

				if ( $attributes['author_name'] || $attributes['author_avatar'] || $attributes['author_inline'] ) {
					if ( $attributes['author_inline'] || $last_author !== $author ) {
						if ( $last_author && ! $attributes['author_inline'] ) {
							$out .= '</li></ul></li>';
						}
						$last_author = $author;

						$out .= '<li>';
						if ( $attributes['author_avatar'] ) {
							$out .= '<img src="' . esc_url( get_avatar_url( $post['post_author'] ) ) . '" width="20" height="20" class="avatar" />';
						}
						if ( $attributes['author_name'] ) {
							$out .= esc_html( $author );
						}

						if ( $attributes['author_inline'] ) {
							$out .= ' ';
						} else {
							$out .= '<ul><li>';
						}
					} else {
						$out .= '<li>';
					}
				} else {
					$out .= '<li>';
				}

				$out .= sprintf(
					'<a class="wp-block-friends-friend-posts" href="%1$s">%2$s</a>',
					esc_url( $attributes['internal_link'] ? ( '/friends/' . $post['ID'] . '/' ) : get_permalink( $post['ID'] ) ),
					esc_html( get_the_title( $post['ID'] ) )
				);

				if ( $attributes['show_date'] ) {
					$post_date = strtotime( $post['post_date_gmt'] );
					foreach ( $date_formats as $compare => $date_format ) {
						if ( date( $compare ) === date( $compare, $post_date ) ) {
							break;
						}
					}
					$out .= ' <span class="date" data-date="' . esc_attr( $post['post_date'] ) . '">';
					if ( 'human' === $date_format ) {
						/* translators: %s is a time span */
						$out .= sprintf( __( '%s ago' ), human_time_diff( $post_date ) );
					} else {
						$out .= date_i18n( $date_format, strtotime( $post['post_date'] ) );
					}
					$out .= '</span>';
				}
				$out .= '</li>';
			}
		}

		if ( $last_author && ( $attributes['author_name'] || $attributes['author_avatar'] ) && ! $attributes['author_inline'] ) {
			$out .= '</ul></li>';
		}
		$out .= '</ul>';

		return $out;
	}

	/**
	 * Register the Blocks Block Visibility
	 */
	public function register_friends_block_visibility() {
		wp_enqueue_script(
			'friends-block-visibility',
			plugins_url( 'blocks/block-visibility.build.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor' )
		);

		wp_enqueue_style(
			'friends-blocks',
			plugins_url( 'friends-blocks.css', __FILE__ )
		);
	}

	/**
	 * Add the friends_visibility attribute globally.
	 *
	 * @param  object $attributes Attributes for the block.
	 * @return object             Attributes for the block.
	 */
	public function block_attributes( $attributes ) {
		return array_merge(
			$attributes,
			array(
				'friends_visibility' => array(
					'type' => 'string',
				),
			)
		);
	}

	/**
	 * Render the "Only visible for friends" Blocks block
	 *
	 * @param  object $content    The content provided by the user.
	 * @param  object $block      Attributes for the block.
	 * @return string             The rendered content.
	 */
	public function render_friends_block_visibility( $content, $block ) {
		$visibility = ( empty( $block['attrs'] ) || empty( $block['attrs']['friends_visibility'] ) ) ? 'default' : $block['attrs']['friends_visibility'];

		switch ( $visibility ) {
			case 'only-friends':
				if ( current_user_can( 'friend' ) || current_user_can( 'administrator' ) ) {
					return $content;
				}
				return '';

			case 'not-friends':
				if ( current_user_can( 'friend' ) && ! current_user_can( 'administrator' ) ) {
					return '';
				}
				return $content;

		}

		return $content;
	}

	/**
	 * Load up the language data for the Blocks blocks
	 */
	public function language_data() {
		$locale_data = array();
		if ( function_exists( 'wp_get_jed_locale_data' ) ) {
			$locale_data = wp_get_jed_locale_data( 'friends' );
		} elseif ( function_exists( 'blocks_get_jed_locale_data' ) ) {
			$locale_data = blocks_get_jed_locale_data( 'friends' );
		}

		if ( ! empty( $locale_data ) ) {
			wp_add_inline_script(
				'friends-block-not-friends',
				'wp.i18n.setLocaleData( ' . wp_json_encode( $locale_data ) . ', "friends" );',
				'before'
			);
		}
	}
}
