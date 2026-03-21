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
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_sidebar_blocks' ) );
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

		register_block_type(
			'friends/stats',
			array(
				'render_callback' => array( $this, 'render_stats_block' ),
			)
		);

		register_block_type(
			'friends/refresh',
			array(
				'render_callback' => array( $this, 'render_refresh_block' ),
			)
		);

		register_block_type(
			'friends/post-formats',
			array(
				'render_callback' => array( $this, 'render_post_formats_block' ),
			)
		);

		register_block_type(
			'friends/add-subscription',
			array(
				'render_callback' => array( $this, 'render_add_subscription_block' ),
			)
		);

		register_block_type(
			'friends/starred-friends-list',
			array(
				'render_callback' => array( $this, 'render_starred_friends_list_block' ),
			)
		);

		register_block_type(
			'friends/search',
			array(
				'render_callback' => array( $this, 'render_search_block' ),
			)
		);

		register_block_type(
			'friends/feed-header',
			array(
				'render_callback' => array( $this, 'render_feed_header_block' ),
			)
		);

		register_block_type(
			'friends/post-entry',
			array(
				'render_callback' => array( $this, 'render_post_entry_block' ),
			)
		);

		register_block_type(
			'friends/author-header',
			array(
				'render_callback' => array( $this, 'render_author_header_block' ),
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
	 * Render the friends/stats block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_stats_block() {
		$subscriptions       = User_Query::all_subscriptions();
		$subscriptions_count = $subscriptions->get_total();

		$out = '<ul class="wp-block-friends-stats">';

		if ( class_exists( '\ActivityPub\Collection\Followers' ) && \defined( 'ACTIVITYPUB_ACTOR_MODE' ) ) {
			$activitypub_actor_mode = \get_option( 'activitypub_actor_mode', \ACTIVITYPUB_ACTOR_MODE );
			if ( \ACTIVITYPUB_ACTOR_MODE === $activitypub_actor_mode || \ACTIVITYPUB_ACTOR_AND_BLOG_MODE === $activitypub_actor_mode ) {
				$follower_count = \ActivityPub\Collection\Followers::count_followers( get_current_user_id() );
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
					$blog_follower_count = \ActivityPub\Collection\Followers::count_followers( \ActivityPub\Collection\Actors::BLOG_USER_ID );
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

		$out .= '<li><a href="' . esc_url( home_url( '/friends/subscriptions/' ) ) . '">';
		$out .= esc_html(
			sprintf(
				/* translators: %s: number of subscriptions */
				_n( '%s Subscription', '%s Subscriptions', $subscriptions_count, 'friends' ),
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
	 * @return string The rendered block HTML.
	 */
	public function render_refresh_block() {
		return '<p class="wp-block-friends-refresh"><a href="' . esc_url( home_url( '/friends/?refresh' ) ) . '">' . esc_html__( 'Refresh', 'friends' ) . '</a></p>';
	}

	/**
	 * Render the friends/post-formats block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_post_formats_block() {
		$out  = '<ul class="wp-block-friends-post-formats">';
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
	 * @return string The rendered block HTML.
	 */
	public function render_add_subscription_block() {
		$out  = '<div class="wp-block-friends-add-subscription">';
		$out .= '<a href="' . esc_url( admin_url( 'admin.php?page=add-friend' ) ) . '">';
		$out .= esc_html__( 'Add Subscription', 'friends' );
		$out .= '</a></div>';
		return $out;
	}

	/**
	 * Render the friends/starred-friends-list block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_starred_friends_list_block() {
		$starred = User_Query::starred_friends_subscriptions();
		if ( ! $starred->get_total() ) {
			return '';
		}

		$out  = '<h3 class="wp-block-heading">&#11088; ' . esc_html( _x( 'Starred', 'Starred Friends', 'friends' ) ) . '</h3>';
		$out .= '<ul class="wp-block-friends-starred-friends-list">';
		foreach ( $starred->get_results() as $friend_user ) {
			if ( Friends::has_required_privileges() ) {
				$url = $friend_user->get_local_friends_page_url();
			} else {
				$url = $friend_user->user_url;
			}

			$out .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $friend_user->display_name ) . '</a></li>';
		}
		$out .= '</ul>';

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
	public function render_feed_header_block() {
		$friends  = Friends::get_instance();
		$frontend = $friends->frontend;
		$data     = $friends->get_main_header_data();

		$hidden_post_count = 0;
		if ( isset( $data['post_count_by_post_status']->trash ) ) {
			$hidden_post_count = $data['post_count_by_post_status']->trash;
		}

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

		$out = '<div class="wp-block-friends-feed-header">';
		$out .= '<h2><a href="' . esc_url( home_url( '/friends/' ) ) . '">' . esc_html( $display_title ) . '</a></h2>';

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

		$out .= '</div>';
		return $out;
	}

	/**
	 * Render the friends/post-entry block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_post_entry_block() {
		global $post;
		if ( ! $post ) {
			return '';
		}

		$friend_user = User::get_post_author( $post );
		if ( ! $friend_user || is_wp_error( $friend_user ) ) {
			return '';
		}

		$author_name = $friend_user->display_name;
		$override    = apply_filters( 'friends_override_author_name', '', $author_name, $post->ID );

		$display_name = $author_name;
		if ( $override && trim( str_replace( $override, '', $author_name ) ) === $author_name ) {
			$display_name .= ' &ndash; ' . esc_html( $override );
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

		$out = '<article class="wp-block-friends-post-entry">';

		// Author line.
		$out .= '<div class="post-entry-author">';
		$out .= '<a href="' . esc_url( $author_url ) . '"><strong>' . esc_html( $display_name ) . '</strong></a>';
		$out .= '</div>';

		// Title.
		$out .= '<h3 class="post-entry-title"><a href="' . esc_url( $guid ) . '" rel="noopener noreferrer" target="_blank">' . esc_html( get_the_title() ) . '</a></h3>';

		// Content.
		$out .= '<div class="post-entry-content">' . apply_filters( 'the_content', get_the_content() ) . '</div>';

		// Permalink section.
		/* translators: %s is a time span */
		$time_ago = sprintf( __( '%s ago' ), human_time_diff( get_post_time( 'U', true ) ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		$out     .= '<div class="post-entry-permalink">';
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

		$out .= '</article>';
		return $out;
	}

	/**
	 * Render the friends/author-header block.
	 *
	 * @return string The rendered block HTML.
	 */
	public function render_author_header_block() {
		$friends  = Friends::get_instance();
		$frontend = $friends->frontend;
		$author   = $frontend->author;

		if ( ! $author ) {
			return '';
		}

		$out = '<div class="wp-block-friends-author-header">';

		// Avatar + name + star.
		$avatar_url = $author->get_avatar_url();
		$out       .= '<h2 id="page-title">';

		$starred     = $author->is_starred();
		$star_class  = $starred ? 'dashicons-star-filled starred' : 'dashicons-star-empty not-starred';
		$star_nonce  = wp_create_nonce( 'star-' . $author->user_login );
		$out        .= '<a href="" class="dashicons ' . esc_attr( $star_class ) . '" data-id="' . esc_attr( $author->user_login ) . '" data-nonce="' . esc_attr( $star_nonce ) . '"></a> ';

		if ( $avatar_url ) {
			$out .= '<img src="' . esc_url( $avatar_url ) . '" width="36" height="36" class="avatar" /> ';
		}
		$out .= esc_html( $author->display_name );
		$out .= '</h2>';

		// Description.
		if ( $author->description ) {
			$out .= '<p>' . wp_kses( $author->description, array( 'a' => array( 'href' => array() ) ) ) . '</p>';
		}

		// Chips.
		$out .= '<div class="author-header-chips">';

		// Role chip.
		$out .= '<span class="chip">' . esc_html( $author->get_role_name() ) . '</span> ';

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

		// Edit chip.
		$out .= '<a class="chip" href="' . esc_url( admin_url( 'admin.php?page=edit-friend&user=' . $author->user_login ) ) . '">' . esc_html__( 'Edit', 'friends' ) . '</a> ';

		// Refresh chip.
		$out .= '<a class="chip" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=friends-refresh&user=' . $author->user_login ), 'friends-refresh' ) ) . '">' . esc_html__( 'Refresh', 'friends' ) . '</a> ';

		$out .= '</div>';
		$out .= '</div>';
		return $out;
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
