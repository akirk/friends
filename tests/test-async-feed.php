<?php
/**
 * Test asynchronous feed loading.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Feed loading regression tests.
 */
class AsyncFeedTest extends \WP_UnitTestCase {
	/**
	 * Subscription whose posts populate the feed.
	 *
	 * @var User
	 */
	private $friend;

	/**
	 * Prepare an administrator and a subscription.
	 */
	public function set_up() {
		parent::set_up();
		$frontend = Friends::get_instance()->frontend;
		$frontend->author = false;
		$frontend->post_format = false;
		$frontend->template = false;
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->friend = User::create( 'async.example', 'subscription', 'https://async.example/' );
		update_user_option( get_current_user_id(), 'friends_frontend_theme', 'default' );
	}

	/**
	 * Restore the main query after AJAX requests.
	 */
	public function tear_down() {
		wp_reset_query();
		parent::tear_down();
	}

	/**
	 * The page shell must not execute the feed SQL or count all matching posts.
	 */
	public function test_initial_page_skips_feed_query() {
		$this->create_posts( 1 );
		$queries = array();
		$record = function ( $sql ) use ( &$queries ) {
			if ( false !== strpos( $sql, 'friend_post_cache' ) && preg_match( '/^SELECT/i', $sql ) ) {
				$queries[] = $sql;
			}
			return $sql;
		};
		add_filter( 'query', $record );
		$this->go_to( home_url( '/friends/' ) );
		remove_filter( 'query', $record );

		global $wp_query;
		$this->assertTrue( Friends::get_instance()->frontend->is_feed_deferred() );
		$this->assertSame( array(), $wp_query->posts );
		$this->assertEmpty( $queries );
		$this->assertTrue( $wp_query->get( 'no_found_rows' ) );
		$this->assertFalse( is_404() );
	}

	/**
	 * The fallback returns actual posts rather than a loading state.
	 */
	public function test_synchronous_fallback() {
		$this->create_posts( 1 );
		$_GET['friends_sync'] = '1';
		try {
			$this->go_to( home_url( '/friends/?friends_sync=1' ) );
			$this->assertFalse( Friends::get_instance()->frontend->is_feed_deferred() );
			$this->assertTrue( have_posts() );
		} finally {
			unset( $_GET['friends_sync'] );
		}
	}

	/**
	 * Cursor pagination must return every post once without total-row counts.
	 */
	public function test_pagination_has_no_duplicates_or_total_count() {
		$ids = $this->create_posts( 5 );
		$received_ids = array();
		$cursor = null;
		for ( $page = 0; $page < 3; ++$page ) {
			$result = $this->request_feed( $this->query_vars(), $page, false, null, $cursor );
			$cursor = $result['data'];
			$this->assertTrue( $result['success'] );
			$this->assertSame( $page + 1, $result['data']['page'] );
			$this->assertSame( $page < 2, $result['data']['has_more'] );
			global $wp_query;
			$this->assertTrue( $wp_query->get( 'no_found_rows' ) );
			$this->assertSame( 0, $wp_query->found_posts );
			$this->assertSame( 0, $wp_query->get( 'offset' ) );
			$received_ids = array_merge( $received_ids, wp_list_pluck( $wp_query->posts, 'ID' ) );
		}
		$this->assertSame( array_reverse( $ids ), $received_ids );
	}

	/**
	 * Posts with the same date need a stable order across page boundaries.
	 */
	public function test_pagination_with_equal_dates() {
		$ids = $this->create_posts( 5 );
		foreach ( $ids as $post_id ) {
			wp_update_post( array( 'ID' => $post_id, 'post_date' => '2020-01-01 12:00:00' ) );
		}
		$received_ids = array();
		$cursor = null;
		for ( $page = 0; $page < 3; ++$page ) {
			$result = $this->request_feed( $this->query_vars(), $page, false, null, $cursor );
			$cursor = $result['data'];
			global $wp_query;
			$received_ids = array_merge( $received_ids, wp_list_pluck( $wp_query->posts, 'ID' ) );
		}
		$this->assertSame( array_reverse( $ids ), $received_ids );
	}

	/**
	 * Ascending feeds resume after, rather than before, the cursor.
	 */
	public function test_ascending_cursor_pagination() {
		$ids = $this->create_posts( 5 );
		$vars = $this->query_vars();
		$vars['order'] = 'ASC';
		$received_ids = array();
		$cursor = null;
		for ( $page = 0; $page < 3; ++$page ) {
			$result = $this->request_feed( $vars, $page, false, null, $cursor );
			$cursor = $result['data'];
			global $wp_query;
			$received_ids = array_merge( $received_ids, wp_list_pluck( $wp_query->posts, 'ID' ) );
		}
		$this->assertSame( $ids, $received_ids );
	}

