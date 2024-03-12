<?php
/**
 * Class Friends_APITest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Notifications
 */
class MessagesTest extends Friends_TestCase_Cache_HTTP {
	/**
	 * Current User ID
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * User ID of a friend at friend.local
	 *
	 * @var int
	 */
	private $friend_id;

	/**
	 * Token for the friend at friend.local
	 *
	 * @var int
	 */
	private $friends_in_token;

	/**
	 * Setup the unit tests.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'friends_enable_wp_friendships', true );

		$this->user_id = $this->factory->user->create(
			array(
				'user_login' => 'me.local',
				'user_email' => 'me@example.org',
				'role'       => 'friend',
				'user_url'   => 'https://me.local',
			)
		);

		$this->friend_id        = $this->factory->user->create(
			array(
				'user_login' => 'friend.local',
				'user_email' => 'friend@example.org',
				'role'       => 'friend',
				'user_url'   => 'https://friend.local',
			)
		);
		$friends                = Friends::get_instance();
		update_option( 'home', 'http://me.local' );

		$this->friends_in_token = wp_generate_password( 128, false );
		update_user_option( $this->user_id, 'friends_out_token', $this->friends_in_token );
		if ( update_user_option( $this->friend_id, 'friends_in_token', $this->friends_in_token ) ) {
			update_option( 'friends_in_token_' . $this->friends_in_token, $this->friend_id );
		}

		$friends_out_token = wp_generate_password( 128, false );
		update_user_option( $this->user_id, 'friends_in_token', $friends_out_token );
		if ( update_user_option( $this->friend_id, 'friends_out_token', $friends_out_token ) ) {
			update_option( 'friends_in_token_' . $friends_out_token, $this->user_id );
		}

		add_filter(
			'user_has_cap',
			function ( $allcaps, $caps, $args, $user ) {
				if ( $this->user_id === $user->ID && in_array( 'friend', $caps, true ) ) {
					$allcaps['friend'] = true;
				}
				return $allcaps;
			},
			10,
			4
		);
	}

	/**
	 * Test sending messages.
	 */
	public function test_send_messages() {
		wp_set_current_user( $this->user_id );
		$friend_user = new User( $this->friend_id );
		$message_id = $friend_user->send_message( 'test' );
		$this->assertNotInstanceOf( 'WP_Error', $message_id );
		$post = get_post( $message_id );
		$this->assertStringContainsString( '"sender":' . $this->user_id, $post->post_content );
		$this->assertStringContainsString( '<p>test</p>', $post->post_content );
	}
}
