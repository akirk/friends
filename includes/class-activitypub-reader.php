<?php
/**
 * Experimental shared ActivityPub reader cache.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Presents ActivityPub's cache alongside the historical Friends cache.
 */
class Activitypub_Reader {
	/** Register the opt-in bridge. */
	public static function init() {
		add_filter( 'friends_frontend_post_types', array( self::class, 'post_types' ) );
		add_filter( 'friends_early_modify_feed_item', array( self::class, 'use_cached_item' ), 20, 3 );
		add_filter( 'posts_pre_query', array( self::class, 'frontend_query' ), 20, 2 );
		add_filter( 'mastodon_api_timelines_args', array( self::class, 'ema_timeline_args' ), 95, 2 );
	}

	/**
	 * Whether the experimental bridge and ActivityPub Reader are enabled.
	 *
	 * @return bool Whether both plugins can share cached objects.
	 */
	public static function enabled() {
		return get_option( 'friends_activitypub_reader_cache' ) && get_option( 'activitypub_reader_ui' )
			&& class_exists( '\\Activitypub\\Collection\\Remote_Posts' )
			&& is_callable( array( '\\Activitypub\\Collection\\Remote_Posts', 'has_recipient' ) );
	}

	/**
	 * Reader access is always scoped to the authenticated local actor.
	 *
	 * @return int|null Authorized actor ID, including zero for the blog actor.
	 */
	public static function actor_id() {
		if ( ! is_user_logged_in() || ! self::enabled() ) {
			return null;
		}
		if ( \Activitypub\user_can_activitypub( get_current_user_id() ) ) {
			return get_current_user_id();
		}
		if ( function_exists( '\Activitypub\user_can_act_as_blog' ) && \Activitypub\user_can_act_as_blog() ) {
			return \Activitypub\Collection\Actors::BLOG_USER_ID;
		}
		return null;
	}

	/**
	 * Let existing Friends templates and reaction controls recognize reader posts.
	 *
	 * @param array $types Existing frontend types.
	 * @return array Frontend types.
	 */
	public static function post_types( $types ) {
		if ( null !== self::actor_id() ) {
			$types[] = 'ap_post';
		}
		return array_unique( $types );
	}

	/**
	 * Skip a duplicate cache write only after ActivityPub has stored the object.
	 *
	 * Failed/unsupported ingestion retains Friends' existing behavior. Friends
	 * adds subscription relationships without rewriting ActivityPub's content.
	 *
	 * @param Feed_Item $item Incoming item.
	 * @param User_Feed $feed Its subscription feed.
	 * @param User      $user Friends subscription.
	 * @return Feed_Item|false Item or false when the shared copy already exists.
	 */
	public static function use_cached_item( $item, $feed, $user ) {
		if ( ! self::enabled() || ! $item || 'activitypub' !== $feed->get_parser() ) {
			return $item;
		}
		$actor_id = Feed_Parser_ActivityPub::get_activitypub_actor_id( null );
		$metadata = $item->meta;
		$meta = isset( $metadata['activitypub'] ) ? $metadata['activitypub'] : array();
		// Announce provenance still belongs to Friends during this first stage.
		if ( ! empty( $meta['reblog'] ) ) {
			return $item;
		}
		$id = ! empty( $metadata['external-id'] ) ? $metadata['external-id'] : $item->permalink;
		$post = \Activitypub\Collection\Remote_Posts::get_by_guid( $id );
		if ( is_wp_error( $post ) || ! \Activitypub\Collection\Remote_Posts::has_recipient( $post->ID, $actor_id ) ) {
			return $item;
		}
		wp_set_object_terms( $post->ID, $feed->get_id(), User_Feed::TAXONOMY, true );
		if ( $user instanceof Subscription ) {
			wp_set_object_terms( $post->ID, $user->get_term_id(), Subscription::TAXONOMY, true );
		}
		return false;
	}

	/**
	 * Keep Mastodon clients on the same shared home feed as Friends' UI.
	 *
	 * @param array            $args EMA query arguments.
	 * @param \WP_REST_Request $request Timeline request.
	 * @return array Arguments marking only the authenticated home timeline.
	 */
	public static function ema_timeline_args( $args, $request ) {
		if ( null !== self::actor_id() && '/enable-mastodon-apps/api/v1/timelines/home' === $request->get_route() && $args ) {
			$args['friends_reader_ema'] = true;
		}
		return $args;
	}

