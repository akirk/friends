<?php
/**
 * Class Friends_Friend_Feed_Discovery
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Notifications
 */
class Feed_Discovery extends Friends_TestCase_Cache_HTTP {

	public function test_alex_kirk_at() {
		$friends = Friends::get_instance();
		$feeds = $friends->feed->discover_available_feeds( 'https://alex.kirk.at/' );
		$this->assertArrayHasKey( 'https://alex.kirk.at/feed/', $feeds );
		$this->assertArrayHasKey( 'autoselect', $feeds['https://alex.kirk.at/feed/'] );
		$this->assertTrue( $feeds['https://alex.kirk.at/feed/']['autoselect'] );
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

	public function test_blueskyweb() {
		$friends = Friends::get_instance();
		$feeds = $friends->feed->discover_available_feeds( 'https://blueskyweb.xyz/rss.xml' );
		$this->assertArrayHasKey( 'https://blueskyweb.xyz/rss.xml', $feeds );
		$this->assertArrayHasKey( 'autoselect', $feeds['https://blueskyweb.xyz/rss.xml'] );
		$this->assertTrue( $feeds['https://blueskyweb.xyz/rss.xml']['autoselect'] );
	}
}
