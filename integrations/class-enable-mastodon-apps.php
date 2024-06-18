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
		add_filter( 'mastodon_api_account', array( 'Friends\User', 'mastodon_api_account' ), 8, 4 );
		add_filter( 'mastodon_api_get_posts_query_args', array( 'Friends\User', 'mastodon_api_get_posts_query_args' ) );
		add_filter( 'mastodon_entity_relationship', array( 'Friends\User', 'mastodon_entity_relationship' ), 10, 2 );
		add_action( 'mastodon_api_account_follow', array( get_called_class(), 'mastodon_api_account_follow' ), 10, 2 );
		add_filter( 'mastodon_api_timelines_args', array( get_called_class(), 'mastodon_api_timelines_args' ) );
	}

	public static function mastodon_api_account_follow( $user_id, $request ) {
		return apply_filters( 'friends_create_and_follow', null, $user_id );
	}

	public static function mastodon_api_timelines_args( $args ) {
		$args['post_type'][] = Friends::CPT;
		return $args;
	}
}
