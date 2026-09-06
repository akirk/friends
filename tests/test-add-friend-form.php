<?php
/**
 * Class AddFriendFormTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Follow widget and the Add Friend form it submits to.
 */
class AddFriendFormTest extends \WP_UnitTestCase {
	/**
	 * Render the Follow widget.
	 *
	 * @return string The widget HTML.
	 */
	private function render_widget() {
		$widget = new Widget_Add_Subscription();
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			array()
		);
		return ob_get_clean();
	}

	/**
	 * Render the Add Friend form.
	 *
	 * @return string The form HTML.
	 */
	private function render_form() {
		ob_start();
		Friends::template_loader()->get_template_part( 'frontend/add-friend-form' );
		return ob_get_clean();
	}

	public function test_widget_submits_to_the_add_friend_page() {
		$html = $this->render_widget();

		$this->assertStringContainsString( 'action="' . esc_url( home_url( '/friends/add-friend/' ) ) . '"', $html );
		$this->assertStringContainsString( 'name="url"', $html );

		// The form used to post to an endpoint that doesn't exist, which silently rendered the main Friends page.
		$this->assertStringNotContainsString( 'add-suscription', $html );
	}

	public function test_add_friend_form_is_empty_without_a_url() {
		$html = $this->render_form();

		$this->assertStringContainsString( 'id="subscription-url"', $html );
		$this->assertStringContainsString( '></textarea>', $html );
	}

	public function test_add_friend_form_prefills_the_url() {
		$_GET['url'] = '@pfefferle@mastodon.social';
		$html = $this->render_form();
		unset( $_GET['url'] );

		$this->assertStringContainsString( '>@pfefferle@mastodon.social</textarea>', $html );
	}

	public function test_add_friend_form_escapes_the_prefilled_url() {
		$_GET['url'] = '</textarea><script>alert(1)</script>';
		$html = $this->render_form();
		unset( $_GET['url'] );

		$this->assertStringNotContainsString( '<script>', $html );
	}
}
