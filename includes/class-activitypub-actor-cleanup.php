<?php
/**
 * Friends integration with ActivityPub's proposed actor cleanup API.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Nominate cache entries and protect references owned by Friends.
 *
 * ActivityPub owns scheduling, cancellation on access, and deletion.
 */
class Activitypub_Actor_Cleanup {
	private $post_actors = array();
	private static $subscribed_actor_ids;
	private static $feed_slugs;

	public function __construct() {
		add_action( 'before_delete_post', array( $this, 'before_delete_post' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'after_delete_post' ) );
		add_filter( 'activitypub_remote_actor_is_in_use', array( self::class, 'protect_actor' ), 10, 2 );
		add_action( 'admin_init', array( self::class, 'start_migration' ) );
		add_action( 'friends_nominate_activitypub_actors_batch', array( self::class, 'migration_batch' ) );
	}

	public static function start_migration() {
		if ( ( get_option( 'friends_ap_actor_nomination_completed' ) && get_option( 'friends_ap_actor_uris_backfilled' ) ) || ! is_callable( array( '\\Activitypub\\Collection\\Remote_Actors', 'mark_for_cleanup' ) ) ) {
			return;
		}
		require_once __DIR__ . '/class-migration.php';
		Migration::nominate_activitypub_actors();
	}

	public static function migration_batch() {
		require_once __DIR__ . '/class-migration.php';
		Migration::nominate_activitypub_actors_batch();
	}

	/**
	 * Preserve the actor reference before WordPress deletes the post metadata.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post being deleted.
	 */
	public function before_delete_post( $post_id, $post ) {
		if ( Friends::CPT !== $post->post_type ) {
			return;
		}
		$meta = get_post_meta( $post_id, Feed_Parser_ActivityPub::SLUG, true );
		if ( ! empty( $meta['attributedTo']['ap_actor_id'] ) && 'ap_actor' === get_post_type( $meta['attributedTo']['ap_actor_id'] ) ) {
			$this->post_actors[ $post_id ] = (int) $meta['attributedTo']['ap_actor_id'];
		} elseif ( ! empty( $meta['attributedTo']['id'] ) && class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			$actor = \Activitypub\Collection\Remote_Actors::get_by_uri( $meta['attributedTo']['id'] );
			if ( ! is_wp_error( $actor ) ) {
				$this->post_actors[ $post_id ] = $actor->ID;
			}
		}
	}

	/**
	 * Nominate an actor after its cached post has been permanently deleted.
	 *
	 * @param int $post_id Deleted post ID.
	 */
	public function after_delete_post( $post_id ) {
		if ( ! isset( $this->post_actors[ $post_id ] ) ) {
			return;
		}
		$actor_id = $this->post_actors[ $post_id ];
		unset( $this->post_actors[ $post_id ] );
		self::nominate( $actor_id );
	}

	/**
	 * Protect subscriptions, and defer cleanup until legacy references have URIs.
	 *
	 * @param bool     $in_use Whether another integration needs the actor.
	 * @param \WP_Post $actor Stored actor.
	 * @return bool Whether the actor must be kept.
	 */
	public static function protect_actor( $in_use, $actor ) {
		if ( $in_use || ! get_option( 'friends_ap_actor_uris_backfilled' ) ) {
			return true;
		}
		if ( null === self::$subscribed_actor_ids ) {
			self::$subscribed_actor_ids = array_fill_keys(
				get_posts(
					array(
						'post_type'   => 'ap_actor',
						'post_status' => array_values( get_post_stati() ),
						'numberposts' => -1,
						'fields'      => 'ids',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'tax_query'   => array(
							array(
								'taxonomy' => User_Feed::TAXONOMY,
								'operator' => 'EXISTS',
							),
						),
					)
				),
				true
			);
			// Keep the existing URL fallback for feeds not linked to an actor yet.
			$slugs = get_terms(
				array(
					'taxonomy'   => User_Feed::TAXONOMY,
					'hide_empty' => false,
					'fields'     => 'slugs',
				)
			);
			self::$feed_slugs = is_wp_error( $slugs ) ? null : array_fill_keys( $slugs, true );
		}
		return null === self::$feed_slugs || isset( self::$subscribed_actor_ids[ $actor->ID ] )
			|| isset( self::$feed_slugs[ sanitize_title( $actor->guid ) ] );
	}

	/**
	 * Delegate candidate nomination to ActivityPub when the proposed API exists.
	 *
	 * @param int $actor_id Remote actor ID.
	 * @return bool Whether the actor was nominated.
	 */
	public static function nominate( $actor_id ) {
		if ( ! get_option( 'friends_ap_actor_uris_backfilled' ) || ! is_callable( array( '\\Activitypub\\Collection\\Remote_Actors', 'mark_for_cleanup' ) ) ) {
			return false;
		}
		return \Activitypub\Collection\Remote_Actors::mark_for_cleanup( $actor_id );
	}
}
