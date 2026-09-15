<?php
/**
 * Class BlockrollTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Blockroll integration.
 */
class BlockrollTest extends \WP_UnitTestCase {
	/**
	 * Setup the unit tests.
	 */
	public function set_up() {
		parent::set_up();
		User_Query::$cache = false;
	}

	/**
	 * Test that the hidden flag is stored as subscription term meta.
	 */
	public function test_set_hidden_uses_term_meta() {
		$subscription = User::create( 'hidden.example', 'subscription', 'https://hidden.example/' );

		Blockroll::set_hidden( $subscription, true );

		$this->assertTrue( Blockroll::is_hidden( $subscription ) );
		$this->assertSame( '1', get_term_meta( $subscription->get_term_id(), Blockroll::HIDE_META, true ) );

		Blockroll::set_hidden( $subscription, false );

		$this->assertFalse( Blockroll::is_hidden( $subscription ) );
		$this->assertSame( '', get_term_meta( $subscription->get_term_id(), Blockroll::HIDE_META, true ) );
	}

	/**
	 * Test that hidden subscriptions are excluded from Blockroll links.
	 */
	public function test_source_links_excludes_hidden_subscriptions() {
		User::create( 'visible.example', 'subscription', 'https://visible.example/', 'Visible' );
		$hidden = User::create( 'hidden.example', 'subscription', 'https://hidden.example/', 'Hidden' );
		Blockroll::set_hidden( $hidden, true );

		$links = Blockroll::source_links( array(), 'friends-subscriptions' );
		$urls  = wp_list_pluck( $links, 'url' );

		$this->assertContains( 'https://visible.example/', $urls );
		$this->assertNotContains( 'https://hidden.example/', $urls );
	}
}
