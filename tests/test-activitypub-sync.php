<?php
/**
 * Class Test_ActivityPub_Sync
 *
 * Tests for the ActivityPub Sync functionality, specifically URL normalization
 * and matching between Friends feeds and ActivityPub actors.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Testable subclass of Admin_ActivityPub_Sync that exposes private methods.
 */
class Testable_Admin_ActivityPub_Sync extends Admin_ActivityPub_Sync {
	/**
	 * Expose build_actor_lookup_map for testing.
	 *
	 * @return array
	 */
	public function test_build_actor_lookup_map() {
		$reflection = new \ReflectionClass( $this );
		$method = $reflection->getMethod( 'build_actor_lookup_map' );
		$method->setAccessible( true );
		return $method->invoke( $this );
	}

	/**
	 * Expose normalize_actor_url for testing.
	 *
	 * @param string $url            The URL to normalize.
	 * @param array  $lookup_map     The lookup map.
	 * @param bool   $network_fallback Whether to fallback to network requests.
	 * @return string|null
	 */
	public function test_normalize_actor_url( $url, $lookup_map = null, $network_fallback = false ) {
		$reflection = new \ReflectionClass( $this );
		$method = $reflection->getMethod( 'normalize_actor_url' );
		$method->setAccessible( true );
		return $method->invoke( $this, $url, $lookup_map, $network_fallback );
	}

	/**
	 * Expose get_sync_status for testing.
	 *
	 * @param int $user_id The user ID.
	 * @return array
	 */
	public function test_get_sync_status( $user_id ) {
		$reflection = new \ReflectionClass( $this );
		$method = $reflection->getMethod( 'get_sync_status' );
		$method->setAccessible( true );
		return $method->invoke( $this, $user_id );
	}
}

/**
 * Test the ActivityPub Sync functionality.
 */
