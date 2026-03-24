<?php
/**
 * This is the Subscriptions list
 *
 * @version 1.0
 * @package Friends
 */

if ( ! empty( $friends_args ) && is_array( $friends_args ) ) {
	$args = array_merge( $friends_args, $args );
}
$args['title'] = __( 'Your Subscriptions', 'friends' );
$args['no-bottom-margin'] = true;

$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$valid_filters = array( 'all', 'starred' );

// Add folder filters dynamically.
$folders     = Friends\Subscription::get_folders();
$folder_map  = array();
foreach ( $folders as $folder ) {
	$folder_key              = 'folder-' . $folder->term_id;
	$valid_filters[]         = $folder_key;
	$folder_map[ $folder_key ] = $folder;
}
if ( ! in_array( $filter, $valid_filters, true ) ) {
	$filter = 'all';
}

$sort = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : 'name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $sort, array( 'name', 'newest', 'oldest', 'posts' ), true ) ) {
	$sort = 'name';
}

Friends\Friends::template_loader()->get_template_part( 'frontend/header', null, $args );

?>
<section class="subscriptions">
	<?php
	$all_subscriptions = Friends\User_Query::all_subscriptions()->get_results();
	$total             = count( $all_subscriptions );

	// Filter.
	if ( 'starred' === $filter ) {
		$filtered = array_filter(
			$all_subscriptions,
			function ( $s ) {
				return $s instanceof Friends\Subscription && $s->is_starred();
			}
		);
	} elseif ( isset( $folder_map[ $filter ] ) ) {
		$folder_term_id = $folder_map[ $filter ]->term_id;
		$filtered       = array_filter(
			$all_subscriptions,
			function ( $s ) use ( $folder_term_id ) {
				if ( ! ( $s instanceof Friends\Subscription ) ) {
					return false;
				}
				$folder = $s->get_folder();
				return $folder && $folder->term_id === $folder_term_id;
			}
		);
	} else {
		$filtered = $all_subscriptions;
	}

	// Sort.
	$filtered = array_values( $filtered );
	if ( 'newest' === $sort ) {
		usort(
			$filtered,
			function ( $a, $b ) {
				return strtotime( $b->user_registered ) - strtotime( $a->user_registered );
			}
		);
	} elseif ( 'oldest' === $sort ) {
		usort(
			$filtered,
			function ( $a, $b ) {
				return strtotime( $a->user_registered ) - strtotime( $b->user_registered );
			}
		);
	} elseif ( 'posts' === $sort ) {
		usort(
			$filtered,
			function ( $a, $b ) {
				$a_stats = $a->get_post_stats();
				$b_stats = $b->get_post_stats();
				return intval( $b_stats['post_count'] ) - intval( $a_stats['post_count'] );
			}
		);
	} else {
		usort(
			$filtered,
			function ( $a, $b ) {
				return strnatcasecmp( $a->display_name, $b->display_name );
			}
		);
	}

	// Paginate.
	$filtered_total          = count( $filtered );
	$subscriptions_per_page  = 20;
	$current_page            = isset( $_GET['spage'] ) ? max( 1, absint( $_GET['spage'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page_subscriptions      = array_slice( $filtered, ( $current_page - 1 ) * $subscriptions_per_page, $subscriptions_per_page );

	// Categorize subscriptions: people (ActivityPub) vs feeds (RSS/Atom).
	$people_count = 0;
	$feed_count   = 0;
	foreach ( $all_subscriptions as $sub ) {
		$is_person = false;
		if ( $sub instanceof Friends\Subscription ) {
			foreach ( $sub->get_active_feeds() as $active_feed ) {
				if ( 'activitypub' === $active_feed->get_parser() ) {
					$is_person = true;
					break;
				}
			}
		}
		if ( $is_person ) {
			++$people_count;
		} else {
			++$feed_count;
		}
	}

	$base_url  = strtok( $_SERVER['REQUEST_URI'], '?' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$link_args = array();
	if ( 'all' !== $filter ) {
		$link_args['filter'] = $filter;
	}
	if ( 'name' !== $sort ) {
		$link_args['sort'] = $sort;
	}
	?>
	<p>
	<?php
	echo esc_html(
		sprintf(
			// translators: %s is the number of people.
			_n( 'You are subscribed to %s person.', 'You are subscribed to %s people.', $people_count, 'friends' ),
			number_format_i18n( $people_count )
		)
	);
	echo ' ';
	echo esc_html(
		sprintf(
			// translators: %s is the number of feeds.
			_n( 'You are subscribed to %s feed.', 'You are subscribed to %s feeds.', $feed_count, 'friends' ),
			number_format_i18n( $feed_count )
		)
	);
	?>
	</p>
	<p>
		<?php esc_html_e( 'Filter:', 'friends' ); ?>
		<?php
		$filters = array(
			'all'     => __( 'All', 'friends' ),
			'starred' => __( 'Starred', 'friends' ),
		);
		foreach ( $folders as $folder ) {
			$filters[ 'folder-' . $folder->term_id ] = $folder->name;
		}

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
			'name'   => __( 'Name', 'friends' ),
			'newest' => __( 'Newest', 'friends' ),
			'oldest' => __( 'Oldest', 'friends' ),
			'posts'  => __( 'Most Posts', 'friends' ),
		);
		$sort_links = array();
		foreach ( $sorts as $sort_key => $sort_label ) {
			$url    = $base_url;
			$s_args = $link_args;
			if ( 'name' !== $sort_key ) {
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
	<?php if ( empty( $page_subscriptions ) ) : ?>
		<p>
		<?php
		if ( 'all' === $filter ) {
			esc_html_e( "You don't have any subscriptions yet.", 'friends' );
		} else {
			esc_html_e( 'No subscriptions match this filter.', 'friends' );
		}
		?>
		</p>
	<?php else : ?>
		<ul>
		<?php
		foreach ( $page_subscriptions as $subscription ) {
			$avatar     = $subscription->get_avatar_url();
			$page_url   = $subscription->get_local_friends_page_url();
			$stats      = $subscription->get_post_stats();
			$post_count = intval( $stats['post_count'] );
			$starred    = $subscription instanceof Friends\Subscription && $subscription->is_starred();
			$folder     = $subscription instanceof Friends\Subscription ? $subscription->get_folder() : null;
			$active_feeds = $subscription instanceof Friends\Subscription ? $subscription->get_active_feeds() : array();
			?>
			<li class="subscription-item">
				<a href="<?php echo esc_url( $page_url ); ?>" class="subscription-link">
					<?php if ( $avatar ) : ?>
						<img width="40" height="40" src="<?php echo esc_url( $avatar ); ?>" loading="lazy" class="avatar" />
					<?php else : ?>
						<img width="40" height="40" src="<?php echo esc_url( get_avatar_url( $subscription->user_login, array( 'size' => 40 ) ) ); ?>" loading="lazy" class="avatar" />
					<?php endif; ?>
					<span class="subscription-info">
						<strong class="subscription-name"><?php echo esc_html( $subscription->display_name ); ?></strong>
						<?php if ( $starred ) : ?>
							<span class="starred" title="<?php esc_attr_e( 'Starred', 'friends' ); ?>">&#9733;</span>
						<?php endif; ?>
						<?php if ( $folder ) : ?>
							<span class="subscription-folder"><?php echo esc_html( $folder->name ); ?></span>
						<?php endif; ?>
					</span>
				</a>
				<span class="subscription-meta">
					<?php
					echo esc_html(
						sprintf(
							// translators: %s is the number of posts.
							_n( '%s post', '%s posts', $post_count, 'friends' ),
							number_format_i18n( $post_count )
						)
					);
					?>
					<?php if ( $subscription->user_registered ) : ?>
						&middot;
						<?php
						echo esc_html(
							sprintf(
								// translators: %s is a date.
								__( 'since %s', 'friends' ),
								date_i18n( get_option( 'date_format' ), strtotime( $subscription->user_registered ) )
							)
						);
						?>
					<?php endif; ?>
					<?php if ( ! empty( $active_feeds ) ) : ?>
						&middot;
						<?php
						$parser_names = array();
						foreach ( $active_feeds as $feed ) {
							$parser = $feed->get_parser();
							if ( $parser && ! in_array( $parser, $parser_names, true ) ) {
								$parser_names[] = $parser;
							}
						}
						echo esc_html( implode( ', ', $parser_names ) );
						?>
					<?php endif; ?>
				</span>
				<?php if ( $subscription->description ) : ?>
					<p class="subscription-description"><?php echo esc_html( wp_trim_words( $subscription->description, 20 ) ); ?></p>
				<?php endif; ?>
			</li>
			<?php
		}
		?>
		</ul>
		<?php
		$total_pages = ceil( $filtered_total / $subscriptions_per_page );
		if ( $total_pages > 1 ) {
			$pagination_args = array(
				'base'    => add_query_arg( 'spage', '%#%' ),
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
	<?php endif; ?>
</section>
<?php
Friends\Friends::template_loader()->get_template_part(
	'frontend/footer',
	null,
	$args
);
