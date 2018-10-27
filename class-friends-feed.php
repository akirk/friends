<?php
/**
 * Friends Feed
 *
 * This contains the feed functions.
 *
 * @package Friends
 */

/**
 * This is the class for the feed part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Feed {
	const XMLNS = 'wordpress-plugin-friends:feed-additions:1';

	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

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
		add_filter( 'pre_get_posts', array( $this, 'private_feed_query' ), 1 );
		add_filter( 'private_title_format', array( $this, 'private_title_format' ) );
		add_filter( 'pre_option_rss_use_excerpt', array( $this, 'feed_use_excerpt' ), 90 );
		add_action( 'rss_item', array( $this, 'feed_additional_fields' ) );
		add_action( 'rss2_item', array( $this, 'feed_additional_fields' ) );
		add_action( 'rss_ns', array( $this, 'additional_feed_namespaces' ) );
		add_action( 'rss2_ns', array( $this, 'additional_feed_namespaces' ) );

		add_action( 'cron_friends_refresh_feeds', array( $this, 'cron_friends_refresh_feeds' ) );
		add_action( 'set_user_role', array( $this, 'retrieve_new_friends_posts' ), 999, 3 );

		add_action( 'wp_loaded', array( $this, 'friends_opml' ), 100 );
		add_action( 'wp_feed_options', array( $this, 'wp_feed_options' ), 10, 2 );
	}

	/**
	 * Cron function to refresh the feeds of the friends' blogs
	 */
	public function cron_friends_refresh_feeds() {
		$this->retrieve_friend_posts();
	}

	/**
	 * Retrieve posts from a remote WordPress for a user or all friend users.
	 *
	 * @param  WP_User|null $single_user A single user or null to fetch all.
	 */
	public function retrieve_friend_posts( WP_User $single_user = null ) {
		if ( $single_user ) {
			$friends = array(
				$single_user,
			);
		} else {
			$friends = new WP_User_Query( array( 'role__in' => array( 'friend', 'restricted_friend', 'pending_friend_request', 'subscription' ) ) );
			$friends = $friends->get_results();

			if ( empty( $friends ) ) {
				return;
			}
		}

		foreach ( $friends as $friend_user ) {
			$feed_url = $this->get_feed_url( $friend_user );

			$feed = $this->fetch_feed( $feed_url );
			if ( is_wp_error( $feed ) ) {
				do_action( 'friends_retrieve_friends_error', $feed, $friend_user );
				continue;
			}
			$feed      = apply_filters( 'friends_feed_content', $feed, $friend_user );
			$new_posts = $this->process_friend_feed( $friend_user, $feed );
			do_action( 'friends_retrieved_new_posts', $new_posts, $friend_user );
		}
	}

	/**
	 * Get the (private) feed URL for a friend.
	 *
	 * @param  WP_User $friend_user The friend user.
	 * @param  boolean $private     Whether to generate a private feed URL (if possible).
	 * @return string               The feed URL.
	 */
	public function get_feed_url( WP_User $friend_user, $private = true ) {
		$feed_url = get_user_option( 'friends_feed_url', $friend_user->ID );
		if ( ! $feed_url ) {
			$feed_url = rtrim( $friend_user->user_url, '/' ) . '/feed/';
		}

		if ( $private && current_user_can( Friends::REQUIRED_ROLE ) ) {
			$token = get_user_option( 'friends_out_token', $friend_user->ID );
			if ( $token ) {
				$feed_url .= '?friend=' . $token;
			}
		}
		return apply_filters( 'friends_friend_feed_url', $feed_url, $friend_user );
	}

	/**
	 * Retrieve the remote post ids.
	 *
	 * @param  WP_User $friend_user The friend user.
	 * @return array A mapping of the remote post ids.
	 */
	public function get_remote_post_ids( WP_User $friend_user ) {
		$remote_post_ids = array();
		$existing_posts  = new WP_Query(
			array(
				'post_type'   => Friends::FRIEND_POST_CACHE,
				'post_status' => array( 'publish', 'private' ),
				'author'      => $friend_user->ID,
				'nopaging'    => true,
			)
		);

		if ( $existing_posts->have_posts() ) {
			while ( $existing_posts->have_posts() ) {
				$post           = $existing_posts->next_post();
				$remote_post_id = get_post_meta( $post->ID, 'remote_post_id', true );
				if ( $remote_post_id ) {
					$remote_post_ids[ $remote_post_id ] = $post->ID;
				}
				$permalink                     = get_permalink( $post );
				$remote_post_ids[ $permalink ] = $post->ID;
				$permalink                     = str_replace( array( '&#38;', '&#038;' ), '&', ent2ncr( $permalink ) );
				$remote_post_ids[ $permalink ] = $post->ID;
			}
		}

		do_action( 'friends_remote_post_ids', $remote_post_ids );
		return $remote_post_ids;
	}

	/**
	 * Process the feed of a friend user.
	 *
	 * @param  WP_User   $friend_user The friend user.
	 * @param  SimplePie $feed        The RSS feed object of the friend user.
	 */
	public function process_friend_feed( WP_User $friend_user, SimplePie $feed ) {
		$new_friend = get_user_option( 'friends_new_friend', $friend_user->ID );

		$remote_post_ids = $this->get_remote_post_ids( $friend_user );
		$new_posts       = array();

		foreach ( $feed->get_items() as $item ) {
			if ( ! apply_filters( 'friends_use_feed_item', true, $item, $feed, $friend_user ) ) {
				continue;
			}
			$permalink = str_replace( array( '&#38;', '&#038;' ), '&', ent2ncr( wp_kses_normalize_entities( $item->get_permalink() ) ) );
			$title     = trim( $item->get_title() );
			$content   = wp_kses_post( trim( $item->get_content() ) );

			// Fallback, when no friends plugin is installed.
			$item->{'post-id'}     = $permalink;
			$item->{'post-status'} = 'publish';
			if ( ! isset( $item->comment_count ) ) {
				$item->comment_count = 0;
			}

			if ( ( ! $content && ! $title ) || ! $permalink ) {
				continue;
			}

			foreach ( array( 'gravatar', 'comments', 'post-status', 'post-id', 'reaction' ) as $key ) {
				if ( ! isset( $item->{$key} ) ) {
					$item->{$key} = false;
				}
				foreach ( array( self::XMLNS, 'com-wordpress:feed-additions:1' ) as $xmlns ) {
					if ( ! isset( $item->data['child'][ $xmlns ][ $key ][0]['data'] ) ) {
						continue;
					}

					if ( 'reaction' === $key ) {
						$item->reaction = $item->data['child'][ $xmlns ][ $key ];
						break;
					}

					$item->{$key} = $item->data['child'][ $xmlns ][ $key ][0]['data'];
					break;
				}
			}

			$item->comments_count = isset( $item->data['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'] ) ? $item->data['child']['http://purl.org/rss/1.0/modules/slash/']['comments'][0]['data'] : 0;

			$post_id = null;
			if ( isset( $remote_post_ids[ $item->{'post-id'} ] ) ) {
				$post_id = $remote_post_ids[ $item->{'post-id'} ];
			}
			if ( is_null( $post_id ) && isset( $remote_post_ids[ $permalink ] ) ) {
				$post_id = $remote_post_ids[ $permalink ];
			}

			if ( is_null( $post_id ) ) {
				$post_id = $this->url_to_postid( $permalink, $friend_user->ID );
			}

			$post_data = array(
				'post_title'        => $title,
				'post_content'      => $content,
				'post_modified_gmt' => $item->get_updated_gmdate( 'Y-m-d H:i:s' ),
				'post_status'       => $item->{'post-status'},
				'guid'              => $permalink,
			);

			if ( ! is_null( $post_id ) ) {
				$post_data['ID'] = $post_id;
				wp_update_post( $post_data );
			} else {
				$post_data['post_author']   = $friend_user->ID;
				$post_data['post_type']     = Friends::FRIEND_POST_CACHE;
				$post_data['post_date_gmt'] = $item->get_gmdate( 'Y-m-d H:i:s' );
				$post_data['comment_count'] = $item->comment_count;
				$post_id                    = wp_insert_post( $post_data, true );
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
				$new_posts[]                   = $post_id;
				$remote_post_ids[ $permalink ] = $post_id;
			}

			$author = $item->get_author();
			if ( $author ) {
				update_post_meta( $post_id, 'author', $author->name );
			}
			if ( $item->gravatar ) {
				update_post_meta( $post_id, 'gravatar', $item->gravatar );
			}
			if ( $item->reaction ) {
				$this->friends->reactions->update_remote_feed_reactions( $post_id, $item->reaction );
			}

			if ( is_numeric( $item->{'post-id'} ) ) {
				update_post_meta( $post_id, 'remote_post_id', $item->{'post-id'} );
			}

			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'comment_count' => $item->comment_count ), array( 'ID' => $post_id ) );
		}

		if ( $new_friend ) {
			delete_user_option( $friend_user->ID, 'friends_new_friend' );
		} else {
			foreach ( $new_posts as $post_id ) {
				$notify_users = apply_filters( 'notify_about_new_friend_post', true, $friend_user, $post_id );
				if ( $notify_users ) {
					do_action( 'notify_new_friend_post', get_post( intval( $post_id ) ) );
				}
			}
		}

		return $new_posts;
	}

	/**
	 * Remove the Private: when sending a private feed.
	 *
	 * @param  string $title_format The title format for a private post title.
	 * @return string The modified title format for a private post title.
	 */
	public function private_title_format( $title_format ) {
		if ( $this->friends->access_control->feed_is_authenticated() ) {
			return '%s';
		}
		return $title_format;
	}

	/**
	 * Disable excerpted feeds for friend feeds
	 *
	 * @param  boolean $feed_use_excerpt Whether to only have excerpts in feeds.
	 * @return boolean The modified flag whether to have excerpts in feeds.
	 */
	public function feed_use_excerpt( $feed_use_excerpt ) {
		if ( $this->friends->access_control->feed_is_authenticated() ) {
			return 0;
		}

		return $feed_use_excerpt;
	}

	/**
	 * Output an additional XMLNS for the feed.
	 */
	public function additional_feed_namespaces() {
		if ( $this->friends->access_control->feed_is_authenticated() ) {
			echo 'xmlns:friends="' . esc_attr( self::XMLNS ) . '"';
		}
	}

	/**
	 * Additional fields for the friends feed.
	 */
	public function feed_additional_fields() {
		$authenticated_user_id = $this->friends->access_control->feed_is_authenticated();
		if ( ! $authenticated_user_id ) {
			return;
		}

		global $post;
		echo '<friends:gravatar>' . esc_html( get_avatar_url( $post->post_author ) ) . '</friends:gravatar>' . PHP_EOL;
		echo '<friends:post-status>' . esc_html( $post->post_status ) . '</friends:post-status>' . PHP_EOL;
		echo '<friends:post-id>' . esc_html( $post->ID ) . '</friends:post-id>' . PHP_EOL;

		$reactions = $this->friends->reactions->get_reactions( $post->ID, $authenticated_user_id );
		foreach ( $reactions as $slug => $reaction ) {
			echo '<friends:reaction';
			echo ' friends:slug="' . esc_attr( $slug ) . '"';
			echo ' friends:count="' . esc_attr( $reaction->count ) . '"';
			if ( $reaction->user_reacted ) {
				echo ' friends:you-reacted="1"';
			}
			echo '>' . esc_html( $reaction->usernames ) . '</friends:reaction>' . PHP_EOL;
		}
	}

	/**
	 * Offers the OPML file for download.
	 */
	public function friends_opml() {
		if ( ! isset( $_GET['friends'] ) || 'opml' !== $_GET['friends'] ) {
			return;
		}

		if ( ! isset( $_GET['auth'] ) || get_option( 'friends_private_rss_key' ) !== $_GET['auth'] ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'friends' ) );
		}

		if ( ! current_user_can( Friends::REQUIRED_ROLE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'friends' ) );
		}

		$friends = new WP_User_Query( array( 'role__in' => array( 'friend', 'restricted_friend', 'friend_request', 'subscription' ) ) );
		$feed    = $this->friends->feed;

		include apply_filters( 'friends_template_path', 'admin/opml.php' );
		exit;
	}

	/**
	 * Configure feed downloading options
	 *
	 * @param  SimplePie $feed The SimplePie object.
	 * @param  string    $url  The URL to fetch.
	 */
	public function wp_feed_options( $feed, $url ) {
		$feed->useragent .= ' Friends/' . Friends::VERSION;
	}

	/**
	 * SimplePie autoloader
	 *
	 * @param  string $class The SimplePie class name.
	 */
	public function wp_simplepie_autoload( $class ) {
		if ( 0 !== strpos( $class, 'SimplePie_' ) ) {
			return;
		}

		$file = __DIR__ . '/lib/' . str_replace( '_', '/', $class ) . '.php';
		include( $file );
	}

	/**
	 * Wrapper around fetch_feed that uses the bundled version
	 *
	 * @param  string $url The feed URL.
	 * @return object The parsed feed.
	 */
	public function fetch_feed( $url ) {
		if ( ! class_exists( 'SimplePie', false ) ) {
			spl_autoload_register( array( $this, 'wp_simplepie_autoload' ) );

			require_once __DIR__ . '/lib/SimplePie.php';
		}

		return fetch_feed( $url );
	}

	/**
	 * Modify the main query for the friends feed
	 *
	 * @param  WP_Query $query The main query.
	 * @return WP_Query The modified main query.
	 */
	public function private_feed_query( WP_Query $query ) {
		if ( ! $this->friends->access_control->feed_is_authenticated() ) {
			return $query;
		}

		$friend_user = $this->friends->access_control->get_authenticated_feed_user();
		if ( ! $query->is_admin && $query->is_feed && ! $friend_user->has_cap( 'restricted_friend' ) ) {
			echo 'private';
			$query->set( 'post_status', array( 'publish', 'private' ) );
		}

		return $query;
	}

	/**
	 * Retrieve new friend's posts after changing roles
	 *
	 * @param  int    $user_id   The user id.
	 * @param  string $new_role  The new role.
	 * @param  string $old_roles The old roles.
	 */
	public function retrieve_new_friends_posts( $user_id, $new_role, $old_roles ) {
		if ( ( 'friend' === $new_role || 'restricted_friend' === $new_role ) && apply_filters( 'friends_immediately_fetch_feed', true ) ) {
			update_user_option( $user_id, 'friends_new_friend', true );
			$this->retrieve_friend_posts( new WP_User( $user_id ) );
		}
	}

	/**
	 * More generic version of the native url_to_postid()
	 *
	 * @param string $url       Permalink to check.
	 * @param int    $author_id The id of the author.
	 * @return int Post ID, or 0 on failure.
	 */
	function url_to_postid( $url, $author_id ) {
		global $wpdb;

		$post_id = $wpdb->get_var( $wpdb->prepare( 'SELECT ID from ' . $wpdb->posts . ' WHERE guid IN (%s, %s) AND post_author = %d LIMIT 1', $url, esc_attr( $url ), $author_id ) );
		return $post_id;
	}
}
