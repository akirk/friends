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
			// translators: %s is a username.
			$message = sprintf( __( 'Howdy, %s' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			// translators: %1$s is a username, %2$s is a URL.
			$message .= sprintf( __( 'Your friend %1$s has posted something new: %2$s', 'friends' ), $author->display_name, site_url( '/friends/' . $post->ID . '/' ) ) . PHP_EOL . PHP_EOL;
			$message .= __( 'Best, the Friends plugin', 'friends' ) . PHP_EOL;

			// translators: %s is a post title.
			$this->send_mail( $user->user_email, sprintf( __( 'New Friend Post: %s', 'friends' ), wp_specialchars_decode( $post->post_title, ENT_QUOTES ) ), $message );
		}
	}

	/**
	 * Notify the users of this site about a new friend request
	 *
	 * @param  WP_User $friend_user The user requesting friendship.
	 */
	public function notify_new_friend_request( WP_User $friend_user ) {
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

			// TODO: Opt out of these e-mails.
			// translators: %s is a username.
			$message = sprintf( __( 'Howdy, %s' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			// translators: %s is a username.
			$message .= sprintf( __( 'You have received a new friend request from %s.', 'friends' ), $friend_user->display_name ) . PHP_EOL . PHP_EOL;
			$message .= sprintf( __( 'Go to your admin page to review the request and approve or delete it.', 'friends' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			$message .= self_admin_url( 'users.php?role=friend_request' ) . PHP_EOL . PHP_EOL;
			$message .= __( 'Best, the Friends plugin', 'friends' ) . PHP_EOL;

			// translators: %s is a username.
			$this->send_mail( $user->user_email, sprintf( __( 'New Friend Request from %s', 'friends' ), wp_specialchars_decode( $friend_user->display_name, ENT_QUOTES ) ), $message );
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

			// TODO: Opt out of these e-mails.
			// translators: %s is a username.
			$message = sprintf( __( 'Howdy, %s' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			// translators: %s is a username.
			$message .= sprintf( __( '%s has accepted your friend request.', 'friends' ), $friend_user->display_name ) . PHP_EOL . PHP_EOL;
			$message .= sprintf( __( 'Go to your friends page and look at their posts.', 'friends' ), $user->display_name ) . PHP_EOL . PHP_EOL;
			$message .= site_url( '/friends/' ) . PHP_EOL . PHP_EOL;
			$message .= __( 'Best, the Friends plugin', 'friends' ) . PHP_EOL;

			// translators: %s is a username.
			$this->send_mail( $user->user_email, sprintf( __( '%s accepted your Friend Request', 'friends' ), wp_specialchars_decode( $friend_user->display_name, ENT_QUOTES ) ), $message );
		}
	}

	/**
	 * Wrapper for wp_mail to be overridden in unit tests
	 *
	 * @param string|array $to          Array or comma-separated list of email addresses to send message.
	 * @param string       $subject     Email subject.
	 * @param string       $message     Message contents.
	 * @param string|array $headers     Optional. Additional headers.
	 * @param string|array $attachments Optional. Files to attach.
	 * @return bool Whether the email contents were sent successfully.
	 */
	public function send_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		if ( ! apply_filters( 'friends_send_mail', true, $to, $subject, $message, $headers ) ) {
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

		$headers = 'Content-Type: text/plain; charset="' . $charset . "\"\n" . $headers;
		return wp_mail( $to, $subject, $message, $headers, $attachments );
	}
}
