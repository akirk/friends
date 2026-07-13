<?php
namespace Friends;

/**
 * Class Enable_Mastodon_Apps
 *
 * This class is used to enable Mastodon Apps to work with Friends.
 *
 * @see https://github.com/akirk/enable-mastodon-apps
 */
class Enable_Mastodon_Apps {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		add_filter( 'mastodon_api_account', array( 'Friends\User', 'mastodon_api_account' ), 10, 4 );
		add_filter( 'mastodon_api_account_id', array( 'Friends\User', 'mastodon_api_account_id' ), 8, 2 );
		add_filter( 'mastodon_api_get_posts_query_args', array( 'Friends\User', 'mastodon_api_get_posts_query_args' ) );
		add_filter( 'mastodon_entity_relationship', array( 'Friends\User', 'mastodon_entity_relationship' ), 10, 2 );
		add_filter( 'mastodon_api_account_follow', array( get_called_class(), 'mastodon_api_account_follow' ), 10, 1 );
		add_action( 'mastodon_api_account_unfollow', array( get_called_class(), 'mastodon_api_account_unfollow' ), 10, 1 );
		add_filter( 'mastodon_api_view_post_types', array( get_called_class(), 'mastodon_api_view_post_types' ) );
		add_filter( 'mastodon_api_favourites_args', array( get_called_class(), 'mastodon_api_favourites_args' ), 10, 2 );
		add_filter( 'mastodon_api_bookmarks_args', array( get_called_class(), 'mastodon_api_bookmarks_args' ), 10, 2 );
		add_filter( 'mastodon_api_status', array( get_called_class(), 'mastodon_api_status' ), 60, 2 );
		add_filter( 'mastodon_api_get_notifications_query_args', array( get_called_class(), 'mastodon_api_get_notifications_query_args' ), 10, 2 );
		add_filter( 'mastodon_api_account_statuses_excluded_post_types', array( get_called_class(), 'mastodon_api_account_statuses_excluded_post_types' ) );
		add_filter( 'mastodon_api_account_statuses', array( get_called_class(), 'mastodon_api_account_statuses' ), 10, 3 );
	}

	public static function mastodon_api_account_follow( $user_id ) {
		$user = User::get_user_by_id( $user_id );
		if ( ! $user ) {
			return apply_filters( 'friends_create_and_follow', null, $user_id );
		}
		foreach ( $user->get_feeds() as $feed ) {
			if ( $feed->is_active() ) {
				continue;
			}
			if ( class_exists( 'Friends\Feed_Parser_ActivityPub' ) && Feed_Parser_ActivityPub::SLUG === $feed->get_parser() ) {
				$feed->activate();
			}
		}
		return $user_id;
	}

	public static function mastodon_api_account_unfollow( $user_id ) {
		$user = User::get_user_by_id( $user_id );
		foreach ( $user->get_active_feeds() as $feed ) {
			$feed->deactivate();
		}
	}

	public static function mastodon_api_view_post_types( $view_post_types ) {
		$view_post_types[] = Friends::CPT;
		return $view_post_types;
	}

	public static function mastodon_api_account_statuses_excluded_post_types( $excluded_post_types ) {
		$excluded_post_types[] = Friends::CPT;
		return $excluded_post_types;
	}

	public static function mastodon_api_favourites_args( $args, $user_id ) {
		return self::mastodon_api_reaction_args(
			self::get_mastodon_api_reaction( 'favourite' ),
			$args,
			$user_id
		);
	}
	public static function mastodon_api_bookmarks_args( $args, $user_id ) {
		return self::mastodon_api_reaction_args(
			self::get_mastodon_api_reaction( 'bookmark' ),
			$args,
			$user_id
		);
	}

	protected static function mastodon_api_reaction_args( $reaction, $args, $user_id ) {
		$tax_query = array();
		if ( isset( $args['tax_query'] ) ) {
			$tax_query = $args['tax_query'];
		}

		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
		}
		Reactions::register_user_taxonomy( $user_id );

		$reaction_query = array(
			'taxonomy' => 'friend-reaction-' . $user_id,
			'field'    => 'slug',
			'terms'    => array( strval( $reaction ) ),
		);

		if ( ! empty( $tax_query ) ) {
			$tax_query[] = $reaction_query;
		} else {
			$tax_query = array( $reaction_query );
		}

		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query

		return $args;
	}

	protected static function get_mastodon_api_reaction( $type ) {
		if ( 'bookmark' === $type ) {
			return apply_filters( 'mastodon_api_bookmark_reaction', apply_filters( 'friends_bookmarks_emoji', '2b50' ), null );
		}

		return apply_filters( 'mastodon_api_favourite_reaction', apply_filters( 'friends_favourites_emoji', '2764' ), null );
	}

	public static function mastodon_api_status( $status, $post_id ) {
		if ( ! $status ) {
			return $status;
		}

		$reaction_post_id = $post_id;
		$paired_post_id   = get_post_meta( $post_id, 'mastodon_reblog_id', true );
		if ( Friends::CPT !== get_post_type( $reaction_post_id ) ) {
			if ( ! $paired_post_id || Friends::CPT !== get_post_type( $paired_post_id ) ) {
				return $status;
			}
			$reaction_post_id = $paired_post_id;
		}

		$reactions = Reactions::get_post_reactions( $reaction_post_id );
		if ( ! is_array( $reactions ) ) {
			return $status;
		}

		$favourite_reaction = self::get_mastodon_api_reaction( 'favourite' );
		$bookmark_reaction  = self::get_mastodon_api_reaction( 'bookmark' );
		$remapped_reactions = array();
		if ( $paired_post_id && intval( $paired_post_id ) !== intval( $reaction_post_id ) ) {
			$remapped_reactions = Reactions::get_post_reactions( $paired_post_id );
			if ( ! is_array( $remapped_reactions ) ) {
				$remapped_reactions = array();
			}
		}

		$favourite = $reactions[ $favourite_reaction ] ?? $remapped_reactions[ $favourite_reaction ] ?? null;
		$bookmark  = $reactions[ $bookmark_reaction ] ?? $remapped_reactions[ $bookmark_reaction ] ?? null;

		if ( $favourite ) {
			$status->favourited       = (bool) $favourite->user_reacted;
			$status->favourites_count = intval( $favourite->count );
		}
		if ( $bookmark ) {
			$status->bookmarked = (bool) $bookmark->user_reacted;
		}

		if ( isset( $status->reblog ) ) {
			$status->reblog->favourited       = $status->favourited;
			$status->reblog->bookmarked       = $status->bookmarked;
			$status->reblog->favourites_count = $status->favourites_count;
		}

		return $status;
	}

	public static function mastodon_api_account_statuses( $statuses, $request, $user_id ) {
		if ( $request->get_param( 'exclude_reblogs' ) ) {
			return $statuses;
		}

		if ( is_wp_error( $statuses ) ) {
			return $statuses;
		}

		$response = null;
		if ( $statuses instanceof \WP_REST_Response ) {
			$response = $statuses;
			$statuses = $response->get_data();
		}

		if ( ! is_array( $statuses ) ) {
			return $response ? $response : $statuses;
		}

		$boosted_posts = self::get_mastodon_api_boosted_posts( $request, $user_id );
		if ( empty( $boosted_posts ) ) {
			return $response ? $response : $statuses;
		}

		$account = apply_filters( 'mastodon_api_account', null, $user_id, $request, null );
		if ( ! is_object( $account ) ) {
			return $response ? $response : $statuses;
		}

		foreach ( $boosted_posts as $post ) {
			$reblog = apply_filters( 'mastodon_api_status', null, $post->ID, array() );
			if ( ! is_object( $reblog ) || empty( $reblog->id ) ) {
				continue;
			}

			$status             = clone $reblog;
			$status->account    = $account;
			$status->reblog     = $reblog;
			$status->content    = '';
			$status->url        = null;
			$status->created_at = self::get_mastodon_api_boosted_at( $post );
			$status->reblogged  = true;
			$statuses[]         = $status;
		}

		usort(
			$statuses,
			function ( $a, $b ) {
				return $b->created_at->getTimestamp() - $a->created_at->getTimestamp();
			}
		);

		$limit = $request->get_param( 'limit' );
		if ( $limit > 0 ) {
			$statuses = array_slice( $statuses, 0, $limit );
		}

		if ( $response ) {
			$response->set_data( $statuses );
			return $response;
		}

		return $statuses;
	}

	protected static function get_mastodon_api_boosted_posts( $request, $user_id ) {
		$args = array(
			'post_type'        => Friends::CPT,
			'post_status'      => array( 'publish', 'private' ),
			'posts_per_page'   => $request->get_param( 'limit' ),
			'suppress_filters' => false,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'boosted',
					'value' => strval( $user_id ),
				),
			),
		);

		return get_posts( $args );
	}

	protected static function get_mastodon_api_boosted_at( \WP_Post $post ) {
		$boosted_at = get_post_meta( $post->ID, 'boosted_at', true );
		if ( $boosted_at ) {
			return new \DateTime( $boosted_at, new \DateTimeZone( 'UTC' ) );
		}

		return new \DateTime( $post->post_modified_gmt, new \DateTimeZone( 'UTC' ) );
	}

	public static function mastodon_api_get_notifications_query_args( $args, $type ) {
		if ( 'mention' !== $type ) {
			return $args;
		}

		if ( ! isset( $args['post_type'] ) ) {
			$args['post_type'] = array();
		} elseif ( ! is_array( $args['post_type'] ) ) {
			$args['post_type'] = array( $args['post_type'] );
		}

		// Include public Friends posts for public mentions.
		if ( ! in_array( Friends::CPT, $args['post_type'] ) ) {
			$args['post_type'][] = Friends::CPT;
		}

		if ( ! isset( $args['post_status'] ) ) {
			$args['post_status'] = array();
		} elseif ( ! is_array( $args['post_status'] ) ) {
			$args['post_status'] = array( $args['post_status'] );
		}

		// Add public post statuses for mentions.
		if ( ! in_array( 'publish', $args['post_status'] ) ) {
			$args['post_status'][] = 'publish';
		}
		if ( ! in_array( 'private', $args['post_status'] ) ) {
			$args['post_status'][] = 'private';
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query

		// Add tax_query to filter for mention tags for the current user.
		$current_user = wp_get_current_user();
		if ( $current_user && $current_user->ID ) {
			$mention_tag = Friend_Tag::mention_tag( $current_user->user_login );

			$mention_query = array(
				'taxonomy' => Friends::TAG_TAXONOMY,
				'field'    => 'name',
				'terms'    => $mention_tag,
			);

			if ( ! isset( $args['tax_query'] ) ) {
				$args['tax_query'] = array( $mention_query );
			} elseif ( ! empty( $args['tax_query'] ) ) {
				$args['tax_query'] = array(
					'relation' => 'OR',
					$args['tax_query'],
					$mention_query,
				);
			} else {
				$args['tax_query'] = array( $mention_query );
			}
		}

		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_tax_query

		return $args;
	}
}
