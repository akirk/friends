<?php
/**
 * Tests for cached timeline identity without an EMA runtime dependency.
 *
 * @package Friends
 */

namespace Friends;

/**
 * The identity filter must also work when EMA is not installed.
 */
class EMA_Timeline_Identity_Test extends \WP_UnitTestCase {
	public function test_object_identity_preserves_boost_events() {
		$post_id = self::factory()->post->create( array( 'post_type' => Friends::CPT ) );
		$post    = get_post( $post_id );
		$this->assertSame( $post->guid, apply_filters( 'mastodon_api_timeline_post_identity', null, $post ) );
		update_post_meta( $post_id, Feed_Parser_ActivityPub::SLUG, array( 'reblog' => true ) );
		$this->assertFalse( apply_filters( 'mastodon_api_timeline_post_identity', null, $post ) );
	}

	public function test_other_providers_keep_their_identity() {
		$post = self::factory()->post->create_and_get();
		$this->assertNull( apply_filters( 'mastodon_api_timeline_post_identity', null, $post ) );
		$this->assertSame( 'other-provider', apply_filters( 'mastodon_api_timeline_post_identity', 'other-provider', $post ) );
	}
}
