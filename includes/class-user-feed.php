<?php
/**
 * Friends Feed URL
 *
 * This contains the functions for managing feed URLs.
 *
 * @package Friends
 */

namespace Friends;

use WP_Error;

/**
 * This is the class for the feed URLs part of the Friends Plugin.
 *
 * @since 1.0
 *
 * @package Friends
 * @author Alex Kirk
 */
class User_Feed {
	const TAXONOMY = 'friend-user-feed';
	const POST_TAXONOMY = 'friend-post-feed';
	const INTERVAL_BACKTRACK = 600;

	/**
	 * Contains a reference to the \WP_Term for the feed.
	 *
	 * @var \WP_Term
	 */
	private $term;

	/**
	 * Contains a reference to the associated User.
	 *
	 * @var User
	 */
	private $friend_user;

	/**
	 * Constructor
	 *
	 * @param \WP_Term  $term        The WordPress term of the feed taxonomy.
	 * @param User|null $friend_user Optionally the associated User, if available.
	 */
	public function __construct( \WP_Term $term, ?User $friend_user = null ) {
		$this->term = $term;
		$this->friend_user = $friend_user;
	}

	/**
	 * The string representation of the term = The URL.
	 *
	 * @return string Term name = URL.
	 */
	public function __toString() {
		return $this->term->name;
	}

	/**
	 * Gets the identifier.
	 *
	 * @return     int  The identifier.
	 */
	public function get_id() {
		return $this->term->term_id;
	}

	/**
	 * Gets the URL (= the term name).
	 *
	 * @return string The URL (= the term name).
	 */
	public function get_url() {
		return $this->term->name;
	}

	/**
	 * Get the private URL of the friend (= append authentication).
	 *
	 * @param      int $validity  The validity in seconds.
	 *
	 * @return     string  The (extended) URL.
	 */
	public function get_private_url( $validity = 3600 ) {
		$feed_url = $this->get_url();
		$friend_user = $this->get_friend_user();

		if ( $friend_user && $friend_user instanceof User && $friend_user->is_friend_url( $feed_url ) && ( friends::has_required_privileges() || wp_doing_cron() ) ) {
			$friends = Friends::get_instance();
			$feed_url = $friends->access_control->append_auth( $feed_url, $friend_user, $validity );
		}

		return apply_filters( 'friends_friend_private_feed_url', $feed_url, $friend_user );
	}

	/**
	 * Get the local feed URL. Dysfunctional at the moment.
	 *
	 * @return string The local feed URL.
	 */
	public function get_local_url() {
		$friend_user = $this->get_friend_user();
		return home_url() . '/friends/' . $friend_user->user_login . '/feed/';
	}

	/**
	 * Get the local HTML URL. This would be the URL where to see the friends post on the friends page.
	 * Dysfunctional at the moment.
	 *
	 * @return string The local HTML URL.
	 */
	public function get_local_html_url() {
		$friend_user = $this->get_friend_user();
		return $friend_user->get_local_friends_page_url();
	}

	/**
	 * Gets the friend user associated wit the term.
	 *
	 * @return User|null The associated user.
	 */
	public function get_friend_user() {
		if ( empty( $this->friend_user ) ) {
			$users = $this->get_all_friend_users();
			$this->friend_user = reset( $users );
		}

		return $this->friend_user;
	}

	/**
	 * Gets the friend users associated wit the term.
	 *
	 * @return array(User) The associated users.
	 */
	public function get_all_friend_users() {
		$users = array();
		$user_term_ids = get_objects_in_term( $this->term->term_id, self::TAXONOMY );
		foreach ( $user_term_ids as $user_term_id ) {
			$user = User::get_user_by_id( $user_term_id );
			if ( $user ) {
				$feeds = $user->get_feeds();
				if ( isset( $feeds[ $this->term->term_id ] ) ) {
					$users[] = $user;
					continue;
				}
			}
			$user = User::get_user_by_id( 1e10 + $user_term_id );
			if ( $user ) {
				$feeds = $user->get_feeds();
				if ( isset( $feeds[ $this->term->term_id ] ) ) {
					$users[] = $user;
					continue;
				}
			}
		}
		return $users;
	}

