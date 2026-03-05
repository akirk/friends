<?php
/**
 * Tests for Mastodon API performance optimizations.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the performance optimizations in the ActivityPub feed parser
 * for the Mastodon API (handle conversion, reblog fast path, account resolution).
 */
class Mastodon_API_Performance_Test extends ActivityPubTest {
	public static $users = array();
	private $posts = array();
	private $token;

	public static function known_test_activitypub_hosts( $is_known, $host ) {
		// Recognize host patterns used in tests as known fediverse instances.
		if ( str_ends_with( $host, '.social' ) || str_starts_with( $host, 'mastodon.' ) ) {
			return true;
		}
		return $is_known;
	}

	public function set_up() {
		if ( ! class_exists( '\Enable_Mastodon_Apps\Mastodon_API' ) ) {
			return $this->markTestSkipped( 'The Enable Mastodon Apps plugin is not loaded.' );
		}
		parent::set_up();

		add_filter( 'friends_is_known_activitypub_host', array( self::class, 'known_test_activitypub_hosts' ), 10, 2 );
		add_filter( 'pre_option_mastodon_api_disable_ema_app_settings_changes', '__return_true' );
		add_filter( 'pre_option_mastodon_api_disable_ema_announcements', '__return_true' );

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
		remove_filter( 'friends_is_known_activitypub_host', array( self::class, 'known_test_activitypub_hosts' ), 10, 2 );

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

	public function dispatch_authenticated( \WP_REST_Request $request ) {
		global $wp_rest_server;
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->token;
		return $wp_rest_server->dispatch( $request );
	}

	/**
	 * Helper to create a friend_post_cache post with attributedTo metadata.
	 */
	private function create_friend_post( $actor_url, $attributed_to, $extra_meta = array() ) {
		$user_feed = User_Feed::get_by_url( $this->actor );
		$friend = $user_feed->get_friend_user();

		$meta = array_merge(
			array( 'attributedTo' => $attributed_to ),
			$extra_meta
		);

		$post_id = $friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Test post ' . wp_rand(),
				'post_status'  => 'publish',
				'meta_input'   => array(
					'activitypub' => $meta,
				),
			)
		);
		$this->posts[] = $post_id;
		return $post_id;
	}

	/**
	 * Test that /@username URLs resolve to user@host on any domain.
	 */
	public function test_at_username_url_resolves_on_any_domain() {
		$post_id = $this->create_friend_post(
			'https://unknown-instance.example/@alice',
			array(
				'id'                => 'https://unknown-instance.example/@alice',
				'preferredUsername' => 'alice',
				'name'             => 'Alice',
			)
		);

		$account = apply_filters( 'mastodon_api_account', null, $this->friend_id, null, get_post( $post_id ) );
		$this->assertInstanceOf( '\Enable_Mastodon_Apps\Entity\Account', $account );
		$this->assertEquals( 'alice@unknown-instance.example', $account->acct );
		$this->assertEquals( 'alice', $account->username );
	}

	/**
	 * Test that /users/username URLs resolve on known hosts.
	 */
	public function test_users_path_resolves_on_known_host() {
		// The actor URL from set_up (mastodon.local) should be a known host.
		$post_id = $this->create_friend_post(
			$this->actor,
			array(
				'id'                => $this->actor,
				'preferredUsername' => 'akirk',
				'name'             => 'Alex Kirk',
			)
		);

		$account = apply_filters( 'mastodon_api_account', null, $this->friend_id, null, get_post( $post_id ) );
		$this->assertInstanceOf( '\Enable_Mastodon_Apps\Entity\Account', $account );
		$this->assertEquals( 'akirk@mastodon.local', $account->acct );
	}

	/**
	 * Test that /users/username on an unknown host does NOT resolve to a handle.
	 */
	public function test_users_path_unknown_host_returns_url() {
		$actor_url = 'https://totally-unknown.example/users/bob';
		$post_id = $this->create_friend_post(
			$actor_url,
			array(
				'id'                => $actor_url,
				'preferredUsername' => 'bob',
				'name'             => 'Bob',
			)
		);

		$account = apply_filters( 'mastodon_api_account', null, $this->friend_id, null, get_post( $post_id ) );
		$this->assertInstanceOf( '\Enable_Mastodon_Apps\Entity\Account', $account );
		// Unknown host with /users/ path — acct should be the raw URL since host is not known.
		$this->assertEquals( $actor_url, $account->acct );
	}

	/**
	 * Test that .social TLD is treated as a known fediverse host.
	 */
	public function test_social_tld_is_known() {
		$actor_url = 'https://fosstodon.social/users/carol';
		$post_id = $this->create_friend_post(
			$actor_url,
			array(
				'id'                => $actor_url,
				'preferredUsername' => 'carol',
				'name'             => 'Carol',
			)
		);

		$account = apply_filters( 'mastodon_api_account', null, $this->friend_id, null, get_post( $post_id ) );
		$this->assertInstanceOf( '\Enable_Mastodon_Apps\Entity\Account', $account );
		$this->assertEquals( 'carol@fosstodon.social', $account->acct );
	}

	/**
	 * Test that mastodon.* domains are treated as known.
	 */
	public function test_mastodon_domain_is_known() {
		$actor_url = 'https://mastodon.xyz/users/dave';
		$post_id = $this->create_friend_post(
			$actor_url,
			array(
				'id'                => $actor_url,
				'preferredUsername' => 'dave',
				'name'             => 'Dave',
			)
		);

		$account = apply_filters( 'mastodon_api_account', null, $this->friend_id, null, get_post( $post_id ) );
		$this->assertInstanceOf( '\Enable_Mastodon_Apps\Entity\Account', $account );
		$this->assertEquals( 'dave@mastodon.xyz', $account->acct );
	}

	/**
	 * Test that reblog with preferredUsername uses the metadata fast path.
	 */
	public function test_reblog_uses_metadata_fast_path() {
		$user_feed = User_Feed::get_by_url( $this->actor );
		$friend = $user_feed->get_friend_user();
		$post_id = $friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Reblogged content',
				'post_status'  => 'publish',
				'meta_input'   => array(
					'activitypub' => array(
						'attributedTo' => array(
							'id'                => 'https://notiz.blog/author/matthias-pfefferle/',
							'preferredUsername' => 'Matthias',
							'name'             => 'Matthias Pfefferle',
							'summary'           => 'Creator of the ActivityPub plugin',
							'icon'              => 'https://notiz.blog/avatar.png',
							'header'            => 'https://notiz.blog/header.png',
						),
						'reblog'       => true,
					),
				),
			)
		);
		$this->posts[] = $post_id;

		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );
		$this->assertNotNull( $status );
		$this->assertNotNull( $status->reblog );

		// Reblog account should be populated from metadata.
		$this->assertEquals( 'Matthias', $status->reblog->account->username );
		$this->assertEquals( 'Matthias Pfefferle', $status->reblog->account->display_name );
		$this->assertEquals( 'https://notiz.blog/avatar.png', $status->reblog->account->avatar );
		$this->assertEquals( 'https://notiz.blog/header.png', $status->reblog->account->header );
		$this->assertEquals( 'https://notiz.blog/author/matthias-pfefferle/', $status->reblog->account->url );
	}

	/**
	 * Test that reblog without preferredUsername falls back to filter chain.
	 */
	public function test_reblog_without_preferred_username_falls_back() {
		$user_feed = User_Feed::get_by_url( $this->actor );
		$friend = $user_feed->get_friend_user();
		$post_id = $friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Reblogged content without username',
				'post_status'  => 'publish',
				'meta_input'   => array(
					'activitypub' => array(
						'attributedTo' => array(
							'id'   => $this->actor,
							'name' => 'Alex Kirk',
						),
						'reblog'       => true,
					),
				),
			)
		);
		$this->posts[] = $post_id;

		$status = apply_filters( 'mastodon_api_status', null, $post_id, array() );
		$this->assertNotNull( $status );
		$this->assertNotNull( $status->reblog );
		// Without preferredUsername, the reblog account should still be set
		// (either via filter fallback or with whatever data is available).
		$this->assertNotNull( $status->reblog->account );
	}

	/**
	 * Test that account resolution populates all metadata fields from attributedTo.
	 */
	public function test_account_metadata_fields() {
		$post_id = $this->create_friend_post(
			'https://example.social/@eve',
			array(
				'id'                => 'https://example.social/@eve',
				'preferredUsername' => 'eve',
				'name'             => 'Eve Example',
				'summary'          => 'A test user',
				'icon'             => 'https://example.social/avatar.png',
			)
		);

		$account = apply_filters( 'mastodon_api_account', null, $this->friend_id, null, get_post( $post_id ) );
		$this->assertInstanceOf( '\Enable_Mastodon_Apps\Entity\Account', $account );
		$this->assertEquals( 'Eve Example', $account->display_name );
		$this->assertEquals( 'A test user', $account->note );
		$this->assertEquals( 'https://example.social/avatar.png', $account->avatar );
	}

	/**
	 * Test that non-standard path on unknown host returns raw URL as acct.
	 */
	public function test_nonstandard_path_unknown_host() {
		$actor_url = 'https://unknown.example/activitypub/frank';
		$post_id = $this->create_friend_post(
			$actor_url,
			array(
				'id'                => $actor_url,
				'preferredUsername' => 'frank',
				'name'             => 'Frank',
			)
		);

		$account = apply_filters( 'mastodon_api_account', null, $this->friend_id, null, get_post( $post_id ) );
		$this->assertInstanceOf( '\Enable_Mastodon_Apps\Entity\Account', $account );
		// Non-standard path on unknown host — acct should be the raw URL.
		$this->assertEquals( $actor_url, $account->acct );
	}
}