class ActivityPubSyncTest extends \WP_UnitTestCase {
	/**
	 * The sync admin instance.
	 *
	 * @var Testable_Admin_ActivityPub_Sync
	 */
	private $sync_admin;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! class_exists( '\Activitypub\Activitypub' ) ) {
			$this->markTestSkipped( 'The ActivityPub plugin is not loaded.' );
		}

		$this->sync_admin = new Testable_Admin_ActivityPub_Sync();

		// Clean up ap_actor posts from previous tests.
		$this->cleanup_ap_actors();
	}

	/**
	 * Clean up test fixtures.
	 */
	public function tear_down() {
		$this->cleanup_ap_actors();
		parent::tear_down();
	}

	/**
	 * Helper to clean up ap_actor posts.
	 */
	private function cleanup_ap_actors() {
		global $wpdb;

		// Clear object cache to ensure we get fresh data.
		wp_cache_flush();

		// Use direct query to ensure we catch all posts.
		$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ap_actor'"
		);

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		// Clear cache again after deletion.
		wp_cache_flush();
	}

	/**
	 * Helper to create an ap_actor post with specific data.
	 *
	 * @param string $canonical_url The canonical actor URL (stored in guid).
	 * @param string $acct          The webfinger acct (e.g., user@domain.com).
	 * @param string $friends_url   Optional Friends feed URL.
	 * @param string $name          Optional display name.
	 * @return int Post ID.
	 */
	private function create_ap_actor( $canonical_url, $acct = '', $friends_url = '', $name = 'Test Actor' ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'ap_actor',
				'post_title'  => $name,
				'post_status' => 'publish',
				'guid'        => $canonical_url,
			)
		);

		if ( ! empty( $acct ) ) {
			update_post_meta( $post_id, '_activitypub_acct', $acct );
		}

		if ( ! empty( $friends_url ) ) {
			update_post_meta( $post_id, '_friends_feed_url', $friends_url );
		}

		return $post_id;
	}

	/**
	 * Test that build_actor_lookup_map returns empty array when no actors exist.
	 */
	public function test_build_actor_lookup_map_empty() {
		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		$this->assertIsArray( $lookup );
		$this->assertEmpty( $lookup );
	}

	/**
	 * Test that build_actor_lookup_map includes canonical URL.
	 */
	public function test_build_actor_lookup_map_canonical_url() {
		$canonical_url = 'https://mastodon.social/users/testuser';
		$this->create_ap_actor( $canonical_url );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		$this->assertArrayHasKey( $canonical_url, $lookup );
		$this->assertEquals( $canonical_url, $lookup[ $canonical_url ] );
	}

	/**
	 * Test that build_actor_lookup_map includes webfinger acct variations.
	 */
	public function test_build_actor_lookup_map_webfinger_acct() {
		$canonical_url = 'https://mastodon.social/users/testuser';
		$acct = 'testuser@mastodon.social';
		$this->create_ap_actor( $canonical_url, $acct );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// Should map acct without @.
		$this->assertArrayHasKey( $acct, $lookup );
		$this->assertEquals( $canonical_url, $lookup[ $acct ] );

		// Should map acct with @.
		$this->assertArrayHasKey( '@' . $acct, $lookup );
		$this->assertEquals( $canonical_url, $lookup[ '@' . $acct ] );
	}

	/**
	 * Test that build_actor_lookup_map includes friends feed URL.
	 */
	public function test_build_actor_lookup_map_friends_url() {
		$canonical_url = 'https://mastodon.social/users/testuser';
		$friends_url = 'https://mastodon.social/@testuser';
		$this->create_ap_actor( $canonical_url, '', $friends_url );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		$this->assertArrayHasKey( $friends_url, $lookup );
		$this->assertEquals( $canonical_url, $lookup[ $friends_url ] );
	}

	/**
	 * Test that build_actor_lookup_map handles multiple actors.
	 */
	public function test_build_actor_lookup_map_multiple_actors() {
		$actors = array(
			array(
				'canonical' => 'https://mastodon.social/users/user1',
				'acct'      => 'user1@mastodon.social',
				'friends'   => 'https://mastodon.social/@user1',
			),
			array(
				'canonical' => 'https://pixelfed.social/users/user2',
				'acct'      => 'user2@pixelfed.social',
				'friends'   => 'https://pixelfed.social/user2',
			),
			array(
				'canonical' => 'https://example.org/users/user3',
				'acct'      => 'user3@example.org',
				'friends'   => '',
			),
		);

		foreach ( $actors as $actor ) {
			$this->create_ap_actor( $actor['canonical'], $actor['acct'], $actor['friends'] );
		}

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// Check all canonical URLs are mapped.
		foreach ( $actors as $actor ) {
			$this->assertArrayHasKey( $actor['canonical'], $lookup );
			$this->assertEquals( $actor['canonical'], $lookup[ $actor['canonical'] ] );

			// Check webfinger variations.
			$this->assertArrayHasKey( $actor['acct'], $lookup );
			$this->assertEquals( $actor['canonical'], $lookup[ $actor['acct'] ] );

			// Check friends URL if present.
			if ( ! empty( $actor['friends'] ) ) {
				$this->assertArrayHasKey( $actor['friends'], $lookup );
				$this->assertEquals( $actor['canonical'], $lookup[ $actor['friends'] ] );
			}
		}
	}

	/**
	 * Test normalize_actor_url returns null for empty input.
	 */
	public function test_normalize_actor_url_empty() {
		$result = $this->sync_admin->test_normalize_actor_url( '' );
		$this->assertNull( $result );

		$result = $this->sync_admin->test_normalize_actor_url( null );
		$this->assertNull( $result );
	}

	/**
	 * Test normalize_actor_url with exact match in lookup map.
	 */
	public function test_normalize_actor_url_exact_match() {
		$canonical_url = 'https://mastodon.social/users/testuser';
		$lookup = array(
			$canonical_url => $canonical_url,
		);

		$result = $this->sync_admin->test_normalize_actor_url( $canonical_url, $lookup );
		$this->assertEquals( $canonical_url, $result );
	}

	/**
	 * Test normalize_actor_url resolves friends URL to canonical.
	 */
	public function test_normalize_actor_url_friends_to_canonical() {
		$canonical_url = 'https://mastodon.social/users/testuser';
		$friends_url = 'https://mastodon.social/@testuser';
		$lookup = array(
			$canonical_url => $canonical_url,
			$friends_url   => $canonical_url,
		);

		$result = $this->sync_admin->test_normalize_actor_url( $friends_url, $lookup );
		$this->assertEquals( $canonical_url, $result );
	}

	/**
	 * Test normalize_actor_url resolves webfinger to canonical.
	 */
	public function test_normalize_actor_url_webfinger_to_canonical() {
		$canonical_url = 'https://mastodon.social/users/testuser';
		$acct = 'testuser@mastodon.social';
		$lookup = array(
			$canonical_url   => $canonical_url,
			$acct            => $canonical_url,
			'@' . $acct      => $canonical_url,
		);

		// Without leading @.
		$result = $this->sync_admin->test_normalize_actor_url( $acct, $lookup );
		$this->assertEquals( $canonical_url, $result );

		// With leading @.
		$result = $this->sync_admin->test_normalize_actor_url( '@' . $acct, $lookup );
		$this->assertEquals( $canonical_url, $result );
	}

	/**
	 * Test normalize_actor_url returns URL as-is when not in lookup map and no network fallback.
	 */
	public function test_normalize_actor_url_unknown_url_no_fallback() {
		$unknown_url = 'https://unknown.server/users/someone';
		$lookup = array();

		$result = $this->sync_admin->test_normalize_actor_url( $unknown_url, $lookup, false );
		$this->assertEquals( $unknown_url, $result );
	}

	/**
	 * Test normalize_actor_url returns null for invalid non-URL input.
	 */
	public function test_normalize_actor_url_invalid_input() {
		$lookup = array();

		// Invalid input that's not a URL.
		$result = $this->sync_admin->test_normalize_actor_url( 'not-a-url', $lookup, false );
		$this->assertNull( $result );
	}

	/**
	 * Test Mastodon URL format matching (/@username vs /users/username).
	 */
	public function test_url_format_matching_mastodon() {
		$canonical_url = 'https://mastodon.social/users/jimniels';
		$friends_url = 'https://mastodon.social/@jimniels';
		$acct = 'jimniels@mastodon.social';

		$this->create_ap_actor( $canonical_url, $acct, $friends_url );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// Both URL formats should resolve to canonical.
		$this->assertEquals( $canonical_url, $this->sync_admin->test_normalize_actor_url( $canonical_url, $lookup ) );
		$this->assertEquals( $canonical_url, $this->sync_admin->test_normalize_actor_url( $friends_url, $lookup ) );
		$this->assertEquals( $canonical_url, $this->sync_admin->test_normalize_actor_url( '@' . $acct, $lookup ) );
	}

	/**
	 * Test Pixelfed URL format matching (/username vs /users/username).
	 */
	public function test_url_format_matching_pixelfed() {
		$canonical_url = 'https://pixelfed.social/users/dansup';
		$friends_url = 'https://pixelfed.social/dansup';
		$acct = 'dansup@pixelfed.social';

		$this->create_ap_actor( $canonical_url, $acct, $friends_url );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// Both URL formats should resolve to canonical.
		$this->assertEquals( $canonical_url, $this->sync_admin->test_normalize_actor_url( $canonical_url, $lookup ) );
		$this->assertEquals( $canonical_url, $this->sync_admin->test_normalize_actor_url( $friends_url, $lookup ) );
		$this->assertEquals( $canonical_url, $this->sync_admin->test_normalize_actor_url( '@' . $acct, $lookup ) );
	}

	/**
	 * Test that URLs not in lookup map are returned as-is for later comparison.
	 */
	public function test_normalize_preserves_unknown_urls() {
		$known_canonical = 'https://mastodon.social/users/known';
		$unknown_url = 'https://other.server/users/unknown';

		$this->create_ap_actor( $known_canonical );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// Known URL normalizes.
		$this->assertEquals( $known_canonical, $this->sync_admin->test_normalize_actor_url( $known_canonical, $lookup ) );

		// Unknown URL is preserved.
		$this->assertEquals( $unknown_url, $this->sync_admin->test_normalize_actor_url( $unknown_url, $lookup ) );
	}

	/**
	 * Test that actors without guid are skipped in lookup map.
	 */
	public function test_build_actor_lookup_map_skips_empty_guid() {
		global $wpdb;

		// Create post (WordPress auto-generates a guid).
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'ap_actor',
				'post_title'  => 'Empty GUID Actor',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, '_activitypub_acct', 'test@example.org' );

		// Directly update the guid to empty (WordPress doesn't allow empty guid via wp_update_post).
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->posts,
			array( 'guid' => '' ),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// Should be empty since no valid canonical URL.
		$this->assertEmpty( $lookup );
	}

	/**
	 * Test lookup map with acct that has leading @ stored.
	 */
	public function test_build_actor_lookup_map_acct_with_leading_at() {
		$canonical_url = 'https://mastodon.social/users/testuser';
		$acct_with_at = '@testuser@mastodon.social';

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'ap_actor',
				'post_title'  => 'Test Actor',
				'post_status' => 'publish',
				'guid'        => $canonical_url,
			)
		);
		update_post_meta( $post_id, '_activitypub_acct', $acct_with_at );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// Should handle stored acct with leading @.
		$this->assertArrayHasKey( '@testuser@mastodon.social', $lookup );
		$this->assertEquals( $canonical_url, $lookup['@testuser@mastodon.social'] );

		// Should also create normalized version.
		$this->assertArrayHasKey( 'testuser@mastodon.social', $lookup );
		$this->assertEquals( $canonical_url, $lookup['testuser@mastodon.social'] );
	}

	/**
	 * Test that sync comparison works correctly with different URL formats.
	 *
	 * This tests the scenario where:
	 * - Friends stores: https://mastodon.social/@jimniels
	 * - ActivityPub stores: https://mastodon.social/users/jimniels
	 */
	public function test_sync_url_format_comparison() {
		// Create ap_actor with canonical URL and stored friends URL.
		$canonical_url = 'https://mastodon.social/users/jimniels';
		$friends_url = 'https://mastodon.social/@jimniels';
		$acct = 'jimniels@mastodon.social';

		$this->create_ap_actor( $canonical_url, $acct, $friends_url );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// Simulate Friends having /@username format.
		$friends_stored_url = 'https://mastodon.social/@jimniels';

		// The lookup map should resolve this to canonical.
		$normalized = $this->sync_admin->test_normalize_actor_url( $friends_stored_url, $lookup );

		// Now both sides should match when comparing.
		$this->assertEquals( $canonical_url, $normalized );

		// Verify the lookup map has the right entries.
		$this->assertArrayHasKey( $canonical_url, $lookup );
		$this->assertArrayHasKey( $friends_url, $lookup );
		$this->assertEquals( $lookup[ $canonical_url ], $lookup[ $friends_url ] );
	}

	/**
	 * Test edge case: URL with trailing slash.
	 */
	public function test_normalize_actor_url_trailing_slash() {
		$canonical_url = 'https://example.org/users/test';
		$canonical_with_slash = 'https://example.org/users/test/';

		$this->create_ap_actor( $canonical_url );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// Exact match works.
		$this->assertEquals( $canonical_url, $this->sync_admin->test_normalize_actor_url( $canonical_url, $lookup ) );

		// With trailing slash returns as-is (different URL, not in map).
		$this->assertEquals( $canonical_with_slash, $this->sync_admin->test_normalize_actor_url( $canonical_with_slash, $lookup ) );
	}

	/**
	 * Test that lookup map correctly handles multiple URL formats for same actor.
	 */
	public function test_build_actor_lookup_map_comprehensive() {
		$canonical_url = 'https://mastodon.social/users/akirk';
		$acct = 'akirk@mastodon.social';
		$friends_url = 'https://mastodon.social/@akirk';

		$post_id = $this->create_ap_actor( $canonical_url, $acct, $friends_url );

		$lookup = $this->sync_admin->test_build_actor_lookup_map();

		// All these should resolve to the same canonical URL.
		$urls_to_test = array(
			$canonical_url,
			$acct,
			'@' . $acct,
			$friends_url,
		);

		foreach ( $urls_to_test as $url ) {
			$this->assertArrayHasKey( $url, $lookup, "URL not in lookup map: $url" );
			$this->assertEquals( $canonical_url, $lookup[ $url ], "URL $url does not resolve to canonical" );
		}
	}

	/**
	 * Test backfill scenario: after backfill, previously unmatched URLs should match.
	 */
	public function test_backfill_enables_matching() {
		// Initially create actor without friends_url.
		$canonical_url = 'https://example.org/users/test';
		$post_id = $this->create_ap_actor( $canonical_url );

		$friends_url = 'https://example.org/@test';

		// Before backfill, friends URL won't resolve.
		$lookup_before = $this->sync_admin->test_build_actor_lookup_map();
		$this->assertArrayNotHasKey( $friends_url, $lookup_before );

		// Simulate backfill by adding _friends_feed_url meta.
		update_post_meta( $post_id, '_friends_feed_url', $friends_url );

		// After backfill, friends URL should resolve.
		$lookup_after = $this->sync_admin->test_build_actor_lookup_map();
		$this->assertArrayHasKey( $friends_url, $lookup_after );
		$this->assertEquals( $canonical_url, $lookup_after[ $friends_url ] );
	}
}
