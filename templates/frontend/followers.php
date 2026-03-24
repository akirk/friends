<?php
/**
 * This is the Followers list
 *
 * @version 1.0
 * @package Friends
 */

$args = array_merge( $friends_args, $args );
$blog_followers = class_exists( '\ActivityPub\Collection\Actors' ) && \ActivityPub\Collection\Actors::BLOG_USER_ID === $args['user_id'];
$args['title'] = __( 'Your Followers', 'friends' );
if ( $blog_followers ) {
	$args['title'] = __( 'Your Blog Followers', 'friends' );
}

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

$args['no-bottom-margin'] = true;

Friends\Friends::template_loader()->get_template_part( 'frontend/header', null, $args );

$followers_list_page = 'users.php?page=activitypub-followers-list';
if ( $blog_followers ) {
	$followers_list_page = 'options-general.php?page=activitypub&tab=followers';
}
?>
<section class="followers">
	<?php
	if ( class_exists( '\ActivityPub\Collection\Followers' ) ) {
		$follower_data = \ActivityPub\Collection\Followers::query( $args['user_id'] );
		$total         = $follower_data['total'];

		// Classify followers only when a filter is active.
		if ( 'all' !== $filter ) {
			$following_ids     = array();
			$not_following_ids = array();
			foreach ( $follower_data['followers'] as $follower ) {
				$url          = $follower->guid;
				$is_following = false;
				if ( $url ) {
					$is_following = Friends\User_Feed::get_by_url( $url );
					if ( ! $is_following || is_wp_error( $is_following ) ) {
						$is_following = Friends\User_Feed::get_by_url( str_replace( '@', 'users/', $url ) );
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

		// Convert only the current page's followers to actor data.
		$display_followers = array();
		foreach ( $page_followers as $follower ) {
			if ( $follower instanceof \WP_Post ) {
				$follower = \Activitypub\Collection\Remote_Actors::get_actor( $follower );
				if ( is_wp_error( $follower ) ) {
					continue;
				}
			}
			$data = $follower->to_array();

			$data['url']       = \ActivityPub\object_to_uri( $data['url'] );
			$data['server']    = wp_parse_url( $data['url'], PHP_URL_HOST );
			$data['css_class'] = '';

			$following = Friends\User_Feed::get_by_url( $data['url'] );
			if ( ! $following || is_wp_error( $following ) ) {
				$following = Friends\User_Feed::get_by_url( str_replace( '@', 'users/', $data['url'] ) );
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
		?>
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

		echo ' <a href="' . esc_attr( admin_url( $followers_list_page ) ) . '">';
		if ( $blog_followers ) {
			esc_html_e( 'View all blog followers in wp-admin', 'friends' );
		} else {
			esc_html_e( 'View all followers in wp-admin', 'friends' );
		}
		echo '</a>';
		?>
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
				$url = $base_url;
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
				$url = $base_url;
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
		<?php
		foreach ( $display_followers as $follower ) {
			?>
			<li>
				<details data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-preview' ) ); ?>" data-following="<?php echo esc_attr( $follower['following'] ); ?>" data-followers="<?php echo esc_attr( $follower['followers'] ); ?>" data-id="<?php echo esc_attr( $follower['id'] ); ?>"><summary><a href="<?php echo esc_url( $follower['url'] ); ?>" class="follower<?php echo esc_attr( $follower['css_class'] ); ?>">
					<img width="40" height="40" src="<?php echo esc_attr( $follower['icon']['url'] ); ?>" loading="lazy" class="avatar activitypub-avatar" />
					<span class="activitypub-actor"><strong class="activitypub-name"><?php echo esc_html( $follower['name'] ); ?></strong> (<span class="activitypub-handle">@<?php echo esc_html( $follower['preferredUsername'] . '@' . $follower['server'] ); ?></span>)</span></a>
				<span class="since">since <?php echo esc_html( $follower['published'] ); ?></span>
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
							'a' => array(
								'href' => array(),
							),
						)
					);
					?>
				</p>
			</summary><p class="loading-posts">
				<span><?php esc_html_e( 'Loading posts', 'friends' ); ?></span>
				<i class="form-icon loading"></i>
			</p></details>
			</li>
			<?php
		}
		?>
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
	} else {
		?>
		<p><?php esc_html_e( 'The follower list is currently dependent on the ActivityPub plugin.', 'friends' ); ?></p>
		<?php

	}
	?>
</section>
<?php
Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	null,
	$args
);
