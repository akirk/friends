<?php
/**
 * Blockroll integration.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Provides Friends subscriptions as a Blockroll source.
 */
class Blockroll {
	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'blockroll_sources', array( __CLASS__, 'sources' ) );
		add_filter( 'blockroll_source_links', array( __CLASS__, 'source_links' ), 10, 2 );
	}

	/**
	 * Register the Friends subscriptions source.
	 *
	 * @param array $sources Source slug => source label.
	 * @return array Sources.
	 */
	public static function sources( $sources ) {
		$sources['friends-subscriptions'] = __( 'Friends subscriptions', 'friends' );
		return $sources;
	}

	/**
	 * Provide Friends subscriptions as Blockroll links.
	 *
	 * @param array  $links  Current links.
	 * @param string $source Selected source.
	 * @return array Links.
	 */
	public static function source_links( $links, $source ) {
		if ( 'friends-subscriptions' !== $source ) {
			return $links;
		}

		$links = array();
		foreach ( User_Query::all_subscriptions()->get_results() as $subscription ) {
			if ( ! $subscription->user_url ) {
				continue;
			}

			$links[] = array(
				'url'         => $subscription->user_url,
				'name'        => $subscription->display_name ? $subscription->display_name : $subscription->user_login,
				'description' => $subscription->description,
				'feedUrl'     => self::feed_url( $subscription ),
				'photo'       => $subscription->get_avatar_url(),
				'xfn'         => array(),
				'added'       => $subscription->user_registered,
			);
		}

		return $links;
	}

	/**
	 * Get a representative feed URL for a subscription.
	 *
	 * @param User $subscription Friend user or virtual subscription.
	 * @return string Feed URL.
	 */
	private static function feed_url( User $subscription ) {
		foreach ( $subscription->get_active_feeds() as $feed ) {
			return $feed->get_url();
		}

		foreach ( $subscription->get_feeds() as $feed ) {
			return $feed->get_url();
		}

		return '';
	}
}
