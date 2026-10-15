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
	 * Last independent feed query observed by the request helper.
	 *
	 * @var \WP_Query
	 */
	private $feed_query;

	/**
	 * Prepare an administrator and a subscription.
	 */
	public function set_up() {
		parent::set_up();
		$frontend = Friends::get_instance()->frontend;
		$this->assertNotFalse(
			has_action( 'wp_ajax_friends-load-feed', array( $frontend, 'ajax_load_feed' ) ),
			'The asynchronous feed endpoint must be registered.'
		);
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
		// Exercise the Friends route independently of the test site's permalink rules.
		$this->go_to( home_url( '/?pagename=friends' ) );
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
			$this->go_to( home_url( '/?pagename=friends&friends_sync=1' ) );
			$this->assertFalse( Friends::get_instance()->frontend->is_feed_deferred() );
			$this->assertTrue( have_posts(), $GLOBALS['wp_query']->request );
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
			$query = $this->feed_query;
			$this->assertTrue( $query->get( 'no_found_rows' ) );
			$this->assertSame( 0, $query->found_posts );
			$this->assertSame( 0, $query->get( 'offset' ) );
			$received_ids = array_merge( $received_ids, wp_list_pluck( $query->posts, 'ID' ) );
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
			$query = $this->feed_query;
			$received_ids = array_merge( $received_ids, wp_list_pluck( $query->posts, 'ID' ) );
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
			$query = $this->feed_query;
			$received_ids = array_merge( $received_ids, wp_list_pluck( $query->posts, 'ID' ) );
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
		$query = $this->feed_query;
		$this->assertSame( array( $ids[2], $ids[1] ), wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertSame( 0, $query->get( 'offset' ) );
	}

	/**
	 * A direct link to a later page can still load without a cursor.
	 */
	public function test_direct_page_without_cursor() {
		$ids = $this->create_posts( 5 );
		$this->request_feed( $this->query_vars(), 1, true );
		$query = $this->feed_query;
		$this->assertSame( array( $ids[2], $ids[1] ), wp_list_pluck( $query->posts, 'ID' ) );
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
	 * Users without feed access cannot fetch the private Friends feed.
	 */
	public function test_unauthorized_user_is_rejected() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		if ( is_multisite() ) {
			remove_user_from_blog( $user_id, get_current_blog_id() );
		}
		wp_set_current_user( $user_id );
		$result = $this->request_feed( $this->query_vars(), 0 );
		$this->assertFalse( $result['success'] );
	}

	/**
	 * Multisite members retain their existing permission to read their site's feed.
	 */
	public function test_multisite_member_can_load_feed() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Site membership applies only to multisite.' );
		}
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$result = $this->request_feed( $this->query_vars(), 0 );
		$this->assertTrue( $result['success'] );
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
	 * Merge bounded candidates without changing the permitted types or statuses.
	 */
	public function test_partitioned_feed_preserves_types_statuses_and_cursor_order() {
		$this->create_posts( 5 );
		foreach ( array( Friends::CPT, 'post' ) as $type ) {
			foreach ( array( 'publish', 'private', 'future' ) as $status ) {
				$this->factory->post->create(
					array(
						'post_type' => $type,
						'post_author' => get_current_user_id(),
						'post_status' => $status,
						'post_date' => 'future' === $status ? '2099-01-01 12:00:00' : '2020-02-01 12:00:00',
					)
				);
			}
		}
		foreach ( array( 'DESC', 'ASC' ) as $order ) {
			$vars = array(
				'post_type' => array( Friends::CPT, 'post' ),
				'post_status' => array( 'publish', 'private', 'future' ),
				'order' => $order,
				'orderby' => 'date ID',
				'posts_per_page' => 2,
			);
			$reference = new \WP_Query( array_merge( $vars, array( 'posts_per_page' => -1, 'no_found_rows' => true ) ) );
			$expected_ids = wp_list_pluck( $reference->posts, 'ID' );
			$received_ids = array();
			$cursor = null;
			for ( $page = 0; $page < 20; ++$page ) {
				$result = $this->request_feed( $vars, $page, false, null, $cursor );
				$this->assertTrue( $result['success'] );
				$query = $this->feed_query;
				$this->assertStringContainsString( 'friends_feed_candidates', $query->request );
				$received_ids = array_merge( $received_ids, wp_list_pluck( $query->posts, 'ID' ) );
				$cursor = $result['data'];
				if ( ! $cursor['has_more'] ) {
					break;
				}
			}
			$this->assertSame( $expected_ids, $received_ids );
			$this->request_feed( $vars, 1, true );
			$this->assertSame( array_slice( $expected_ids, 2, 2 ), wp_list_pluck( $this->feed_query->posts, 'ID' ) );
		}
	}

	/**
	 * The status-format filter can use ordered lookups without a taxonomy sort.
	 */
	public function test_status_format_preserves_filtered_cursor_results() {
		foreach ( array( Friends::CPT, 'post' ) as $type ) {
			foreach ( array( 'publish', 'private', 'future' ) as $status ) {
				foreach ( array( 'status', 'image' ) as $format ) {
					$post_id = $this->factory->post->create(
						array(
							'post_type' => $type,
							'post_author' => get_current_user_id(),
							'post_status' => $status,
							'post_date' => 'future' === $status ? '2099-01-01 12:00:00' : '2020-02-01 12:00:00',
						)
					);
					wp_set_object_terms( $post_id, 'post-format-' . $format, 'post_format' );
				}
			}
		}
		foreach ( array( 'ASC', 'DESC' ) as $order ) {
			$vars = array(
				'post_type' => array( Friends::CPT, 'post' ),
				'post_status' => array( 'publish', 'private', 'future' ),
				'posts_per_page' => 2,
				'orderby' => 'date ID',
				'order' => $order,
				'tax_query' => array( array( 'taxonomy' => 'post_format', 'field' => 'slug', 'terms' => array( 'post-format-status' ) ) ),
			);
			$reference = new \WP_Query( array_merge( $vars, array( 'posts_per_page' => -1, 'no_found_rows' => true ) ) );
			$expected_ids = wp_list_pluck( $reference->posts, 'ID' );
			$this->assertCount( 6, $expected_ids );
			$received_ids = array();
			$cursor = null;
			for ( $page = 0; $page < 10; ++$page ) {
				$result = $this->request_feed( $vars, $page, false, null, $cursor );
				$this->assertTrue( $result['success'] );
				$query = $this->feed_query;
				$this->assertStringContainsString( 'friends_feed_candidates', $query->request );
				$this->assertStringContainsString( 'friends_feed_format', $query->request );
				$this->assertStringNotContainsString( 'LEFT JOIN', $query->request );
				$this->assertStringNotContainsString( 'GROUP BY', $query->request );
				$received_ids = array_merge( $received_ids, wp_list_pluck( $query->posts, 'ID' ) );
				$cursor = $result['data'];
				if ( ! $cursor['has_more'] ) {
					break;
				}
			}
			$this->assertSame( $expected_ids, $received_ids );
			$this->request_feed( $vars, 1, true );
			$this->assertSame( array_slice( $expected_ids, 2, 2 ), wp_list_pluck( $this->feed_query->posts, 'ID' ) );
			$this->request_feed( $vars, 500, true );
			$this->assertStringNotContainsString( 'friends_feed_format', $this->feed_query->request );
			$this->assertStringContainsString( 'LEFT JOIN', $this->feed_query->request );
		}
	}

	/**
	 * A format filter combined with a subscription filter keeps both constraints.
	 */
	public function test_status_format_with_subscription_retains_original_query() {
		$ids = $this->create_posts( 1 );
		wp_set_object_terms( $ids[0], 'post-format-status', 'post_format' );
		$vars = $this->query_vars();
		$vars['post_status'] = array( 'publish', 'private', 'future' );
		$vars['tax_query'][] = array( 'taxonomy' => 'post_format', 'field' => 'slug', 'terms' => array( 'post-format-status' ) );
		$this->request_feed( $vars, 0 );
		$query = $this->feed_query;
		$this->assertSame( $ids, wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertStringNotContainsString( 'friends_feed_format', $query->request );
		$this->assertStringNotContainsString( 'friends_feed_candidates', $query->request );
	}

	/**
	 * Joined taxonomy queries retain WordPress's original execution path.
	 */
	public function test_partition_optimization_skips_taxonomy_joins() {
		$this->create_posts( 3 );
		$vars = $this->query_vars();
		$vars['post_status'] = array( 'publish', 'private', 'future' );
		$this->request_feed( $vars, 0 );
		$this->assertStringNotContainsString( 'friends_feed_candidates', $this->feed_query->request );
	}

	/**
	 * Do not materialize large candidate sets for deep numbered pages.
	 */
	public function test_partition_optimization_skips_deep_offsets() {
		$vars = $this->query_vars();
		unset( $vars['tax_query'], $vars['friends_author'] );
		$vars['post_status'] = array( 'publish', 'private', 'future' );
		$this->request_feed( $vars, 500, true );
		$this->assertStringNotContainsString( 'friends_feed_candidates', $this->feed_query->request );
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
	 * Feed requests leave the caller's query, post, author context, and locale alone.
	 */
	public function test_request_restores_callers_context() {
		$ids = $this->create_posts( 3 );
		$previous_query = $GLOBALS['wp_query'];
		$previous_post = get_post( $ids[0] );
		$GLOBALS['post'] = $previous_post;
		$frontend = Friends::get_instance()->frontend;
		$frontend->post_format = 'image';
		$frontend->author = $this->friend;
		$locale = get_locale();
		$result = $this->request_feed( $this->query_vars(), 0, true );
		$this->assertTrue( $result['success'] );
		$this->assertSame( $previous_query, $GLOBALS['wp_query'] );
		$this->assertSame( $previous_post, $GLOBALS['post'] );
		$this->assertSame( 'image', $frontend->post_format );
		$this->assertSame( $this->friend, $frontend->author );
		$this->assertSame( $locale, get_locale() );
	}

	/**
	 * The legacy AJAX action still returns HTML rather than feed metadata.
	 */
	public function test_legacy_endpoint_preserves_html_response() {
		$this->create_posts( 3 );
		$previous_query = $GLOBALS['wp_query'];
		$result = $this->request_feed( $this->query_vars(), 0, false, null, null, null, 'friends-load-next-page' );
		$this->assertTrue( $result['success'] );
		$this->assertIsString( $result['data'] );
		$this->assertStringContainsString( '<article', $result['data'] );
		$this->assertSame( $previous_query, $GLOBALS['wp_query'] );
	}

	/**
	 * An interrupted feed operation restores the caller's frontend context.
	 */
	public function test_frontend_context_is_restored_on_failure() {
		$frontend = Friends::get_instance()->frontend;
		$frontend->author = $this->friend;
		$frontend->post_format = 'image';
		$locale = get_locale();
		try {
			$frontend->with_feed_context( array( 'friends_post_format' => 'status' ), function () {
				throw new \RuntimeException( 'interrupt-feed-context' );
			} );
			$this->fail( 'The feed operation should have been interrupted.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'interrupt-feed-context', $e->getMessage() );
		}
		$this->assertSame( $this->friend, $frontend->author );
		$this->assertSame( 'image', $frontend->post_format );
		$this->assertSame( $locale, get_locale() );
	}

	/**
	 * SQL hooks must not affect nested queries or survive an exception.
	 */
	public function test_query_hooks_are_scoped_and_removed_on_failure() {
		$feed = new Feed_Query();
		$nested_sql = '';
		$inside = false;
		$interrupt = function ( $query ) use ( &$inside, &$nested_sql ) {
			if ( $inside || ! $query->get( 'friends_feed_fast' ) ) {
				return;
			}
			$inside = true;
			$nested = new \WP_Query( array(
				'post_type' => Friends::CPT,
				'post_status' => array( 'publish', 'private' ),
				'no_found_rows' => true,
				'friends_feed_fast' => true,
				'friends_feed_cursor' => array( 'date' => '2020-01-01 12:00:00', 'id' => 99 ),
				'orderby' => 'date ID',
			) );
			$nested_sql = $nested->request;
			throw new \RuntimeException( 'interrupt-feed-query' );
		};
		add_action( 'pre_get_posts', $interrupt );
		try {
			$feed->fetch( array_merge( $this->query_vars(), array( 'paged' => 1 ) ) );
			$this->fail( 'The query should have been interrupted.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'interrupt-feed-query', $e->getMessage() );
		} finally {
			remove_action( 'pre_get_posts', $interrupt );
		}
		$this->assertStringNotContainsString( 'friends_feed_candidates', $nested_sql );
		$this->assertStringNotContainsString( '2020-01-01 12:00:00', $nested_sql );
		$this->assertFalse( has_filter( 'posts_clauses_request', array( $feed, 'clauses' ) ) );
	}

	/**
	 * Rendering exceptions must restore the surrounding loop and output buffers.
	 */
	public function test_renderer_restores_loop_on_failure() {
		$this->create_posts( 1 );
		$feed = ( new Feed_Query() )->fetch( array_merge( $this->query_vars(), array( 'paged' => 1 ) ) );
		$previous_query = $GLOBALS['wp_query'];
		$previous_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$buffer_level = ob_get_level();
		$interrupt = function () {
			throw new \RuntimeException( 'interrupt-feed-render' );
		};
		add_action( 'the_post', $interrupt );
		try {
			( new Feed_Renderer() )->render( $feed['query'] );
			$this->fail( 'Rendering should have been interrupted.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'interrupt-feed-render', $e->getMessage() );
		} finally {
			remove_action( 'the_post', $interrupt );
		}
		$this->assertSame( $previous_query, $GLOBALS['wp_query'] );
		$this->assertSame( $previous_post, isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null );
		$this->assertSame( $buffer_level, ob_get_level() );
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
	 * @param string      $action AJAX action to exercise.
	 * @return array JSON response.
	 */
	private function request_feed( $vars, $page, $initial = false, $signature = null, $cursor = null, $profile = null, $action = 'friends-load-feed' ) {
		$previous_request = $_REQUEST;
		$capture_query = function ( $posts, $query ) {
			if ( $query->get( 'friends_feed_fast' ) ) {
				$this->feed_query = $query;
			}
			return $posts;
		};
		add_filter( 'the_posts', $capture_query, 10, 2 );
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
			do_action( 'wp_ajax_' . $action );
		} catch ( \RuntimeException $e ) {
			if ( 'async-feed-response' !== $e->getMessage() ) {
				throw $e;
			}
		} finally {
			$output = ob_get_clean();
			remove_filter( 'the_posts', $capture_query );
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
