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
	const HIDE_META = 'hide_from_blockroll';

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
			if ( ! $subscription->user_url || self::is_hidden( $subscription ) ) {
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

	/**
	 * Whether Blockroll is available.
	 *
	 * @return bool True if Blockroll is active.
	 */
	public static function is_available() {
		return defined( 'BLOCKROLL_PLUGIN_FILE' );
	}

	/**
	 * Whether a subscription is hidden from Blockroll for the current user.
	 *
	 * @param User $subscription Friend user or virtual subscription.
	 * @return bool True if hidden from Blockroll.
	 */
	public static function is_hidden( User $subscription ) {
		if ( ! $subscription instanceof Subscription ) {
			return false;
		}

		return (bool) get_term_meta( $subscription->get_term_id(), self::HIDE_META, true );
	}

	/**
	 * Set whether a subscription is hidden from Blockroll.
	 *
	 * @param User $subscription Friend user or virtual subscription.
	 * @param bool $hide         Whether to hide the subscription.
	 */
	public static function set_hidden( User $subscription, $hide ) {
		if ( ! $subscription instanceof Subscription ) {
			return;
		}

		if ( $hide ) {
			update_term_meta( $subscription->get_term_id(), self::HIDE_META, true );
			return;
		}

		delete_term_meta( $subscription->get_term_id(), self::HIDE_META );
	}
}
