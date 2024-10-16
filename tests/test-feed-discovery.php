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
		$this->assertEquals( 'Chris Wiegman', User::get_display_name_from_feeds( $feeds ) );
	}

	public function test_blueskyweb() {
		$friends = Friends::get_instance();
		$feeds = $friends->feed->discover_available_feeds( 'https://blueskyweb.xyz/rss.xml' );
		$this->assertArrayHasKey( 'https://blueskyweb.xyz/rss.xml', $feeds );
		$this->assertArrayHasKey( 'autoselect', $feeds['https://blueskyweb.xyz/rss.xml'] );
		$this->assertTrue( $feeds['https://blueskyweb.xyz/rss.xml']['autoselect'] );
	}

	public function test_klingerio() {
		$friends = Friends::get_instance();
		$feeds = $friends->feed->discover_available_feeds( 'https://klinger.io/' );
		$this->assertArrayHasKey( 'https://klinger.io/rss.xml', $feeds );
		$this->assertArrayHasKey( 'autoselect', $feeds['https://klinger.io/rss.xml'] );
		$this->assertTrue( $feeds['https://klinger.io/rss.xml']['autoselect'] );
		$this->assertEquals( 'Andreas Klinger', User::get_display_name_from_feeds( $feeds ) );
		$this->assertEquals( 'https://klinger.io/favicon-32x32.png', $feeds['https://klinger.io/']['avatar'] );
	}

	public function test_wordpresscom() {
		$friends = Friends::get_instance();
		$feeds = $friends->feed->discover_available_feeds( 'https://wordpress.com/' );
		$this->assertArrayHasKey( 'https://wordpress.com/blog/feed/', $feeds );
		$this->assertArrayHasKey( 'autoselect', $feeds['https://wordpress.com/blog/feed/'] );
		$this->assertTrue( $feeds['https://wordpress.com/blog/feed/']['autoselect'] );
		$this->assertEquals( 'WordPress.com', User::get_display_name_from_feeds( $feeds ) );
		$this->assertEquals( 'wordpress.com', User::get_user_login_from_feeds( $feeds ) );
	}

	public function test_kellyblog() {
		$friends = Friends::get_instance();
		$feeds = $friends->feed->discover_available_feeds( 'https://kelly.blog/' );
		$this->assertArrayHasKey( 'https://kelly.blog/feed/', $feeds );
		$this->assertArrayHasKey( 'autoselect', $feeds['https://kelly.blog/feed/'] );
		$this->assertTrue( $feeds['https://kelly.blog/feed/']['autoselect'] );
		$this->assertEquals( 'kelly.blog', User::get_display_name_from_feeds( $feeds ) );
		$this->assertEquals( 'kelly.blog', User::get_user_login_from_feeds( $feeds ) );
	}

}
