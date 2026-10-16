<?php
/**
 * ActivityPub actor cleanup regression tests.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Exercise actor cleanup with the existing ActivityPub API.
 */
class ActivityPubActorCleanupTest extends \WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		if ( ! is_callable( array( '\Activitypub\Collection\Remote_Actors', 'delete' ) ) ) {
			$this->markTestSkipped( 'ActivityPub is required.' );
		}
		Friends::get_instance()->feed->get_feed_parser( Feed_Parser_ActivityPub::SLUG );
	}

	private function create_actor() {
		return self::factory()->post->create(
			array(
				'post_type'   => 'ap_actor',
				'post_status' => 'publish',
			)
		);
	}

	private function create_cached_post( $actor_id ) {
		return self::factory()->post->create(
			array(
				'post_type'   => Friends::CPT,
				'post_status' => 'publish',
				'meta_input'  => array( 'activitypub' => array( 'attributedTo' => array( 'ap_actor_id' => $actor_id ) ) ),
			)
		);
	}

	public function test_actor_is_deleted_only_after_last_cached_post() {
		$actor_id = $this->create_actor();
		$first    = $this->create_cached_post( $actor_id );
		$second   = $this->create_cached_post( $actor_id );

		wp_delete_post( $first, true );
		$this->assertNotNull( get_post( $actor_id ) );
		wp_trash_post( $second );
		$this->assertNotNull( get_post( $actor_id ) );
		wp_delete_post( $second, true );
		$this->assertNull( get_post( $actor_id ) );
	}

	public function test_blog_relationships_and_interactions_protect_actor() {
		if ( ! method_exists( Feed_Parser_ActivityPub::class, 'delete_unused_actor' ) ) {
			$this->markTestSkipped( 'The cleanup API is not present on the base branch.' );
		}
		$actor_id = $this->create_actor();
		foreach ( array( '_activitypub_following', '_activitypub_followed_by', '_activitypub_followed_by_pending', '_activitypub_blocked_by' ) as $key ) {
			add_post_meta( $actor_id, $key, '0' );
			$this->assertFalse( Feed_Parser_ActivityPub::delete_unused_actor( $actor_id ) );
			delete_post_meta( $actor_id, $key );
		}
		$comment_id = self::factory()->comment->create();
		add_comment_meta( $comment_id, '_activitypub_remote_actor_id', $actor_id );
		$this->assertFalse( Feed_Parser_ActivityPub::delete_unused_actor( $actor_id ) );
		wp_trash_comment( $comment_id );
		$this->assertFalse( Feed_Parser_ActivityPub::delete_unused_actor( $actor_id ) );
		wp_delete_comment( $comment_id, true );
		$this->assertTrue( Feed_Parser_ActivityPub::delete_unused_actor( $actor_id ) );
	}

	public function test_string_ids_and_legacy_author_uris_protect_actor() {
		if ( ! method_exists( Feed_Parser_ActivityPub::class, 'delete_unused_actor' ) ) {
			$this->markTestSkipped( 'The cleanup API is not present on the base branch.' );
		}
		$actor_id = $this->create_actor();
		$post_id  = $this->create_cached_post( (string) $actor_id );
		$this->assertFalse( Feed_Parser_ActivityPub::delete_unused_actor( $actor_id ) );
		update_post_meta( $post_id, 'activitypub', array( 'attributedTo' => array( 'id' => get_post( $actor_id )->guid ) ) );
		$this->assertFalse( Feed_Parser_ActivityPub::delete_unused_actor( $actor_id ) );
		wp_delete_post( $post_id, true );
		$this->assertNull( get_post( $actor_id ) );
	}

	public function test_feed_relationship_protects_actor() {
		if ( ! method_exists( Feed_Parser_ActivityPub::class, 'delete_unused_actor' ) ) {
			$this->markTestSkipped( 'The cleanup API is not present on the base branch.' );
		}
		$actor_id = $this->create_actor();
		$term     = wp_insert_term( 'Retained actor feed', User_Feed::TAXONOMY );
		$this->assertNotWPError( $term );
		register_taxonomy_for_object_type( User_Feed::TAXONOMY, 'ap_actor' );
		wp_set_object_terms( $actor_id, $term['term_id'], User_Feed::TAXONOMY );
		$this->assertFalse( Feed_Parser_ActivityPub::delete_unused_actor( $actor_id ) );
	}

	public function test_migration_advances_past_protected_actors() {
		if ( ! method_exists( Feed_Parser_ActivityPub::class, 'delete_unused_actor' ) ) {
			$this->markTestSkipped( 'The cleanup API is not present on the base branch.' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		$protected = $this->create_actor();
		add_post_meta( $protected, '_activitypub_following', '0' );
		$orphan = $this->create_actor();
		delete_option( 'friends_unused_ap_actors_completed' );
		delete_option( 'friends_unused_ap_actors_in_progress' );
		$batch_size = function () {
			return 1;
		};
		add_filter( 'friends_unused_ap_actors_batch_size', $batch_size );
		try {
			$result = Migration::run_migration( 'cleanup_unused_activitypub_actors' );
			$this->assertTrue( $result['success'] );
			$this->assertFalse( (bool) get_option( 'friends_unused_ap_actors_completed' ) );
			for ( $i = 0; $i < 100 && get_option( 'friends_unused_ap_actors_in_progress' ); ++$i ) {
				Migration::cleanup_unused_activitypub_actors_batch();
			}
			$this->assertTrue( (bool) get_option( 'friends_unused_ap_actors_completed' ) );
			$this->assertNotNull( get_post( $protected ) );
			$this->assertNull( get_post( $orphan ) );
		} finally {
			remove_filter( 'friends_unused_ap_actors_batch_size', $batch_size );
			wp_clear_scheduled_hook( 'friends_cleanup_unused_activitypub_actors_batch' );
		}
	}
}