	/**
	 * Whether the feed is active (=subscribed).
	 *
	 * @return bool Feed is active if true.
	 */
	public function get_active() {
		return self::validate_active( get_metadata( 'term', $this->term->term_id, 'active', true ) );
	}

	/**
	 * The post format to which the feed items should be imported.
	 *
	 * @return string The post format.
	 */
	public function get_post_format() {
		return self::validate_post_format( get_metadata( 'term', $this->term->term_id, 'post-format', true ) );
	}

	/**
	 * The parser to be used to fetch and parse the feed.
	 *
	 * @return string The parser slug.
	 */
	public function get_parser() {
		return self::validate_parser( get_metadata( 'term', $this->term->term_id, 'parser', true ) );
	}

	/**
	 * The (expected) mime-type of the feed.
	 *
	 * @return string The mime-type.
	 */
	public function get_mime_type() {
		return self::validate_mime_type( get_metadata( 'term', $this->term->term_id, 'mime-type', true ) );
	}

	/**
	 * The title of the feed.
	 *
	 * @return string The feed title.
	 */
	public function get_title() {
		return self::validate_title( get_metadata( 'term', $this->term->term_id, 'title', true ) );
	}

	/**
	 * A log entry of the last retrieval of the feed.
	 *
	 * @return string The log line.
	 */
	public function get_last_log() {
		return self::validate_last_log( get_metadata( 'term', $this->term->term_id, 'last-log', true ) );
	}

	/**
	 * Whether the feed is active.
	 *
	 * @return boolean Is the feed to be fetched?
	 */
	public function is_active() {
		return self::validate_active( get_metadata( 'term', $this->term->term_id, 'active', true ) );
	}

	/**
	 * The interval for calculating the next poll date.
	 *
	 * @return int The interval.
	 */
	public function get_interval() {
		return self::validate_interval( get_metadata( 'term', $this->term->term_id, 'interval', true ) );
	}

	/**
	 * The modifiert for the next interval.
	 *
	 * Examples:
	 * - interval 3600, modifier: 100. intervals: 3600, 3600, 3600, 3600
	 * - interval 3600, modifier: 110. intervals: 3600, 3960, 4356, 4792
	 * - interval 3600, modifier: 200. intervals: 3600, 7200, 14400, 28800
	 *
	 * @return int The modifier.
	 */
	public function get_modifier() {
		return self::validate_modifier( get_metadata( 'term', $this->term->term_id, 'modifier', true ) );
	}

	/**
	 * The datetime when the feed should be polled next.
	 *
	 * @return string The next poll date.
	 */
	public function get_next_poll() {
		return self::validate_poll_date( get_metadata( 'term', $this->term->term_id, 'next-poll', true ) );
	}

	/**
	 * Whether the feed poll has been started within the last 60 seconds.
	 *
	 * @return bool Is the feed currently being polled?
	 */
	public function can_be_polled_now() {
		$polling_now = get_metadata( 'term', $this->term->term_id, 'last-poll', true ) > gmdate( 'Y-m-d H:i:s', time() - 60 );
		// Explicitly use time() to allow mocking it inside the namespace.
		$due = gmdate( 'Y-m-d H:i:s', time() ) >= $this->get_next_poll();
		return $due && ! $polling_now;
	}

	public function set_polling_now() {
		// Explicitly use time() to allow mocking it inside the namespace.
		$this->update_metadata( 'last-poll', gmdate( 'Y-m-d H:i:s', time() ) );
	}

	public function was_polled() {
		$interval = $this->get_interval();
		$percent = $this->get_modifier();

		$updated_interval = $interval * $percent / 100;
		if ( $interval !== $updated_interval ) {
			$this->update_metadata( 'interval', $updated_interval );
		}
		// Set a time 5 minutes earlier so that an hourly job that takes longer gets a chance to refresh it.
		return $this->update_metadata( 'next-poll', gmdate( 'Y-m-d H:i:s', time() + $interval - self::INTERVAL_BACKTRACK ) );
	}


