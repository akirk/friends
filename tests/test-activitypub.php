<?php
/**
 * Class Test_ActivityPub
 *
 * @package Friends
 */

namespace Friends;

/**
 * A testcase for the Friends plugin that provides a mock HTTP server.
 *
 * @package
 */
class ActivityPubTest extends Friends_TestCase_Cache_HTTP {
	public static $users = array();
	protected $friend;
	protected $friend_id;
	protected $friend_name;
	protected $friend_nicename;
	protected $actor;

	/**
	 * Helper method to send an ActivityPub message to a user's inbox
	 */
	private function receive_activity( $user_id, $activity_data, $use_server_dispatch = false ) {
		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . $user_id . '/inbox' );
		$request->set_body( wp_json_encode( $activity_data ) );
		$request->set_header( 'Content-type', 'application/json' );

		if ( $use_server_dispatch ) {
			return $this->server->dispatch( $request );
		}

		return rest_do_request( $request );
	}

	public function test_custom_emojis_from_actor_tags() {
		$tags = array(
			array(
				'type' => 'Hashtag',
				'name' => '#activitypub',
			),
			array(
				'type' => 'Emoji',
				'name' => ':party:',
				'icon' => array(
					'url' => 'https://example.com/party.png',
				),
			),
		);

		$this->assertEquals(
			array(
				':party:' => 'https://example.com/party.png',
			),
			Feed_Parser_ActivityPub::get_custom_emojis_from_actor_tags( $tags )
		);
	}

	public function test_custom_emojis_from_single_actor_tag() {
		$tag = array(
			'type' => 'Emoji',
			'name' => ':bot:',
			'icon' => array(
				'url' => 'https://example.com/bot.png',
			),
		);

		$this->assertEquals(
			array(
				':bot:' => 'https://example.com/bot.png',
			),
			Feed_Parser_ActivityPub::get_custom_emojis_from_actor_tags( $tag )
		);
	}

	public function test_replace_custom_emojis() {
		$html = Feed_Parser_ActivityPub::replace_custom_emojis(
			'Elena Rocks :party: :unknown:',
			array(
				':party:' => 'https://example.com/party.png',
			)
		);

		$this->assertStringContainsString( 'Elena Rocks ', $html );
		$this->assertStringContainsString( '<img class="activitypub-custom-emoji" src="https://example.com/party.png" alt=":party:" title="party" loading="lazy" />', $html );
		$this->assertStringContainsString( ':unknown:', $html );
	}

	public function test_replace_custom_emojis_for_activitypub_user() {
		$user_feed = User_Feed::get_by_url( $this->actor );
		$this->assertInstanceOf( User_Feed::class, $user_feed );

		$ap_actor_id = $this->factory->post->create(
			array(
				'post_type'    => 'ap_actor',
				'post_status'  => 'publish',
				'post_title'   => 'Alex :party:',
				'post_content' => wp_json_encode(
					array(
						'id'  => $this->actor,
						'tag' => array(
							array(
								'type' => 'Emoji',
								'name' => ':party:',
								'icon' => array(
									'url' => 'https://example.com/party.png',
								),
							),
						),
					)
				),
				'guid'         => $this->actor,
			)
		);

		$user_feed->set_ap_actor_id( $ap_actor_id );
		wp_update_user(
			array(
				'ID'           => $this->friend_id,
				'display_name' => 'Alex :party:',
			)
		);

		$friend = User::get_user_by_id( $this->friend_id );
		$html   = Feed_Parser_ActivityPub::replace_custom_emojis_for_user( $friend->display_name, $friend );

		$this->assertStringContainsString( 'Alex ', $html );
		$this->assertStringContainsString( '<img class="activitypub-custom-emoji" src="https://example.com/party.png" alt=":party:" title="party" loading="lazy" />', $html );
	}

	public function test_incoming_post() {
		$this->friend->update_user_option( 'activitypub_friends_show_replies', '1' );
		$now = time() - 10;
		$status_id = 123;

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$post_count = count( $posts );

		// Let's post a new Note through the REST API.
		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;
		$content = 'Test ' . $date . ' ' . wp_rand();
		$attachment_url = 'https://mastodon.local/files/original/1234.png';
		$attachment_width = 400;
		$attachment_height = 600;

		$activity_data = array(
			'type'   => 'Create',
			'id'     => $id,
			'actor'  => $this->actor,
			'object' => array(
				'type'         => 'Note',
				'id'           => $id,
				'attributedTo' => $this->actor,
				'inReplyTo'    => null,
				'content'      => $content,
				'url'          => 'https://mastodon.local/users/akirk/statuses/' . ( $status_id++ ),
				'published'    => $date,
				'attachment'   => array(
					array(
						'type'      => 'Document',
						'mediaType' => 'image/png',
						'url'       => $attachment_url,
						'name'      => '',
						'blurhash'  => '',
						'width'     => $attachment_width,
						'height'    => $attachment_height,
					),
				),
			),
		);

		$response = $this->receive_activity( get_current_user_id(), $activity_data, true );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringStartsWith( $content, $posts[0]->post_content );
		$this->assertStringContainsString( '<img src="' . esc_url( $attachment_url ) . '" width="' . esc_attr( $attachment_width ) . '" height="' . esc_attr( $attachment_height ) . '"', $posts[0]->post_content );

		// Do another test post, this time with a URL that has an @-id.
		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;
		$content = 'Test ' . $date . ' ' . wp_rand();

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_body(
			wp_json_encode(
				array(
					'type'   => 'Create',
					'id'     => $id,
					'actor'  => 'https://mastodon.local/@akirk',

					'object' =>
					array(
						'type'         => 'Note',
						'id'           => $id,
						'attributedTo' => 'https://mastodon.local/@akirk',
						'inReplyTo'    => null,
						'content'      => $content,
						'url'          => 'https://mastodon.local/users/akirk/statuses/' . ( $status_id++ ),
						'published'    => $date,
					),
				)
			)
		);

		$request->set_header( 'Content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$this->assertEquals( $post_count + 2, count( $posts ) );
		$this->assertEquals( $content, $posts[0]->post_content );
		$this->assertEquals( $this->friend_name, get_post_meta( $posts[0]->ID, 'author', true ) );

		$this->friend->delete_user_option( 'activitypub_friends_show_replies' );
	}

	public function test_incoming_update_person() {
		$user_feed = User_Feed::get_by_url( $this->actor );
		$friend_user = $user_feed->get_friend_user();
		$old_description = $friend_user->description;

		$new_description = 'New Description' . wp_rand();

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_body(
			wp_json_encode(
				array(
					'type'   => 'Update',
					'id'     => $this->actor . '#update',
					'actor'  => $this->actor,
					'object' => array(
						'type'         => 'Person',
						'id'           => $this->actor,
						'summary'      => $new_description,
					),
				)
			)
		);
		$request->set_header( 'Content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$friend_user = User::get_user_by_id( $this->friend_id );

		$this->assertNotEquals( $old_description, $new_description );
		$this->assertEquals( $friend_user->description, $new_description );
	}

	public function test_incoming_update_post() {
		$this->friend->update_user_option( 'activitypub_friends_show_replies', '1' );
		$now = time() - 10;
		$status_id = 123;

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$post_count = count( $posts );

		// Let's post a new Note through the REST API.
		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;
		$content = 'Test ' . $date . ' ' . wp_rand();
		$attachment_url = 'https://mastodon.local/files/original/1234.png';
		$attachment_width = 400;
		$attachment_height = 600;

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_body(
			wp_json_encode(
				array(
					'type'   => 'Create',
					'id'     => $id,
					'actor'  => $this->actor,
					'object' => array(
						'type'         => 'Note',
						'id'           => $id,
						'attributedTo' => $this->actor,
						'inReplyTo'    => null,
						'content'      => $content,
						'url'          => 'https://mastodon.local/users/akirk/statuses/' . $status_id,
						'published'    => $date,
						'attachment'   => array(
							array(
								'type'      => 'Document',
								'mediaType' => 'image/png',
								'url'       => $attachment_url,
								'name'      => '',
								'blurhash'  => '',
								'width'     => $attachment_width,
								'height'    => $attachment_height,

							),
						),
					),
				)
			)
		);
		$request->set_header( 'Content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringStartsWith( $content, $posts[0]->post_content );
		$this->assertStringContainsString( '<img src="' . esc_url( $attachment_url ) . '" width="' . esc_attr( $attachment_width ) . '" height="' . esc_attr( $attachment_height ) . '"', $posts[0]->post_content );

		// Update the post
		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;
		$updated_content = 'Test ' . $date . ' ' . wp_rand();

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_body(
			wp_json_encode(
				array(
					'type'   => 'Update',
					'id'     => $id . '#update',
					'actor'  => 'https://mastodon.local/@akirk',

					'object' =>
					array(
						'type'         => 'Note',
						'id'           => $id,
						'attributedTo' => 'https://mastodon.local/@akirk',
						'inReplyTo'    => null,
						'content'      => $updated_content,
						'url'          => 'https://mastodon.local/users/akirk/statuses/' . $status_id,
						'published'    => $date,
					),
				)
			)
		);

		$request->set_header( 'Content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertEquals( $updated_content, $posts[0]->post_content );
		$this->assertEquals( $this->friend_name, get_post_meta( $posts[0]->ID, 'author', true ) );

		$this->friend->delete_user_option( 'activitypub_friends_show_replies' );
	}

	public function test_incoming_mention_of_others() {
		$now = time() - 10;
		$status_id = 123;

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$post_count = count( $posts );

		// Let's post a new Note through the REST API.
		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;
		$content = '<a rel="mention" class="u-url mention" href="https://example.org/users/abc">@<span>abc</span></a> Test ' . $date . ' ' . wp_rand();

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_body(
			wp_json_encode(
				array(
					'type'   => 'Create',
					'id'     => $id,
					'actor'  => $this->actor,

					'object' =>
					array(
						'type'         => 'Note',
						'id'           => $id,
						'attributedTo' => $this->actor,
						'inReplyTo'    => null,
						'content'      => $content,
						'url'          => 'https://mastodon.local/users/akirk/statuses/' . ( $status_id++ ),
						'published'    => $date,
					),
				)
			)
		);
		$request->set_header( 'Content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT, 'post_status' => 'trash' ) ) );

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringStartsWith( $content, $posts[0]->post_content );
	}

	public function test_incoming_announce() {
		$now = time() - 10;
		$status_id = 123;

		self::$users['https://notiz.blog/author/matthias-pfefferle/'] = array(
			'id'   => 'https://notiz.blog/author/matthias-pfefferle/',
			'url'  => 'https://notiz.blog/author/matthias-pfefferle/',
			'name' => 'Matthias Pfefferle',
		);

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$post_count = count( $posts );

		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;

		$object = 'https://notiz.blog/2022/11/14/the-at-protocol/';

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_body(
			wp_json_encode(
				array(
					'type'      => 'Announce',
					'id'        => $id,
					'actor'     => $this->actor,
					'published' => $date,
					'object'    => $object,
				)
			)
		);
		$request->set_header( 'Content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$p = wp_parse_url( $object );
		$cache = __DIR__ . '/fixtures/' . sanitize_title( $p['host'] . '-' . $p['path'] ) . '.json';
		$this->assertFileExists( $cache );

		$object = json_decode( wp_remote_retrieve_body( json_decode( file_get_contents( $cache ), true ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringContainsString( 'Dezentrale Netzwerke', $posts[0]->post_content );
		$this->assertEquals( 'Matthias Pfefferle', get_post_meta( $posts[0]->ID, 'author', true ) );
	}

	public function test_friend_mentions() {
		add_filter( 'activitypub_cache_possible_friend_mentions', '__return_false' );
		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => '@' . sanitize_title( $this->friend_nicename ) . '  hello',
				'post_status'  => 'publish',
			)
		);

		$activitypub_post = new \Activitypub\Transformer\Post( get_post( $post_id ) );
		$object = $activitypub_post->to_object();

		$tags = $object->get_tag();
		if ( ! $tags ) {
			$tags = array();
		}

		$this->assertContains(
			array(
				'type' => 'Mention',
				'href' => $this->actor,
				'name' => '@' . $this->friend_nicename,
			),
			$tags
		);

		$this->assertContains( \get_rest_url( null, '/activitypub/1.0/users/1/followers' ), $object->get_cc() );
		$this->assertContains( $this->actor, $object->get_cc() );

		remove_all_filters( 'activitypub_from_post_object' );
		remove_all_filters( 'activitypub_cache_possible_friend_mentions' );

		\wp_trash_post( $post_id );
	}

	public function test_dont_override_activitypub_mentions() {
		add_filter( 'activitypub_cache_possible_friend_mentions', '__return_false' );
		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => '@' . sanitize_title( $this->friend_nicename ) . '@mastodon.social  hello',
				'post_status'  => 'publish',
			)
		);

		$activitypub_post = new \Activitypub\Transformer\Post( get_post( $post_id ) );
		$object = $activitypub_post->to_object();

		$tags = $object->get_tag();
		if ( ! $tags ) {
			$tags = array();
		}
		$this->assertNotContains(
			array(
				'type' => 'Mention',
				'href' => $this->actor,
				'name' => '@' . $this->friend_nicename,
			),
			$tags
		);

		$this->assertContains( \get_rest_url( null, '/activitypub/1.0/users/1/followers' ), $object->get_cc() );
		$this->assertNotContains( $this->actor, $object->get_cc() );

		remove_all_filters( 'activitypub_from_post_object' );
		remove_all_filters( 'activitypub_cache_possible_friend_mentions' );

		\wp_trash_post( $post_id );
	}

	/**
	 * Test whether the example domains are skipped.
	 */
	public function test_feed_details() {
		$friends = Friends::get_instance();
		$friend = User::get_user_by_id( $this->friend_id );
		$feeds = $friend->get_feeds();
		$feed = array_pop( $feeds );

		$parser = $friends->feed->get_feed_parser( $feed->get_parser() );

		$details = $parser->update_feed_details(
			array(
				'url' => $feed->get_url(),
			)
		);

		$this->assertEquals( 'https://mastodon.local/users/akirk', $details['url'] );
		$this->assertEquals( 'Alex Kirk', $details['title'] );
		$this->assertEquals( 'https://mastodon.local/users/akirk.png', $details['avatar'] );
		$this->assertEquals( 'akirk.mastodon.local', $details['suggested-username'] );
	}

	public function test_feed_details_suggested_username_with_emoji_display_name() {
		$actor_url = 'https://mastodon.local/users/dcoder';
		self::$users[ $actor_url ] = array(
			'id'                => $actor_url,
			'url'               => $actor_url,
			'name'              => 'DCoder 🇱🇹❤🇺🇦',
			'preferredUsername' => 'dcoder',
			'icon'              => array(
				'type' => 'Image',
				'url'  => $actor_url . '.png',
			),
		);

		$friends = Friends::get_instance();
		$friend = User::get_user_by_id( $this->friend_id );
		$feeds = $friend->get_feeds();
		$feed = array_pop( $feeds );
		$parser = $friends->feed->get_feed_parser( $feed->get_parser() );

		$details = $parser->update_feed_details(
			array(
				'url' => $actor_url,
			)
		);

		$this->assertEquals( 'DCoder 🇱🇹❤🇺🇦', $details['title'] );
		$this->assertEquals( 'dcoder.mastodon.local', $details['suggested-username'] );
	}

	public function test_feed_details_suggested_username_with_cyrillic_display_name() {
		$actor_url = 'https://mastodon.local/users/roskomsvoboda';
		self::$users[ $actor_url ] = array(
			'id'                => $actor_url,
			'url'               => $actor_url,
			'name'              => 'РосКомСвобода',
			'preferredUsername' => 'roskomsvoboda',
			'icon'              => array(
				'type' => 'Image',
				'url'  => $actor_url . '.png',
			),
		);

		$friends = Friends::get_instance();
		$friend = User::get_user_by_id( $this->friend_id );
		$feeds = $friend->get_feeds();
		$feed = array_pop( $feeds );
		$parser = $friends->feed->get_feed_parser( $feed->get_parser() );

		$details = $parser->update_feed_details(
			array(
				'url' => $actor_url,
			)
		);

		$this->assertEquals( 'РосКомСвобода', $details['title'] );
		$this->assertEquals( 'roskomsvoboda.mastodon.local', $details['suggested-username'] );
	}

	public function test_feed_details_suggested_username_with_special_chars_display_name() {
		$actor_url = 'https://mastodon.local/users/sidler';
		self::$users[ $actor_url ] = array(
			'id'                => $actor_url,
			'url'               => $actor_url,
			'name'              => 'D:\\side\\>:idle:',
			'preferredUsername' => 'sidler',
			'icon'              => array(
				'type' => 'Image',
				'url'  => $actor_url . '.png',
			),
		);

		$friends = Friends::get_instance();
		$friend = User::get_user_by_id( $this->friend_id );
		$feeds = $friend->get_feeds();
		$feed = array_pop( $feeds );
		$parser = $friends->feed->get_feed_parser( $feed->get_parser() );

		$details = $parser->update_feed_details(
			array(
				'url' => $actor_url,
			)
		);

		$this->assertEquals( 'sidler.mastodon.local', $details['suggested-username'] );
	}

	/**
	 * Test whether the example domains are skipped.
	 *
	 * @param string $actor The actor.
	 * @param string $domain The domain.
	 * @param string $username The username.
	 *
	 * @dataProvider example_actors
	 */
	public function test_get_example_metadata_by_actor( $actor, $domain, $username ) {
		add_filter( 'pre_http_request', array( $this, 'invalid_http_response' ), 8, 3 );
		$metadata = \ActivityPub\get_remote_metadata_by_actor( $actor );
		$this->assertEquals( sprintf( 'https://%s/users/%s/', $domain, $username ), $metadata['url'], $actor );
		$this->assertEquals( $username, $metadata['name'], $actor );
		remove_filter( 'pre_http_request', array( $this, 'invalid_http_response' ), 8, 3 );
	}

	public static function is_known_activitypub_test_host( $is_known, $host ) {
		// The test actor is on mastodon.local; recognize mastodon.* as known so
		// convert_actor_to_mastodon_handle() resolves /users/username paths correctly.
		if ( str_starts_with( $host, 'mastodon.' ) ) {
			return true;
		}
		return $is_known;
	}

	public function set_up() {
		if ( ! class_exists( '\Activitypub\Activitypub' ) ) {
			return $this->markTestSkipped( 'The Activitypub plugin is not loaded.' );
		}
		parent::set_up();

		add_filter( 'activitypub_defer_signature_verification', '__return_true' );
		add_filter( 'friends_is_known_activitypub_host', array( get_called_class(), 'is_known_activitypub_test_host' ), 10, 2 );
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'friends_get_activitypub_metadata' ), 10, 2 );
		add_filter( 'activitypub_pre_http_get_remote_object', array( get_called_class(), 'friends_get_activitypub_metadata' ), 10, 2 );
		add_filter( 'friends_get_activitypub_metadata', array( get_called_class(), 'friends_get_activitypub_metadata' ), 5, 2 );
		add_filter( 'pre_friends_webfinger_resolve', array( get_called_class(), 'pre_friends_webfinger_resolve' ), 5, 2 );

		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		$this->friend_name = 'Alex Kirk';
		$this->actor = 'https://mastodon.local/users/akirk';

		$user_feed = User_Feed::get_by_url( $this->actor );
		if ( is_wp_error( $user_feed ) ) {
			$friend = User::create( 'akirk.blog', 'subscription', '', $this->friend_name );
			$friend->save_feed(
				$this->actor,
				array(
					'parser' => 'activitypub',
					'active' => true,
				)
			);
		} else {
			$friend = $user_feed->get_friend_user();
		}

		$this->friend = $friend;
		$this->friend_id = $friend->ID;
		$this->friend_nicename = $friend->user_nicename;
		if ( ! $this->friend_nicename ) {
			$this->friend_nicename = $friend->user_login;
		}
		$this->friend_nicename = sanitize_title( $this->friend_nicename );

		self::$users[ $this->actor ] = array(
			'id'                => $this->actor,
			'url'               => $this->actor,
			'name'              => $this->friend_name,
			'preferredUsername' => 'akirk',
			'icon'              => array(
				'type' => 'Image',
				'url'  => $this->actor . '.png',
			),
		);
		self::$users['https://mastodon.local/@akirk'] = self::$users[ $this->actor ];

		_delete_all_posts();
	}

	public function tear_down() {
		remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
		remove_filter( 'friends_is_known_activitypub_host', array( get_called_class(), 'is_known_activitypub_test_host' ), 10, 2 );
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'friends_get_activitypub_metadata' ), 10, 2 );
		remove_filter( 'activitypub_pre_http_get_remote_object', array( get_called_class(), 'friends_get_activitypub_metadata' ), 10, 2 );
		remove_filter( 'friends_get_activitypub_metadata', array( get_called_class(), 'friends_get_activitypub_metadata' ), 5, 2 );
		remove_filter( 'pre_friends_webfinger_resolve', array( get_called_class(), 'pre_friends_webfinger_resolve' ), 5, 2 );
		remove_filter( 'pre_http_request', array( $this, 'invalid_http_response' ), 8 );
		parent::tear_down();
	}

	public static function friends_get_activitypub_metadata( $ret, $url ) {
		if ( isset( self::$users[ $url ] ) ) {
			return self::$users[ $url ];
		}
		return $ret;
	}

	public static function pre_friends_webfinger_resolve( $ret, $url ) {
		if ( preg_match( '/^@?' . Feed_Parser_ActivityPub::ACTIVITYPUB_USERNAME_REGEXP . '$/i', $url, $m ) ) {
			$url = 'https://' . $m[2] . '/@' . $m[1];
		}
		if ( isset( self::$users[ $url ] ) ) {
			return self::$users[ $url ]['url'];
		}
		return $ret;
	}

	private function mock_local_user_activitypub_metadata( $local_user ) {
		$current_user = get_userdata( $local_user );
		$local_activitypub_id = get_author_posts_url( $local_user );

		self::$users[ $local_activitypub_id ] = array(
			'id'                => $local_activitypub_id,
			'url'               => $local_activitypub_id,
			'name'              => $current_user->display_name,
			'preferredUsername' => $current_user->user_login,
			'icon'              => array(
				'type' => 'Image',
				'url'  => get_avatar_url( $current_user->ID ),
			),
		);

		return $local_activitypub_id;
	}

	public function invalid_http_response() {
		return $this->assertTrue( false ); // should not be called.
	}

	public function test_direct_message_is_added_to_activitypub_outbox() {
		$post_id = Friends::get_instance()->messages->send_message( $this->friend, $this->actor, 'Hello by DM.' );

		$this->assertIsInt( $post_id );
		$outbox_id = get_post_meta( $post_id, 'activitypub_direct_message_outbox_id', true );
		$this->assertNotEmpty( $outbox_id );

		$outbox_item = get_post( $outbox_id );
		$this->assertInstanceOf( \WP_Post::class, $outbox_item );
		$this->assertSame( 'ap_outbox', $outbox_item->post_type );
		$this->assertSame( 'pending', $outbox_item->post_status );
		$this->assertSame( 'Create', get_post_meta( $outbox_id, '_activitypub_activity_type', true ) );
		$this->assertSame( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, get_post_meta( $outbox_id, 'activitypub_content_visibility', true ) );

		$activity = json_decode( $outbox_item->post_content, true );
		$this->assertSame( 'Create', $activity['type'] );
		$this->assertContains( $this->actor, $activity['to'] );
		$this->assertContains( $this->actor, $activity['object']['to'] );
		$this->assertStringContainsString( '@akirk', $activity['object']['content'] );
	}

	public function test_direct_message_reply_outbox_activity_has_in_reply_to() {
		$reply_to_post_id = $this->friend->insert_post(
			array(
				'post_type'    => Messages::CPT,
				'post_content' => 'Hello from the fediverse.',
				'post_status'  => 'friends_read',
				'guid'         => $this->actor . '/statuses/123',
			)
		);

		$post_id = Friends::get_instance()->messages->send_message( $this->friend, $this->actor, 'Reply by DM.', '', $reply_to_post_id );

		$this->assertIsInt( $post_id );
		$outbox_id = get_post_meta( $post_id, 'activitypub_direct_message_outbox_id', true );
		$activity  = json_decode( get_post( $outbox_id )->post_content, true );

		$this->assertSame( $this->actor . '/statuses/123', $activity['object']['inReplyTo'] );
	}

	public function example_actors() {
		$actors = array();
		foreach ( array( 'user', 'test' ) as $username ) {
			foreach ( array( 'example.org', 'example.net', 'example2.com', 'my-domain.com', 'your-domain.org', 'test.net' ) as $domain ) {
				foreach ( array( '@', '' ) as $leading_at ) {
					$actors[] = array( $leading_at . $username . '@' . $domain, $domain, $username );
				}
				$actors[] = array( sprintf( 'https://%s/users/%s/', $domain, $username ), $domain, $username );
				$actors[] = array( sprintf( 'https://%s/users/%s', $domain, $username ), $domain, $username );
				$actors[] = array( sprintf( 'https://%s/@%s', $domain, $username ), $domain, $username );
			}
		}

		$actors[] = array( 'example@abc.org', 'abc.org', 'example' );

		return $actors;
	}

	public function test_auto_tagging_hashtags_and_mentions() {
		delete_option( 'friends_disable_auto_tagging' );

		$now = time() - 10;
		$status_id = 456;

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$post_count = count( $posts );

		$local_user = get_current_user_id();
		$current_user = get_userdata( $local_user );
		$local_activitypub_id = $this->mock_local_user_activitypub_metadata( $local_user );

		// Create ActivityPub post with hashtags and CC mention
		$date = gmdate( \DATE_W3C, $now++ );
		$id = $this->actor . '/status/' . $status_id;
		$content = 'Testing #hashtag and #another-tag functionality!';

		$activity_data = array(
			'type'   => 'Create',
			'id'     => $id,
			'actor'  => $this->actor,
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'     => array( $local_activitypub_id ),
			'object' => array(
				'id'           => $id,
				'type'         => 'Note',
				'published'    => $date,
				'attributedTo' => $this->actor,
				'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
				'content'      => $content,
				'cc'           => array( $local_activitypub_id ),
				'tag'          => array(
					array(
						'type' => 'Hashtag',
						'name' => '#hashtag',
						'href' => 'https://mastodon.local/tags/hashtag',
					),
					array(
						'type' => 'Hashtag',
						'name' => '#another-tag',
						'href' => 'https://mastodon.local/tags/another-tag',
					),
					array(
						'type' => 'Mention',
						'href' => $local_activitypub_id,
						'name' => '@' . $current_user->user_login,
					),
				),
			),
		);
		
		$response = $this->receive_activity( get_current_user_id(), $activity_data, true );

		$new_posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$this->assertEquals( $post_count + 1, count( $new_posts ) );
		$this->assertStringContainsString( $content, $new_posts[0]->post_content );

		// Check that hashtags are applied
		$tags = wp_get_post_terms( $new_posts[0]->ID, Friends::TAG_TAXONOMY );
		$tag_names = wp_list_pluck( $tags, 'name' );
		
		$this->assertContains( 'hashtag', $tag_names );
		$this->assertContains( 'another-tag', $tag_names );
		
		// Check that mention tag is applied (should be mention-{current_user_login})
		$expected_mention_tag = 'mention-' . $current_user->user_login;
		$this->assertContains( $expected_mention_tag, $tag_names );
	}

	public function test_auto_tagging_respects_disable_option() {
		update_option( 'friends_disable_auto_tagging', true );

		$now = time() - 10;
		$status_id = 789;

		$local_user = get_current_user_id();
		
		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$post_count = count( $posts );

		$current_user = get_userdata( $local_user );
		$local_activitypub_id = $this->mock_local_user_activitypub_metadata( $local_user );

		$date = gmdate( \DATE_W3C, $now++ );
		$id = $this->actor . '/status/' . $status_id;
		$content = 'Testing #disabled-hashtag when auto-tagging is disabled!';

		$activity_data = array(
			'type'   => 'Create',
			'id'     => $id,
			'actor'  => $this->actor,
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'     => array( $local_activitypub_id ),
			'object' => array(
				'id'           => $id,
				'type'         => 'Note',
				'published'    => $date,
				'attributedTo' => $this->actor,
				'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
				'content'      => $content,
				'cc'           => array( $local_activitypub_id ),
				'tag'          => array(
					array(
						'type' => 'Hashtag',
						'name' => '#disabled-hashtag',
						'href' => 'https://mastodon.local/tags/disabled-hashtag',
					),
					array(
						'type' => 'Mention',
						'href' => $local_activitypub_id,
						'name' => '@' . $current_user->user_login,
					),
				),
			),
		);
		
		$response = $this->receive_activity( $local_user, $activity_data );

		$posts = get_posts( $this->friend->modify_get_posts_args_by_author( array( 'post_type' => Friends::CPT ) ) );

		$this->assertEquals( $post_count + 1, count( $posts ) );

		// Check that hashtags are NOT applied but mentions still are
		$tags = wp_get_post_terms( $posts[0]->ID, Friends::TAG_TAXONOMY );
		$tag_names = wp_list_pluck( $tags, 'name' );
		
		$this->assertNotContains( 'disabled-hashtag', $tag_names );
		
		// Mentions should still work even when auto-tagging is disabled
		$expected_mention_tag = 'mention-' . $current_user->user_login;
		$this->assertContains( $expected_mention_tag, $tag_names );

		// Clean up
		delete_option( 'friends_disable_auto_tagging' );
	}

	public function test_direct_message_from_unknown_actor_is_saved() {
		$unknown_actor = 'https://mastodon.local/users/unknown-dm-sender';
		self::$users[ $unknown_actor ] = array(
			'id'                => $unknown_actor,
			'url'               => $unknown_actor,
			'name'              => 'Unknown DM Sender',
			'preferredUsername' => 'unknown-dm-sender',
			'icon'              => array(
				'type' => 'Image',
				'url'  => $unknown_actor . '.png',
			),
		);

		$local_user = get_current_user_id();
		$local_activitypub_id = $this->mock_local_user_activitypub_metadata( $local_user );
		$date = gmdate( \DATE_W3C, time() - 10 );
		$id = $unknown_actor . '/statuses/direct-message';
		$content = 'Hello from someone you do not follow.';

		$parser = Friends::get_instance()->feed->get_feed_parser( Feed_Parser_ActivityPub::SLUG );
		$parser->handle_received_direct_message(
			array(
				'type'   => 'Create',
				'id'     => $id . '/activity',
				'actor'  => $unknown_actor,
				'to'     => array( $local_activitypub_id ),
				'object' => array(
					'id'           => $id,
					'type'         => 'Note',
					'published'    => $date,
					'attributedTo' => $unknown_actor,
					'to'           => array( $local_activitypub_id ),
					'content'      => $content,
				),
			),
			$local_user
		);

		$user_feed = User_Feed::get_by_url( $unknown_actor );
		$this->assertInstanceof( User_Feed::class, $user_feed );
		$this->assertFalse( $user_feed->is_active(), 'Receiving a DM must not follow the sender.' );

		$friend_user = $user_feed->get_friend_user();
		$messages = get_posts(
			array(
				'post_type'   => Messages::CPT,
				'post_status' => 'friends_unread',
				'numberposts' => -1,
			)
		);

		$this->assertCount( 1, $messages );
		$this->assertSame( $content, $messages[0]->post_content );
		$this->assertSame( $id, $messages[0]->guid );
		$this->assertSame( (int) $friend_user->ID, (int) User::get_post_author( $messages[0] )->ID );
		$this->assertSame( $unknown_actor, get_post_meta( $messages[0]->ID, 'friends_feed_url', true ) );
	}

	public function test_local_comment_urls_do_not_resolve_to_posts() {
		$parser = Friends::get_instance()->feed->get_feed_parser( Feed_Parser_ActivityPub::SLUG );

		$this->assertSame( '', $parser->fix_comment_url_to_postid( home_url( '/?c=123' ) ) );
		$this->assertSame( '', $parser->fix_comment_url_to_postid( home_url( '/friends/?foo=bar&c=123' ) ) );
		$this->assertSame( home_url( '/friends/?post=123' ), $parser->fix_comment_url_to_postid( home_url( '/friends/?post=123' ) ) );
	}

	public function test_incoming_reply_to_local_comment_is_deferred_to_activitypub_comments() {
		$post_id = $this->friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Cached ActivityPub post.',
				'post_status'  => 'publish',
				'guid'         => 'https://mastodon.local/users/akirk/statuses/123456',
				'meta_input'   => array(
					Feed_Parser_ActivityPub::SLUG => array(
						'attributedTo' => array(
							'id' => $this->actor,
						),
					),
				),
			)
		);
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'Local comment.',
				'comment_type'     => 'comment',
				'user_id'          => get_current_user_id(),
				'comment_approved' => 1,
			)
		);

		$parser = Friends::get_instance()->feed->get_feed_parser( Feed_Parser_ActivityPub::SLUG );
		$result = $parser->handle_received_activity(
			array(
				'type'   => 'Create',
				'id'     => $this->actor . '/statuses/reply/activity',
				'actor'  => $this->actor,
				'object' => array(
					'type'         => 'Note',
					'id'           => $this->actor . '/statuses/reply',
					'attributedTo' => $this->actor,
					'inReplyTo'    => home_url( '/?c=' . $comment_id ),
					'content'      => 'Reply to the local comment.',
					'url'          => $this->actor . '/statuses/reply',
					'published'    => gmdate( \DATE_W3C, time() - 10 ),
				),
			),
			get_current_user_id(),
			'create'
		);

		$this->assertFalse( $result );
	}

	public function test_activitypub_can_persist_reply_to_local_comment_on_cached_post() {
		if ( ! class_exists( '\Activitypub\Handler\Create' ) ) {
			$this->markTestSkipped( 'The ActivityPub Create handler is not available.' );
		}

		$post_id = $this->friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Cached ActivityPub post.',
				'post_status'  => 'publish',
				'guid'         => 'https://mastodon.local/users/akirk/statuses/123456',
				'meta_input'   => array(
					Feed_Parser_ActivityPub::SLUG => array(
						'attributedTo' => array(
							'id' => $this->actor,
						),
					),
				),
			)
		);
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'Local comment.',
				'comment_type'     => 'comment',
				'user_id'          => get_current_user_id(),
				'comment_approved' => 1,
			)
		);
		$reply_id = $this->actor . '/statuses/reply';
		$this->assertSame( (int) $comment_id, (int) \Activitypub\url_to_commentid( home_url( '/?c=' . $comment_id ) ) );

		$activity = array(
			'type'   => 'Create',
			'id'     => $reply_id . '/activity',
			'actor'  => $this->actor,
			'object' => array(
				'type'         => 'Note',
				'id'           => $reply_id,
				'attributedTo' => $this->actor,
				'inReplyTo'    => home_url( '/?c=' . $comment_id ),
				'content'      => 'Reply to the local comment.',
				'url'          => $reply_id,
				'published'    => gmdate( \DATE_W3C, time() - 10 ),
			),
		);
		$handler = function ( $activity, $user_ids, $activity_object ) {
			\Activitypub\Handler\Create::create_interaction( $activity, $user_ids, $activity_object );
		};
		add_action( 'activitypub_handled_inbox_create', $handler, 10, 3 );
		do_action( 'activitypub_handled_inbox_create', $activity, array( get_current_user_id() ), null );
		remove_action( 'activitypub_handled_inbox_create', $handler, 10 );

		$replies = get_comments(
			array(
				'post_id' => $post_id,
				'parent'  => $comment_id,
			)
		);

		$this->assertCount( 1, $replies );
		$reply = reset( $replies );
		$this->assertSame( (int) $post_id, (int) $reply->comment_post_ID );
		$this->assertSame( (int) $comment_id, (int) $reply->comment_parent );
	}

	public function test_comment_reply_link_uses_local_friends_url() {
		$remote_post_url = 'https://mastodon.local/users/akirk/statuses/123456';
		$post_id = $this->friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Cached ActivityPub post.',
				'post_status'  => 'publish',
				'guid'         => $remote_post_url,
			)
		);
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'Local comment.',
				'comment_type'     => 'comment',
				'user_id'          => get_current_user_id(),
				'comment_approved' => 1,
			)
		);

		$parser = Friends::get_instance()->feed->get_feed_parser( Feed_Parser_ActivityPub::SLUG );
		$link = $parser->fix_comment_reply_link(
			'<a href="' . esc_url( $remote_post_url ) . '?replytocom=' . $comment_id . '#respond">Reply</a>',
			array(),
			get_comment( $comment_id ),
			get_post( $post_id )
		);

		$this->assertStringContainsString( $this->friend->get_local_friends_page_url( $post_id ), $link );
		$this->assertStringNotContainsString( $remote_post_url, $link );
	}

	public function test_get_remote_comments_skips_existing_local_comments() {
		$post_id = $this->friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Cached ActivityPub post.',
				'post_status'  => 'publish',
				'guid'         => 'https://mastodon.local/users/akirk/statuses/123456',
				'meta_input'   => array(
					Feed_Parser_ActivityPub::SLUG => array(
						'attributedTo' => array(
							'id' => $this->actor,
						),
					),
				),
			)
		);
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'Already stored locally.',
				'comment_type'     => 'comment',
				'user_id'          => get_current_user_id(),
				'comment_approved' => 1,
			)
		);
		add_comment_meta( $comment_id, 'source_id', 'remote-comment-1' );
		$existing_comment = get_comment( $comment_id );

		$filter = function ( $context ) {
			$status = (object) array(
				'id'             => 'remote-comment-1',
				'uri'            => 'https://mastodon.local/users/akirk/statuses/remote-comment-1',
				'account'        => (object) array(
					'display_name' => 'Alex Kirk',
					'url'          => $this->actor,
					'acct'         => 'akirk@mastodon.local',
				),
				'content'        => 'Already stored locally.',
				'created_at'     => new \DateTimeImmutable( '2026-01-01 00:00:00' ),
				'in_reply_to_id' => null,
			);

			$context['descendants'] = array( $status );
			return $context;
		};
		add_filter(
			'mastodon_api_status_context',
			$filter
		);

		$parser = Friends::get_instance()->feed->get_feed_parser( Feed_Parser_ActivityPub::SLUG );
		$comments = $parser->get_remote_comments( array( $existing_comment ), $post_id );
		remove_filter( 'mastodon_api_status_context', $filter );

		$this->assertCount( 1, $comments );
		$this->assertSame( (int) $comment_id, (int) $comments[0]->comment_ID );
	}

	public function test_comment_on_cached_post_federation() {
		$remote_post_url = 'https://mastodon.local/users/akirk/statuses/123456';

		// Create a cached friend post with a known remote GUID.
		$post_id = $this->friend->insert_post(
			array(
				'post_type'    => Friends::CPT,
				'post_content' => 'Hello from the fediverse!',
				'post_status'  => 'publish',
				'guid'         => $remote_post_url,
				'meta_input'   => array(
					'activitypub' => array(
						'attributedTo' => array(
							'id'                => $this->actor,
							'preferredUsername' => 'akirk',
							'name'              => $this->friend_name,
						),
					),
				),
			)
		);
		$this->assertIsInt( $post_id );
		$this->assertEmpty( get_post_meta( $post_id, 'activitypub_status', true ) );

		// Verify get_permalink returns the remote URL (via Friends' post_type_link filter).
		$permalink = get_permalink( $post_id );
		$this->assertEquals( $remote_post_url, $permalink, 'get_permalink should return the remote GUID for friend_post_cache posts' );

		// Submit a comment as the current local user.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => '@akirk Nice post!',
				'comment_type'    => 'comment',
				'user_id'         => get_current_user_id(),
				'comment_approved' => 1,
			)
		);
		$this->assertIsInt( $comment_id );
		$this->assertGreaterThan( 0, $comment_id );

		$comment = get_comment( $comment_id );
		$this->assertSame( ACTIVITYPUB_OBJECT_STATE_FEDERATED, get_post_meta( $post_id, 'activitypub_status', true ) );

		// Verify the comment should be federated.
		if ( function_exists( 'Activitypub\should_comment_be_federated' ) ) {
			$this->assertTrue(
				\Activitypub\should_comment_be_federated( $comment ),
				'Comment on a cached post should be marked for federation'
			);
		}

		// Verify the ActivityPub transformer produces the correct inReplyTo.
		if ( class_exists( '\Activitypub\Transformer\Comment' ) ) {
			$transformer = \Activitypub\Transformer\Factory::get_transformer( $comment );
			if ( $transformer && ! is_wp_error( $transformer ) ) {
				$object = $transformer->to_object();
				$this->assertEquals(
					$remote_post_url,
					$object->get_in_reply_to(),
					'inReplyTo should be the remote ActivityPub URL, not the local permalink'
				);
			}
		}

		wp_delete_comment( $comment_id, true );
		wp_delete_post( $post_id, true );
	}

	public function test_incoming_move() {
		$new_url = 'https://example.com/new_url';

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_body(
			wp_json_encode(
				array(
					'type'   => 'Move',
					'id'     => $this->actor . '/move',
					'actor'  => $this->actor,
					'object'  => $this->actor,
					'target' => $new_url,
				)
			)
		);
		$request->set_header( 'Content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$old_user_feed = User_Feed::get_by_url( $this->actor );
		$this->assertTrue( is_wp_error( $old_user_feed ) );

		$new_user_feed = User_Feed::get_by_url( $new_url );
		$this->assertInstanceof( 'Friends\User_Feed', $new_user_feed );
	}
}
