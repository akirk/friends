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
	private static $references;

	public function __construct() {
		add_action( 'before_delete_post', array( $this, 'before_delete_post' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'after_delete_post' ) );
		add_filter( 'activitypub_remote_actor_is_in_use', array( self::class, 'protect_actor' ), 10, 2 );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( self::class, 'invalidate_references' ), 10, 3 );
		}
		add_action( 'admin_init', array( self::class, 'start_migration' ) );
		add_action( 'friends_nominate_activitypub_actors_batch', array( self::class, 'migration_batch' ) );
	}

	public static function start_migration() {
		if ( get_option( 'friends_ap_actor_nomination_completed' ) || ! is_callable( array( '\\Activitypub\\Collection\\Remote_Actors', 'mark_for_cleanup' ) ) ) {
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
		if ( ! empty( $meta['attributedTo']['ap_actor_id'] ) ) {
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
	 * Protect actors used by Friends feeds or cached posts, including trashed posts.
	 *
	 * @param bool     $in_use Whether another integration needs this actor.
	 * @param \WP_Post $actor  Remote actor post.
	 * @return bool Whether Friends still needs the actor.
	 */
	public static function protect_actor( $in_use, $actor ) {
		if ( $in_use || ! is_wp_error( User_Feed::get_by_ap_actor_id( $actor->ID ) ) || ! is_wp_error( User_Feed::get_by_url( $actor->guid ) ) ) {
			return true;
		}

		if ( null === self::$references ) {
			self::$references = array();
			$page = 1;
			do {
				$query = new \WP_Query(
					array(
						'post_type'      => Friends::CPT,
						'post_status'    => array_values( get_post_stati() ),
						'posts_per_page' => 100,
						'paged'          => $page++,
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'no_found_rows'  => true,
					)
				);
				foreach ( $query->posts as $post ) {
					$meta = get_post_meta( $post->ID, Feed_Parser_ActivityPub::SLUG, true );
					$author = isset( $meta['attributedTo'] ) ? $meta['attributedTo'] : array();
					if ( ! empty( $author['ap_actor_id'] ) ) {
						self::$references[ 'id:' . (int) $author['ap_actor_id'] ] = true;
					}
					if ( ! empty( $author['id'] ) ) {
						self::$references[ 'uri:' . $author['id'] ] = true;
					}
				}
				$count = count( $query->posts );
			} while ( 100 === $count );
		}
		return isset( self::$references[ 'id:' . $actor->ID ] ) || isset( self::$references[ 'uri:' . $actor->guid ] );
	}

	/**
	 * Invalidate the request-local reference index when cached author data changes.
	 *
	 * @param int|int[] $meta_id Metadata ID or IDs.
	 * @param int       $post_id Post ID.
	 * @param string    $key Metadata key.
	 */
	public static function invalidate_references( $meta_id, $post_id, $key ) {
		if ( Feed_Parser_ActivityPub::SLUG === $key ) {
			self::$references = null;
		}
	}

	/**
	 * Delegate candidate nomination to ActivityPub when the proposed API exists.
	 *
	 * @param int $actor_id Remote actor ID.
	 * @return bool Whether the actor was nominated.
	 */
	public static function nominate( $actor_id ) {
		if ( ! is_callable( array( '\\Activitypub\\Collection\\Remote_Actors', 'mark_for_cleanup' ) ) ) {
			return false;
		}
		return \Activitypub\Collection\Remote_Actors::mark_for_cleanup( $actor_id );
	}
}
