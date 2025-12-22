<?php
/**
 * Test usernames with special characters like apostrophes
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the handling of usernames with special characters
 */
class UsernameSpecialCharsTest extends \WP_UnitTestCase {
	/**
	 * User ID of a friend with apostrophe in name
	 *
	 * @var int
	 */
	private $friend_with_apostrophe_id;

	/**
	 * Setup the unit tests.
	 */
	public function set_up() {
		parent::set_up();

		// Create a friend with an apostrophe in the username
		$this->friend_with_apostrophe_id = $this->factory->user->create(
			array(
				'user_login' => "John's Friend",
				'user_email' => 'johns.friend@example.org',
				'role'       => 'friend',
			)
		);
	}

	/**
	 * Test that get_edit_friend_link properly encodes usernames with apostrophes
	 */
	public function test_edit_friend_link_with_apostrophe() {
		$user = new User( $this->friend_with_apostrophe_id );
		$link = Admin::admin_edit_user_link( '', $user );
		
		// The link should contain the URL-encoded username
		$this->assertStringContainsString( rawurlencode( "John's Friend" ), $link );
		
		// The link should NOT contain the unencoded apostrophe directly in the URL
		$this->assertStringNotContainsString( "user=John's Friend", $link );
	}

	/**
	 * Test that get_by_username works with apostrophes
	 */
	public function test_get_by_username_with_apostrophe() {
		$user = User::get_by_username( "John's Friend" );
		
		$this->assertNotFalse( $user );
		$this->assertInstanceOf( '\Friends\User', $user );
		$this->assertEquals( "John's Friend", $user->user_login );
	}

	/**
	 * Test that URL parameter decoding works correctly
	 */
	public function test_url_parameter_decoding() {
		// Simulate what happens when the URL is parsed
		$_GET['user'] = "John's Friend";
		
		// The code uses wp_unslash() which should preserve the apostrophe
		$username = wp_unslash( $_GET['user'] );
		
		$this->assertEquals( "John's Friend", $username );
		
		// Verify we can look up the user
		$user = User::get_by_username( $username );
		$this->assertNotFalse( $user );
		$this->assertEquals( "John's Friend", $user->user_login );
		
		// Clean up
		unset( $_GET['user'] );
	}

	/**
	 * Test that nonces work with usernames containing apostrophes
	 */
	public function test_nonces_with_apostrophe() {
		$user = new User( $this->friend_with_apostrophe_id );
		
		// Create a nonce with the username
		$nonce = wp_create_nonce( 'test-action-' . $user->user_login );
		
		// Verify the nonce works
		$this->assertNotFalse( wp_verify_nonce( $nonce, 'test-action-' . $user->user_login ) );
		
		// Verify it doesn't work with sanitized username (which would remove the apostrophe)
		$this->assertFalse( wp_verify_nonce( $nonce, 'test-action-' . sanitize_user( $user->user_login ) ) );
	}

	/**
	 * Test the unfriend link with apostrophes
	 */
	public function test_unfriend_link_with_apostrophe() {
		$user = new User( $this->friend_with_apostrophe_id );
		$link = Admin::get_unfriend_link( $user );
		
		// The link should contain the URL-encoded username
		$this->assertStringContainsString( rawurlencode( "John's Friend" ), $link );
	}
}
