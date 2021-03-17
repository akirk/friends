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
	 * Whether an excerpt is currently being generated.
	 *
	 * @var        int
	 */
	private $current_excerpt = null;

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
			add_filter( 'get_the_excerpt', array( $this, 'current_excerpt_start' ), 9, 2 );
			add_filter( 'get_the_excerpt', array( $this, 'current_excerpt_end' ), 11, 2 );
			add_filter( 'wp_loaded', array( $this, 'add_block_visibility_attribute' ), 10, 2 );
			add_action( 'enqueue_block_editor_assets', array( $this, 'register_friends_block_visibility' ) );

			if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
				// Blocks is not active.
				return;
			}
			register_block_type_from_metadata(
				FRIENDS_PLUGIN_DIR . '/blocks/friends-list',
				array(
					'render_callback' => array( $this, 'render_friends_list_block' ),
				)
			);

			register_block_type_from_metadata(
				FRIENDS_PLUGIN_DIR . '/blocks/friend-posts',
				array(
					'render_callback' => array( $this, 'render_friend_posts_block' ),
				)
			);

		}
	}

	/**
	 * Render the Friends List block
	 *
	 * @param  array $attributes Attributes set by Blocks.
	 * @return string The new block content.
	 */
	public function render_friends_list_block( $attributes ) {
		if ( ! isset( $attributes['user_types'] ) ) {
			$attributes['user_types'] = 'friends';
		}
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
		$count = 0;
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
	 * Render the Friend Posts block
	 *
	 * @param  array $attributes Attributes set by Blocks.
	 * @return string The new block content.
	 */
	public function render_friend_posts_block( $attributes ) {
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
					'post_type'   => Friends::CPT,
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
						if ( gmdate( $compare ) === gmdate( $compare, $post_date ) ) {
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
	 * Register the Block Visibility script.
	 */
	public function register_friends_block_visibility() {
		wp_enqueue_script(
			'friends-block-visibility',
			plugins_url( 'blocks/block-visibility/build/index.js', FRIENDS_PLUGIN_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-editor' ),
			Friends::VERSION
		);

		wp_enqueue_style(
			'friends-blocks',
			plugins_url( 'friends-blocks.css', FRIENDS_PLUGIN_FILE ),
			array(),
			Friends::VERSION
		);
	}


	/**
	 * Adds a block visibility attribute.
	 */
	function add_block_visibility_attribute() {
		$registered_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();

		foreach ( $registered_blocks as $block ) {
			$block->attributes['friendsVisibility'] = array(
				'type'    => 'string',
				'default' => '',
			);
		}
	}
	/**
	 * Render the "Only visible for friends" Blocks block
	 *
	 * @param  object $content    The content provided by the user.
	 * @param  object $block      Attributes for the block.
	 * @return string             The rendered content.
	 */
	public function render_friends_block_visibility( $content, $block ) {
		$css_class = ( empty( $block['attrs'] ) || empty( $block['attrs']['className'] ) ) ? 'default' : $block['attrs']['className'];
		$visibility = '';
		if ( preg_match( '/\bonly-friends\b/', $css_class ) ) {
			$visibility = 'only-friends';
		} elseif ( preg_match( '/\bnot-friends\b/', $css_class ) ) {
			$visibility = 'not-friends';
		}
		$class_only_friends = ' class="only-friends" style="background-color: #efe; padding-left: .5em;"';
		$class_not_friends = ' class="not-friends" style="background-color: #fee; padding-left: .5em;"';
		$class_watermark = ' class="watermark" style="float: right; padding-top: .5em; padding-right: .5em; font-size: 80%; color: #ccc;"';

		switch ( $visibility ) {
			case 'only-friends':
				if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
					if ( $this->current_excerpt ) {
						return $content;
					}
					return '<div' . $class_only_friends . '><span' . $class_watermark . '>' . __( 'Only friends', 'friends' ) . '</span>' . $content . '</div>';
				}
				if ( current_user_can( 'friend' ) ) {
					return $content;
				}
				return '';

			case 'not-friends':
				if ( current_user_can( Friends::REQUIRED_ROLE ) ) {
					if ( $this->current_excerpt ) {
						return $content;
					}

					return '<div' . $class_not_friends . '><span' . $class_watermark . '>' . __( 'Not friends', 'friends' ) . '</span>' . $content . '</span></div>';
				}
				if ( current_user_can( 'friend' ) ) {
					return '';
				}
				return $content;

		}

		return $content;
	}

	/**
	 * Remember the current post being excerpted. With this we can change the visibility rendering.
	 *
	 * @param      string  $text   The text.
	 * @param      WP_Post $post   The post.
	 *
	 * @return     string  The text.
	 */
	public function current_excerpt_start( $text = '', $post = null ) {
		if ( $post ) {
			$this->current_excerpt = $post->ID;
		}
		return $text;
	}

	/**
	 * Stop remembering the current post being excerpted.
	 *
	 * @param      string  $text   The text.
	 * @param      WP_Post $post   The post.
	 *
	 * @return     string  The text.
	 */
	public function current_excerpt_end( $text = '', $post = null ) {
		if ( $post && $this->current_excerpt === $post->ID ) {
			$this->current_excerpt = null;
		}
		return $text;
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
