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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_sidebar_blocks' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Safely get block wrapper attributes, returning a fallback when not in a block context.
	 *
	 * @param array $extra_attributes Extra attributes to add.
	 * @return string The wrapper attributes string.
	 */
	private function get_wrapper_attributes( $extra_attributes = array() ) {
		if ( function_exists( 'get_block_wrapper_attributes' ) && \WP_Block_Supports::$block_to_render ) {
			return get_block_wrapper_attributes( $extra_attributes );
		}
		$attrs = '';
		foreach ( $extra_attributes as $key => $value ) {
			$attrs .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
		return $attrs;
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

		$list_supports = array(
			'color'      => array(
				'background' => true,
				'text'       => true,
				'link'       => true,
			),
			'typography' => array(
				'fontSize'   => true,
				'lineHeight' => true,
			),
			'spacing'    => array(
				'padding' => true,
				'margin'  => true,
			),
		);

		$chip_supports = array(
			'color'      => array(
				'background' => true,
				'text'       => true,
				'link'       => true,
			),
			'typography' => array(
				'fontSize' => true,
			),
			'spacing'    => array(
				'padding' => true,
				'margin'  => true,
			),
		);

		register_block_type(
			'friends/stats',
			array(
				'render_callback' => array( $this, 'render_stats_block' ),
				'supports'        => $list_supports,
			)
		);

		register_block_type(
			'friends/refresh',
			array(
				'render_callback' => array( $this, 'render_refresh_block' ),
				'supports'        => $list_supports,
			)
		);

		register_block_type(
			'friends/post-formats',
			array(
				'render_callback' => array( $this, 'render_post_formats_block' ),
				'supports'        => $list_supports,
			)
		);

		register_block_type(
			'friends/add-subscription',
			array(
				'render_callback' => array( $this, 'render_add_subscription_block' ),
				'supports'        => $list_supports,
			)
		);

		register_block_type(
			'friends/search',
			array(
				'render_callback' => array( $this, 'render_search_block' ),
			)
		);

		register_block_type(
			'friends/feed-title',
			array(
				'render_callback' => array( $this, 'render_feed_title_block' ),
				'supports'        => array(
					'color'      => array(
						'background' => true,
						'text'       => true,
						'link'       => true,
					),
					'typography' => array(
						'fontSize'   => true,
						'lineHeight' => true,
					),
				),
			)
		);

		register_block_type(
			'friends/feed-chips',
			array(
				'render_callback' => array( $this, 'render_feed_chips_block' ),
				'supports'        => $chip_supports,
			)
		);

		register_block_type(
			'friends/post-content',
			array(
				'render_callback' => array( $this, 'render_post_content_block' ),
			)
		);

		register_block_type(
			'friends/post-permalink',
			array(
				'render_callback' => array( $this, 'render_post_permalink_block' ),
				'supports'        => $list_supports,
			)
		);

		register_block_type(
			'friends/post-reblog',
			array(
				'render_callback' => array( $this, 'render_post_reblog_block' ),
			)
		);

		register_block_type(
			'friends/post-boost',
			array(
				'render_callback' => array( $this, 'render_post_boost_block' ),
			)
		);

		register_block_type(
			'friends/post-reactions',
			array(
				'render_callback' => array( $this, 'render_post_reactions_block' ),
			)
		);

		register_block_type(
			'friends/post-comments',
			array(
				'render_callback' => array( $this, 'render_post_comments_block' ),
			)
		);

		register_block_type(
			'friends/author-star',
			array(
				'render_callback' => array( $this, 'render_author_star_block' ),
			)
		);

		register_block_type(
			'friends/author-avatar',
			array(
				'render_callback' => array( $this, 'render_author_avatar_block' ),
			)
		);

		register_block_type(
			'friends/author-name',
			array(
				'render_callback' => array( $this, 'render_author_name_block' ),
				'supports'        => array(
					'color'      => array(
						'background' => true,
						'text'       => true,
						'link'       => true,
					),
					'typography' => array(
						'fontSize'   => true,
						'lineHeight' => true,
					),
				),
			)
		);

		register_block_type(
			'friends/author-description',
			array(
				'render_callback' => array( $this, 'render_author_description_block' ),
				'supports'        => $list_supports,
			)
		);

		register_block_type(
			'friends/author-chips',
			array(
				'render_callback' => array( $this, 'render_author_chips_block' ),
				'supports'        => $chip_supports,
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
			return '<p>' . esc_html__( "You're not following anyone yet.", 'friends' ) . '</p>';
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

		$user_id        = isset( $friends_args['user_id'] ) ? $friends_args['user_id'] : get_current_user_id();
		$blog_followers = class_exists( '\ActivityPub\Collection\Actors' ) && \ActivityPub\Collection\Actors::BLOG_USER_ID === $user_id;

		$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $filter, array( 'all', 'following', 'not-following' ), true ) ) {
			$filter = 'all';
		}
		// Backwards compatibility with ?mutual.
		if ( isset( $_GET['mutual'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filter = 'following';
		}

		$sort = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : 'newest'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $sort, array( 'newest', 'oldest', 'name' ), true ) ) {
			$sort = 'newest';
		}

		$followers_list_page = 'users.php?page=activitypub-followers-list';
		if ( $blog_followers ) {
			$followers_list_page = 'options-general.php?page=activitypub&tab=followers';
		}

		$follower_data     = \ActivityPub\Collection\Followers::query( $user_id );
		$total             = $follower_data['total'];
		// Classify followers only when a filter is active.
		if ( 'all' !== $filter ) {
			$following_ids     = array();
			$not_following_ids = array();
			foreach ( $follower_data['followers'] as $follower ) {
				$url          = $follower->guid;
				$is_following = false;
				if ( $url ) {
					$is_following = User_Feed::get_by_url( $url );
					if ( ! $is_following || is_wp_error( $is_following ) ) {
						$is_following = User_Feed::get_by_url( str_replace( '@', 'users/', $url ) );
					}
				}
				if ( $is_following && ! is_wp_error( $is_following ) ) {
					$following_ids[] = $follower->ID;
				} else {
					$not_following_ids[] = $follower->ID;
				}
			}

			if ( 'following' === $filter ) {
				$filtered_followers = array_filter(
					$follower_data['followers'],
					function ( $f ) use ( $following_ids ) {
						return in_array( $f->ID, $following_ids, true );
					}
				);
			} else {
				$filtered_followers = array_filter(
					$follower_data['followers'],
					function ( $f ) use ( $not_following_ids ) {
						return in_array( $f->ID, $not_following_ids, true );
					}
				);
			}
		} else {
			$filtered_followers = $follower_data['followers'];
		}

		// Sort.
		$filtered_followers = array_values( $filtered_followers );
		if ( 'oldest' === $sort ) {
			usort(
				$filtered_followers,
				function ( $a, $b ) {
					return $a->ID - $b->ID;
				}
			);
		} elseif ( 'name' === $sort ) {
			usort(
				$filtered_followers,
				function ( $a, $b ) {
					return strnatcasecmp( $a->post_title, $b->post_title );
				}
			);
		}

		// Paginate.
		$filtered_total     = count( $filtered_followers );
		$followers_per_page = 20;
		$current_page       = isset( $_GET['fpage'] ) ? max( 1, absint( $_GET['fpage'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_followers     = array_slice( $filtered_followers, ( $current_page - 1 ) * $followers_per_page, $followers_per_page );
		$display_followers  = array();

		foreach ( $page_followers as $follower ) {
			if ( $follower instanceof \WP_Post ) {
				$follower = \Activitypub\Collection\Remote_Actors::get_actor( $follower );
				if ( is_wp_error( $follower ) ) {
					continue;
				}
			}
			$data           = $follower->to_array();
			$data['url']    = \ActivityPub\object_to_uri( $data['url'] );
			$data['server'] = wp_parse_url( $data['url'], PHP_URL_HOST );
			$data['css_class'] = '';

			$following = User_Feed::get_by_url( $data['url'] );
			if ( ! $following || is_wp_error( $following ) ) {
				$following = User_Feed::get_by_url( str_replace( '@', 'users/', $data['url'] ) );
			}

			if ( $following && ! is_wp_error( $following ) ) {
				$data['friend_user'] = $following->get_friend_user();
				$data['action_url']  = $following->get_friend_user()->get_local_friends_page_url();
				$data['url']         = $following->get_friend_user()->get_local_friends_page_url();
				if ( 'all' === $filter ) {
					$data['css_class'] = ' already-following';
				}
			} else {
				$data['friend_user'] = false;
				$data['action_url']  = add_query_arg( 'url', $data['url'], admin_url( 'admin.php?page=add-friend' ) );
			}
			$data['remove_action_url'] = add_query_arg( 's', $data['url'], admin_url( $followers_list_page ) );
			$display_followers[]       = $data;
		}

		$base_url  = strtok( $_SERVER['REQUEST_URI'], '?' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$link_args = array();
		if ( 'all' !== $filter ) {
			$link_args['filter'] = $filter;
		}
		if ( 'newest' !== $sort ) {
			$link_args['sort'] = $sort;
		}

		ob_start();
		?>
		<section class="followers">
			<p>
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
			<p>
				<?php esc_html_e( 'Filter:', 'friends' ); ?>
				<?php
				$filters = array(
					'all'           => __( 'All', 'friends' ),
					'following'     => __( 'Following', 'friends' ),
					'not-following' => __( 'Not Following', 'friends' ),
				);
				$filter_links = array();
				foreach ( $filters as $filter_key => $filter_label ) {
					$url    = $base_url;
					$f_args = $link_args;
					if ( 'all' !== $filter_key ) {
						$f_args['filter'] = $filter_key;
					} else {
						unset( $f_args['filter'] );
					}
					if ( $f_args ) {
						$url = add_query_arg( $f_args, $url );
					}
					if ( $filter === $filter_key ) {
						$filter_links[] = '<strong>' . esc_html( $filter_label ) . '</strong>';
					} else {
						$filter_links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $filter_label ) . '</a>';
					}
				}
				echo wp_kses(
					implode( ' | ', $filter_links ),
					array(
						'strong' => array(),
						'a'      => array( 'href' => array() ),
					)
				);
				?>

				&nbsp;&nbsp;
				<?php esc_html_e( 'Sort:', 'friends' ); ?>
				<?php
				$sorts = array(
					'newest' => __( 'Newest', 'friends' ),
					'oldest' => __( 'Oldest', 'friends' ),
					'name'   => __( 'Name', 'friends' ),
				);
				$sort_links = array();
				foreach ( $sorts as $sort_key => $sort_label ) {
					$url    = $base_url;
					$s_args = $link_args;
					if ( 'newest' !== $sort_key ) {
						$s_args['sort'] = $sort_key;
					} else {
						unset( $s_args['sort'] );
					}
					if ( $s_args ) {
						$url = add_query_arg( $s_args, $url );
					}
					if ( $sort === $sort_key ) {
						$sort_links[] = '<strong>' . esc_html( $sort_label ) . '</strong>';
					} else {
						$sort_links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $sort_label ) . '</a>';
					}
				}
				echo wp_kses(
					implode( ' | ', $sort_links ),
					array(
						'strong' => array(),
						'a'      => array( 'href' => array() ),
					)
				);
				?>
			</p>
			<ul>
			<?php foreach ( $display_followers as $follower ) : ?>
				<li>
					<details data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-preview' ) ); ?>" data-following="<?php echo esc_attr( $follower['following'] ); ?>" data-followers="<?php echo esc_attr( $follower['followers'] ); ?>" data-id="<?php echo esc_attr( $follower['id'] ); ?>">
						<summary>
							<a href="<?php echo esc_url( $follower['url'] ); ?>" class="follower<?php echo esc_attr( $follower['css_class'] ); ?>">
								<img width="40" height="40" src="<?php echo esc_attr( $follower['icon']['url'] ); ?>" loading="lazy" class="avatar activitypub-avatar" />
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
			<?php
			$total_pages = ceil( $filtered_total / $followers_per_page );
			if ( $total_pages > 1 ) {
				$pagination_args = array(
					'base'    => add_query_arg( 'fpage', '%#%' ),
					'format'  => '',
					'current' => $current_page,
					'total'   => $total_pages,
				);
				if ( $link_args ) {
					$pagination_args['add_args'] = $link_args;
				}
				echo '<nav class="pagination">';
				echo wp_kses_post( paginate_links( $pagination_args ) );
				echo '</nav>';
			}
			?>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the friends/stats block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	public function render_stats_block( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$subscriptions       = User_Query::all_subscriptions();
		$subscriptions_count = $subscriptions->get_total();

		$out = '<ul ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-stats' ) ) . '>';

		if ( class_exists( '\ActivityPub\Collection\Followers' ) && \defined( 'ACTIVITYPUB_ACTOR_MODE' ) ) {
			$activitypub_actor_mode = \get_option( 'activitypub_actor_mode', \ACTIVITYPUB_ACTOR_MODE );
			if ( \ACTIVITYPUB_ACTOR_MODE === $activitypub_actor_mode || \ACTIVITYPUB_ACTOR_AND_BLOG_MODE === $activitypub_actor_mode ) {
				$mutual_count = Feed_Parser_ActivityPub::count_mutual_followers( get_current_user_id() );
				$out         .= '<li><a href="' . esc_url( home_url( '/friends/mutual/' ) ) . '">';
				$out         .= esc_html(
					sprintf(
						/* translators: %s: number of mutual friends */
						_n( '%s Friend', '%s Friends', $mutual_count, 'friends' ),
						$mutual_count
					)
				);
				$out .= '</a></li>';

				$follower_count = Feed_Parser_ActivityPub::count_followers( get_current_user_id() );
				$out           .= '<li><a href="' . esc_url( home_url( '/friends/followers/' ) ) . '">';
				$out           .= esc_html(
					sprintf(
						/* translators: %s: number of followers */
						_n( '%s Follower', '%s Followers', $follower_count, 'friends' ),
						$follower_count
					)
				);
				$out .= '</a></li>';
			}
			if ( \ACTIVITYPUB_BLOG_MODE === $activitypub_actor_mode || \ACTIVITYPUB_ACTOR_AND_BLOG_MODE === $activitypub_actor_mode ) {
				if ( class_exists( '\ActivityPub\Collection\Actors' ) ) {
					$blog_follower_count = Feed_Parser_ActivityPub::count_followers( \ActivityPub\Collection\Actors::BLOG_USER_ID );
					$out                .= '<li><a href="' . esc_url( home_url( '/friends/blog-followers/' ) ) . '">';
					$out                .= esc_html(
						sprintf(
							/* translators: %s: number of followers */
							_n( '%s Blog Follower', '%s Blog Followers', $blog_follower_count, 'friends' ),
							$blog_follower_count
						)
					);
					$out .= '</a></li>';
				}
			}
		}

		$out .= '<li><a href="' . esc_url( home_url( '/friends/following/' ) ) . '">';
		$out .= esc_html(
			sprintf(
				/* translators: %s: number of subscriptions */
				_n( '%s Following', '%s Following', $subscriptions_count, 'friends' ),
				$subscriptions_count
			)
		);
		$out .= '</a></li>';
		$out .= '</ul>';

		return $out;
	}

	/**
	 * Render the friends/refresh block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	public function render_refresh_block( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return '<p ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-refresh' ) ) . '><a href="' . esc_url( home_url( '/friends/?refresh' ) ) . '">' . esc_html__( 'Refresh', 'friends' ) . '</a></p>';
	}

	/**
	 * Render the friends/post-formats block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	public function render_post_formats_block( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$out  = '<ul ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-post-formats' ) ) . '>';
		$out .= '<li><a href="' . esc_url( home_url( '/friends/' ) ) . '">' . esc_html_x( 'All', 'all posts', 'friends' ) . '</a></li>';

		$default_formats = array( 'standard', 'status', 'image', 'video' );
		foreach ( get_post_format_strings() as $slug => $title ) {
			if ( in_array( $slug, $default_formats, true ) ) {
				$out .= '<li><a href="' . esc_url( home_url( '/friends/type/' . $slug . '/' ) ) . '">' . esc_html( $title ) . '</a></li>';
			}
		}

		$out .= '</ul>';
		return $out;
	}

	/**
	 * Render the friends/add-subscription block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	public function render_add_subscription_block( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$out  = '<div ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-add-subscription' ) ) . '>';
		$out .= '<a href="' . esc_url( admin_url( 'admin.php?page=add-friend' ) ) . '">';
		$out .= esc_html__( 'Follow', 'friends' );
		$out .= '</a></div>';
		return $out;
	}

	/**
	 * Render the friends/search block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_search_block() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$out  = '<div class="wp-block-friends-search">';
		$out .= '<form action="' . esc_url( home_url( '/friends/' ) ) . '">';
		$out .= '<input type="text" name="s" placeholder="' . esc_attr__( 'Search or paste URL', 'friends' ) . '" value="' . esc_attr( $search ) . '" autocomplete="off" data-nonce="' . esc_attr( wp_create_nonce( 'friends-autocomplete' ) ) . '" />';
		$out .= ' <button type="submit">' . esc_html__( 'Search', 'friends' ) . '</button>';
		$out .= '</form></div>';
		return $out;
	}

	/**
	 * Render the friends/feed-header block.
	 *
	 * @return string The rendered block HTML.
	 */
	/**
	 * Render the friends/feed-title block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_feed_title_block() {
		$friends  = Friends::get_instance();
		$frontend = $friends->frontend;

		// Determine feed title.
		$title = __( 'Main Feed', 'friends' );
		$format_titles = array(
			'standard' => _x( 'Post feed', 'Post format', 'friends' ),
			'aside'    => _x( 'Aside feed', 'Post format', 'friends' ),
			'chat'     => _x( 'Chat feed', 'Post format', 'friends' ),
			'gallery'  => _x( 'Gallery feed', 'Post format', 'friends' ),
			'link'     => _x( 'Link feed', 'Post format', 'friends' ),
			'image'    => _x( 'Image feed', 'Post format', 'friends' ),
			'quote'    => _x( 'Quote feed', 'Post format', 'friends' ),
			'status'   => _x( 'Status feed', 'Post format', 'friends' ),
			'video'    => _x( 'Video feed', 'Post format', 'friends' ),
			'audio'    => _x( 'Audio feed', 'Post format', 'friends' ),
		);
		if ( $frontend->post_format && isset( $format_titles[ $frontend->post_format ] ) ) {
			$title = $format_titles[ $frontend->post_format ];
		}

		if ( isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$title = sprintf(
				// translators: %s is a search term.
				__( 'Search for "%s"', 'friends' ),
				esc_html( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}

		// Build display title with reaction/tag context.
		$display_title = $title;
		if ( $frontend->reaction ) {
			$display_title = sprintf(
				// translators: %1$s is an emoji reaction, %2$s is a type of feed.
				__( 'My %1$s reactions on %2$s', 'friends' ),
				$frontend->reaction,
				$title
			);
		} elseif ( $frontend->tag ) {
			$display_title = sprintf(
				// translators: %1$s is a hash tag, %2$s is a type of feed.
				_x( '#%1$s on %2$s', '#tag on feed', 'friends' ),
				$frontend->tag,
				$title
			);
		}

		return '<h2 ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-feed-title' ) ) . '><a href="' . esc_url( home_url( '/friends/' ) ) . '">' . esc_html( $display_title ) . '</a></h2>';
	}

	/**
	 * Render the friends/feed-chips block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	public function render_feed_chips_block( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$friends = Friends::get_instance();
		$data    = $friends->get_main_header_data();

		$hidden_post_count = 0;
		if ( isset( $data['post_count_by_post_status']->trash ) ) {
			$hidden_post_count = $data['post_count_by_post_status']->trash;
		}

		$out = '<div ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-feed-chips' ) ) . '>';

		if ( isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search_term = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Clear search chip.
			$out .= '<a class="chip" href="' . esc_url( remove_query_arg( 's' ) ) . '">';
			$out .= esc_html( sprintf( /* translators: %s is the search term */ __( 'Clear search for "%s"', 'friends' ), $search_term ) );
			$out .= '</a> ';

			// Order chips.
			if ( 'DESC' === $current_order ) {
				$out .= '<a class="chip" href="' . esc_url( add_query_arg( 'order', 'ASC' ) ) . '">' . esc_html__( 'Oldest first', 'friends' ) . '</a> ';
			} else {
				$out .= '<a class="chip active" href="' . esc_url( add_query_arg( 'order', 'ASC' ) ) . '">' . esc_html__( 'Oldest first', 'friends' ) . '</a> ';
				$out .= '<a class="chip" href="' . esc_url( remove_query_arg( 'order' ) ) . '">' . esc_html__( 'Newest first', 'friends' ) . '</a> ';
			}
		} else {
			// Post count chips.
			$nonce = wp_create_nonce( 'friends_post_counts' );
			foreach ( $data['post_count_by_post_format'] as $post_format => $count ) {
				$out .= '<a class="chip post-count-' . esc_attr( $post_format ) . '" data-nonce="' . esc_attr( $nonce ) . '" href="' . esc_url( home_url( '/friends/type/' . $post_format . '/' ) ) . '">' . esc_html( $friends->get_post_format_plural_string( $post_format, $count ) ) . '</a> ';
			}

			// Hidden items chip.
			if ( isset( $_GET['show-hidden'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$out .= '<a class="chip" href="' . esc_url( remove_query_arg( 'show-hidden' ) ) . '">' . esc_html__( 'Hide hidden items', 'friends' ) . '</a> ';
			} elseif ( $hidden_post_count > 0 ) {
				$out .= '<a class="chip" href="' . esc_url( add_query_arg( 'show-hidden', 1 ) ) . '">';
				$out .= esc_html( sprintf( /* translators: %s is the number of hidden posts */ _n( '%s hidden items', '%s hidden items', $hidden_post_count, 'friends' ), number_format_i18n( $hidden_post_count ) ) );
				$out .= '</a> ';
			}

			// Reaction chips.
			if ( class_exists( __NAMESPACE__ . '\Reactions' ) ) {
				foreach ( Reactions::get_available_emojis() as $slug => $reaction ) {
					$out .= '<a class="chip" href="' . esc_url( home_url( '/friends/reaction' . $slug . '/' ) ) . '">';
					$out .= esc_html( sprintf( /* translators: %s is an emoji */ __( 'Reacted with %s', 'friends' ), $reaction->char ) );
					$out .= '</a> ';
				}
			}
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Render the friends/post-content block.
	 *
	 * Renders friend post content directly without block parsing.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_post_content_block() {
		global $post;
		if ( ! $post ) {
			return '<div class="wp-block-friends-post-content"><p><em>' . esc_html__( 'Post content will appear here.', 'friends' ) . '</em></p></div>';
		}

		$content = get_the_content();
		$content = wp_kses_post( $content );
		$content = wpautop( $content );

		return '<div class="wp-block-friends-post-content">' . $content . '</div>';
	}

	/**
	 * Render the friends/post-permalink block.
	 *
	 * Shows "X ago on domain.com Y min read" for each post.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	public function render_post_permalink_block( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		global $post;
		if ( ! $post ) {
			return '<div ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-post-permalink' ) ) . '><em>' . esc_html__( '3 days ago on example.com', 'friends' ) . ' <span class="reading-time">' . esc_html__( '2 min read', 'friends' ) . '</span></em></div>';
		}

		$friend_user = User::get_post_author( $post );
		if ( ! $friend_user || is_wp_error( $friend_user ) ) {
			return '';
		}

		$author_url = $friend_user->get_local_friends_page_url();
		$guid       = get_the_guid( $post );
		$domain     = wp_parse_url( $guid, PHP_URL_HOST );
		$local_url  = $author_url . $post->ID . '/';

		// Reading time.
		$read_time_seconds = Frontend::calculate_read_time( get_the_content() );
		$read_time         = '';
		if ( $read_time_seconds >= 60 ) {
			$mins = ceil( $read_time_seconds / MINUTE_IN_SECONDS );
			/* translators: Time difference between two dates, in minutes (min=minute). %s: Number of minutes. */
			$read_time = sprintf( _n( '%s min', '%s mins', $mins ), $mins ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		} elseif ( $read_time_seconds > 20 ) {
			$read_time = _x( '< 1 min', 'reading time', 'friends' );
		}

		/* translators: %s is a time span */
		$time_ago = sprintf( __( '%s ago' ), human_time_diff( get_post_time( 'U', true ) ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		$out      = '<div ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-post-permalink' ) ) . '>';
		$out     .= sprintf(
			// translators: %1$s is a date or relative time, %2$s is a site name or domain.
			_x( '%1$s on %2$s', 'at-date-on-post', 'friends' ),
			'<a href="' . esc_url( $local_url ) . '" title="' . esc_attr( get_the_time( 'r' ) ) . '">' . esc_html( $time_ago ) . '</a>',
			'<a href="' . esc_url( $guid ) . '" rel="noopener noreferrer" target="_blank">' . esc_html( $domain ) . '</a>'
		);
		if ( $read_time ) {
			$out .= ' <span class="reading-time" title="' . esc_attr__( 'Estimated reading time', 'friends' ) . '">';
			$out .= esc_html( sprintf( /* translators: %s is a timeframe */ __( '%s read', 'friends' ), $read_time ) );
			$out .= '</span>';
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * Render the friends/post-reblog block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_post_reblog_block() {
		global $post;
		if ( ! $post ) {
			return '<span class="wp-block-friends-post-reblog">' . esc_html_x( 'Reblog', 'button', 'friends' ) . '</span>';
		}

		$friend_user = User::get_post_author( $post );
		if ( $friend_user && ! is_wp_error( $friend_user ) && get_current_user_id() === $friend_user->ID ) {
			return '';
		}

		$post_id       = get_the_ID();
		$reblog_nonce  = wp_create_nonce( 'friends-reblog' );
		$reblog_status = get_post_meta( $post_id, 'reblogged', true ) ? ' dashicons-saved' : '';

		$out  = '<span class="wp-block-friends-post-reblog">';
		$out .= '<a tabindex="0" href="#" data-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $reblog_nonce ) . '" class="friends-reblog has-icon-right" title="' . esc_attr_x( 'Reblog', 'button', 'friends' ) . '">';
		$out .= '<i class="dashicons dashicons-controls-repeat"></i> <span class="text">' . esc_html_x( 'Reblog', 'button', 'friends' ) . '</span>';
		$out .= '<i class="friends-reblog-status dashicons' . esc_attr( $reblog_status ) . '"></i>';
		$out .= '</a></span>';

		return $out;
	}

	/**
	 * Render the friends/post-boost block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_post_boost_block() {
		global $post;
		if ( ! $post ) {
			return '<span class="wp-block-friends-post-boost">' . esc_html_x( 'Boost', 'button', 'friends' ) . '</span>';
		}

		if ( ! has_filter( 'friends_boost' ) && ! class_exists( '\Activitypub\Activitypub' ) ) {
			return '';
		}

		$friend_user = User::get_post_author( $post );
		if ( $friend_user && ! is_wp_error( $friend_user ) && get_current_user_id() === $friend_user->ID ) {
			return '';
		}

		$post_id      = get_the_ID();
		$boost_nonce  = wp_create_nonce( 'friends-boost' );
		$boost_status = get_post_meta( $post_id, 'boosted', true ) ? ' dashicons-saved' : '';

		$out  = '<span class="wp-block-friends-post-boost">';
		$out .= '<a tabindex="0" href="#" data-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $boost_nonce ) . '" class="friends-boost has-icon-right" title="' . esc_attr_x( 'Boost', 'button', 'friends' ) . '">';
		$out .= '<i class="dashicons dashicons-controls-repeat"></i> <span class="text">' . esc_html_x( 'Boost', 'button', 'friends' ) . '</span>';
		$out .= '<i class="friends-boost-status dashicons' . esc_attr( $boost_status ) . '"></i>';
		$out .= '</a></span>';

		return $out;
	}

	/**
	 * Render the friends/post-reactions block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_post_reactions_block() {
		global $post;
		if ( ! $post ) {
			return '<span class="wp-block-friends-post-reactions">⭐ ❤️</span>';
		}

		if ( ! class_exists( __NAMESPACE__ . '\Reactions' ) ) {
			return '';
		}

		$post_id        = get_the_ID();
		$reactions      = Reactions::get_post_reactions();
		$reaction_nonce = wp_create_nonce( 'friends-reaction' );

		$out = '<span class="wp-block-friends-post-reactions">';

		foreach ( $reactions as $slug => $reaction ) {
			$pressed = $reaction->user_reacted ? ' pressed' : '';
			$out    .= '<button class="friends-reaction' . esc_attr( $pressed ) . '" data-id="' . esc_attr( $post_id ) . '" data-emoji="' . esc_attr( $slug ) . '" data-nonce="' . esc_attr( $reaction_nonce ) . '" title="' . esc_attr( $reaction->usernames ) . '">';
			$out    .= '<span>' . esc_html( $reaction->emoji ) . '</span> ' . esc_html( $reaction->count );
			$out    .= '</button> ';
		}

		// Render available emojis as inline buttons for reactions not yet used.
		$available = Reactions::get_available_emojis();
		foreach ( $available as $slug => $emoji ) {
			if ( isset( $reactions[ $slug ] ) ) {
				continue;
			}
			$out .= '<button class="friends-reaction-picker" data-id="' . esc_attr( $post_id ) . '" data-emoji="' . esc_attr( $slug ) . '" data-nonce="' . esc_attr( $reaction_nonce ) . '">' . esc_html( $emoji->char ) . '</button> ';
		}

		$out .= '</span>';
		return $out;
	}

	/**
	 * Render the friends/post-comments block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_post_comments_block() {
		global $post;
		if ( ! $post ) {
			return '<span class="wp-block-friends-post-comments">💬 ' . esc_html__( 'Comments' ) . '</span>'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		}

		$post_id      = get_the_ID();
		$has_mention  = get_post_meta( $post_id, '_has_mention_in_comments', true );
		$comment_icon = $has_mention ? 'format-status' : 'admin-comments';
		$comment_text = $has_mention ? __( 'Comments (You were mentioned)', 'friends' ) : __( 'Comments' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain

		$out  = '<div class="wp-block-friends-post-comments">';
		$out .= '<a href="#" class="comments" data-id="' . esc_attr( $post_id ) . '" data-cnonce="' . esc_attr( wp_create_nonce( 'comments-' . $post_id ) ) . '">';
		$out .= '<i class="dashicons dashicons-' . esc_attr( $comment_icon ) . '"></i> <span class="text">' . esc_html( $comment_text ) . '</span>';
		$out .= '</a>';
		$out .= '<div class="comments-content closed"></div>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Get the current author from the frontend context.
	 *
	 * @return User|null
	 */
	private function get_frontend_author() {
		$friends = Friends::get_instance();
		return $friends->frontend->author;
	}

	/**
	 * Render the friends/author-star block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_author_star_block() {
		$author = $this->get_frontend_author();
		if ( ! $author ) {
			return '<span class="wp-block-friends-author-star">&#9734;</span>';
		}

		$starred    = $author->is_starred();
		$star_class = $starred ? 'dashicons-star-filled starred' : 'dashicons-star-empty not-starred';
		$star_nonce = wp_create_nonce( 'star-' . $author->user_login );

		return '<a href="" class="wp-block-friends-author-star dashicons ' . esc_attr( $star_class ) . '" data-id="' . esc_attr( $author->user_login ) . '" data-nonce="' . esc_attr( $star_nonce ) . '"></a>';
	}

	/**
	 * Render the friends/author-avatar block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_author_avatar_block() {
		$author = $this->get_frontend_author();
		if ( ! $author ) {
			return '<span class="wp-block-friends-author-avatar"></span>';
		}

		$avatar_url = $author->get_avatar_url();
		if ( ! $avatar_url ) {
			return '<span class="wp-block-friends-author-avatar"></span>';
		}

		return '<img class="wp-block-friends-author-avatar" src="' . esc_url( $avatar_url ) . '" width="36" height="36" />';
	}

	/**
	 * Render the friends/author-name block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	public function render_author_name_block( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$author = $this->get_frontend_author();
		if ( ! $author ) {
			return '<h2 ' . $this->get_wrapper_attributes(
				array(
					'class' => 'wp-block-friends-author-name',
					'id'    => 'page-title',
				)
			) . '>' . esc_html__( 'Author Name', 'friends' ) . '</h2>';
		}

		return '<h2 ' . $this->get_wrapper_attributes(
			array(
				'class' => 'wp-block-friends-author-name',
				'id'    => 'page-title',
			)
		) . '>' . esc_html( $author->display_name ) . '</h2>';
	}

	/**
	 * Render the friends/author-description block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	public function render_author_description_block( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$author = $this->get_frontend_author();
		if ( ! $author ) {
			return '<p ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-author-description' ) ) . '><em>' . esc_html__( 'Author description will appear here.', 'friends' ) . '</em></p>';
		}
		if ( ! $author->description ) {
			return '';
		}

		return '<p ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-author-description' ) ) . '>' . wp_kses( $author->description, array( 'a' => array( 'href' => array() ) ) ) . '</p>';
	}

	/**
	 * Render the friends/author-chips block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	public function render_author_chips_block( $attributes = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$friends = Friends::get_instance();
		$author  = $this->get_frontend_author();
		if ( ! $author ) {
			return '<div ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-author-chips' ) ) . '><span class="chip">' . esc_html__( 'Following', 'friends' ) . '</span> <span class="chip">example.com</span> <span class="chip">' . esc_html__( 'Edit', 'friends' ) . '</span></div>';
		}

		$out = '<div ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-author-chips' ) ) . '>';

		// Role chip.
		$out .= '<span class="chip">' . esc_html( $author->get_role_name() ) . '</span> ';

		// Folder chip.
		if ( $author instanceof Subscription ) {
			$folder = $author->get_folder();
			if ( $folder ) {
				$out .= '<span class="chip">&#128193; ' . esc_html( $folder->name ) . '</span> ';
			}
		}

		// Since chip.
		$out .= '<span class="chip">' . esc_html( sprintf( /* translators: %s is a date */ __( 'Since %s', 'friends' ), date_i18n( __( 'F j, Y' ), strtotime( $author->user_registered ) ) ) ) . '</span> '; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain

		// User URL chip.
		if ( $author->user_url ) {
			$domain = wp_parse_url( $author->user_url, PHP_URL_HOST );
			if ( $domain ) {
				$domain = preg_replace( '/^www\./', '', $domain );
				$out   .= '<a class="chip" href="' . esc_url( $author->user_url ) . '">' . esc_html( $domain ) . '</a> ';
			}
		}

		// Post count chips.
		$post_counts = $author->get_post_count_by_post_format();
		foreach ( $post_counts as $post_format => $count ) {
			$out .= '<a class="chip" href="' . esc_url( $author->get_local_friends_page_url() . 'type/' . $post_format . '/' ) . '">' . esc_html( $friends->get_post_format_plural_string( $post_format, $count ) ) . '</a> ';
		}

		// Hidden items chip.
		$hidden_post_count = $author->get_post_in_trash_count();
		if ( $hidden_post_count > 0 ) {
			$out .= '<span class="chip">';
			$out .= esc_html( sprintf( /* translators: %s is the number of hidden posts */ _n( '%s hidden items', '%s hidden items', $hidden_post_count, 'friends' ), number_format_i18n( $hidden_post_count ) ) );
			$out .= '</span> ';
		}

		// Reaction chips.
		if ( class_exists( __NAMESPACE__ . '\Reactions' ) ) {
			foreach ( Reactions::get_available_emojis() as $slug => $reaction ) {
				$out .= '<a class="chip" href="' . esc_url( $author->get_local_friends_page_url() . 'reaction' . $slug . '/' ) . '">';
				$out .= esc_html( sprintf( /* translators: %s is an emoji */ __( 'Reacted with %s', 'friends' ), $reaction->char ) );
				$out .= '</a> ';
			}
		}

		// Feed count chip.
		$active_feeds = count( $author->get_active_feeds() );
		$total_feeds  = count( $author->get_feeds() );
		if ( $active_feeds > 0 ) {
			$out .= '<a class="chip" href="' . esc_url( admin_url( 'admin.php?page=edit-friend-feeds&user=' . $author->user_login ) ) . '">';
			$out .= esc_html( sprintf( /* translators: %s is the number of feeds */ _n( '%s feed', '%s feeds', $active_feeds, 'friends' ), $active_feeds ) );
			if ( $total_feeds > $active_feeds ) {
				$extra = $total_feeds - $active_feeds;
				$out  .= '&nbsp;<small>(+' . esc_html( $extra ) . ' ' . esc_html__( 'more', 'friends' ) . ')</small>';
			}
			$out .= '</a> ';
		}

		// Folder selector.
		if ( $author instanceof Subscription ) {
			$folders        = Subscription::get_folders();
			$current_folder = $author->get_folder();
			$current_id     = $current_folder ? $current_folder->term_id : 0;
			$nonce          = wp_create_nonce( 'friends-move-to-folder' );

			$out .= '<span class="chip friends-folder-selector">';
			$out .= '&#128193; <select class="friends-move-to-folder" data-id="' . esc_attr( $author->user_login ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
			$out .= '<option value="0"' . selected( $current_id, 0, false ) . '>' . esc_html__( 'No folder', 'friends' ) . '</option>';
			foreach ( $folders as $folder ) {
				$out .= '<option value="' . esc_attr( $folder->term_id ) . '"' . selected( $current_id, $folder->term_id, false ) . '>' . esc_html( $folder->name ) . '</option>';
			}
			$out .= '<option value="new">' . esc_html__( '+ New folder', 'friends' ) . '</option>';
			$out .= '</select>';
			$out .= '</span> ';
		}

		// Edit chip.
		$out .= '<a class="chip" href="' . esc_url( admin_url( 'admin.php?page=edit-friend&user=' . $author->user_login ) ) . '">' . esc_html__( 'Edit', 'friends' ) . '</a> ';

		// Refresh chip.
		$out .= '<a class="chip" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=friends-refresh&user=' . $author->user_login ), 'friends-refresh' ) ) . '">' . esc_html__( 'Refresh', 'friends' ) . '</a> ';

		$out .= '</div>';
		return $out;
	}

	/**
	 * Render the Friends List block
	 *
	 * @param  array $attributes Attributes set by Blocks.
	 * @return string The new block content.
	 */
	public function render_friends_list_block( $attributes = array() ) {
		if ( ! isset( $attributes['user_types'] ) ) {
			$attributes['user_types'] = 'subscriptions';
		}

		if ( 'folders' === $attributes['user_types'] ) {
			return $this->render_friends_list_by_folder( $attributes );
		}

		if ( ! empty( $attributes['folder'] ) ) {
			$friends     = User_Query::subscriptions_in_folder( intval( $attributes['folder'] ) );
			$folder_term = get_term( intval( $attributes['folder'] ), Subscription::TAXONOMY );
			$no_users    = '';
		} else {
			$folder_term = null;
			switch ( $attributes['user_types'] ) {
				case 'starred':
					$friends  = User_Query::starred_friends_subscriptions();
					$no_users = '';
					break;
				default:
				case 'subscriptions':
					$friends  = User_Query::all_subscriptions();
					$no_users = __( "You're not following anyone yet.", 'friends' );
					break;
			}
		}

		if ( $friends->get_total() === 0 ) {
			if ( ! $no_users ) {
				return '';
			}
			return '<span ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-friends-list no-users' ) ) . '>' . $no_users . '</span>';
		}

		$heading = '';
		if ( $folder_term && ! is_wp_error( $folder_term ) ) {
			$heading = '<h3>&#128193; ' . esc_html( $folder_term->name ) . '</h3>';
		}

		if ( ! empty( $attributes['users_inline'] ) ) {
			$out   = $heading;
			$first = true;
		} else {
			$out = $heading . '<ul ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-friends-list' ) ) . '>';
		}
		$count = 0;
		foreach ( $friends->get_results() as $friend_user ) {
			++$count;
			if ( ! empty( $attributes['users_inline'] ) ) {
				if ( ! $first ) {
					$out .= ', ';
				}
				$first = false;
			} else {
				$out .= '<li>';
			}

			if ( Friends::has_required_privileges() ) {
				$url = $friend_user->get_local_friends_page_url();
			} else {
				$url = $friend_user->user_url;
			}

			$out .= sprintf(
				'<a class="wp-user" href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html( $friend_user->display_name )
			);
			if ( empty( $attributes['users_inline'] ) ) {
				$out .= '</li>';
			}
		}
		if ( empty( $attributes['users_inline'] ) ) {
			$out .= '</ul>';
		}

		return $out;
	}

	/**
	 * Render the friends list grouped by folder hierarchy.
	 *
	 * @param array $attributes Block attributes.
	 * @return string The rendered block HTML.
	 */
	private function render_friends_list_by_folder( $attributes ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$all     = User_Query::all_subscriptions();
		$folders = Subscription::get_folders();

		if ( $all->get_total() === 0 ) {
			return '<span ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-friends-list no-users' ) ) . '>' . esc_html__( "You're not following anyone yet.", 'friends' ) . '</span>';
		}

		$out = '<div ' . $this->get_wrapper_attributes( array( 'class' => 'wp-block-friends-friends-list folders' ) ) . '>';

		// Render each folder as a collapsible details element.
		foreach ( $folders as $folder ) {
			$folder_subs = User_Query::subscriptions_in_folder( $folder->term_id );
			if ( $folder_subs->get_total() === 0 ) {
				continue;
			}

			$out .= '<details class="friends-folder" open>';
			$out .= '<summary>&#128193; ' . esc_html( $folder->name ) . ' <small>(' . $folder_subs->get_total() . ')</small></summary>';
			$out .= '<ul>';
			foreach ( $folder_subs->get_results() as $friend_user ) {
				$url  = Friends::has_required_privileges() ? $friend_user->get_local_friends_page_url() : $friend_user->user_url;
				$out .= '<li><a class="wp-user" href="' . esc_url( $url ) . '">' . esc_html( $friend_user->display_name ? $friend_user->display_name : $friend_user->user_login ) . '</a></li>';
			}
			$out .= '</ul>';
			$out .= '</details>';
		}

		// Render unfoldered subscriptions.
		$unfoldered = User_Query::unfoldered_subscriptions();
		if ( $unfoldered->get_total() > 0 ) {
			if ( ! empty( $folders ) ) {
				$out .= '<details class="friends-folder" open>';
				$out .= '<summary>' . esc_html__( 'Uncategorized', 'friends' ) . ' <small>(' . $unfoldered->get_total() . ')</small></summary>';
			}
			$out .= '<ul>';
			foreach ( $unfoldered->get_results() as $friend_user ) {
				$url  = Friends::has_required_privileges() ? $friend_user->get_local_friends_page_url() : $friend_user->user_url;
				$out .= '<li><a class="wp-user" href="' . esc_url( $url ) . '">' . esc_html( $friend_user->display_name ? $friend_user->display_name : $friend_user->user_login ) . '</a></li>';
			}
			$out .= '</ul>';
			if ( ! empty( $folders ) ) {
				$out .= '</details>';
			}
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Render the Friend Posts block
	 *
	 * @param  array $attributes Attributes set by Blocks.
	 * @return string The new block content.
	 */
	public function render_friend_posts_block( $attributes = array() ) {
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
	 * Enqueue the sidebar blocks editor script.
	 */
	public function enqueue_sidebar_blocks() {
		wp_enqueue_script(
			'friends-sidebar-blocks',
			plugins_url( 'blocks/sidebar-blocks/index.js', FRIENDS_PLUGIN_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render' ),
			Friends::VERSION,
			true
		);

		// Pass folder data to the editor for the friends-list block.
		$folders = Subscription::get_folders();
		$folder_data = array();
		foreach ( $folders as $folder ) {
			$folder_data[] = array(
				'term_id' => $folder->term_id,
				'name'    => $folder->name,
			);
		}
		wp_localize_script( 'friends-sidebar-blocks', 'friendsFolders', $folder_data );
	}

	/**
	 * Hide blocks that were marked as "only visible for friends".
	 *
	 * Since the friendship feature was removed, "only-friends" content
	 * stays hidden to prevent accidental exposure. "not-friends" content
	 * is shown to everyone.
	 *
	 * @param  string $content The content provided by the user.
	 * @param  array  $block   Attributes for the block.
	 * @return string          The rendered content.
	 */
	public function render_friends_block_visibility( $content, $block ) {
		$css_class = ( empty( $block['attrs'] ) || empty( $block['attrs']['className'] ) ) ? '' : $block['attrs']['className'];

		if ( preg_match( '/\bonly-friends\b/', $css_class ) ) {
			// Content meant only for friends stays hidden since friendships were removed.
			if ( ! Friends::has_required_privileges() ) {
				return '';
			}
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
