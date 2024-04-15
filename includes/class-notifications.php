<?php
/**
 * Friends Notifications
 *
 * This contains the functions for Notifications.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the Notifications part of the Friends Plugin.
 *
 * @since 0.6
 *
 * @package Friends
 * @author Alex Kirk
 */
class Notifications {
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
		add_action( 'friends_rewrite_mail_html', array( $this, 'rewrite_mail_html' ) );
		add_action( 'notify_new_friend_post', array( $this, 'notify_new_friend_post' ), 10, 2 );
		add_filter( 'notify_keyword_match_post', array( $this, 'notify_keyword_match_post' ), 10, 3 );
		add_action( 'notify_new_friend_request', array( $this, 'notify_new_friend_request' ) );
		add_action( 'notify_accepted_friend_request', array( $this, 'notify_accepted_friend_request' ) );
		add_action( 'notify_friend_message_received', array( $this, 'notify_friend_message_received' ), 10, 3 );
	}

	/**
	 * Sets the friends plugin from email address when sending mail.
	 *
	 * If you're having issues with the receiver using the envelope From: address in favor of the mail header,
	 * you can use a filter like this:
	 * add_filter( 'wp_mail_from', function( $from ) {
	 * ini_set( 'sendmail_from', $from );
	 * return $from;
	 * }, 100 );
	 *
	 * @param      string $from   The from that's supposed to be used.
	 *
	 * @return     string  The friends plugin from email address.
	 */
	public function use_friends_plugin_from_email_address( $from ) {
		$from = preg_replace( '/^wordpress@/', 'friends-plugin@', $from );
		return $from;
	}


	/**
	 * Gets the friends plugin from email address.
	 *
	 * @return     string  The friends plugin from email address.
	 */
	public function get_friends_plugin_from_email_address() {
		add_filter( 'wp_mail_from', array( $this, 'use_friends_plugin_from_email_address' ) );
		$address = apply_filters( 'wp_mail_from', 'wordpress@' . preg_replace( '#^www\.#', '', wp_parse_url( network_home_url(), PHP_URL_HOST ) ) );
		remove_filter( 'wp_mail_from', array( $this, 'use_friends_plugin_from_email_address' ) );
		return $address;
	}

	/**
	 * Notify the users of this site about a new friend post
	 *
	 * @param  \WP_Post  $post The new post by a friend or subscription.
	 * @param  User_Feed $user_feed The feed where the post came from.
	 */
	public function notify_new_friend_post( \WP_Post $post, User_Feed $user_feed ) {
		if (
			// Post might be trashed through rules.
			'trash' === $post->post_status
			// Don't notify about posts older than a week.
			|| strtotime( $post->post_date_gmt ) + WEEK_IN_SECONDS < time()
		) {
			return;
		}
		$user = new User( Friends::get_main_friend_user_id() );
		if ( ! $user->user_email ) {
			return;
		}
		$author = $user_feed->get_friend_user();

		$notify_user = ! get_user_option( 'friends_no_new_post_notification', $user->ID );
		$notify_user = $notify_user && ! get_user_option( 'friends_no_new_post_notification_' . $author->user_login, $user->ID );

		if ( ! apply_filters( 'notify_user_about_friend_post', $notify_user, $user, $post, $author ) ) {
			return;
		}

		$email_title = $post->post_title;

		$params = array(
			'author' => $author,
			'post'   => $post,
		);

		$email_message = array();
		ob_start();
		Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
		Friends::template_loader()->get_template_part( 'email/new-friend-post', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer' );
		$email_message['html'] = ob_get_contents();
		ob_end_clean();

		ob_start();
		Friends::template_loader()->get_template_part( 'email/new-friend-post-text', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer-text' );
		$email_message['text'] = ob_get_contents();
		ob_end_clean();

		$this->send_mail( $user->user_email, wp_specialchars_decode( $email_title, ENT_QUOTES ), $email_message, array(), array(), $author->user_login );
	}

	/**
	 * Notifies about a post that matched the keyword.
	 *
	 * @param      bool     $notified  Whether a notification was sent.
	 * @param      \WP_Post $post      The new post by a friend or subscription.
	 * @param      string   $keyword   The matched keyword.
	 */
	public function notify_keyword_match_post( $notified, \WP_Post $post, $keyword ) {
		if ( 'trash' === $post->post_status ) {
			return $notified;
		}

		$user = new User( Friends::get_main_friend_user_id() );
		if ( ! $user->user_email ) {
			return $notified;
		}
		$notify_user = ! get_user_option( 'friends_no_keyword_notification_' . $post->post_author, $user->ID );

		if ( ! apply_filters( 'notify_user_about_keyword_post', $notify_user, $user, $post, $keyword ) ) {
			return $notified;
		}

		$author = User::get_post_author( $post );
		// translators: %s is a keyword string specified by the user.
		$email_title = sprintf( __( 'Keyword matched: %s', 'friends' ), $keyword );

		$params = array(
			'author'  => $author,
			'post'    => $post,
			'keyword' => $keyword,
		);

		$email_message = array();
		ob_start();
		Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
		Friends::template_loader()->get_template_part( 'email/keyword-match-post', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer' );
		$email_message['html'] = ob_get_contents();
		ob_end_clean();

		ob_start();
		Friends::template_loader()->get_template_part( 'email/keyword-match-post-text', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer-text' );
		$email_message['text'] = ob_get_contents();
		ob_end_clean();

		$this->send_mail( $user->user_email, wp_specialchars_decode( $email_title, ENT_QUOTES ), $email_message, array(), array(), $author->user_login );

		return true;
	}

	/**
	 * Notify the users of this site about a new friend request
	 *
	 * @param  User $friend_user The user requesting friendship.
	 */
	public function notify_new_friend_request( User $friend_user ) {
		if ( ! $friend_user->has_cap( 'friend_request' ) ) {
			return;
		}
		$user = new User( Friends::get_main_friend_user_id() );

		if ( ! $user->user_email ) {
			return;
		}

		$notify_user = ! get_user_option( 'friends_no_friend_request_notification', $user->ID );
		if ( ! apply_filters( 'notify_user_about_friend_request', $notify_user, $user, $friend_user ) ) {
			return;
		}

		// translators: %s is a user display name.
		$email_title = sprintf( __( '%s sent a Friend Request', 'friends' ), $friend_user->display_name );

		$params = array(
			'user'        => $user,
			'friend_user' => $friend_user,
		);

		$email_message = array();
		ob_start();
		Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
		Friends::template_loader()->get_template_part( 'email/new-friend-request', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer' );
		$email_message['html'] = ob_get_contents();
		ob_end_clean();

		ob_start();
		Friends::template_loader()->get_template_part( 'email/new-friend-request-text', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer-text' );
		$email_message['text'] = ob_get_contents();
		ob_end_clean();

		$this->send_mail( $user->user_email, $email_title, $email_message );
	}

	/**
	 * Notify the users of this site about an accepted friend request
	 *
	 * @param  User $friend_user The user who accepted friendship.
	 */
	public function notify_accepted_friend_request( User $friend_user ) {
		$user = new User( Friends::get_main_friend_user_id() );
		if ( ! $user->user_email ) {
			return;
		}

		if ( ! apply_filters( 'notify_user_about_accepted_friend_request', true, $user, $friend_user ) ) {
			return;
		}

		// translators: %s is a user display name.
		$email_title = sprintf( __( '%s accepted your Friend Request', 'friends' ), $friend_user->display_name );

		$params = array(
			'user'        => $user,
			'friend_user' => $friend_user,
		);

		$email_message = array();
		ob_start();
		Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
		Friends::template_loader()->get_template_part( 'email/accepted-friend-request', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer' );
		$email_message['html'] = ob_get_contents();
		ob_end_clean();

		ob_start();
		Friends::template_loader()->get_template_part( 'email/accepted-friend-request-text', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer-text' );
		$email_message['text'] = ob_get_contents();
		ob_end_clean();

		$this->send_mail( $user->user_email, $email_title, $email_message );
	}

	/**
	 * Notify the users of this site about a received message
	 *
	 * @param  User $friend_user The user who sent the message.
	 */

	/**
	 * Notify the users of this site about a received message
	 *
	 * @param  User   $friend_user The user who sent the message.
	 * @param       string $message     The message.
	 * @param       string $subject     The subject.
	 */
	public function notify_friend_message_received( User $friend_user, $message, $subject ) {
		$user = new User( Friends::get_main_friend_user_id() );
		if ( ! $user->user_email ) {
			return;
		}

		if ( ! apply_filters( 'notify_user_about_friend_message', true, $user, $friend_user ) ) {
			return;
		}

		// translators: %s is a user display name.
		$email_title = sprintf( __( '%s sent you a message', 'friends' ), $friend_user->display_name );

		$params = array(
			'user'        => $user,
			'friend_user' => $friend_user,
			'subject'     => $subject,
			'message'     => $message,
		);

		$email_message = array();
		ob_start();
		Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
		Friends::template_loader()->get_template_part( 'email/friend-message-received', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer' );
		$email_message['html'] = ob_get_contents();
		ob_end_clean();

		ob_start();
		Friends::template_loader()->get_template_part( 'email/friend-message-received-text', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer-text' );
		$email_message['text'] = ob_get_contents();
		ob_end_clean();

		$this->send_mail( $user->user_email, $email_title, $email_message );
	}

	/**
	 * Rewrite HTML for e-mail (by inlining some CSS styles)
	 *
	 * @param      string $html   The HTML.
	 *
	 * @return     string  The rewritten HTML.
	 */
	public function rewrite_mail_html( $html ) {
		$img_regex = '<figure\b[^>]*>\s*(<a\b[^>]*>\s*)?<img\b';
		$html = preg_replace( '/' . $img_regex . '/i', '$0 style="max-width: 100% !important; height: auto !important;"', $html );
		$html = preg_replace( '/(' . $img_regex . '.*?)width=[\'"]\d+[\'"]/i', '$1', $html );
		$html = preg_replace( '/(' . $img_regex . '.*?)height=[\'"]\d+[\'"]/i', '$1', $html );
		return $html;
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
		$sitename = get_option( 'blogname' );

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
					$plain_text = wp_strip_all_tags( $message['html'] );
				}

				$headers[]    = 'Content-type: text/html';
				$alt_function = function ( $mailer ) use ( $plain_text ) {
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

		add_filter( 'wp_mail_from', array( $this, 'use_friends_plugin_from_email_address' ) );

		$mail = wp_mail( $to, $subject, $message, $headers, $attachments );

		remove_filter( 'wp_mail_from', array( $this, 'use_friends_plugin_from_email_address' ) );

		if ( $alt_function ) {
			remove_action( 'phpmailer_init', $alt_function );
		}

		return $mail;
	}
}
