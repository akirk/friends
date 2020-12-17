<?php
/**
 * Class Friends_Friend_UserTest
 *
 * @package Friends
 */

/**
 * Test the Notifications
 */
class Friends_Friend_UserTest extends WP_UnitTestCase {
	/**
	 * Test get_user_login_for_url.
	 */
	public function test_get_user_login_for_url() {
		$this->assertEquals( Friend_User::get_user_login_for_url( 'me.local' ), 'me.local' );
	}
	/**
	 * Test get_display_name_for_url.
	 */
	public function test_get_display_name_for_url() {
		$this->assertEquals( Friend_User::get_display_name_for_url( 'me.local' ), 'me.local' );
	}
}
