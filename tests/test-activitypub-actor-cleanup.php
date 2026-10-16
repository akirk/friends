<?php
/**
 * ActivityPub actor cleanup integration tests.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Exercise nomination and protection through the proposed ActivityPub API.
 */
class ActivityPubActorCleanupTest extends \WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		if ( ! class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			$this->markTestSkipped( 'ActivityPub is required.' );
		}
		Friends::get_instance()->feed->get_feed_parser( Feed_Parser_ActivityPub::SLUG );
		if ( class_exists( Activitypub_Actor_Cleanup::class ) ) {
			Activitypub_Actor_Cleanup::invalidate_references( 0, 0, 'activitypub' );
		}
	}

	private function create_actor() {
		return self::factory()->post->create( array( 'post_type' => 'ap_actor', 'post_status' => 'publish' ) );
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

	public function test_deleting_cached_post_does_not_delete_actor_immediately() {
		$actor_id = $this->create_actor();
		$post_id  = $this->create_cached_post( $actor_id );
		wp_delete_post( $post_id, true );
		$this->assertNotNull( get_post( $actor_id ) );
		if ( is_callable( array( '\Activitypub\Collection\Remote_Actors', 'mark_for_cleanup' ) ) ) {
			$this->assertTrue( has_term( 'candidate', 'ap_actor_cleanup', $actor_id ) );
		}
	}

	public function test_retained_authors_are_protected_and_index_is_invalidated() {
		$actor_id = $this->create_actor();
		$actor    = get_post( $actor_id );
		$post_id  = $this->create_cached_post( (string) $actor_id );
		$this->assertTrue( apply_filters( 'activitypub_remote_actor_is_in_use', false, $actor ) );
		update_post_meta( $post_id, 'activitypub', array( 'attributedTo' => array( 'id' => $actor->guid ) ) );
		wp_trash_post( $post_id );
		$this->assertTrue( apply_filters( 'activitypub_remote_actor_is_in_use', false, $actor ) );
		wp_delete_post( $post_id, true );
		$this->assertFalse( apply_filters( 'activitypub_remote_actor_is_in_use', false, $actor ) );
	}

	public function test_feed_relationship_protects_actor() {
		$actor_id = $this->create_actor();
		$term     = wp_insert_term( 'Retained actor feed', User_Feed::TAXONOMY );
		$this->assertNotWPError( $term );
		register_taxonomy_for_object_type( User_Feed::TAXONOMY, 'ap_actor' );
		wp_set_object_terms( $actor_id, $term['term_id'], User_Feed::TAXONOMY );
		$this->assertTrue( apply_filters( 'activitypub_remote_actor_is_in_use', false, get_post( $actor_id ) ) );
	}

	public function test_missing_api_leaves_migration_pending() {
		if ( is_callable( array( '\Activitypub\Collection\Remote_Actors', 'mark_for_cleanup' ) ) ) {
			$this->markTestSkipped( 'This case covers ActivityPub without the proposed API.' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		delete_option( 'friends_ap_actor_nomination_completed' );
		$result = Migration::run_migration( 'nominate_activitypub_actors' );
		$this->assertFalse( $result['success'] );
		$this->assertFalse( (bool) get_option( 'friends_ap_actor_nomination_completed' ) );
	}

	public function test_migration_nominates_actors_without_deleting_them() {
		if ( ! is_callable( array( '\Activitypub\Collection\Remote_Actors', 'mark_for_cleanup' ) ) ) {
			$this->markTestSkipped( 'Requires the companion ActivityPub draft PR.' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		$first  = $this->create_actor();
		$second = $this->create_actor();
		delete_option( 'friends_ap_actor_nomination_completed' );
		delete_option( 'friends_ap_actor_nomination_in_progress' );
		$batch_size = function () {
			return 1;
		};
		add_filter( 'friends_ap_actor_nomination_batch_size', $batch_size );
		try {
			$result = Migration::run_migration( 'nominate_activitypub_actors' );
			$this->assertTrue( $result['success'] );
			for ( $i = 0; $i < 100 && get_option( 'friends_ap_actor_nomination_in_progress' ); ++$i ) {
				Migration::nominate_activitypub_actors_batch();
			}
			$this->assertTrue( (bool) get_option( 'friends_ap_actor_nomination_completed' ) );
			foreach ( array( $first, $second ) as $actor_id ) {
				$this->assertNotNull( get_post( $actor_id ) );
				$this->assertTrue( has_term( 'candidate', 'ap_actor_cleanup', $actor_id ) );
			}
		} finally {
			remove_filter( 'friends_ap_actor_nomination_batch_size', $batch_size );
			wp_clear_scheduled_hook( 'friends_nominate_activitypub_actors_batch' );
		}
	}
}