	/**
	 * Validates the post format against defined post formats.
	 *
	 * @param  string $post_format The post format to be validated.
	 * @return string              A validated post format.
	 */
	public static function validate_post_format( $post_format ) {
		$post_formats = get_post_format_strings();
		$post_formats['autodetect'] = true;
		if ( isset( $post_formats[ $post_format ] ) ) {
			return $post_format;
		}
		$keys = array_keys( $post_formats );
		return reset( $keys );
	}

	/**
	 * Validates the parser against defined parsers.
	 *
	 * @param  string $parser The parser to be validated.
	 * @return string         A validated parser.
	 */
	public static function validate_parser( $parser ) {
		$friends = Friends::get_instance();
		$parsers = $friends->feed->get_registered_parsers();
		if ( isset( $parsers[ $parser ] ) ) {
			return $parser;
		}
		// We're lax with parsers to allow deactivating parser plugins without deleting this information.
		return $parser;
	}

	/**
	 * Validates the mime-type.
	 *
	 * @param  string $mime_type The mime-type to be validated.
	 * @return string            A validated mime-type.
	 */
	public static function validate_mime_type( $mime_type ) {
		return substr( preg_replace( '/[^a-z0-9\/_+-]/', '', $mime_type ), 0, 100 );
	}

	/**
	 * Validates the title.
	 *
	 * @param  string $title The title to be validated.
	 * @return string        A validated title.
	 */
	public static function validate_title( $title ) {
		return substr( $title, 0, 100 );
	}

	/**
	 * Validates the last log line.
	 *
	 * @param  string $last_log The log line to be validated.
	 * @return string           A validated log line.
	 */
	public static function validate_last_log( $last_log ) {
		return substr( $last_log, 0, 1000 );
	}

	/**
	 * Validates the polling interval.
	 *
	 * @param  string $interval The interval to be validated.
	 * @return int           A validated interval.
	 */
	public static function validate_interval( $interval ) {
		if ( $interval > WEEK_IN_SECONDS ) {
			return WEEK_IN_SECONDS;
		}
		if ( $interval < HOUR_IN_SECONDS ) {
			return HOUR_IN_SECONDS;
		}
		return intval( $interval );
	}

	/**
	 * Validates the interval percentage modifier.
	 *
	 * @param  string $percentage The percentage modifier.
	 * @return int           A validated percentage modifier.
	 */
	public static function validate_modifier( $percentage ) {
		if ( $percentage > 500 ) {
			return 500;
		}
		if ( $percentage < 100 ) {
			return 100;
		}
		return intval( $percentage );
	}

	/**
	 * Validates a poll date.
	 *
	 * @param  string $poll_date The poll date to be validated.
	 * @return string           A validated poll date.
	 */
	public static function validate_poll_date( $poll_date ) {
		if ( ! preg_match( '/^2\d{3}-[01]\d-[0123]\d [012]\d:[0-5]\d:[0-5]\d$/', $poll_date ) ) {
			// Explicitly use time() to allow mocking it inside the namespace.
			return gmdate( 'Y-m-d H:i:s', time() );
		}
		return $poll_date;
	}

	/**
	 * Validates the active attribute.
	 *
	 * @param  string $active The active value to be validated.
	 * @return string         A validated active value.
	 */
	public static function validate_active( $active ) {
		return (bool) $active;
	}

