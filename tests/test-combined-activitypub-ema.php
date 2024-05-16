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
	public function set_up() {
		if ( ! class_exists( '\Enable_Mastodon_Apps\Mastodon_API' ) ) {
			return $this->markTestSkipped( 'The Enable Mastodon Apps plugin is not loaded.' );
		}
		parent::set_up();
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
		$second_account = apply_filters( 'mastodon_api_account', null, $this->friend_id, null, get_post( $post_id ) );
		if ( $friend instanceof Subscription ) {
			$this->assertTrue( $second_account->id > 1e10 );
		} else {
			$this->assertEquals( $friend->ID, $second_account->id );
		}
		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $second_account->id );
		$this->assertEquals( $friend->ID, $re_resolved_account_id );

		$third_account = apply_filters( 'mastodon_api_account', null, $this->actor, null, null );
		$this->assertEquals( $second_account->id, $third_account->id );
	}
}
