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
			$feeds[ $role ] = array();
			foreach ( $outline->outline as $friend ) {
				$user_login = str_replace( ' ', '-', strtolower( sanitize_user( $friend['text'] ) ) );
				$friend_user = Subscription::create( $user_login, 'subscription', $friend['htmlUrl'], $friend['text'] );
				$friend_user->save_feed(
					$friend['xmlUrl'],
					array(
						'active' => true,
					)
				);
				$feeds[ $role ][] = $friend_user;
			}
		}

		return $feeds;
	}
}
