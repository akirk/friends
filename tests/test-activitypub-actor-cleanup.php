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
		update_option( 'friends_ap_actor_uris_backfilled', true, false );

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
	}

	public function test_retained_author_is_recovered_by_uri_after_cache_deletion() {
		$actor_id = $this->create_actor();
		$post_id = $this->create_cached_post( $actor_id );
		$meta = get_post_meta( $post_id, 'activitypub', true );
		$this->assertSame( get_post( $actor_id )->guid, $meta['attributedTo']['id'] );
		$uri = $meta['attributedTo']['id'];
		wp_delete_post( $actor_id, true );
		$replacement = self::factory()->post->create( array( 'post_type' => 'ap_actor', 'post_status' => 'publish', 'guid' => $uri ) );
		$this->assertNotSame( $actor_id, $replacement );
		$this->assertSame( $replacement, Feed_Parser_ActivityPub::resolve_actor_id( $meta['attributedTo'] ) );
		$this->assertSame( $uri, Feed_Parser_ActivityPub::get_actor_url_from_attributed_to( $meta['attributedTo'] ) );
		wp_delete_post( $post_id, true );
	}

	public function test_missing_actor_is_fetched_and_cached_again() {
		if ( ! method_exists( Feed_Parser_ActivityPub::class, 'resolve_actor_id' ) ) {
			$this->markTestSkipped( 'URI recovery is not available on the base branch.' );
		}
		$uri = 'https://example.com/recoverable-author';
		$old_id = self::factory()->post->create( array( 'post_type' => 'ap_actor', 'guid' => $uri ) );
		wp_delete_post( $old_id, true );
		$respond = function ( $pre, $args, $url ) use ( $uri ) {
			if ( $uri !== $url ) {
				return new \WP_Error( 'unexpected_request', 'No other remote resource is available.' );
			}
			return array(
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'headers' => array( 'content-type' => 'application/activity+json' ),
				'body' => wp_json_encode( array( 'id' => $uri, 'type' => 'Person', 'name' => 'Recovered author', 'preferredUsername' => 'recovered', 'inbox' => $uri . '/inbox' ) ),
			);
		};
		add_filter( 'pre_http_request', $respond, 10, 3 );
		try {
			$author = array( 'ap_actor_id' => $old_id, 'id' => $uri );
			$recovered = Feed_Parser_ActivityPub::resolve_actor_id( $author );
			$this->assertGreaterThan( 0, $recovered );
			$this->assertNotSame( $old_id, $recovered );
			$this->assertSame( $uri, get_post( $recovered )->guid );
			$this->assertSame( 'Recovered author', Feed_Parser_ActivityPub::get_actor_metadata_from_attributed_to( $author )['name'] );
		} finally {
			remove_filter( 'pre_http_request', $respond, 10 );
		}
	}

	public function test_failed_refetch_preserves_uri_and_inline_author_fallback() {
		if ( ! method_exists( Feed_Parser_ActivityPub::class, 'resolve_actor_id' ) ) {
			$this->markTestSkipped( 'URI recovery is not available on the base branch.' );
		}
		$uri = 'https://example.com/unavailable-author';
		$block = function () {
			return new \WP_Error( 'unavailable', 'Actor is unavailable.' );
		};
		add_filter( 'pre_http_request', $block );
		try {
			$author = array( 'ap_actor_id' => 999999, 'id' => $uri, 'name' => 'Cached author' );
			$this->assertSame( 0, Feed_Parser_ActivityPub::resolve_actor_id( $author ) );
			$metadata = Feed_Parser_ActivityPub::get_actor_metadata_from_attributed_to( $author );
			$this->assertSame( $uri, $metadata['url'] );
			$this->assertSame( 'Cached author', $metadata['name'] );
		} finally {
			remove_filter( 'pre_http_request', $block );
		}
	}

	public function test_subscription_is_not_cache_only() {
		if ( ! is_callable( array( '\\Activitypub\\Collection\\Remote_Actors', 'set_cache_only' ) ) ) {
			$this->markTestSkipped( 'Requires the companion ActivityPub draft.' );
		}
		$actor_id = $this->create_actor();
		\Activitypub\Collection\Remote_Actors::set_cache_only( $actor_id, true );
		$term = wp_insert_term( 'Retained actor feed', User_Feed::TAXONOMY );
		$this->assertNotWPError( $term );
		User_Feed::get_by_id( $term['term_id'] )->set_ap_actor_id( $actor_id );
		$this->assertFalse( (bool) get_post_meta( $actor_id, '_activitypub_cache_only', true ) );
		$this->assertFalse( \Activitypub\Collection\Remote_Actors::set_cache_only( $actor_id, true ) );
	}

	public function test_missing_api_leaves_migration_pending() {
		if ( is_callable( array( '\Activitypub\Collection\Remote_Actors', 'set_cache_only' ) ) ) {
			$this->markTestSkipped( 'This case covers ActivityPub without the proposed API.' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		delete_option( 'friends_ap_actor_classification_completed' );
		$result = Migration::run_migration( 'classify_activitypub_actors' );
		$this->assertFalse( $result['success'] );
		$this->assertFalse( (bool) get_option( 'friends_ap_actor_classification_completed' ) );
	}

	public function test_migration_backfills_uris_then_classifies_actors() {
		if ( ! is_callable( array( '\Activitypub\Collection\Remote_Actors', 'set_cache_only' ) ) ) {
			$this->markTestSkipped( 'Requires the companion ActivityPub draft PR.' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		$first  = $this->create_actor();
		$second = $this->create_actor();
		$sanitize = array( Feed_Parser_ActivityPub::class, 'preserve_actor_uri' );
		$hook = 'sanitize_post_meta_activitypub_for_' . Friends::CPT;
		remove_filter( $hook, $sanitize );
		try {
			$post_id = $this->create_cached_post( $first );
		} finally {
			add_filter( $hook, $sanitize, 10, 4 );
		}
		$this->assertArrayNotHasKey( 'id', get_post_meta( $post_id, 'activitypub', true )['attributedTo'] );
		delete_option( 'friends_ap_actor_uris_backfilled' );
		delete_option( 'friends_ap_actor_classification_completed' );
		delete_option( 'friends_ap_actor_classification_in_progress' );
		$batch_size = function () {
			return 1;
		};
		add_filter( 'friends_ap_actor_classification_batch_size', $batch_size );
		try {
			$result = Migration::run_migration( 'classify_activitypub_actors' );
			$this->assertTrue( $result['success'] );
			for ( $i = 0; $i < 100 && get_option( 'friends_ap_actor_classification_in_progress' ); ++$i ) {
				Migration::classify_activitypub_actors_batch();
			}
			$this->assertTrue( (bool) get_option( 'friends_ap_actor_classification_completed' ) );
			$this->assertSame( get_post( $first )->guid, get_post_meta( $post_id, 'activitypub', true )['attributedTo']['id'] );
			foreach ( array( $first, $second ) as $actor_id ) {
				$this->assertNotNull( get_post( $actor_id ) );
				$this->assertTrue( (bool) get_post_meta( $actor_id, '_activitypub_cache_only', true ) );
			}
		} finally {
			remove_filter( 'friends_ap_actor_classification_batch_size', $batch_size );
			wp_clear_scheduled_hook( 'friends_classify_activitypub_actors_batch' );
		}
	}
}
