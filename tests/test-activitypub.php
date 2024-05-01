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
	private $friend_id;
	private $friend_name;
	private $friend_nicename;
	private $actor;

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

		$this->assertContains(
			array(
				'type' => 'Mention',
				'href' => $this->actor,
				'name' => '@' . $this->friend_nicename,
			),
			$object->get_tag()
		);

		$this->assertContains( \get_rest_url( null, '/activitypub/1.0/users/1/followers' ), $object->get_to() );
		$this->assertContains( $this->actor, $object->get_cc() );

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
		$metadata = \ActivityPub\get_remote_metadata_by_actor( $actor );
		$this->assertEquals( sprintf( 'https://%s/users/%s/', $domain, $username ), $metadata['url'], $actor );
		$this->assertEquals( $username, $metadata['name'], $actor );
	}

	public function set_up() {
		if ( ! class_exists( '\Activitypub\Activitypub' ) ) {
			return $this->markTestSkipped( 'The Activitypub plugin is not loaded.' );
		}
		parent::set_up();

		add_filter( 'activitypub_defer_signature_verification', '__return_true' );
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'friends_get_activitypub_metadata' ), 10, 2 );
		add_filter( 'friends_get_activitypub_metadata', array( get_called_class(), 'friends_get_activitypub_metadata' ), 5, 2 );

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
			$this->friend_id = $this->factory->user->create(
				array(
					'user_login'   => 'akirk.blog',
					'display_name' => $this->friend_name,
					'nicename'     => $this->friend_nicename,
					'role'         => 'friend',
				)
			);
			$user_feed = User_Feed::save(
				new User( $this->friend_id ),
				$this->actor,
				array(
					'parser' => 'activitypub',
				)
			);
		} else {
			$this->friend_id = $user_feed->get_friend_user()->ID;
		}

		$userdata = get_userdata( $this->friend_id );
		$this->friend_nicename = $userdata->user_nicename;

		self::$users[ $this->actor ] = array(
			'id'   => $this->actor,
			'url'  => $this->actor,
			'name' => $this->friend_name,
			'icon' => array(
				'type' => 'Image',
				'url'  => $this->actor . '.png',
			),
		);
		self::$users['https://mastodon.local/@akirk'] = self::$users[ $this->actor ];

		_delete_all_posts();
	}

	public function tear_down() {
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'friends_get_activitypub_metadata' ), 10, 2 );
		remove_filter( 'friends_get_activitypub_metadata', array( get_called_class(), 'friends_get_activitypub_metadata' ), 5, 2 );
		remove_filter( 'pre_http_request', array( $this, 'invalid_http_response' ), 8 );
		parent::tear_down();
	}

	public static function friends_get_activitypub_metadata( $ret, $url ) {
		if ( isset( self::$users[ $url ] ) ) {
			return self::$users[ $url ];
		}
		return $ret;
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
}
