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
			'friends_send_mail', function( $assert ) use ( $that ) {
				$that->assertTrue( $assert );
				return false;
			}
		);

		apply_filters( 'friends_send_mail', true );
		// TODO: Implementation.
	}
}
