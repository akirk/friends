<?php
/**
 * Frontend display mode saving tests.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Verify that the main feed toggle updates the settings page default.
 */
class DisplayModeAjaxTest extends \WP_Ajax_UnitTestCase {
	/**
	 * Both toggle directions update the default without replacing area overrides.
	 */
	public function test_main_feed_toggle_updates_default() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_option( 'friends_main_user_id', $user_id );
		$views = array( 'friends/type/status' => 'expanded' );
		update_user_option( $user_id, 'friends_frontend_views', $views );
		$previous_post = $_POST;
		$previous_request = $_REQUEST;

		try {
			foreach ( array( 'collapsed', 'expanded' ) as $display_mode ) {
				$_POST = array(
					'view'        => 'friends',
					'mode'        => $display_mode,
					'_ajax_nonce' => wp_create_nonce( 'friends-display-mode-friends' ),
				);
				$_REQUEST = $_POST;
				$this->_last_response = '';
				try {
					$this->_handleAjax( 'friends-display-mode' );
				} catch ( \WPAjaxDieContinueException $exception ) {
					// Successful JSON responses end the AJAX request.
				}
				$this->assertTrue( json_decode( $this->_last_response, true )['success'] );
				$this->assertSame( $display_mode, get_user_option( 'friends_frontend_default_view', $user_id ) );
				$this->assertSame( $views, get_user_option( 'friends_frontend_views', $user_id ) );
			}
		} finally {
			$_POST = $previous_post;
			$_REQUEST = $previous_request;
			wp_set_current_user( 0 );
		}
	}
}
