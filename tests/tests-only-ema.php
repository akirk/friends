<?php
/**
 * Class Friends_APITest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Enable Mastodon Apps integration
 */
class Only_EnableMastdodonApps_Test extends Friends_TestCase_Cache_HTTP {
	private $unhooked = array();
	private $posts = array();
	public function set_up() {
		if ( ! class_exists( '\Enable_Mastodon_Apps\Mastodon_API' ) ) {
			return $this->markTestSkipped( 'The Enable Mastodon Apps plugin is not loaded.' );
		}
		parent::set_up();

		// It's too late and the parser is already loaded, but we can unhook its hooks.
		global $wp_filter;
		foreach ( $wp_filter as $hook => $hooked ) {
			foreach ( $hooked as $priority => $functions ) {
				foreach ( $functions as $function ) {
					if ( is_array( $function['function'] ) && $function['function'][0] instanceof Feed_Parser_ActivityPub ) {
						$this->unhooked[] = array( $hook, $function['function'], $priority, $function['accepted_args'] );
						remove_filter( $hook, $function['function'], $priority );
					}
				}
			}
		}
	}

	public function tear_down() {
		foreach ( $this->unhooked as $unhooked ) {
			add_filter( $unhooked[0], $unhooked[1], $unhooked[2], $unhooked[3] );
		}
		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	public function test_ema_account_canonical_id() {
		$this->assertTrue( \has_filter( 'mastodon_api_account' ) );

		$friend_username = 'friend.local';
		$friend_id = $this->factory->user->create(
			array(
				'user_login' => $friend_username,
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
			)
		);

		$account = apply_filters( 'mastodon_api_account', null, $friend_id, null, null );
		$this->assertEquals( $friend_id, $account->id );

		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $account->id );
		$this->assertEquals( $friend_id, $re_resolved_account_id );
	}

	public function test_ema_timeline_canonical_id_user() {
		$this->assertTrue( \has_filter( 'mastodon_api_account' ) );

		$friend_username = 'friend.local';
		$friend_id = $this->factory->user->create(
			array(
				'user_login' => $friend_username,
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
			)
		);
		$friend = new User( get_user_by( 'ID', $friend_id ) );
		$post_id = $friend->insert_post(
			array(
				'post_type'     => Friends::CPT,
				'post_title'    => 'First Friend Post',
				'post_date_gmt' => '2024-05-01 10:00:00',
				'post_status'   => 'publish',
			)
		);
		$this - posts[] = $post_id;

		$request = new \WP_REST_Request( 'GET', '/api/mastodon/timelines/home' );
		$statuses = apply_filters( 'mastodon_api_timelines', null, $request );
		$this->assertNotEmpty( $statuses->data );
		$status = $statuses->data[0];
		$this->assertEquals( $post_id, $status->id );

		$account = $statuses->data[0]->account;
		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $account->id );
		$this->assertEquals( $friend->ID, $re_resolved_account_id );
	}

	public function test_ema_timeline_canonical_id_subscription() {
		$this->assertTrue( \has_filter( 'mastodon_api_account' ) );

		$friend_username = 'friend.local';
		$friend = Subscription::create( $friend_username, 'subscription', 'https://friend.local' );
		$post_id = $friend->insert_post(
			array(
				'post_type'     => Friends::CPT,
				'post_title'    => 'First Friend Post',
				'post_date_gmt' => '2024-05-01 10:00:00',
				'post_status'   => 'publish',
			)
		);
		$this->posts[] = $post_id;

		$request = new \WP_REST_Request( 'GET', '/api/mastodon/timelines/home' );
		$statuses = apply_filters( 'mastodon_api_timelines', null, $request );
		$this->assertNotEmpty( $statuses->data );
		$status = $statuses->data[0];
		$this->assertEquals( $post_id, $status->id );

		$account = $statuses->data[0]->account;
		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $account->id );
		$this->assertEquals( $friend->ID, $re_resolved_account_id );
	}
}
