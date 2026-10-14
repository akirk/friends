<?php
/**
 * Tests for media on local Mastodon boost wrappers.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Exercise boost formatting without requiring the optional Mastodon plugin.
 */
class Boost_Media_Test extends Friends_TestCase_Cache_HTTP {
	public function test_local_boost_keeps_media_on_original_status() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( 0 );
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Friends::CPT,
				'post_status' => 'publish',
				'meta_input' => array( 'boosted' => $user_id ),
			)
		);
		$original = (object) array(
			'id' => strval( $post_id ),
			'content' => 'A photo',
			'created_at' => new \DateTime( '2024-05-01T10:00:00Z' ),
			'media_attachments' => array( (object) array( 'id' => 'photo' ) ),
			'reblog' => null,
		);
		$status_filter = function () use ( $original ) {
			return $original;
		};
		$account_filter = function () use ( $user_id ) {
			return (object) array( 'id' => strval( $user_id ) );
		};
		add_filter( 'mastodon_api_status', $status_filter, PHP_INT_MAX );
		add_filter( 'mastodon_api_account', $account_filter, PHP_INT_MAX );
		$request = new \WP_REST_Request( 'GET', '/api/v1/accounts/' . $user_id . '/statuses' );
		$request->set_param( 'limit', 20 );
		try {
			$statuses = Enable_Mastodon_Apps::mastodon_api_account_statuses( array(), $request, $user_id );
		} finally {
			remove_filter( 'mastodon_api_status', $status_filter, PHP_INT_MAX );
			remove_filter( 'mastodon_api_account', $account_filter, PHP_INT_MAX );
		}

		$this->assertCount( 1, $statuses );
		$this->assertCount( 0, $statuses[0]->media_attachments );
		$this->assertSame( $original, $statuses[0]->reblog );
		$this->assertCount( 1, $original->media_attachments );
		$this->assertSame( 'photo', $original->media_attachments[0]->id );
	}
}
