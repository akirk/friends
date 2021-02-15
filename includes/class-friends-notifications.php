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
	 * Gets the friends plugin from email address.
	 *
	 * @return     string  The friends plugin from email address.
	 */
	public function get_friends_plugin_from_email_address() {
		$domain = parse_url( get_option( 'siteurl' ), PHP_URL_HOST );
		return 'friends-plugin@' . $domain;
	}

	/**
	 * Notify the users of this site about a new friend request
	 *
	 * @param  WP_Post $post The new post by a friend.
	 */
	public function notify_new_friend_post( WP_Post $post ) {
		if ( 'trash' === $post->post_status ) {
			return;
		}

		$users = Friend_User_Query::all_admin_users();
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

			$author      = new Friend_User( $post->post_author );
			$email_title = $post->post_title;

			$message = array();
			ob_start();

			Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
			Friends::template_loader()->get_template_part(
				'email/new-friend-post',
				null,
				array(
					'author' => $author,
					'post'   => $post,
				)
			);
			Friends::template_loader()->get_template_part( 'email/footer' );
			$message['html'] = ob_get_contents();
			ob_end_clean();

			ob_start();
			Friends::template_loader()->get_template_part(
				'email/new-friend-post.text',
				null,
				array(
					'author' => $author,
					'post'   => $post,
				)
			);
			$message['text'] = ob_get_contents();
			ob_end_clean();

			$this->send_mail( $user->user_email, wp_specialchars_decode( $email_title, ENT_QUOTES ), $message, array(), array(), $author->user_login );
		}
	}

	/**
	 * Notify the users of this site about a new friend request
	 *
	 * @param  Friend_User $friend_user The user requesting friendship.
	 */
	public function notify_new_friend_request( Friend_User $friend_user ) {
		if ( ! $friend_user->has_cap( 'friend_request' ) ) {
			return;
		}

		$users = Friend_User_Query::all_admin_users();
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
			$email_title = sprintf( __( '%s sent a Friend Request', 'friends' ), $friend_user->display_name );

			$message = array();
			ob_start();
			Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
			Friends::template_loader()->get_template_part(
				'email/new-friend-request',
				null,
				array(
					'user'        => $user,
					'friend_user' => $friend_user,
				)
			);
			Friends::template_loader()->get_template_part( 'email/footer' );
			$message['html'] = ob_get_contents();
			ob_end_clean();

			ob_start();
			Friends::template_loader()->get_template_part(
				'email/new-friend-request.text',
				null,
				array(
					'user'        => $user,
					'friend_user' => $friend_user,
				)
			);
			$message['text'] = ob_get_contents();
			ob_end_clean();

			$this->send_mail( $user->user_email, $email_title, $message );
		}

	}

	/**
	 * Notify the users of this site about an accepted friend request
	 *
	 * @param  Friend_User $friend_user The user who accepted friendship.
	 */
	public function notify_accepted_friend_request( Friend_User $friend_user ) {
		$users = Friend_User_Query::all_admin_users();
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
			Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
			Friends::template_loader()->get_template_part(
				'email/accepted-friend-request',
				null,
				array(
					'user'        => $user,
					'friend_user' => $friend_user,
				)
			);
			Friends::template_loader()->get_template_part( 'email/footer' );
			$message['html'] = ob_get_contents();
			ob_end_clean();

			ob_start();
			Friends::template_loader()->get_template_part(
				'email/accepted-friend-request.text',
				null,
				array(
					'user'        => $user,
					'friend_user' => $friend_user,
				)
			);
			$message['text'] = ob_get_contents();
			ob_end_clean();

			$this->send_mail( $user->user_email, $email_title, $message );
		}
	}

	/**
	 * Wrapper for wp_mail to be overridden in unit tests
	 *
	 * @param string|array $to                Array or comma-separated list of email addresses to send message.
	 * @param string       $subject           Email subject.
	 * @param string       $message           Message contents.
	 * @param array        $headers           Optional. Additional headers.
	 * @param array        $attachments       Optional. Files to attach.
	 * @param string       $override_sitename Optional. Override the sitename.
	 * @return bool Whether the email contents were sent successfully.
	 */
	public function send_mail( $to, $subject, $message, array $headers = array(), array $attachments = array(), $override_sitename = false ) {
		if ( is_multisite() ) {
			$sitename = get_site_option( 'site_name' );
			$charset  = get_site_option( 'blog_charset' );
		} else {
			$sitename = get_option( 'blogname' );
			$charset  = get_option( 'blog_charset' );
		}

		if ( $override_sitename ) {
			$sitename = $override_sitename;
		}

		// translators: %1$s is the site name, %2$s is the subject.
		$subject = sprintf( _x( '[%1$s] %2$s', 'email subject', 'friends' ), wp_specialchars_decode( $sitename, ENT_QUOTES ), $subject );
		$subject = apply_filters( 'friends_send_mail_subject', $subject );

		if ( ! apply_filters( 'friends_send_mail', true, $to, $subject, $message, $headers, $attachments ) ) {
			return;
		}

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
					'phpmailer_init',
					$alt_function
				);

				$message = $message['html'];
			} elseif ( isset( $message['text'] ) ) {
				$message = $message['text'];
			}
		}
		$headers[] = 'From: ' . $this->get_friends_plugin_from_email_address();

		$mail = wp_mail( $to, $subject, $message, $headers, $attachments );
		if ( $alt_function ) {
			remove_action( 'phpmailer_init', $alt_function );
		}

		return $mail;
	}
}
