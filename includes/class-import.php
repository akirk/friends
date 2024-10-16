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
	public static function opml( $opml ) {
		$opml = simplexml_load_string( $opml );
		if ( ! $opml ) {
			return new \WP_Error( 'friends_import_opml_error', __( 'Failed to parse OPML.', 'friends' ) );
		}

		$feeds = array();
		foreach ( $opml->body->outline as $outline ) {
			$role = (string) $outline['text'];
			foreach ( $outline->outline as $friend ) {
				$user_login = str_replace( ' ', '-', strtolower( sanitize_user( (string) $friend['text'] ) ) );
				$feed = User_Feed::get_by_url( (string) $friend['xmlUrl'] );
				if ( $feed instanceof User_Feed ) {
					$friend_user = $feed->get_friend_user();
					if ( ! isset( $feeds[ $friend_user->user_login ] ) ) {
						$feeds[ $friend_user->user_login ] = array();
					}
					$feeds[ $friend_user->user_login ][] = $feed;
					continue;
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
					continue;
				}
				$feed = $friend_user->save_feed(
					(string) $friend['xmlUrl'],
					array(
						'active'    => true,
						'mime-type' => 'atom' === (string) $friend['type'] ? 'application/atom+xml' : 'application/rss+xml',
					)
				);
				if ( ! $feed instanceof User_Feed ) {
					continue;
				}

				if ( ! isset( $feeds[ $friend_user->user_login ] ) ) {
					$feeds[ $friend_user->user_login ] = array();
				}
				$feeds[ $friend_user->user_login ][] = $feed;
			}
		}

		return $feeds;
	}
}
