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

	public function test_error_messages() {
		switch_to_locale( 'de_DE' );
		$translate = function( $translated, $text, $domain ) {
			if ( 'friends' === $domain && 'de_DE' === get_locale() ) {
				if ( 'An invalid URL was provided.' === $text ) {
					return 'Eine ungültige URL wurde angegeben.';
				}
			}
			return $translated;

		};
		add_filter( 'gettext', $translate, 10, 3 );

		$request = new \WP_REST_Request( 'GET', '/' . REST::PREFIX . '/friendship-requested' );
		$request->set_param( 'url', 'abc' );
		$response = $this->server->dispatch( $request );

		$this->assertArrayHasKey( 'code', $response->data );
		$this->assertEquals( 'friends_invalid_url', $response->data['code'] );
		$this->assertEquals( 'An invalid URL was provided.', $response->data['message'] );

		$this->assertEquals( 'Eine ungültige URL wurde angegeben.', Rest::translate_error_message( $response->data['message'] ) );

		remove_filter( 'gettext', $translate, 10, 3 );

		restore_previous_locale();
	}
}
