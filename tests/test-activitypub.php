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

	public function test_incoming_post() {
		update_user_option( 'activitypub_friends_show_replies', '1', $this->friend_id );
		$now = time() - 10;
		$status_id = 123;

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

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

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringStartsWith( $content, $posts[0]->post_content );
		$this->assertStringContainsString( '<img src="' . esc_url( $attachment_url ) . '" width="' . esc_attr( $attachment_width ) . '" height="' . esc_attr( $attachment_height ) . '"', $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );

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

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

		$this->assertEquals( $post_count + 2, count( $posts ) );
		$this->assertEquals( $content, $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );
		$this->assertEquals( $this->friend_name, get_post_meta( $posts[0]->ID, 'author', true ) );

		delete_user_option( 'activitypub_friends_show_replies', $this->friend_id );
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

		$friend_user = $user_feed->get_friend_user();

		$this->assertNotEquals( $old_description, $new_description );
		$this->assertEquals( $friend_user->description, $new_description );
	}

	public function test_incoming_update_post() {
		update_user_option( 'activitypub_friends_show_replies', '1', $this->friend_id );
		$now = time() - 10;
		$status_id = 123;

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

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

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringStartsWith( $content, $posts[0]->post_content );
		$this->assertStringContainsString( '<img src="' . esc_url( $attachment_url ) . '" width="' . esc_attr( $attachment_width ) . '" height="' . esc_attr( $attachment_height ) . '"', $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );

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

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertEquals( $updated_content, $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );
		$this->assertEquals( $this->friend_name, get_post_meta( $posts[0]->ID, 'author', true ) );

		delete_user_option( 'activitypub_friends_show_replies', $this->friend_id );
	}

	public function test_incoming_mention_of_others() {
		$now = time() - 10;
		$status_id = 123;

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

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

		$posts = get_posts(
			array(
				'post_type'   => Friends::CPT,
				'author'      => $this->friend_id,
				'post_status' => 'trash',
			)
		);

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringStartsWith( $content, $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );
	}

	public function test_incoming_announce() {
		$now = time() - 10;
		$status_id = 123;

		self::$users['https://notiz.blog/author/matthias-pfefferle/'] = array(
			'id'   => 'https://notiz.blog/author/matthias-pfefferle/',
			'url'  => 'https://notiz.blog/author/matthias-pfefferle/',
			'name' => 'Matthias Pfefferle',
		);

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

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

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringContainsString( 'Dezentrale Netzwerke', $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );
		$this->assertEquals( 'Matthias Pfefferle', get_post_meta( $posts[0]->ID, 'author', true ) );
	}

	public function test_possible_mentions() {
		add_filter( 'activitypub_cache_possible_friend_mentions', '__return_false' );
		$mentions = \Friends\Feed_Parser_ActivityPub::get_possible_mentions();
		$this->assertContains( \get_author_posts_url( get_current_user_id() ), $mentions );

		remove_all_filters( 'activitypub_from_post_object' );
		remove_all_filters( 'activitypub_cache_possible_friend_mentions' );
	}

	public function test_friend_mentions() {
		add_filter( 'activitypub_cache_possible_friend_mentions', '__return_false' );
		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => '@' . sanitize_title( $this->friend_nicename ) . '  hello',
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
		$friend = new User( $this->friend_id );
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
		$metadata = \Activitypub\get_remote_metadata_by_actor( $actor );
		$this->assertEquals( sprintf( 'https://%s/users/%s/', $domain, $username ), $metadata['url'], $actor );
		$this->assertEquals( $username, $metadata['name'], $actor );
		remove_filter( 'pre_http_request', array( $this, 'invalid_http_response' ), 8, 3 );
	}

	public function set_up() {
		if ( ! class_exists( '\Activitypub\Activitypub' ) ) {
			return $this->markTestSkipped( 'The Activitypub plugin is not loaded.' );
		}
		parent::set_up();

		add_filter( 'activitypub_defer_signature_verification', '__return_true' );
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
			$friend = User::create( 'akirk.blog', 'subscription', '', $this->friend_name, null, null, null, true );
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

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

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

		$new_posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

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
		
		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

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

		$posts = get_posts(
			array(
				'post_type' => Friends::CPT,
				'author'    => $this->friend_id,
			)
		);

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
