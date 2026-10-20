<?php
/**
 * Friends 3rd Parties
 *
 * This contains the functions for working with third party plugins.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Friends Plugin 3rd Party handling.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Third_Parties {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends = null;

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_filter( 'wp_sweep_excluded_taxonomies', array( $this, 'wp_sweep_excluded_taxonomies' ) );
		add_filter( 'socialspec_following', array( $this, 'socialspec_following' ) );
	}

	/**
	 * SocialSpec
	 * Add the followed feeds to /.well-known/following.opml.
	 *
	 * Like the public OPML, only feeds with a public RSS or Atom URL are listed.
	 *
	 * @param array $outlines The OPML outline attributes.
	 * @return array
	 */
	public function socialspec_following( $outlines ) {
		foreach ( User_Query::all_associated_users()->get_results() as $friend_user ) {
			foreach ( $friend_user->get_active_feeds() as $feed ) {
				switch ( $feed->get_mime_type() ) {
					case 'application/atom+xml':
					case 'application/atomxml':
						$type = 'atom';
						break;
					case 'application/rss+xml':
					case 'application/rssxml':
						$type = 'rss';
						break;
					default:
						continue 2;
				}

				$outlines[] = array(
					'type'    => $type,
					'text'    => $friend_user->display_name,
					'htmlUrl' => $friend_user->user_url,
					'xmlUrl'  => $feed->get_url(),
				);
			}
		}

		return $outlines;
	}

	/**
	 * WP Sweep
	 * Prevent WP Sweep from sweeping our taxonomies.
	 *
	 * @since 2.0
	 *
	 * @param array $excluded_taxonomies list of taxonomies excluded from sweeping.
	 * @return array
	 */
	public function wp_sweep_excluded_taxonomies( $excluded_taxonomies ) {
		return array_merge( $excluded_taxonomies, array( User_Feed::TAXONOMY, Subscription::TAXONOMY ) );
	}
}