	/**
	 * New arrivals must not shift older posts into an already loaded page.
	 */
	public function test_new_posts_do_not_shift_cursor() {
		$ids = $this->create_posts( 5 );
		$first = $this->request_feed( $this->query_vars(), 0 );
		$new_post = $this->factory->post->create(
			array( 'post_type' => Friends::CPT, 'post_status' => 'publish', 'post_date' => '2021-01-01 12:00:00' )
		);
		wp_set_object_terms( $new_post, $this->friend->get_term_id(), Subscription::TAXONOMY );
		$this->request_feed( $this->query_vars(), 1, false, null, $first['data'] );
		global $wp_query;
		$this->assertSame( array( $ids[2], $ids[1] ), wp_list_pluck( $wp_query->posts, 'ID' ) );
		$this->assertSame( 0, $wp_query->get( 'offset' ) );
	}

	/**
	 * A direct link to a later page can still load without a cursor.
	 */
	public function test_direct_page_without_cursor() {
		$ids = $this->create_posts( 5 );
		$this->request_feed( $this->query_vars(), 1, true );
		global $wp_query;
		$this->assertSame( array( $ids[2], $ids[1] ), wp_list_pluck( $wp_query->posts, 'ID' ) );
	}

	/**
	 * Cursor dates cannot be changed after the server signs them.
	 */
	public function test_tampered_cursor_is_rejected() {
		$this->create_posts( 3 );
		$first = $this->request_feed( $this->query_vars(), 0 );
		$cursor = $first['data'];
		$cursor['cursor'] = str_replace( '2020', '2021', $cursor['cursor'] );
		$result = $this->request_feed( $this->query_vars(), 1, false, null, $cursor );
		$this->assertFalse( $result['success'] );
	}

	/**
	 * Cursors are valid only for the query and filters that produced them.
	 */
	public function test_cursor_is_bound_to_query() {
		$this->create_posts( 3 );
		$first = $this->request_feed( $this->query_vars(), 0 );
		$vars = $this->query_vars();
		$vars['order'] = 'ASC';
		$result = $this->request_feed( $vars, 1, false, null, $first['data'] );
		$this->assertFalse( $result['success'] );
	}

	/**
	 * The first response contains the existing empty-state UI.
	 */
	public function test_empty_feed_has_no_next_page() {
		$result = $this->request_feed( $this->query_vars(), 0, true );
		$this->assertTrue( $result['success'] );
		$this->assertFalse( $result['data']['has_more'] );
		$this->assertStringContainsString( 'friends-empty-state', $result['data']['html'] );
	}

	/**
	 * A query modified after signing cannot be used to fetch private content.
	 */
	public function test_invalid_signature_is_rejected() {
		$result = $this->request_feed( $this->query_vars(), 0, false, 'invalid' );
		$this->assertFalse( $result['success'] );
	}

