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
		add_filter( 'pre_http_request', array( $this, 'block_http_requests' ), 10 );

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
		remove_filter( 'pre_http_request', array( $this, 'block_http_requests' ) );

		if ( ! class_exists( '\Enable_Mastodon_Apps\Mastodon_API' ) ) {
			return;
		}

		if ( \Enable_Mastodon_Apps\Mastodon_API::get_last_error() ) {
			$stderr = fopen( 'php://stderr', 'w' );
			fwrite( $stderr, PHP_EOL . \Enable_Mastodon_Apps\Mastodon_API::get_last_error() . PHP_EOL );
			fclose( $stderr );
		}
	}

	public function block_http_requests() {
		return new \WP_Error( 'http_request_failed', 'HTTP requests have been blocked.' );
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
		$request = $this->api_request( 'GET', '/api/v1/accounts/' . $friend_id );
		$response = $this->dispatch_authenticated( $request );
		$account = $response->get_data();

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
		$this->posts[] = $post_id;

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$statuses = $response->get_data();

		$this->assertNotEmpty( $statuses );
		$status = $statuses[0];
		$this->assertEquals( $post_id, $status->id );

		$account = $status->account;
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

		$request = $this->api_request( 'GET', '/api/v1/timelines/home' );
		$response = $this->dispatch_authenticated( $request );
		$statuses = $response->get_data();

		$this->assertNotEmpty( $statuses );
		$status = $statuses[0];
		$this->assertEquals( $post_id, $status->id );

		$account = $status->account;
		$re_resolved_account_id = apply_filters( 'mastodon_api_mapback_user_id', $account->id );
		$this->assertEquals( $friend->ID, $re_resolved_account_id );
	}
}
