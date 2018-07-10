<?php
/**
 * Friends Notifications
 *
 * This contains the functions for Notifications.
 *
 * @package Friends
 */

/**
 * This is the class for the Notifications part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Friends_Notifications {
	/**
	 * Contains a reference to the Friends class.
	 *
	 * @var Friends
	 */
	private $friends;

	/**
	 * Constructor
	 *
	 * @param Friends $friends A reference to the Friends object.
	 */
	public function __construct( Friends $friends ) {
		$this->friends = $friends;
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'notify_new_friend_post', array( $this, 'notify_new_friend_post' ) );
		add_action( 'notify_new_friend_request', array( $this, 'notify_new_friend_request' ) );
		add_action( 'notify_accepted_friend_request', array( $this, 'notify_accepted_friend_request' ) );
	}

	/**
	 * Notify the users of this site about a new friend request
	 *
	 * @param  WP_Post $post The new post by a friend.
	 */
	public function notify_new_friend_post( WP_Post $post ) {
		$users = new WP_User_Query( array( 'role' => 'administrator' ) );
		$users = $users->get_results();

		foreach ( $users as $user ) {
			if ( ! $user->user_email ) {
				continue;
			}
			$notify_user = ! get_user_option( 'friends_no_new_post_notification', $user->ID );
			$notify_user = $notify_user && ! get_user_option( 'friends_no_new_post_notification_' . $post->post_author, $user->ID );

			if ( ! apply_filters( 'notify_user_about_friend_post', $notify_user, $user, $post ) ) {
				continue;
			}

			$author = new WP_User( $post->post_author );
			// translators: %s is a post title.
			$email_title = sprintf( __( 'New Friend Post: %s', 'friends' ), $post->post_title );

			$message = array();
			ob_start();
			include apply_filters( 'friends_template_path', 'email/new-friend-post.php' );
			$message['html'] = ob_get_contents();
			ob_end_clean();

			ob_start();
			include apply_filters( 'friends_template_path', 'email/new-friend-post.text.php' );
			$message['text'] = ob_get_contents();
			ob_end_clean();

			$this->send_mail( $user->user_email, wp_specialchars_decode( $email_title, ENT_QUOTES ), $message );
		}
	}

	/**
	 * Notify the users of this site about a new friend request
	 *
	 * @param  WP_User $friend_user The user requesting friendship.
	 */
	public function notify_new_friend_request( WP_User $friend_user ) {
		if ( ! $friend_user->has_cap( 'friend_request' ) ) {
			return;
		}

		$users = new WP_User_Query( array( 'role' => 'administrator' ) );
		$users = $users->get_results();

		foreach ( $users as $user ) {
			if ( ! $user->user_email ) {
				continue;
			}

			$notify_user = ! get_user_option( 'friends_no_friend_request_notification', $user->ID );
			if ( ! apply_filters( 'notify_user_about_friend_request', $notify_user, $user, $friend_user ) ) {
				continue;
			}

			// translators: %s is a user display name.
			$email_title = sprintf( __( 'New Friend Request from %s', 'friends' ), $friend_user->display_name );

			$message = array();
			ob_start();
			include apply_filters( 'friends_template_path', 'email/new-friend-request.php' );
			$message['html'] = ob_get_contents();
			ob_end_clean();

			ob_start();
			include apply_filters( 'friends_template_path', 'email/new-friend-request.text.php' );
			$message['text'] = ob_get_contents();
			ob_end_clean();

			$this->send_mail( $user->user_email, $email_title, $message );
		}

	}

	/**
	 * Notify the users of this site about an accepted friend request
	 *
	 * @param  WP_User $friend_user The user who accepted friendship.
	 */
	public function notify_accepted_friend_request( WP_User $friend_user ) {
		$users = new WP_User_Query( array( 'role' => 'administrator' ) );
		$users = $users->get_results();

		foreach ( $users as $user ) {
			if ( ! $user->user_email ) {
				continue;
			}

			if ( ! apply_filters( 'notify_user_about_accepted_friend_request', true, $user, $friend_user ) ) {
				continue;
			}

			// translators: %s is a user display name.
			$email_title = sprintf( __( '%s accepted your Friend Request', 'friends' ), $friend_user->display_name );

			$message = array();
			ob_start();
			include apply_filters( 'friends_template_path', 'email/accepted-friend-request.php' );
			$message['html'] = ob_get_contents();
			ob_end_clean();

			ob_start();
			include apply_filters( 'friends_template_path', 'email/accepted-friend-request.text.php' );
			$message['text'] = ob_get_contents();
			ob_end_clean();

			$this->send_mail( $user->user_email, $email_title, $message );
		}
	}

	/**
	 * Wrapper for wp_mail to be overridden in unit tests
	 *
	 * @param string|array $to          Array or comma-separated list of email addresses to send message.
	 * @param string       $subject     Email subject.
	 * @param string       $message     Message contents.
	 * @param array        $headers     Optional. Additional headers.
	 * @param array        $attachments Optional. Files to attach.
	 * @return bool Whether the email contents were sent successfully.
	 */
	public function send_mail( $to, $subject, $message, array $headers = array(), array $attachments = array() ) {
		if ( ! apply_filters( 'friends_send_mail', true, $to, $subject, $message, $headers, $attachments ) ) {
			return;
		}

		if ( is_multisite() ) {
			$sitename = get_site_option( 'site_name' );
		} else {
			$sitename = get_option( 'blogname' );
		}

		$subject = sprintf( '[%s] %s', wp_specialchars_decode( $sitename, ENT_QUOTES ), $subject );

		if ( is_multisite() ) {
			$sitename = get_site_option( 'site_name' );
			$charset  = get_option( 'blog_charset' );
		} else {
			$sitename = get_option( 'blogname' );
			$charset  = get_option( 'blog_charset' );
		}

		$domain = parse_url( get_option( 'siteurl' ), PHP_URL_HOST );

		$alt_function = null;
		if ( is_array( $message ) ) {
			if ( isset( $message['html'] ) ) {
				if ( isset( $message['text'] ) ) {
					$plain_text = $message['text'];
				} else {
					$plain_text = strip_tags( $message['html'] );
				}

				$headers[]    = 'Content-type: text/html';
				$alt_function = function( $mailer ) use ( $plain_text ) {
					$mailer->{'AltBody'} = $plain_text;
				};
				add_action(
					'phpmailer_init', $alt_function
				);

				$message = $message['html'];
			} elseif ( isset( $message['text'] ) ) {
				$message = $message['text'];
			}
		}
		$headers[] = 'From: friends-plugin@' . $domain;

		$mail = wp_mail( $to, $subject, $message, $headers, $attachments );
		if ( $alt_function ) {
			remove_action( 'phpmailer_init', $alt_function );
		}

		return $mail;
	}
}