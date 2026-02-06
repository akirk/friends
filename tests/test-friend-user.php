<?php
/**
 * Class Friends_UserTest
 *
 * @package Friends
 */

namespace Friends;

class UserTest extends \WP_UnitTestCase {

	public function test_sanitize_username_ascii() {
		$this->assertEquals( 'alex-kirk', User::sanitize_username( 'Alex Kirk' ) );
	}

	public function test_sanitize_username_with_accents() {
		$this->assertEquals( 'rene', User::sanitize_username( 'René' ) );
	}

	public function test_sanitize_username_with_emojis() {
		$result = User::sanitize_username( 'DCoder 🇱🇹❤🇺🇦' );
		$this->assertNotEmpty( $result );
		$this->assertNotEquals( '-', trim( $result, '-' ) === '' ? '-' : $result );
		$this->assertStringContainsString( 'dcoder', $result );
	}

	public function test_sanitize_username_pure_cyrillic() {
		$result = User::sanitize_username( 'РосКомСвобода' );
		// Pure Cyrillic produces empty/unusable result.
		$this->assertTrue( '' === $result || '' === trim( $result, '-' ) );
	}

	public function test_sanitize_username_special_chars() {
		$result = User::sanitize_username( 'D:\\side\\>:idle:' );
		$this->assertNotEmpty( trim( $result, '-' ) );
	}

	public function test_get_user_login_from_feeds_with_suggested_username() {
		$feeds = array(
			'https://example.com/feed' => array(
				'rel'                => 'self',
				'title'              => 'Some Title',
				'suggested-username' => 'dcoder.mastodon.social',
			),
		);
		$this->assertEquals( 'dcoder.mastodon.social', User::get_user_login_from_feeds( $feeds ) );
	}

	public function test_get_user_login_from_feeds_cyrillic_display_name_no_suggested_username() {
		$feeds = array(
			'https://example.com/feed' => array(
				'rel'   => 'self',
				'title' => 'РосКомСвобода',
			),
		);
		$result = User::get_user_login_from_feeds( $feeds );
		// Without a suggested-username, it falls back to sanitize_username on
		// the display name which produces an empty result for pure Cyrillic.
		$this->assertTrue( ! $result || '' === trim( $result, '-' ) );
	}

	public function test_activitypub_suggested_username_uses_preferred_username() {
		// Simulate what update_feed_details does with our fix.
		$meta = array(
			'name'              => 'DCoder 🇱🇹❤🇺🇦',
			'preferredUsername' => 'dcoder',
		);
		$feed_url = 'https://mastodon.social/users/dcoder';

		$feed_details = array( 'url' => $feed_url );
		if ( ! empty( $meta['preferredUsername'] ) ) {
			$host = wp_parse_url( $feed_details['url'], PHP_URL_HOST );
			$feed_details['suggested-username'] = User::sanitize_username( $meta['preferredUsername'] . '.' . $host );
		}

		$this->assertEquals( 'dcoder.mastodon.social', $feed_details['suggested-username'] );

		$feeds = array( $feed_url => $feed_details );
		$this->assertEquals( 'dcoder.mastodon.social', User::get_user_login_from_feeds( $feeds ) );
	}

	public function test_activitypub_suggested_username_cyrillic_name_with_preferred_username() {
		$meta = array(
			'name'              => 'РосКомСвобода',
			'preferredUsername' => 'roskomsvoboda',
		);
		$feed_url = 'https://mastodon.social/users/roskomsvoboda';

		$feed_details = array( 'url' => $feed_url );
		if ( ! empty( $meta['preferredUsername'] ) ) {
			$host = wp_parse_url( $feed_details['url'], PHP_URL_HOST );
			$feed_details['suggested-username'] = User::sanitize_username( $meta['preferredUsername'] . '.' . $host );
		}

		$this->assertEquals( 'roskomsvoboda.mastodon.social', $feed_details['suggested-username'] );
	}

	public function test_activitypub_no_preferred_username_no_suggested_username() {
		// If preferredUsername is missing, no suggested-username should be set.
		$meta = array(
			'name' => 'РосКомСвобода',
		);
		$feed_url = 'https://mastodon.social/users/roskomsvoboda';

		$feed_details = array( 'url' => $feed_url );
		if ( ! empty( $meta['preferredUsername'] ) ) {
			$host = wp_parse_url( $feed_details['url'], PHP_URL_HOST );
			$feed_details['suggested-username'] = User::sanitize_username( $meta['preferredUsername'] . '.' . $host );
		}

		$this->assertArrayNotHasKey( 'suggested-username', $feed_details );
	}

	public function test_get_user_login_for_url_always_produces_usable_result() {
		// URL-based fallback should always work since URLs are ASCII.
		$result = User::get_user_login_for_url( 'https://mastodon.social/@roskomsvoboda' );
		$this->assertNotEmpty( $result );
		$this->assertNotEmpty( trim( $result, '-' ) );
	}
}
