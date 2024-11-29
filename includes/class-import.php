<?php
/**
 * Friends import
 *
 * This contains the import functions.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the import part of the Friends Plugin.
 *
 * @since 3.0
 *
 * @package Friends
 * @author Alex Kirk
 */
class Import {

	private static function get_feed_from_opml_node( $friend ) {
		$xml_url = (string) $friend['xmlUrl'];
		if ( ! $xml_url ) {
			return null;
		}

		$username = (string) $friend['text'];
		if ( ! $username ) {
			$username = (string) $friend['title'];
		}
		if ( ! $username ) {
			$username = (string) $friend['htmlUrl'];
			$username = preg_replace( '/^https?:\/\//', '', $username );
		}
		if ( ! $username ) {
			$username = (string) $xml_url;
			$username = preg_replace( '/^https?:\/\//', '', $username );
		}

		$user_login = User::sanitize_username( $username );
		$feed = User_Feed::get_by_url( $xml_url );
		if ( $feed instanceof User_Feed ) {
			return $feed;
		}

		$friend_user = User::get_by_username( $user_login );
		if ( ! $friend_user || is_wp_error( $friend_user ) ) {
			$friend_user = Subscription::create(
				$user_login,
				'subscription',
				(string) $friend['htmlUrl'],
				(string) $friend['text']
			);
		}

		if ( ! $friend_user instanceof User ) {
			return null;
		}

		$feed = $friend_user->save_feed(
			$xml_url,
			array(
				'active'    => true,
				'mime-type' => 'atom' === (string) $friend['type'] ? 'application/atom+xml' : 'application/rss+xml',
			)
		);

		if ( ! $feed instanceof User_Feed ) {
			if ( is_wp_error( $feed ) && apply_filters( 'friends_debug', false ) ) {
				wp_trigger_error( __FUNCTION__, $feed->get_error_message() );

			}
			return null;
		}

		return $feed;
	}

	private static function recurse_into_opml( $feeds, $friend ) {
		$xml_url = (string) $friend['xmlUrl'];
		if ( $xml_url ) {
			$feed = self::get_feed_from_opml_node( $friend );
			if ( $feed instanceof User_Feed ) {
				$friend_user = $feed->get_friend_user();

				if ( ! isset( $feeds[ $friend_user->user_login ] ) ) {
					$feeds[ $friend_user->user_login ] = array();
				}
				$feeds[ $friend_user->user_login ][] = $feed;
			}
			return $feeds;
		}

		foreach ( $friend->outline as $child ) {
			$feeds = self::recurse_into_opml( $feeds, $child );
		}

		return $feeds;
	}

	public static function opml( $opml ) {
		$opml = simplexml_load_string( $opml );
		if ( ! $opml ) {
			return new \WP_Error( 'friends_import_opml_error', __( 'Failed to parse OPML.', 'friends' ) );
		}

		$feeds = self::recurse_into_opml( array(), $opml->body );

		return $feeds;
	}
}
