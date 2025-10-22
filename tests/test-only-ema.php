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
class Only_EnableMastodonAppsTest extends Friends_TestCase_Cache_HTTP {
	private $unhooked = array();
	private $posts = array();
	private $token;
	private $administrator_id;

	public function set_up() {
		if ( ! class_exists( '\Enable_Mastodon_Apps\Mastodon_API' ) ) {
			return $this->markTestSkipped( 'The Enable Mastodon Apps plugin is not loaded.' );
		}
		parent::set_up();

		add_filter( 'pre_option_mastodon_api_disable_ema_app_settings_changes', '__return_true' );
		add_filter( 'pre_option_mastodon_api_disable_ema_announcements', '__return_true' );

		$this->administrator_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$app = \Enable_Mastodon_Apps\Mastodon_App::save( 'Test App', array( 'https://test' ), 'read write follow push', 'https://mastodon.local' );
		$oauth = new \Enable_Mastodon_Apps\Mastodon_OAuth();
		$this->token = wp_generate_password( 128, false );
		$userdata = get_userdata( $this->administrator_id );
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

	public function test_ema_notifications_endpoint_exists() {
		// Test that the notifications endpoint exists
		$request = $this->api_request( 'GET', '/api/v1/notifications' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertNotWPError( $response );
		$this->assertNotEquals( 404, $response->get_status(), 'Notifications endpoint should exist' );
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
		$friend = Subscription::create( $friend_username, 'friend', 'https://friend.local' );
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

	public function test_ema_notifications_query_args_filter() {
		// Test that the mastodon_api_get_notifications_query_args filter is properly registered
		$this->assertTrue( has_filter( 'mastodon_api_get_notifications_query_args' ), 'mastodon_api_get_notifications_query_args filter should be registered' );

		// Get the administrator user
		$admin_user = get_userdata( $this->administrator_id );
		wp_set_current_user( $admin_user->ID );

		// Test that the filter adds the correct query args for mentions
		$args = apply_filters( 'mastodon_api_get_notifications_query_args', array(), 'mention' );

		// Should include Friends CPT
		$this->assertContains( Friends::CPT, $args['post_type'], 'Friends CPT should be in post_type for mentions' );

		// Should include public and private statuses
		$this->assertContains( 'publish', $args['post_status'], 'Public status should be included for mentions' );
		$this->assertContains( 'private', $args['post_status'], 'Private status should be included for mentions' );

		// Should have a tax_query for mention tags
		$this->assertArrayHasKey( 'tax_query', $args, 'Should have tax_query for filtering mentions' );
		$this->assertNotEmpty( $args['tax_query'], 'tax_query should not be empty' );

		// Verify the tax_query filters by mention tag
		$has_mention_query = false;
		foreach ( $args['tax_query'] as $query ) {
			if ( isset( $query['taxonomy'] ) && $query['taxonomy'] === Friends::TAG_TAXONOMY ) {
				if ( isset( $query['terms'] ) && $query['terms'] === 'mention-' . $admin_user->user_login ) {
					$has_mention_query = true;
					break;
				}
			}
		}
		$this->assertTrue( $has_mention_query, 'tax_query should filter by mention tag for current user' );
	}

	public function test_ema_notifications_mentions_queryable() {
		// This test verifies that Friends posts with mention tags are properly queryable
		// through the mastodon_api_get_notifications_query_args filter, which allows
		// EMA to distinguish between mentions and private messages.

		// Get the administrator user
		$admin_user = get_userdata( $this->administrator_id );

		// Create a friend who will mention the admin
		$friend_username = 'friend.local';
		$friend_id = $this->factory->user->create(
			array(
				'user_login' => $friend_username,
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
			)
		);
		$friend = new User( get_user_by( 'ID', $friend_id ) );

		// Create a post from the friend that mentions the admin
		$mention_tag = 'mention-' . $admin_user->user_login;
		$mention_post_id = $friend->insert_post(
			array(
				'post_type'     => Friends::CPT,
				'post_title'    => 'Post mentioning admin',
				'post_content'  => 'Hey @' . $admin_user->user_login . ', check this out!',
				'post_date_gmt' => '2024-05-01 10:00:00',
				'post_status'   => 'publish',
			)
		);
		$this->posts[] = $mention_post_id;

		// Add the mention tag to the post
		wp_set_object_terms( $mention_post_id, array( $mention_tag ), Friends::TAG_TAXONOMY );

		// Create a regular friend post without a mention tag (should not be found)
		$regular_post_id = $friend->insert_post(
			array(
				'post_type'     => Friends::CPT,
				'post_title'    => 'Regular post',
				'post_content'  => 'Just a regular post',
				'post_date_gmt' => '2024-05-01 11:00:00',
				'post_status'   => 'publish',
			)
		);
		$this->posts[] = $regular_post_id;

		// Verify that our filter produces the correct query args for mentions
		wp_set_current_user( $admin_user->ID );
		$mention_args = apply_filters( 'mastodon_api_get_notifications_query_args', array(), 'mention' );

		// Verify the filter adds the required post type and statuses
		$this->assertContains( Friends::CPT, $mention_args['post_type'], 'Filter should add Friends CPT' );
		$this->assertContains( 'publish', $mention_args['post_status'], 'Filter should add publish status' );
		$this->assertContains( 'private', $mention_args['post_status'], 'Filter should add private status' );

		// Verify the filter adds the mention tag tax_query
		$this->assertArrayHasKey( 'tax_query', $mention_args, 'Filter should add tax_query' );

		// Query posts with the mention filter args
		$mention_query = new \WP_Query( $mention_args );
		$this->assertGreaterThan( 0, $mention_query->found_posts, 'Should find posts with mention tags' );

		// Verify only the mention post is found, not the regular post
		$found_ids = wp_list_pluck( $mention_query->posts, 'ID' );
		$this->assertContains( $mention_post_id, $found_ids, 'Should find the post with mention tag' );
		$this->assertNotContains( $regular_post_id, $found_ids, 'Should not find posts without mention tags' );

		// Verify that non-mention type queries don't add the tax_query
		$non_mention_args = apply_filters( 'mastodon_api_get_notifications_query_args', array(), 'follow' );
		$this->assertArrayNotHasKey( 'post_type', $non_mention_args, 'Filter should not modify non-mention queries' );
	}

	public function test_ema_notifications_endpoint_without_mentions() {
		// Get the administrator user
		$admin_user = get_userdata( $this->administrator_id );

		// Create a friend
		$friend_username = 'friend.local';
		$friend_id = $this->factory->user->create(
			array(
				'user_login' => $friend_username,
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
			)
		);
		$friend = new User( get_user_by( 'ID', $friend_id ) );

		// Create a post from the friend WITHOUT a mention tag
		$post_id = $friend->insert_post(
			array(
				'post_type'     => Friends::CPT,
				'post_title'    => 'Post without mention',
				'post_content'  => 'Just a regular post',
				'post_date_gmt' => '2024-05-01 10:00:00',
				'post_status'   => 'publish',
			)
		);
		$this->posts[] = $post_id;

		// Verify no mention tags on the post
		$terms = wp_get_object_terms( $post_id, Friends::TAG_TAXONOMY );
		if ( ! empty( $terms ) ) {
			$term_names = wp_list_pluck( $terms, 'name' );
			$this->assertNotContains( 'mention-' . $admin_user->user_login, $term_names, 'Post should not have mention tag' );
		}

		// Request notifications as the admin user, specifically mention type
		$request = $this->api_request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'types[]', 'mention' );
		$response = $this->dispatch_authenticated( $request );

		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status(), 'Notifications endpoint should return 200' );

		$notifications = $response->get_data();

		// Verify there are no mention notifications for this post
		foreach ( $notifications as $notification ) {
			if ( 'mention' === $notification->type ) {
				$this->assertNotEquals( $post_id, $notification->status->id, 'Should not have mention notification for post without mention tag' );
			}
		}
	}

}
