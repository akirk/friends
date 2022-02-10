<?php
/**
 * Class Friends_UserTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Notifications
 */
class UserTest extends \WP_UnitTestCase {
	/**
	 * Test get_user_login_for_url.
	 */
	public function test_get_user_login_for_url() {
		$this->assertEquals( User::get_user_login_for_url( 'me.local' ), 'me.local' );
	}
	/**
	 * Test get_display_name_for_url.
	 */
	public function test_get_display_name_for_url() {
		$this->assertEquals( User::get_display_name_for_url( 'me.local' ), 'me.local' );
	}
}