	/**
	 * Registers the taxonomy
	 */
	public static function register_taxonomy() {
		$args = array(
			'labels'            => array(
				'name'          => _x( 'Posts from Feed', 'taxonomy general name', 'friends' ),
				'singular_name' => _x( 'Post from Feed', 'taxonomy singular name', 'friends' ),
				'menu_name'     => __( 'Post from Feed', 'friends' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => false,
			'public'            => false,
		);
		register_taxonomy( self::POST_TAXONOMY, 'post', $args );
		register_taxonomy_for_object_type( self::POST_TAXONOMY, 'post' );

		$args = array(
			'labels'            => array(
				'name'          => _x( 'Feed URL', 'taxonomy general name', 'friends' ),
				'singular_name' => _x( 'Feed URL', 'taxonomy singular name', 'friends' ),
				'menu_name'     => __( 'Feed URL', 'friends' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => false,
			'public'            => false,
		);
		register_taxonomy( self::TAXONOMY, 'user', $args );
		register_taxonomy_for_object_type( self::TAXONOMY, 'user' );

		register_term_meta(
			self::TAXONOMY,
			'post-format',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_post_format' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'mime-type',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_mime_type' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'title',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_title' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'last-log',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_last_log' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'active',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_active' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'parser',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_parser' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'interval',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_interval' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'modifier',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_modifier' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'next-poll',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_poll_date' ),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'last-poll',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'validate_poll_date' ),
			)
		);

		do_action( 'friends_after_register_feed_taxonomy' );
	}

	public function activate() {
		if ( metadata_exists( 'term', $this->term->term_id, 'active' ) ) {
			update_metadata( 'term', $this->term->term_id, 'active', true );
		} else {
			add_metadata( 'term', $this->term->term_id, 'active', true, true );
		}
		do_action( 'friends_user_feed_activated', $this );
	}

	public function deactivate() {
		delete_metadata( 'term', $this->term->term_id, 'active' );
		do_action( 'friends_user_feed_deactivated', $this );
	}

	/**
	 * Delete this feed.
	 */
	public function delete() {
		wp_delete_term( $this->term->term_id, self::TAXONOMY );
	}

	/**
	 * Saves multiple feeds for a user.
	 *
	 * See save() for possible options.
	 *
	 * @param      User  $friend_user  The associated user.
	 * @param      array $feeds        The feeds in the format array( url => options ).
	 *
	 * @return     array      Array of the newly created terms.
	 */
	public static function save_multiple( User $friend_user, array $feeds ) {
		$all_urls = array();
		foreach ( wp_get_object_terms( $friend_user->ID, self::TAXONOMY ) as $term ) {
			$all_urls[ $term->name ] = $term->term_id;
		}

		$term_ids = wp_set_object_terms( $friend_user->ID, array_keys( array_merge( $all_urls, $feeds ) ), self::TAXONOMY );
		if ( is_wp_error( $term_ids ) ) {
			return $term_ids;
		}

		foreach ( wp_get_object_terms( $friend_user->ID, self::TAXONOMY ) as $term ) {
			$all_urls[ $term->name ] = $term->term_id;
		}

		foreach ( $feeds as $url => $options ) {
			if ( ! isset( $all_urls[ $url ] ) ) {
				continue;
			}
			$term_id = $all_urls[ $url ];
			foreach ( $options as $key => $value ) {
				if ( in_array( $key, array( 'active', 'parser', 'post-format', 'mime-type', 'title' ) ) ) {
					if ( metadata_exists( 'term', $term_id, $key ) ) {
						update_metadata( 'term', $term_id, $key, $value );
					} else {
						add_metadata( 'term', $term_id, $key, $value, true );
					}
				}
			}
		}
		return $term_ids;
	}

	/**
	 * Saves a new feed as a term for the user.
	 *
	 * @param  User   $friend_user The user to be associated.
	 * @param  string $url         The feed URL.
	 * @param  array  $args        Further parameters. Possibly array keys: active, parser, post_format, mime_type, title.
	 * @return User_Feed                  A newly created User_Feed.
	 */
	public static function save( User $friend_user, $url, $args = array() ) {
		$all_urls = $friend_user->save_feeds(
			array(
				$url => $args,
			)
		);

		if ( is_wp_error( $all_urls ) ) {
			return $all_urls;
		}

		if ( ! is_array( $all_urls ) || ! isset( $all_urls[ $url ] ) ) {
			return new \WP_Error( 'cound-not-save-feed' );
		}

		$term_id = $all_urls[ $url ];

		$term = get_term( $term_id );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		return new self( $term, $friend_user );
	}

	/**
	 * Generic function for getting User_Feed metadata.
	 *
	 * @param      string $key    The key.
	 */
	public function get_metadata( $key ) {
		if ( metadata_exists( 'term', $this->term->term_id, $key ) ) {
			return get_metadata( 'term', $this->term->term_id, $key, true );
		}
		return null;
	}

	/**
	 * Generic function for updating User_Feed metadata.
	 *
	 * @param      string $key    The key.
	 * @param      string $value  The value.
	 */
	public function update_metadata( $key, $value ) {
		if ( metadata_exists( 'term', $this->term->term_id, $key ) ) {
			return update_metadata( 'term', $this->term->term_id, $key, $value );
		}
		return add_metadata( 'term', $this->term->term_id, $key, $value, true );
	}

	/**
	 * Generic function for deleting User_Feed metadata.
	 *
	 * @param      string $key    The key.
	 */
	public function delete_metadata( $key ) {
		if ( metadata_exists( 'term', $this->term->term_id, $key ) ) {
			return delete_metadata( 'term', $this->term->term_id, $key );
		}
	}

	/**
	 * Update the last-log metadata.
	 *
	 * @param      string $value  The value to log.
	 *
	 * @return     int|false The inserted term id.
	 */
	public function update_last_log( $value ) {
		// Explicitly use time() to allow mocking it inside the namespace.
		return $this->update_metadata( 'last-log', gmdate( 'Y-m-d H:i:s', time() ) . ': ' . $value );
	}

	/**
	 * Get the feed with the specific id.
	 *
	 * @param      int $id     The feed id.
	 *
	 * @return     object|\WP_Error   A User_Feed object.
	 */
	public static function get_by_id( $id ) {
		$term = get_term( $id, self::TAXONOMY );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		return new self( $term );
	}

	/**
	 * Get the feed with a specific URL.
	 *
	 * @param      string $url     The feed URL.
	 *
	 * @return     object|\WP_Error   A User_Feed object.
	 */
	public static function get_by_url( $url ) {
		$term_query = new \WP_Term_Query(
			array(
				'taxonomy' => self::TAXONOMY,
				'slug'     => $url,
			)
		);
		foreach ( $term_query->get_terms() as $term ) {
			return new self( $term );
		}

		return new \WP_Error( 'term_not_found' );
	}

	public static function get_all_users() {
		$term_query = new \WP_Term_Query(
			array(
				'taxonomy' => self::TAXONOMY,
			)
		);
		$users = array();
		foreach ( $term_query->get_terms() as $term ) {
			$feed = new self( $term );
			$friend_user = $feed->get_friend_user();
			$users[ $friend_user->ID ] = $friend_user;
		}

		return $users;
	}

	/**
	 * Get all feeds due.
	 *
	 * @param      bool $ignore_due_date     Whether to get also undue feeds.
	 *
	 * @return     array   An array of User_Feed objects.
	 */
	public static function get_all_due( $ignore_due_date = false ) {
		$term_query = new \WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'meta_key'   => 'active', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => true, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$due_feeds = array();
		foreach ( $term_query->get_terms() as $term ) {
			$feed = new self( $term );
			// Explicitly use time() to allow mocking it inside the namespace.
			if ( $ignore_due_date || gmdate( 'Y-m-d H:i:s', time() ) >= $feed->get_next_poll() ) {
				$due_feeds[] = $feed;
			}
		}

		// Let's poll the oldest feeds first.
		usort(
			$due_feeds,
			function ( $a, $b ) {
				return strcmp( $a->get_next_poll(), $b->get_next_poll() );
			}
		);

		return $due_feeds;
	}

	/**
	 * Get all feeds for a parser
	 *
	 * @param      string $parser     The feed parser.
	 *
	 * @return     array   A list of user feeds.
	 */
	public static function get_by_parser( $parser ) {
		$term_query = new \WP_Term_Query(
			array(
				'taxonomy'   => self::TAXONOMY,
				'meta_key'   => 'parser', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $parser, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$feeds = array();
		foreach ( $term_query->get_terms() as $term ) {
			$feeds[] = new self( $term );
		}

		return $feeds;
	}

	/**
	 * Get the parser for a post
	 *
	 * @param      int $post_id     The post id.
	 *
	 * @return     string The feed parser.
	 */
	public static function get_parser_for_post_id( $post_id ) {
		$user_feeds = wp_get_object_terms( $post_id, User_Feed::TAXONOMY );
		if ( empty( $user_feeds ) ) {
			// We used to save the parser as post meta.
			return get_post_meta( $post_id, 'parser', true );
		}

		$user_feed = reset( $user_feeds );
		$user_feed = new self( $user_feed );
		return $user_feed->get_parser();
	}
}
