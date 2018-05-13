<?php
/**
 * Class Friends_NoticiationTest
 *
 * @package Friends
 */

/**
 * Test the Notifications
 */
class Friends_NoticiationTest extends WP_UnitTestCase {
	/**
	 * Test notifications of a new post.
	 */
	public function test_notify_new_post() {
		$that = $this;
		add_filter(
			'friends_send_mail', function() use ( $that ) {
				$that->assertTrue( true );
				return false;
			}
		);
		// TODO: Implementation.
	}
}
