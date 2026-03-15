<?php
/**
 * Friends Blocks
 *
 * This contains the functions for blocks.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Friends Plugin blocks.
 *
 * @since 0.8
 *
 * @package Friends
 * @author Alex Kirk
 */
class Blocks {
	/**
	 * Whether an excerpt is currently being generated.
	 *
	 * @var        int
	 */
	private $current_excerpt = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		if ( function_exists( 'classicpress_version' ) ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'language_data' ) );
		add_filter( 'render_block', array( $this, 'render_friends_block_visibility' ), 10, 2 );
		add_filter( 'get_the_excerpt', array( $this, 'current_excerpt_start' ), 9, 2 );
		add_filter( 'get_the_excerpt', array( $this, 'current_excerpt_end' ), 11, 2 );
		add_filter( 'wp_loaded', array( $this, 'add_block_visibility_attribute' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'register_friends_block_visibility' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register our blocks.
	 */
	public function register_blocks() {
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

		register_block_type(
			'friends/subscriptions-query',
			array(
				'render_callback' => array( $this, 'render_subscriptions_query_block' ),
			)
		);

		register_block_type(
			'friends/subscription',
			array(
				'uses_context'    => array( 'friends/subscription' ),
				'render_callback' => array( $this, 'render_subscription_block' ),
			)
		);

		register_block_type(
			'friends/followers',
			array(
				'render_callback' => array( $this, 'render_followers_block' ),
			)
		);
	}

	/**
	 * Render the friends/subscriptions-query block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return string The rendered block HTML.
	 */
	public function render_subscriptions_query_block( $attributes, $content, $block ) {
		$subscriptions = User_Query::all_subscriptions()->get_results();

		if ( empty( $subscriptions ) ) {
			return '<p>' . esc_html__( "You don't have any subscriptions yet.", 'friends' ) . '</p>';
		}

		$items = '';
		foreach ( $subscriptions as $subscription ) {
			$context = array_merge(
				$block->context,
				array(
					'friends/subscription' => array(
						'ID'           => $subscription->ID,
						'display_name' => $subscription->display_name,
						'user_url'     => $subscription->user_url,
						'avatar_url'   => $subscription->get_avatar_url(),
						'page_url'     => $subscription->get_local_friends_page_url(),
					),
				)
			);

			$inner_content = '';
			foreach ( $block->parsed_block['innerBlocks'] as $inner_block ) {
				$inner_content .= ( new \WP_Block( $inner_block, $context ) )->render();
			}
			$items .= '<li>' . $inner_content . '</li>';
		}

		return '<ul class="wp-block-friends-subscriptions-query">' . $items . '</ul>';
	}

	/**
	 * Render the friends/subscription block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content.
	 * @param WP_Block $block      Block instance.
	 * @return string The rendered block HTML.
	 */
	public function render_subscription_block( $attributes, $content, $block ) {
		if ( empty( $block->context['friends/subscription'] ) ) {
			return '';
		}

		$subscription = $block->context['friends/subscription'];

		$out  = '<div class="wp-block-friends-subscription">';
		$out .= '<a href="' . esc_url( $subscription['page_url'] ) . '">';
		if ( $subscription['avatar_url'] ) {
			$out .= '<img src="' . esc_url( $subscription['avatar_url'] ) . '" alt="" class="avatar" width="40" height="40" />';
		}
		$out .= '<span>' . esc_html( $subscription['display_name'] ) . '</span>';
		$out .= '</a>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Render the friends/followers block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_followers_block() {
		global $friends_args;

		if ( ! class_exists( '\ActivityPub\Collection\Followers' ) ) {
			return '<section class="followers"><p>' . esc_html__( 'The follower list is currently dependent on the ActivityPub plugin.', 'friends' ) . '</p></section>';
		}

		$user_id      = isset( $friends_args['user_id'] ) ? $friends_args['user_id'] : get_current_user_id();
		$only_mutual  = isset( $friends_args['only_mutual'] ) ? $friends_args['only_mutual'] : false;
		$blog_followers = class_exists( '\ActivityPub\Collection\Actors' ) && \ActivityPub\Collection\Actors::BLOG_USER_ID === $user_id;

		$followers_list_page = 'users.php?page=activitypub-followers-list';
		if ( $blog_followers ) {
			$followers_list_page = 'options-general.php?page=activitypub&tab=followers';
		}

		$follower_data    = \ActivityPub\Collection\Followers::get_followers_with_count( $user_id );
		$total            = $follower_data['total'];
		$already_following = 0;

		foreach ( $follower_data['followers'] as $k => $follower ) {
			$data           = $follower->to_array();
			$data['url']    = \ActivityPub\object_to_uri( $data['url'] );
			$data['server'] = wp_parse_url( $data['url'], PHP_URL_HOST );
			$data['css_class'] = '';

			$following = User_Feed::get_by_url( $data['url'] );
			if ( ! $following || is_wp_error( $following ) ) {
				$following = User_Feed::get_by_url( str_replace( '@', 'users/', $data['url'] ) );
			}

			if ( $following && ! is_wp_error( $following ) ) {
				++$already_following;
				$data['friend_user'] = $following->get_friend_user();
				$data['action_url']  = $following->get_friend_user()->get_local_friends_page_url();
				$data['url']         = $following->get_friend_user()->get_local_friends_page_url();
				if ( ! $only_mutual ) {
					$data['css_class'] = ' already-following';
				}
			} else {
				$data['friend_user'] = false;
				$data['action_url']  = add_query_arg( 'url', $data['url'], admin_url( 'admin.php?page=add-friend' ) );
			}
			$data['remove_action_url']        = add_query_arg( 's', $data['url'], admin_url( $followers_list_page ) );
			$follower_data['followers'][ $k ] = $data;
		}

		ob_start();
		?>
		<section class="followers">
			<p>
			<?php if ( $only_mutual ) : ?>
				<a href="?">
			<?php endif; ?>
			<?php
			if ( $blog_followers ) {
				echo esc_html(
					sprintf(
						// translators: %s is the number of followers.
						_n( 'You have %s blog follower.', 'You have %s blog followers.', $total, 'friends' ),
						$total
					)
				);
			} else {
				echo esc_html(
					sprintf(
						// translators: %s is the number of followers.
						_n( 'You have %s follower.', 'You have %s followers.', $total, 'friends' ),
						$total
					)
				);
			}
			?>
			<?php if ( $only_mutual ) : ?>
				</a>
				<?php
				$not_yet_following = $total - $already_following;
				echo esc_html(
					sprintf(
						// translators: %s is the number of followers not yet followed back.
						_n( "You're not yet following %s of them.", "You're not yet following %s of them.", $not_yet_following, 'friends' ),
						$not_yet_following
					)
				);
				?>
			<?php else : ?>
				<a href="?mutual">
				<?php
				echo esc_html(
					sprintf(
						// translators: %s is the number of followers you follow back.
						_n( "You're following %s of them.", "You're following %s of them.", $already_following, 'friends' ),
						$already_following
					)
				);
				?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( $followers_list_page ) ); ?>">
				<?php
				if ( $blog_followers ) {
					esc_html_e( 'View all blog followers in wp-admin', 'friends' );
				} else {
					esc_html_e( 'View all followers in wp-admin', 'friends' );
				}
				?>
			</a>
			</p>
			<ul>
			<?php foreach ( $follower_data['followers'] as $follower ) : ?>
				<?php if ( $only_mutual && ! $follower['friend_user'] ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<li>
					<details data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-preview' ) ); ?>" data-following="<?php echo esc_attr( $follower['following'] ); ?>" data-followers="<?php echo esc_attr( $follower['followers'] ); ?>" data-id="<?php echo esc_attr( $follower['id'] ); ?>">
						<summary>
							<a href="<?php echo esc_url( $follower['url'] ); ?>" class="follower<?php echo esc_attr( $follower['css_class'] ); ?>">
								<img width="40" height="40" src="<?php echo esc_attr( $follower['icon']['url'] ); ?>" class="avatar activitypub-avatar" />
								<span class="activitypub-actor">
									<strong class="activitypub-name"><?php echo esc_html( $follower['name'] ); ?></strong>
									(<span class="activitypub-handle">@<?php echo esc_html( $follower['preferredUsername'] . '@' . $follower['server'] ); ?></span>)
								</span>
							</a>
							<span class="since"><?php echo esc_html( $follower['published'] ); ?></span>
							<span class="their-followers"></span>
							<span class="their-following"></span>
							&nbsp;&nbsp;
							<?php if ( $follower['friend_user'] ) : ?>
								<span class="follower" title="<?php esc_attr_e( 'Already following', 'friends' ); ?>">
									<span class="ab-icon dashicons dashicons-businessperson" style="vertical-align: middle;"><span class="ab-icon dashicons dashicons-yes"></span></span>
								</span>
							<?php else : ?>
								<a href="<?php echo esc_url( $follower['action_url'] ); ?>" class="follower follower-add">
									<span class="ab-icon dashicons dashicons-businessperson" style="vertical-align: middle;"><span class="ab-icon dashicons dashicons-plus"></span></span>
								</a>
							<?php endif; ?>
							<a href="<?php echo esc_url( $follower['remove_action_url'] ); ?>" class="follower follower-delete" title="<?php esc_attr_e( 'Remove follower', 'friends' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-followers' ) ); ?>" data-handle="<?php echo esc_attr( $follower['preferredUsername'] . '@' . $follower['server'] ); ?>" data-id="<?php echo esc_attr( $follower['id'] ); ?>">
								<span class="ab-icon dashicons dashicons-admin-users" style="vertical-align: middle;"><span class="ab-icon dashicons dashicons-no"></span></span>
							</a>
							<p class="description">
								<?php
								echo wp_kses(
									$follower['summary'],
									array(
										'a' => array( 'href' => array() ),
									)
								);
								?>
							</p>
						</summary>
						<p class="loading-posts">
							<span><?php esc_html_e( 'Loading posts', 'friends' ); ?></span>
							<i class="form-icon loading"></i>
						</p>
					</details>
				</li>
			<?php endforeach; ?>
			</ul>
		</section>
		<?php
		return ob_get_clean();
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
			default:
			case 'subscriptions':
				$friends  = User_Query::all_subscriptions();
				$no_users = __( "You don't have any subscriptions yet.", 'friends' );
				break;
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
			++$count;
			if ( $attributes['users_inline'] ) {
				if ( ! $first ) {
					$out .= ', ';
				}
				$first = false;
			} else {
				$out .= '<li>';
			}

			if ( friends::has_required_privileges() ) {
				$url = $friend_user->get_local_friends_page_url();
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

		$only_users = array();
		if ( isset( $attributes['only_users'] ) ) {
			$only_users = array_flip( array_filter( preg_split( '/[, ]+/', $attributes['only_users'] ) ) );
		}
		$exclude_users = array();
		if ( isset( $attributes['exclude_users'] ) ) {
			$exclude_users = array_flip( array_filter( preg_split( '/[, ]+/', $attributes['exclude_users'] ) ) );
		}

		$count     = max( 1, min( 100, $attributes['count'] ) );
		$remaining = $count;
		$offset    = 0;
		$out       = '<ul class="friend-posts">';
		while ( $remaining > 0 ) {
			$recent_posts = wp_get_recent_posts(
				array(
					'numberposts' => $count,
					'offset'      => $offset,
					'post_type'   => apply_filters( 'friends_frontend_post_types', array() ),
				)
			);
			if ( count( $recent_posts ) === 0 ) {
				break;
			}
			$offset += $count;

			foreach ( $recent_posts as $post ) {
				$friend_user = new User( $post['post_author'] );

				if ( ! empty( $only_users ) && ! isset( $only_users[ $friend_user->user_login ] ) && ! isset( $only_users[ $friend_user->ID ] ) && ! isset( $only_users[ $friend_user->display_name ] ) ) {
					continue;
				}

				if ( ! empty( $exclude_users ) && isset( $exclude_users[ $friend_user->user_login ] ) && isset( $exclude_users[ $friend_user->ID ] ) && isset( $exclude_users[ $friend_user->display_name ] ) ) {
					continue;
				}

				if ( $remaining <= 0 ) {
					break 2;
				}
				--$remaining;

				$author = $friend_user->display_name;
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
				$title = get_the_title( $post['ID'] );
				if ( empty( $title ) ) {
					$title = get_the_excerpt( $post['ID'] );
				}
				$out .= sprintf(
					'<a class="wp-block-friends-friend-posts" href="%1$s">%2$s</a>',
					esc_url( $attributes['internal_link'] ? $friend_user->get_local_friends_page_url( $post['ID'] ) : get_permalink( $post['ID'] ) ),
					esc_html( $title )
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
						$out .= sprintf( __( '%s ago' ), human_time_diff( $post_date ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
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
			array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
			Friends::VERSION,
			true
		);

		wp_enqueue_style(
			'friends-blocks',
			plugins_url( 'friends-blocks.css', FRIENDS_PLUGIN_FILE ),
			array(),
			Friends::VERSION,
			true
		);
	}

	/**
	 * Adds a block visibility attribute.
	 */
	public function add_block_visibility_attribute() {
		$registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();

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
	 * @param  string $content    The content provided by the user.
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

		if ( ! $visibility ) {
			return $content;
		}
		$class_only_friends = ' class="only-friends" style="background-color: #efe; padding-left: .5em;"';
		$class_not_friends = ' class="not-friends" style="background-color: #fee; padding-left: .5em;"';
		$class_watermark = ' class="watermark" style="float: right; padding-top: .5em; padding-right: .5em; font-size: 80%; color: #ccc;"';

		switch ( $visibility ) {
			case 'only-friends':
				if ( Friends::has_required_privileges() ) {
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
				if ( Friends::has_required_privileges() ) {
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
	 * @param      string   $text   The text.
	 * @param      \WP_Post $post   The post.
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
	 * @param      string   $text   The text.
	 * @param      \WP_Post $post   The post.
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
