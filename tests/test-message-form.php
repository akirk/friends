<?php
/**
 * Test the direct message form.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the direct message form.
 */
class MessageFormTest extends \WP_UnitTestCase {
	/**
	 * Render the message form.
	 *
	 * @param array $args Template arguments.
	 * @return string
	 */
	private function render_form( $args = array() ) {
		$friend_user             = new \stdClass();
		$friend_user->user_login = 'friend.example';
		$args                    = array_merge(
			array(
				'accounts'          => array( 'https://friend.example/' => 'Friend' ),
				'blocks-everywhere' => false,
				'friend_user'       => $friend_user,
			),
			$args
		);

		ob_start();
		Friends::template_loader()->get_template_part( 'frontend/messages/message-form', null, $args );
		return ob_get_clean();
	}

	public function test_new_message_has_optional_content_warning() {
		$html = $this->render_form();

		$this->assertStringContainsString( '>Content warning</label>', $html );
		$this->assertStringContainsString( 'name="friends_message_subject"', $html );
		$this->assertStringContainsString( 'placeholder="Content warning (optional)"', $html );
		$this->assertStringContainsString( 'id="friends-send-new-message"', $html );
	}

	public function test_reply_has_an_empty_optional_content_warning() {
		$html = $this->render_form(
			array(
				'reply_to' => 42,
			)
		);

		$this->assertStringContainsString( '>Content warning</label>', $html );
		$this->assertStringContainsString( 'id="friends_message_content_warning_42"', $html );
		$this->assertStringContainsString( 'name="friends_message_subject" value=""', $html );
		$this->assertStringNotContainsString( 'id="friends-send-new-message"', $html );
	}
}
