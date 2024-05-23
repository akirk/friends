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
	private $token;

	public function set_up() {
		if ( ! class_exists( '\Enable_Mastodon_Apps\Mastodon_API' ) ) {
			return $this->markTestSkipped( 'The Enable Mastodon Apps plugin is not loaded.' );
		}
		parent::set_up();

		$administrator = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$app = \Enable_Mastodon_Apps\Mastodon_App::save( 'Test App', array( 'https://test' ), 'read write follow push', 'https://mastodon.local' );
		$oauth = new \Enable_Mastodon_Apps\Mastodon_OAuth();
		$this->token = wp_generate_password( 128, false );
		$userdata = get_userdata( $administrator );
		$oauth->get_token_storage()->setAccessToken( $this->token, $app->get_client_id(), $userdata->ID, time() + HOUR_IN_SECONDS, $app->get_scopes() );

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

		if ( ! class_exists( '\Enable_Mastodon_Apps\Mastodon_API' ) ) {
			return;
		}

		if ( \Enable_Mastodon_Apps\Mastodon_API::get_last_error() ) {
			$stderr = fopen( 'php://stderr', 'w' );
			fwrite( $stderr, PHP_EOL . \Enable_Mastodon_Apps\Mastodon_API::get_last_error() . PHP_EOL );
			fclose( $stderr );
		}
	}

	public function api_request( $method, $endpoint ) {
		$request = new \WP_REST_Request( $method, '/' . \Enable_Mastodon_Apps\Mastodon_API::PREFIX . $endpoint );
		return $request;
	}

	public function dispatch( \WP_REST_Request $request ) {
		global $wp_rest_server;
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			unset( $_SERVER['HTTP_AUTHORIZATION'] );
		}
		return $wp_rest_server->dispatch( $request );
	}

	public function dispatch_authenticated( \WP_REST_Request $request ) {
		global $wp_rest_server;
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		return $wp_rest_server->dispatch( $request );
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
		$request = $this->api_request( 'GET', '/api/v1/accounts/' . $feedless_friend_id );
		$response = $this->dispatch_authenticated( $request );
		$account = $response->get_data();

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

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$statuses = $response->get_data();

		$this->assertNotEmpty( $statuses );
		$status = $statuses[0];
		$this->assertEquals( $post_id, $status->id );

		$account = $statuses[0]->account;
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

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$statuses = $response->get_data();

		$this->assertNotEmpty( $statuses );
		$status = $statuses[0];
		$this->assertEquals( $post_id, $status->id );

		$account = $statuses[0]->account;
		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $account->id );
		$this->assertEquals( $friend->ID, $re_resolved_account_id );

		$account = $statuses[0]->account;
		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $account->id );
		$this->assertEquals( $friend->ID, $re_resolved_account_id );

		$this->assertNotEquals( $status->reblog->id, $status->id );
		$this->assertNotEquals( $status->reblog->account->id, $status->account->id );
		$this->assertNotEquals( $status->reblog->account->username, $status->account->username );
	}

	public function test_submit_external_user_and_status_reply() {
		$external_user_url = 'https://mastodon.elsewhere/@user';
		$external_url = $external_user_url . '/1';

		$friend_id = \Enable_Mastodon_Apps\Mastodon_API::remap_user_id( $external_user_url );

		add_filter(
			'mastodon_api_get_posts_query_args',
			function ( $args ) use ( $friend_id, $external_user_url ) {
				if ( $external_user_url === $args['author'] ) {
					$args['author'] = $friend_id;
				}
				return $args;
			}
		);

		add_filter(
			'mastodon_api_account',
			function ( $account, $user_id ) use ( $friend_id, $external_user_url ) {
				if ( $user_id !== $friend_id ) {
					return $account;
				}
				$account = new \Enable_Mastodon_Apps\Entity\Account();
				$account->id = $external_user_url;
				$account->username = 'user';
				$account->display_name = 'User';
				$account->acct = 'user@mastodon.elsewhere';
				$account->url = $external_user_url;
				return $account;
			},
			10,
			2
		);

		$filter_external_status = function ( $statuses, $args ) use ( $friend_id, $external_url ) {
			if ( isset( $args['author'] ) && $args['author'] === $friend_id ) {
				$status = new \Enable_Mastodon_Apps\Entity\Status();
				$status->id = $external_url;
				$status->uri = $status->id;
				$status->account = apply_filters( 'mastodon_api_account', null, $friend_id );
				$status->content = 'Hello, World!';
				$statuses->data[] = $status;
			}
			return $statuses;
		};

		add_filter( 'mastodon_api_statuses', $filter_external_status, 10, 2 );
		$request = $this->api_request( 'GET', '/api/v1/accounts/' . $friend_id . '/statuses' );
		$response = $this->dispatch_authenticated( $request );
		$statuses = json_decode( json_encode( $response->get_data() ) );

		$this->assertNotEmpty( $statuses );
		$this->assertTrue( is_numeric( $statuses[0]->id ) );

		$mock = new \MockAction();
		$cached_external_post_id = null;
		add_filter(
			'friends_cache_url_post_id',
			function ( $post_id, $url ) use ( $friend_id, $filter_external_status, &$cached_external_post_id ) {
				$response = $filter_external_status( new \WP_REST_Response(), array( 'author' => $friend_id ) );
				foreach ( $response->data as $status ) {
					if ( $url === $status->uri ) {
						if ( $cached_external_post_id ) {
							return $cached_external_post_id;
						}
						$cached_external_post_id = wp_insert_post(
							array(
								'post_title'   => '',
								'post_content' => $status->content,
								'post_type'    => Friends::CPT,
								'post_status'  => 'publish',
								'post_author'  => $friend_id,
							)
						);
						return $cached_external_post_id;
					}
				}
				return $post_id;
			},
			10,
			2
		);
		add_filter( 'friends_cache_url_post_id', array( $mock, 'action' ), 10 );

		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_body_params(
			array(
				'in_reply_to_id' => $statuses[0]->id,
				'status'         => 'Hello, World!',
			)
		);
		$response = $this->dispatch_authenticated( $request );
		$reply = $response->get_data();

		$this->assertNotNull( $cached_external_post_id );
		// The id should be updated because we cache the post.
		$this->assertNotEquals( $statuses[0]->id, $reply->in_reply_to_id );

		$this->assertEquals( 1, $mock->get_call_count() );
		$this->assertEquals( 1, get_comments_number( $cached_external_post_id ) );
		remove_filter( 'mastodon_api_statuses', $filter_external_status, 10 );
	}

	public function test_submit_external_status_reply() {
		$friend_id = $this->factory->user->create(
			array(
				'role' => 'friend',
			)
		);
		$external_url = 'https://mastodon.elsewhere/@user/1';

		$filter_external_status = function ( $statuses, $args ) use ( $friend_id, $external_url ) {
			if ( $args['author'] === $friend_id ) {
				$status = new \Enable_Mastodon_Apps\Entity\Status();
				$status->id = $external_url;
				$status->uri = $status->id;
				$status->account = apply_filters( 'mastodon_api_account', null, $friend_id );
				$status->content = 'Hello, World!';
				$status->created_at = new \DateTime( 'now' );
				$statuses->data[] = $status;
			}
			return $statuses;
		};

		add_filter( 'mastodon_api_statuses', $filter_external_status, 10, 2 );
		$request = $this->api_request( 'GET', '/api/v1/accounts/' . $friend_id . '/statuses' );
		$response = $this->dispatch_authenticated( $request );
		$statuses = json_decode( json_encode( $response->get_data() ) );

		$this->assertNotEmpty( $statuses );
		$this->assertTrue( is_numeric( $statuses[0]->id ) );

		$mock = new \MockAction();
		$cached_external_post_id = null;
		add_filter(
			'friends_cache_url_post_id',
			function ( $post_id, $url ) use ( $friend_id, $filter_external_status, &$cached_external_post_id ) {
				$response = $filter_external_status( new \WP_REST_Response(), array( 'author' => $friend_id ) );
				foreach ( $response->data as $status ) {
					if ( $url === $status->uri ) {
						if ( $cached_external_post_id ) {
							return $cached_external_post_id;
						}
						$cached_external_post_id = wp_insert_post(
							array(
								'post_title'   => '',
								'post_content' => $status->content,
								'post_type'    => Friends::CPT,
								'post_status'  => 'publish',
								'post_author'  => $friend_id,
							)
						);
						return $cached_external_post_id;
					}
				}
				return $post_id;
			},
			10,
			2
		);
		add_filter( 'friends_cache_url_post_id', array( $mock, 'action' ), 10 );

		$request = $this->api_request( 'POST', '/api/v1/statuses' );
		$request->set_body_params(
			array(
				'in_reply_to_id' => $statuses[0]->id,
				'status'         => 'Hello, World!',
			)
		);
		$response = $this->dispatch_authenticated( $request );
		$reply = $response->get_data();

		$this->assertNotNull( $cached_external_post_id );
		// The id should be updated because we cache the post.
		$this->assertNotEquals( $statuses[0]->id, $reply->in_reply_to_id );

		$this->assertEquals( 1, $mock->get_call_count() );
		$this->assertEquals( 1, get_comments_number( $cached_external_post_id ) );
		remove_filter( 'mastodon_api_statuses', $filter_external_status, 10 );
	}
}
