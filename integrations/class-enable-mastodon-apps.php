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

	public static function mastodon_api_favourites_args( $args, $user_id ) {
		return self::mastodon_api_reaction_args(
			apply_filters( 'friends_favourites_emoji', '2764' ), // ❤️
			$args,
			$user_id
		);
	}
	public static function mastodon_api_bookmarks_args( $args, $user_id ) {
		return self::mastodon_api_reaction_args(
			apply_filters( 'friends_bookmarks_emoji', '2b50' ), // ⭐
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
}
