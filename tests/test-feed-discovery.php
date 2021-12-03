<?php
/**
 * Class Friends_Friend_Feed_Discovery
 *
 * @package Friends
 */

/**
 * Test the Notifications
 */
class Friends_Friend_Feed_Discovery extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		add_filter(
			'pre_http_request',
			function( $preempt, $request, $url ) {
				$p = wp_parse_url( $url );
				$cache = __DIR__ . '/data/' . sanitize_title( $p['host'] . '-' . $p['path'] ) . '.response';
				if ( file_exists( $cache ) ) {
					return apply_filters(
						'fake_http_response',
						unserialize( file_get_contents( $cache ) ),
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

		add_filter(
			'http_response',
			function( $response, $args, $url ) {
				$p = wp_parse_url( $url );
				$cache = __DIR__ . '/data/' . sanitize_title( $p['host'] . '-' . $p['path'] ) . '.response';
				if ( ! file_exists( $cache ) ) {
					$headers = wp_remote_retrieve_headers( $response );
					file_put_contents(
						$cache,
						serialize(
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
			},
			10,
			3
		);
	}

	public function test_alexander_kirk_at() {
		$friends = Friends::get_instance();
		$feeds = $friends->feed->discover_available_feeds( 'https://alexander.kirk.at/' );
		$this->assertArrayHasKey( 'https://alexander.kirk.at/feed/', $feeds );
		$this->assertArrayHasKey( 'autoselect', $feeds['https://alexander.kirk.at/feed/'] );
		$this->assertTrue( $feeds['https://alexander.kirk.at/feed/']['autoselect'] );
	}

	public function test_johnblackbourn() {
		$friends = Friends::get_instance();
		$feeds = $friends->feed->discover_available_feeds( 'https://johnblackbourn.com/' );
		$this->assertArrayHasKey( 'https://johnblackbourn.com/feed/', $feeds );
		$this->assertArrayHasKey( 'autoselect', $feeds['https://johnblackbourn.com/feed/'] );
		$this->assertTrue( $feeds['https://johnblackbourn.com/feed/']['autoselect'] );
	}

	public function test_chriswiegman() {
		$friends = Friends::get_instance();
		$feeds = $friends->feed->discover_available_feeds( 'https://chriswiegman.com/' );
		$this->assertArrayHasKey( 'https://chriswiegman.com/feed/', $feeds );
		$this->assertArrayHasKey( 'autoselect', $feeds['https://chriswiegman.com/feed/'] );
		$this->assertTrue( $feeds['https://chriswiegman.com/feed/']['autoselect'] );
	}
}
