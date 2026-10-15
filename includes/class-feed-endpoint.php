<?php
/**
 * Authenticated feed requests and response metadata.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Transport boundary for the asynchronous feed and legacy HTML endpoint.
 */
class Feed_Endpoint {
	/**
	 * Frontend rendering context.
	 *
	 * @var Frontend
	 */
	private $frontend;

	/**
	 * Set the frontend whose theme and author context should be used.
	 *
	 * @param Frontend $frontend Frontend instance.
	 */
	public function __construct( Frontend $frontend ) {
		$this->frontend = $frontend;
	}

	/**
	 * Validate a request, fetch an independent query, and return a feed page.
	 */
	public function serve() {
		$request = $this->read_request();
		$response = $this->frontend->with_feed_context(
			$request['vars'],
			function ( $vars ) use ( $request ) {
				global $wpdb;
				$query_started = microtime( true );
				$query_db_calls = $wpdb->num_queries;
				$page = ( new Feed_Query() )->fetch( $vars, $request['cursor'], $request['single'] );
				$query = $page['query'];
				$query_ms = ( microtime( true ) - $query_started ) * 1000;
				$query_db_calls = $wpdb->num_queries - $query_db_calls;
				$render_started = microtime( true );
				$render_db_calls = $wpdb->num_queries;
				$html = $request['explain'] ? '' : ( new Feed_Renderer() )->render( $query, $request['initial'], $page['has_more'] );
				$response = array(
					'html'        => $html,
					'has_more'    => $page['has_more'],
					'page'        => $vars['paged'],
					'cursor'      => $page['cursor'],
					'cursor_sign' => $page['cursor'] ? hash_hmac( 'sha256', $request['signature_context'] . "\n" . $page['cursor'], wp_salt( 'nonce' ) ) : null,
				);
				if ( $request['profile'] ) {
					$finished = microtime( true );
					$request_started = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? floatval( $_SERVER['REQUEST_TIME_FLOAT'] ) : $query_started;
					$response['timings'] = array(
						'posts'           => $query->post_count,
						'rows_fetched'    => $page['rows_fetched'],
						'limit'           => $query->get( 'posts_per_page' ),
						'query_ms'        => round( $query_ms, 1 ),
						'render_ms'       => round( ( $finished - $render_started ) * 1000, 1 ),
						'setup_ms'        => round( ( $query_started - $request_started ) * 1000, 1 ),
						'php_ms'          => round( ( $finished - $request_started ) * 1000, 1 ),
						'query_db_calls'  => $query_db_calls,
						'render_db_calls' => $wpdb->num_queries - $render_db_calls,
					);
				}
				if ( $request['explain'] ) {
					$response['explain'] = $this->explain( $query->request );
				}
				return $response;
			}
		);
		wp_send_json_success( $response );
	}

	/**
	 * Preserve the original endpoint's string response for existing clients.
	 */
	public function serve_legacy() {
		$request = $this->read_query();
		$html = $this->frontend->with_feed_context(
			$request['vars'],
			function ( $vars ) {
				$query = new \WP_Query( $vars );
				if ( ! $query->have_posts() ) {
					return __( 'No further posts of your friends could were found.', 'friends' );
				}
				return ( new Feed_Renderer() )->render( $query );
			}
		);
		wp_send_json_success( $html );
	}

	/**
	 * Verify access and decode the server-signed query shared by both endpoints.
	 *
	 * @return array Query arguments and their signature context.
	 */
	private function read_query() {
		if ( ! $this->frontend->can_view_feed() ) {
			wp_send_json_error( null, 403 );
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['query_vars'] ) || ! isset( $_POST['page'] ) || ! isset( $_POST['qv_sign'] ) ) {
			wp_send_json_error();
			exit;
		}
		$query_vars = wp_unslash( $_POST['query_vars'] );
		// Accept only query arguments signed by the page shell.
		if ( ! is_string( $query_vars ) || ! is_string( $_POST['qv_sign'] ) || ! hash_equals( sha1( wp_salt( 'nonce' ) . $query_vars ), wp_unslash( $_POST['qv_sign'] ) ) ) {
			wp_send_json_error();
			exit;
		}

		$signed_query_vars = $query_vars;
		$query_vars = json_decode( $query_vars, true );
		if ( ! is_array( $query_vars ) ) {
			wp_send_json_error();
		}
		$query_vars['paged'] = max( 1, intval( $_POST['page'] ) + 1 );
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return array(
			'vars'              => $query_vars,
			'signature_context' => $signed_query_vars,
		);
	}

	/**
	 * Validate asynchronous pagination and opt-in administrator diagnostics.
	 *
	 * @return array Validated feed request arguments.
	 */
	private function read_request() {
		$request = $this->read_query();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$profile = ! empty( $_POST['profile'] );
		if ( $profile ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( null, 403 );
			}
			check_ajax_referer( 'friends-feed-profile', 'profile_nonce' );
		}
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:disable WordPress.Security.NonceVerification.Missing

		$initial_feed = ! empty( $_POST['initial_feed'] );
		$cursor = null;
		if ( ! $initial_feed && ! empty( $_POST['cursor'] ) ) {
			$cursor_json = wp_unslash( $_POST['cursor'] );
			if ( ! is_string( $cursor_json ) || ! isset( $_POST['cursor_sign'] ) || ! is_string( $_POST['cursor_sign'] )
				|| ! hash_equals( hash_hmac( 'sha256', $request['signature_context'] . "\n" . $cursor_json, wp_salt( 'nonce' ) ), wp_unslash( $_POST['cursor_sign'] ) ) ) {
				wp_send_json_error( null, 400 );
			}
			$cursor = json_decode( $cursor_json, true );
			if ( ! is_array( $cursor ) || ! isset( $cursor['id'], $cursor['date'] ) || ! is_int( $cursor['id'] ) || $cursor['id'] < 1
				|| ! is_string( $cursor['date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cursor['date'] ) ) {
				wp_send_json_error( null, 400 );
			}
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return array_merge(
			$request,
			array(
				'initial' => $initial_feed,
				'cursor'  => $cursor,
				'profile' => $profile,
				'single'  => $profile && isset( $_POST['profile_size'] ) && 1 === absint( wp_unslash( $_POST['profile_size'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'explain' => $profile && ! empty( $_POST['explain'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			)
		);
	}

	/**
	 * Explain generated SQL without exposing an arbitrary SQL execution endpoint.
	 *
	 * @param string $sql SQL generated by the feed query.
	 * @return array SQL, execution plan, indexes, and any database error.
	 */
	private function explain( $sql ) {
		global $wpdb;
		$previous_suppression = $wpdb->suppress_errors( true );
		// Both statements use SQL and table names generated by WordPress, never client SQL.
		$plan = $wpdb->get_results( 'EXPLAIN ' . $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$plan_error = $wpdb->last_error;
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$index_error = $wpdb->last_error;
		$wpdb->suppress_errors( $previous_suppression );
		return array(
			'sql'     => $sql,
			'plan'    => $plan,
			'indexes' => $indexes,
			'error'   => $plan_error ? $plan_error : $index_error,
		);
	}
}
