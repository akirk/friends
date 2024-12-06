<?php
/**
 * Class Friends_TestCase_Cache_HTTP
 *
 * @package Friends
 */

namespace Friends;

/**
 * A testcase for the Friends plugin that provides a mock HTTP server.
 *
 * @package
 */
class Friends_TestCase_Cache_HTTP extends \WP_UnitTestCase {
	protected $server;

	public function set_up() {
		parent::set_up();

		// Manually activate the REST server.
		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		add_filter(
			'rest_url',
			function () {
				return get_option( 'home' ) . '/wp-json/';
			}
		);

		add_filter(
			'get_user_option_friends_rest_url',
			function () {
				return get_option( 'home' ) . '/wp-json/' . REST::PREFIX;
			}
		);

		wp_get_current_user()->display_name = 'Alex';

		add_filter( 'friends_host_is_valid', array( get_called_class(), 'friends_host_is_valid' ), 10, 2 );
		add_filter( 'pre_http_request', array( get_called_class(), 'pre_http_request' ), 10, 3 );
		add_filter( 'http_response', array( get_called_class(), 'http_response' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'friends_host_is_valid', array( get_called_class(), 'friends_host_is_valid' ) );
		remove_filter( 'pre_http_request', array( get_called_class(), 'pre_http_request' ) );
		remove_filter( 'http_response', array( get_called_class(), 'http_response' ) );
		parent::tear_down();
	}

	public static function friends_host_is_valid( $ret, $host ) {
		if ( in_array(
			$host,
			array(
				'me.local',
				'friend.local',
				'example.org',
				'notiz.blog',
			)
		) ) {
			// Hosts used for test cases.
			return $host;
		}
		return $ret;
	}

	public static function get_cache_filename( $url ) {
		$p = wp_parse_url( $url );
		if ( ! $p ) {
			return null;
		}
		$cache_filename = $p['host'];
		if ( ! empty( $p['path'] ) ) {
			$cache_filename .= '-' . $p['path'];
		}
		return __DIR__ . '/fixtures/' . sanitize_title( $cache_filename ) . '.json';
	}

	public static function pre_http_request( $preempt, $request, $url ) {
		if ( $preempt ) {
			return $preempt;
		}
		$p = wp_parse_url( $url );
		$cache = self::get_cache_filename( $url );
		if ( ! $cache ) {
			return $preempt;
		}
		if ( file_exists( $cache ) ) {
			return apply_filters(
				'fake_http_response',
				json_decode( file_get_contents( $cache ), true ), // phpcs:ignore
				$p['scheme'] . '://' . $p['host'],
				$url,
				$request
			);
		}

		$home_url = home_url();

		// Pretend the url now is the requested one.
		update_option( 'home', $p['scheme'] . '://' . $p['host'] );
		$rest_prefix = home_url() . '/wp-json';

		if ( false === strpos( $url, $rest_prefix ) ) {
			// Restore the old home_url.
			update_option( 'home', $home_url );
			return $preempt;
		}

		$url = substr( $url, strlen( $rest_prefix ) );
		$r   = new \WP_REST_Request( $request['method'], $url );
		if ( ! empty( $request['body'] ) ) {
			foreach ( $request['body'] as $key => $value ) {
				$r->set_param( $key, $value );
			}
		}
		global $wp_rest_server;
		$response = $wp_rest_server->dispatch( $r );
		// Restore the old url.
		update_option( 'home', $home_url );

		return apply_filters(
			'fake_http_response',
			array(
				'headers'  => array(
					'content-type' => 'text/json',
				),
				'body'     => wp_json_encode( $response->data ),
				'response' => array(
					'code' => $response->status,
				),
			),
			$p['scheme'] . '://' . $p['host'],
			$url,
			$request
		);
	}

	public static function http_response( $response, $args, $url ) {
		$cache = self::get_cache_filename( $url );
		if ( ! file_exists( $cache ) ) {
			$headers = wp_remote_retrieve_headers( $response );
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$cache,
				wp_json_encode(
					array(
						'headers'  => $headers->getAll(),
						'body'     => wp_remote_retrieve_body( $response ),
						'response' => array(
							'code' => wp_remote_retrieve_response_code( $response ),
						),
					)
				)
			);
		}
		return $response;
	}
}
