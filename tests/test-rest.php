<?php
/**
 * Class Friends_RestTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the REST API.
 */
class RestTest extends Friends_TestCase_Cache_HTTP {
	/**
	 * The REST server.
	 *
	 * @var object
	 */
	protected $server;

	/**
	 * Setup the unit tests.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'friends_enable_wp_friendships', true );

		User_Query::$cache = false;

		// Emulate HTTP requests to the REST API.
		add_filter(
			'pre_http_request',
			function ( $preempt, $request, $url ) {
				$p = wp_parse_url( $url );

				$home_url = home_url();

				// Pretend the url now is the requested one.
				update_option( 'home', $p['scheme'] . '://' . $p['host'] );
				$rest_prefix = home_url() . '/wp-json';
				if ( substr( $url, -6 ) === '/feed/' ) {
					// Restore the old home_url.
					update_option( 'home', $home_url );
					return apply_filters(
						'fake_http_response',
						array(
							'headers'  => array(
								'content-type' => 'application/rss+xml',
							),
							// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
							'body'     => file_get_contents( __DIR__ . '/data/friend-feed-1-public-post.rss' ),
							'response' => array(
								'code' => 200,
							),
						),
						$p['scheme'] . '://' . $p['host'],
						$url,
						$request
					);
				} elseif ( false === strpos( $url, $rest_prefix ) ) {
					$html = '<html><head><title>' . esc_html( ucwords( strtr( wp_parse_url( $home_url, PHP_URL_HOST ), '.', ' ' ) ) ) . '</title>' . implode( PHP_EOL, Friends::get_html_rel_links() ) . '</head></html>';

					// Restore the old home_url.
					update_option( 'home', $home_url );
					return apply_filters(
						'fake_http_response',
						array(
							'headers'  => array(
								'content-type' => 'text/html',
							),
							'body'     => $html,
							'response' => array(
								'code' => 200,
							),
						),
						$p['scheme'] . '://' . $p['host'],
						$url,
						$request
					);
				} elseif ( 0 === strpos( $url, $rest_prefix ) ) {
					$rest_path = substr( $p['path'], strlen( '/wp-json' ) );
					$rest_request = new \WP_REST_Request( $request['method'], $rest_path );

					if ( ! empty( $request['body'] ) ) {
						$rest_request->set_body_params( $request['body'] );
					}
					if ( isset( $p['query'] ) ) {
						parse_str( $p['query'], $query );
						$rest_request->set_query_params( $query );
					}
					$rest_request->set_headers( $request['headers'] );
					$response = $this->server->dispatch( $rest_request );

					// Restore the old home_url.
					update_option( 'home', $home_url );
					return apply_filters(
						'fake_http_response',
						array(
							'headers'  => array(
								'content-type' => 'application/json',
							),
							'body'     => wp_json_encode( $response->data ),
							'response' => array(
								'code' => 200,
							),
						),
						$p['scheme'] . '://' . $p['host'],
						$url,
						$request
					);
				}
				return $preempt;
			},
			10,
			3
		);
	}

	/**
	 * Check that all endpoints are reachable.
	 */
	public function test_endpoints() {
		$the_route = '/' . REST::PREFIX;
		$routes    = $this->server->get_routes();
		foreach ( $routes as $route => $route_config ) {
			if ( 0 === strpos( $the_route, $route ) ) {
				$this->assertTrue( is_array( $route_config ) );
				foreach ( $route_config as $i => $endpoint ) {
					$this->assertArrayHasKey( 'callback', $endpoint );
					$this->assertArrayHasKey( 0, $endpoint['callback'], get_class( $this ) );
					$this->assertArrayHasKey( 1, $endpoint['callback'], get_class( $this ) );
					$this->assertTrue( is_callable( array( $endpoint['callback'][0], $endpoint['callback'][1] ) ) );
				}
			}
		}
	}

	/**
	 * Check that browser extension action filters receive the authenticated user and context.
	 */
	public function test_extension_actions_receive_user_and_context() {
		$user_id = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Extension User',
			)
		);
		$key     = Admin::get_browser_api_key( $user_id );

		wp_set_current_user( 0 );

		$received = array();
		$callback = function ( $actions, $current_user, $context ) use ( &$received ) {
			$received = array(
				'user_id'             => $current_user->ID,
				'current_user_id'     => get_current_user_id(),
				'key'                 => $context['key'],
				'extension_version'   => $context['extension_version'],
			);

			$actions[] = array(
				'name' => 'Inline Action',
				'url'  => rest_url( REST::PREFIX . '/extension/action' ),
				'run'  => 'inline',
			);

			return $actions;
		};

		add_filter( 'friends_browser_extension_actions', $callback, 10, 3 );

		$request = new \WP_REST_Request( 'POST', '/' . REST::PREFIX . '/extension' );
		$request->set_param( 'key', $key );
		$request->set_param( 'version', '1.6.0' );

		$response = $this->server->dispatch( $request );
		remove_filter( 'friends_browser_extension_actions', $callback, 10 );

		$data = $response->get_data();

		$this->assertSame( $user_id, $received['user_id'] );
		$this->assertSame( $user_id, $received['current_user_id'] );
		$this->assertSame( $key, $received['key'] );
		$this->assertSame( '1.6.0', $received['extension_version'] );
		$this->assertCount( 1, $data['actions'] );
		$this->assertSame( 'inline', $data['actions'][0]['run'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * Check that inline browser extension actions are dispatched through a filter.
	 */
	public function test_extension_action_dispatches_to_filter() {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$key     = Admin::get_browser_api_key( $user_id );

		wp_set_current_user( 0 );

		$received = array();
		$callback = function ( $response, $action, $request, $current_user, $context ) use ( &$received ) {
			$received = array(
				'action'          => $action,
				'current_user_id' => get_current_user_id(),
				'filter_user_id'  => $current_user->ID,
				'context_version' => $context['version'],
			);

			return array(
				'success' => true,
				'message' => 'Saved.',
				'url'     => $request->get_param( 'url' ),
			);
		};

		add_filter( 'friends_browser_extension_action', $callback, 10, 5 );

		$request = new \WP_REST_Request( 'POST', '/' . REST::PREFIX . '/extension/action' );
		$request->set_param( 'action', 'save' );
		$request->set_param( 'key', $key );
		$request->set_param( 'version', '1.6.0' );
		$request->set_param( 'url', 'https://example.org/post' );

		$response = $this->server->dispatch( $request );
		remove_filter( 'friends_browser_extension_action', $callback, 10 );

		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertSame( 'Saved.', $data['message'] );
		$this->assertSame( 'https://example.org/post', $data['url'] );
		$this->assertSame( 'save', $received['action'] );
		$this->assertSame( $user_id, $received['current_user_id'] );
		$this->assertSame( $user_id, $received['filter_user_id'] );
		$this->assertSame( '1.6.0', $received['context_version'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * Check that inline browser extension actions reject invalid API keys.
	 */
	public function test_extension_action_rejects_invalid_key() {
		$called   = false;
		$callback = function () use ( &$called ) {
			$called = true;
			return true;
		};

		add_filter( 'friends_browser_extension_action_save', $callback );

		$request = new \WP_REST_Request( 'POST', '/' . REST::PREFIX . '/extension/action' );
		$request->set_param( 'action', 'save' );
		$request->set_param( 'key', 'invalid' );

		$response = $this->server->dispatch( $request );
		remove_filter( 'friends_browser_extension_action_save', $callback );

		$data = $response->get_data();

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'friends_invalid_browser_extension_key', $data['code'] );
		$this->assertFalse( $called );
	}

}