	/**
	 * Guard Friends routes which accept individual reader post IDs.
	 *
	 * @param int $post_id Candidate post.
	 * @return bool Whether this actor can read it.
	 */
	public static function can_read( $post_id ) {
		$actor_id = self::actor_id();
		return null !== $actor_id && \Activitypub\Collection\Remote_Posts::has_recipient( $post_id, $actor_id );
	}

	/**
	 * Adapt the synchronous feed, leaving deferred page shells untouched.
	 *
	 * @param array|null $posts Earlier short circuit.
	 * @param \WP_Query  $query Friends main query.
	 * @return array|null Visible posts or the original value.
	 */
	public static function frontend_query( $posts, $query ) {
		if ( null !== $posts || $query->get( 'friends_reader_source' ) || ( empty( $query->is_friends_page ) && ! $query->get( 'friends_reader_ema' ) )
			|| $query->get( 'friends_async_feed' ) || null === self::actor_id() ) {
			return $posts;
		}
		$page = ( new Feed_Query() )->fetch( $query->query_vars );
		$query->set( 'no_found_rows', true );
		$query->max_num_pages = max( 1, (int) $query->get( 'paged' ) ) + ( $page['has_more'] ? 1 : 0 );
		return $page['query']->posts;
	}

	/**
	 * Fetch bounded candidates from both stores, then merge before pagination.
	 *
	 * @param array      $vars Friends query variables.
	 * @param array|null $cursor Date/ID cursor shared by both sources.
	 * @param bool       $single Fetch one diagnostic item.
	 * @return array A normal Feed_Query page.
	 */
	public static function fetch( $vars, $cursor, $single ) {
		$actor_id = self::actor_id();
		if ( null === $actor_id ) {
			$vars['friends_reader_source'] = true;
			$vars['post_type'] = array_values( array_diff( (array) $vars['post_type'], array( 'ap_post' ) ) );
			return ( new Feed_Query() )->fetch( $vars, $cursor, $single );
		}
		$limit = $single ? 1 : max( 1, isset( $vars['posts_per_page'] ) ? (int) $vars['posts_per_page'] : (int) get_option( 'posts_per_page' ) );
		$offset = $cursor ? 0 : ( max( 1, isset( $vars['paged'] ) ? (int) $vars['paged'] : 1 ) - 1 ) * $limit;
		$legacy = $vars;
		$legacy['post_type'] = array_values( array_diff( (array) $vars['post_type'], array( 'ap_post' ) ) );
		$legacy['friends_reader_source'] = true;
		$legacy['friends_reader_actor'] = $actor_id;
		$legacy['posts_per_page'] = $offset + $limit + 1;
		$legacy['paged'] = 1;
		$legacy['suppress_filters'] = false;
		// Remove duplicate legacy objects in SQL, before the candidate limit.
		$exclude_duplicates = static function ( $where, $query ) use ( $actor_id ) {
			if ( ! $query->get( 'friends_reader_source' ) || 'ap_post' === $query->get( 'post_type' ) ) {
				return $where;
			}
			global $wpdb;
			return $where . $wpdb->prepare(
				" AND ({$wpdb->posts}.post_type <> %s OR NOT EXISTS (SELECT 1 FROM {$wpdb->posts} shared_ap INNER JOIN {$wpdb->postmeta} shared_recipient ON shared_recipient.post_id = shared_ap.ID AND shared_recipient.meta_key = '_activitypub_user_id' AND shared_recipient.meta_value = %s WHERE shared_ap.post_type = 'ap_post' AND shared_ap.post_status = 'publish' AND BINARY shared_ap.guid = BINARY {$wpdb->posts}.guid AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} duplicate_author INNER JOIN {$wpdb->term_relationships} duplicate_feed ON duplicate_feed.object_id = CAST(duplicate_author.meta_value AS UNSIGNED) INNER JOIN {$wpdb->term_taxonomy} duplicate_tax ON duplicate_tax.term_taxonomy_id = duplicate_feed.term_taxonomy_id WHERE duplicate_author.post_id = shared_ap.ID AND duplicate_author.meta_key = '_activitypub_remote_actor_id' AND duplicate_tax.taxonomy = %s)))",
				Friends::CPT,
				(string) $actor_id,
				User_Feed::TAXONOMY
			);
		};
		add_filter( 'posts_where', $exclude_duplicates, 20, 2 );
		try {
			$old = ( new Feed_Query() )->fetch( $legacy, $cursor );
		} finally {
			remove_filter( 'posts_where', $exclude_duplicates, 20 );
		}
		$remote = $vars;
		$remote['post_type'] = 'ap_post';
		$remote['post_status'] = 'publish';
		$remote['friends_reader_source'] = true;
		$remote['posts_per_page'] = $offset + $limit + 1;
		$remote['paged'] = 1;
		$remote['suppress_filters'] = false;
		// post_author is not the remote actor; subscription taxonomy handles authors.
		unset( $remote['author'], $remote['author__in'], $remote['author__not_in'] );
		// Keep subscription/reaction filters; translate post formats to AP object types.
		if ( ! empty( $remote['tax_query'] ) ) {
			$remote['tax_query'] = self::reader_tax_query( $remote['tax_query'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		$recipient = array(
			'key'   => '_activitypub_user_id',
			'value' => $actor_id,
		);
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$remote['meta_query'] = empty( $remote['meta_query'] ) ? array( $recipient ) : array(
			'relation' => 'AND',
			$remote['meta_query'],
			$recipient,
		);
		$known_author = static function ( $where, $query ) {
			if ( ! $query->get( 'friends_reader_source' ) || 'ap_post' !== $query->get( 'post_type' ) ) {
				return $where;
			}
			global $wpdb;
			return $where . $wpdb->prepare(
				" AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} shared_author INNER JOIN {$wpdb->term_relationships} shared_feed ON shared_feed.object_id = CAST(shared_author.meta_value AS UNSIGNED) INNER JOIN {$wpdb->term_taxonomy} shared_tax ON shared_tax.term_taxonomy_id = shared_feed.term_taxonomy_id WHERE shared_author.post_id = {$wpdb->posts}.ID AND shared_author.meta_key = '_activitypub_remote_actor_id' AND shared_tax.taxonomy = %s)",
				User_Feed::TAXONOMY
			);
		};
		add_filter( 'posts_where', $known_author, 20, 2 );
		try {
			$new = ( new Feed_Query() )->fetch( $remote, $cursor );
		} finally {
			remove_filter( 'posts_where', $known_author, 20 );
		}
		$posts = array_merge( $old['query']->posts, $new['query']->posts );
		$ascending = isset( $vars['order'] ) && 'ASC' === strtoupper( $vars['order'] );
		usort(
			$posts,
			static function ( $a, $b ) use ( $ascending ) {
				$result = strcmp( $b->post_date, $a->post_date );
				if ( ! $result ) {
					$result = $b->ID <=> $a->ID;
				}
				return $ascending ? -$result : $result;
			}
		);
		$has_more = count( $posts ) > $offset + $limit || $old['has_more'] || $new['has_more'];
		$posts = array_slice( $posts, $offset, $limit );
		$query = $old['query'];
		$query->posts = $posts;
		$query->post_count = count( $posts );
		$last = end( $posts );
		return array(
			'query'        => $query,
			'has_more'     => $has_more,
			'rows_fetched' => $old['rows_fetched'] + $new['rows_fetched'],
			'cursor'       => $has_more && $last ? wp_json_encode(
				array(
					'date' => $last->post_date,
					'id'   => (int) $last->ID,
				)
			) : null,
		);
	}

	/**
	 * Translate the two common Friends format views without sharing taxonomy state.
	 *
	 * @param array $query Taxonomy clauses.
	 * @return array Reader taxonomy clauses.
	 */
	private static function reader_tax_query( $query ) {
		foreach ( $query as $key => $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}
			if ( isset( $clause['taxonomy'] ) && 'post_format' === $clause['taxonomy'] ) {
				// A status view is Note; other format views stay empty until mapped.
				$terms = (array) $clause['terms'];
				$query[ $key ] = array(
					'taxonomy' => 'ap_object_type',
					'field'    => 'name',
					'terms'    => in_array( 'post-format-status', $terms, true ) && ( empty( $clause['operator'] ) || 'IN' === $clause['operator'] ) ? array( 'Note' ) : array( '__friends_unmapped_format__' ),
				);
			} elseif ( ! isset( $clause['taxonomy'] ) ) {
				$query[ $key ] = self::reader_tax_query( $clause );
			}
		}
		return $query;
	}
}