	/**
	 * Subscribers cannot fetch the private Friends feed.
	 */
	public function test_unauthorized_user_is_rejected() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$result = $this->request_feed( $this->query_vars(), 0 );
		$this->assertFalse( $result['success'] );
	}

	/**
	 * Profiling compares LIMIT 1 with the normal lookahead query.
	 */
	public function test_profile_compares_one_post_and_full_page() {
		$this->create_posts( 5 );
		$single = $this->request_feed( $this->query_vars(), 0, true, null, null, array( 'size' => 1 ) );
		$this->assertTrue( $single['success'] );
		$this->assertSame( 1, $single['data']['timings']['limit'] );
		$this->assertSame( 1, $single['data']['timings']['posts'] );
		$full = $this->request_feed( $this->query_vars(), 0, true, null, null, array( 'size' => 0 ) );
		$this->assertSame( 3, $full['data']['timings']['limit'] );
		$this->assertSame( 2, $full['data']['timings']['posts'] );
		$this->assertGreaterThanOrEqual( 0, $full['data']['timings']['query_ms'] );
		$this->assertGreaterThanOrEqual( 0, $full['data']['timings']['render_ms'] );
	}

	/**
	 * EXPLAIN captures the actual SQL without rendering posts.
	 */
	public function test_explain_captures_sql_without_rendering() {
		$this->create_posts( 5 );
		$result = $this->request_feed( $this->query_vars(), 0, true, null, null, array( 'size' => 0, 'explain' => true ) );
		$this->assertTrue( $result['success'] );
		$this->assertSame( '', $result['data']['html'] );
		$this->assertStringContainsString( 'SELECT', $result['data']['explain']['sql'] );
		$this->assertStringContainsString( Friends::CPT, $result['data']['explain']['sql'] );
		$this->assertArrayHasKey( 'plan', $result['data']['explain'] );
		$this->assertArrayHasKey( 'indexes', $result['data']['explain'] );
	}

	/**
	 * Normal feed responses contain no profiling data.
	 */
	public function test_profile_is_opt_in() {
		$result = $this->request_feed( $this->query_vars(), 0, true );
		$this->assertArrayNotHasKey( 'timings', $result['data'] );
		$this->assertArrayNotHasKey( 'explain', $result['data'] );
	}

	/**
	 * A signed feed query is insufficient to enable profiling without a nonce.
	 */
	public function test_profile_requires_nonce() {
		$result = $this->request_feed( $this->query_vars(), 0, true, null, null, array( 'size' => 1, 'nonce' => 'invalid' ) );
		$this->assertNull( $result );
	}

	/**
	 * Create dated posts with a deterministic order.
	 *
	 * @param int $count Number of posts.
	 * @return int[] Post IDs in ascending date order.
	 */
	private function create_posts( $count ) {
		$ids = array();
		for ( $i = 0; $i < $count; ++$i ) {
			$ids[] = $this->factory->post->create(
				array(
					'post_type'    => Friends::CPT,
					'post_status'  => 'publish',
					'post_author'  => get_current_user_id(),
					'post_content' => 'Async test content',
					'post_date'    => '2020-01-01 12:00:0' . $i,
				)
			);
			wp_set_object_terms( end( $ids ), $this->friend->get_term_id(), Subscription::TAXONOMY );
		}
		return $ids;
	}

	/**
	 * Query parameters used by the signed feed endpoint.
	 *
	 * @return array Query parameters.
	 */
	private function query_vars() {
		return array(
			'post_type'      => Friends::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 2,
			'order'          => 'DESC',
			'friends_theme'  => 'default',
			'friends_author' => $this->friend->ID,
			'tax_query'      => array(
				array(
					'taxonomy' => Subscription::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $this->friend->get_term_id(),
				),
			),
		);
	}

	/**
	 * Invoke the endpoint and capture its JSON response without exiting.
	 *
	 * @param array       $vars Query parameters.
	 * @param int         $page Last loaded page (zero for the first page).
	 * @param bool        $initial Whether to include empty states and navigation.
	 * @param string|null $signature Override the signature for rejection tests.
	 * @param array|null  $cursor Cursor and signature returned by the previous page.
	 * @param array|null  $profile Optional profiling size and nonce override.
	 * @return array JSON response.
	 */
	private function request_feed( $vars, $page, $initial = false, $signature = null, $cursor = null, $profile = null ) {
		$previous_request = $_REQUEST;
		$json = wp_json_encode( $vars );
		$_POST = array(
			'query_vars'   => wp_slash( $json ),
			'qv_sign'      => null === $signature ? sha1( wp_salt( 'nonce' ) . $json ) : $signature,
			'page'         => $page,
			'initial_feed' => $initial,
		);
		if ( $cursor ) {
			$_POST['cursor'] = wp_slash( $cursor['cursor'] );
			$_POST['cursor_sign'] = $cursor['cursor_sign'];
		}
		if ( null !== $profile ) {
			$_POST['profile'] = 1;
			$_POST['profile_size'] = $profile['size'];
			$_POST['explain'] = ! empty( $profile['explain'] );
			$_POST['profile_nonce'] = isset( $profile['nonce'] ) ? $profile['nonce'] : wp_create_nonce( 'friends-feed-profile' );
		}
		$_REQUEST = array_merge( $_REQUEST, $_POST );
		$handler = function () {
			return function () {
				throw new \RuntimeException( 'async-feed-response' );
			};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $handler );
		ob_start();
		try {
			Friends::get_instance()->frontend->ajax_load_feed();
		} catch ( \RuntimeException $e ) {
			if ( 'async-feed-response' !== $e->getMessage() ) {
				throw $e;
			}
		} finally {
			$output = ob_get_clean();
			remove_filter( 'wp_die_ajax_handler', $handler );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			$_POST = array();
			$_REQUEST = $previous_request;
			while ( is_locale_switched() ) {
				restore_previous_locale();
			}
		}
		return json_decode( $output, true );
	}
}
