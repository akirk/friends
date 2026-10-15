<?php
/**
 * Display mode preference tests.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Verify area preferences and the main feed fallback.
 */
class DisplayModeTest extends \WP_UnitTestCase {
	/**
	 * Area overrides survive default changes and can fall back again.
	 */
	public function test_area_fallback_and_user_isolation() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$frontend = Friends::get_instance()->frontend;
		$this->assertTrue(
			is_callable( array( $frontend, 'get_display_mode' ) ),
			'The frontend must resolve the saved display mode for each area.'
		);
		$area = new \ReflectionProperty( Frontend::class, 'display_view' );
		$area->setAccessible( true );
		$previous = $area->getValue( $frontend );
		try {
			$area->setValue( $frontend, 'friends/type/status' );
			$this->assertSame( 'expanded', $frontend->get_display_mode() );
			update_user_option( $user_id, 'friends_frontend_default_view', 'collapsed' );
			$this->assertSame( 'collapsed', $frontend->get_display_mode() );
			update_user_option( $user_id, 'friends_frontend_views', array( 'friends/type/status' => 'expanded' ) );
			$this->assertSame( 'expanded', $frontend->get_display_mode() );
			$area->setValue( $frontend, 'friends' );
			$this->assertSame( 'collapsed', $frontend->get_display_mode() );
			$area->setValue( $frontend, 'friends/type/status' );
			delete_user_option( $user_id, 'friends_frontend_views' );
			$this->assertSame( 'collapsed', $frontend->get_display_mode() );
			wp_set_current_user( self::factory()->user->create() );
			$this->assertSame( 'expanded', $frontend->get_display_mode() );
		} finally {
			$area->setValue( $frontend, $previous );
			wp_set_current_user( 0 );
		}
	}
}
