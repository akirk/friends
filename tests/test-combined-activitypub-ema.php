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
class Combined_ActivityPub_EnableMastdodonApps_Test extends ActivityPubTest {
	public static $users = array();
	private $posts = array();

	public function set_up() {
		if ( ! class_exists( '\Enable_Mastodon_Apps\Mastodon_API' ) ) {
			return $this->markTestSkipped( 'The Enable Mastodon Apps plugin is not loaded.' );
		}
		parent::set_up();

		self::$users['https://notiz.blog/author/matthias-pfefferle/'] = array(
			'id'   => 'https://notiz.blog/author/matthias-pfefferle/',
			'url'  => 'https://notiz.blog/author/matthias-pfefferle/',
			'name' => 'Matthias Pfefferle',
		);
	}

	public function tear_down() {
		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	public function test_account_canonical_id() {
		$this->assertTrue( \has_filter( 'mastodon_api_account' ) );

		$friend_username = 'friend.local';
		$feedless_friend_id = $this->factory->user->create(
			array(
				'user_login' => $friend_username,
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
			)
		);

		$account = apply_filters( 'mastodon_api_account', null, $feedless_friend_id, null, null );
		$this->assertEquals( $feedless_friend_id, $account->id );

		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $account->id );
		$this->assertEquals( $feedless_friend_id, $re_resolved_account_id );

		$user_feed = User_Feed::get_by_url( $this->actor );
		$friend = $user_feed->get_friend_user();
		$post_id = $friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Hello, World!',
				'meta_input'   => array(
					'activitypub' => array(
						'attributedTo' => array(
							'id'                => $this->actor,
							'preferredUsername' => 'user',
							'name'              => 'Mr User',
							'summary'           => 'A user that only exists during testing',
						),
					),
				),
			)
		);
		$this->posts[] = $post_id;
		$second_account = apply_filters( 'mastodon_api_account', null, $this->friend_id, null, get_post( $post_id ) );
		$this->assertTrue( $second_account->id > 1e10 );
		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $second_account->id );
		$this->assertEquals( $friend->ID, $re_resolved_account_id );

		$third_account = apply_filters( 'mastodon_api_account', null, $this->actor, null, null );
		$this->assertEquals( $second_account->id, $third_account->id );
	}

	public function test_timeline_canonical_id_user() {
		$user_feed = User_Feed::get_by_url( $this->actor );
		$friend = $user_feed->get_friend_user();
		$post_id = $friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Hello, World!',
				'post_status'  => 'publish',
				'meta_input'   => array(
					'activitypub' => array(
						'attributedTo' => array(
							'id'                => $this->actor,
							'preferredUsername' => 'user',
							'name'              => 'Mr User',
							'summary'           => 'A user that only exists during testing',
						),
					),
				),
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

	public function test_reblogs() {
		$user_feed = User_Feed::get_by_url( $this->actor );
		$friend = $user_feed->get_friend_user();
		$post_id = $friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Hello, World!',
				'post_status'  => 'publish',
				'meta_input'   => array(
					'activitypub' => array(
						'attributedTo' => array(
							'id'                => 'https://notiz.blog/author/matthias-pfefferle/',
							'preferredUsername' => 'Matthias',
							'name'              => 'Matthias Pfefferle',
							'summary'           => 'Creator of the ActivityPub plugin',
						),
						'reblog'       => true,
					),
				),
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

		$account = $statuses->data[0]->account;
		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $account->id );
		$this->assertEquals( $friend->ID, $re_resolved_account_id );

		$this->assertNotEmpty( $statuses->data );
		$this->assertNotEquals( $status->reblog->id, $status->id );
		$this->assertNotEquals( $status->reblog->account->id, $status->account->id );
		$this->assertNotEquals( $status->reblog->account->username, $status->account->username );
	}
}
