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
		add_filter( 'friends_rewrite_mail_html', array( $this, 'rewrite_mail_html' ) );
		add_filter( 'friends_rewrite_mail_html', array( $this, 'highlight_keywords' ), 10, 2 );
		add_action( 'notify_new_friend_post', array( $this, 'notify_new_friend_post' ), 10, 3 );
		add_filter( 'friends_notify_keyword_match_post', array( $this, 'notify_keyword_match_post' ), 10, 3 );
		add_action( 'notify_new_friend_request', array( $this, 'notify_new_friend_request' ) );
		add_action( 'notify_accepted_friend_request', array( $this, 'notify_accepted_friend_request' ) );
		add_action( 'notify_friend_message_received', array( $this, 'notify_friend_message_received' ), 10, 3 );
		add_action( 'notify_unknown_friend_message_received', array( $this, 'notify_unknown_friend_message_received' ), 10, 4 );
		add_action( 'activitypub_new_follower_email', array( $this, 'activitypub_new_follower_email' ), 10, 3 );
		add_action( 'activitypub_followers_post_follow', array( $this, 'activitypub_followers_post_follow' ), 10, 4 );
		add_action( 'activitypub_followers_pre_remove_follower', array( $this, 'activitypub_followers_pre_remove_follower' ), 10, 3 );
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
	 * @param  \WP_Post     $post The new post by a friend or subscription.
	 * @param  User_Feed    $user_feed The feed where the post came from.
	 * @param  string|false $keyword If a post matched a keyword, the keyword is specified.
	 */
	public function notify_new_friend_post( \WP_Post $post, User_Feed $user_feed, $keyword ) {
		if (
			// Post might be trashed through rules.
			'trash' === $post->post_status
			// Don't notify about posts older than a week.
			|| strtotime( $post->post_date_gmt ) + WEEK_IN_SECONDS < time()
		) {
			return;
		}
		$user = new User( Friends::get_main_friend_user_id() );
		if ( defined( 'WP_TESTS_EMAIL' ) ) {
			$user->user_email = WP_TESTS_EMAIL;
		}
		if ( ! $user->user_email ) {
			return;
		}
		$author = $user_feed->get_friend_user();

		if ( ! get_post_format( $post ) ) {
			$post_format = 'standard';
		} else {
			$post_format = get_post_format( $post );
		}

		$notify_user = ! get_user_option( 'friends_no_new_post_notification', $user->ID );
		$notify_user = $notify_user && ! get_user_option( 'friends_no_new_post_notification_' . $author->user_login, $user->ID );
		$notify_user = $notify_user && ! get_user_option( 'friends_no_new_post_format_notification_' . $post_format, $user->ID );
		$notify_user = $notify_user && ! get_user_option( 'friends_no_new_post_by_parser_notification_' . $user_feed->get_parser(), $user->ID );

		// If the post would notify anyway and it was a keyword match, notify that way.
		if ( $notify_user && $keyword && get_user_option( 'friends_keyword_notification_override_disabled', $user->ID ) ) {
			add_filter( 'get_user_option_friends_keyword_notification_override_disabled', '__return_false' );
			$this->notify_keyword_match_post( false, $post, $keyword );
			remove_filter( 'get_user_option_friends_keyword_notification_override_disabled', '__return_false' );
			return;
		}

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
		if ( defined( 'WP_TESTS_EMAIL' ) ) {
			$user->user_email = WP_TESTS_EMAIL;
		}
		if ( ! $user->user_email ) {
			return $notified;
		}
		$notify_user = ! get_user_option( 'friends_no_keyword_notification_' . $post->post_author, $user->ID );
		// If the override was disabled, don't notify. This could be temporarily disabled later.

		if ( $notify_user && get_user_option( 'friends_keyword_notification_override_disabled', $user->ID ) ) {
			return $notified;
		}

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
		if ( defined( 'WP_TESTS_EMAIL' ) ) {
			$user->user_email = WP_TESTS_EMAIL;
		}
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
		if ( defined( 'WP_TESTS_EMAIL' ) ) {
			$user->user_email = WP_TESTS_EMAIL;
		}
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
	 * @param  User   $friend_user The user who sent the message.
	 * @param       string $message     The message.
	 * @param       string $subject     The subject.
	 */
	public function notify_friend_message_received( User $friend_user, $message, $subject ) {
		$user = new User( Friends::get_main_friend_user_id() );
		if ( defined( 'WP_TESTS_EMAIL' ) ) {
			$user->user_email = WP_TESTS_EMAIL;
		}
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
	 * Notify the users of this site about a received message
	 *
	 * @param       string $sender_name The user who sent the message.
	 * @param       string $message     The message.
	 * @param       string $subject     The subject.
	 * @param       string $feed_url    The url of the user who sent the message.
	 */
	public function notify_unknown_friend_message_received( $sender_name, $message, $subject, $feed_url ) {
		$user = new User( Friends::get_main_friend_user_id() );
		if ( defined( 'WP_TESTS_EMAIL' ) ) {
			$user->user_email = WP_TESTS_EMAIL;
		}
		if ( ! $user->user_email ) {
			return;
		}

		if ( ! apply_filters( 'notify_user_about_friend_message', true, $user ) ) {
			return;
		}

		// translators: %s is a user display name.
		$email_title = sprintf( __( '%s sent you a message', 'friends' ), $sender_name );

		$params = array(
			'user'        => $user,
			'sender_name' => $sender_name,
			'feed_url'    => $feed_url,
			'subject'     => $subject,
			'message'     => $message,
		);

		$email_message = array();
		ob_start();
		Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
		Friends::template_loader()->get_template_part( 'email/unknown-friend-message-received', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer' );
		$email_message['html'] = ob_get_contents();
		ob_end_clean();

		ob_start();
		Friends::template_loader()->get_template_part( 'email/unknown-friend-message-received-text', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer-text' );
		$email_message['text'] = ob_get_contents();
		ob_end_clean();

		$this->send_mail( $user->user_email, $email_title, $email_message );
	}

	/**
	 * Augment the ActivityPub new follower e-mail
	 *
	 * @param      array $actor  The actor.
	 */
	public function activitypub_new_follower_email( $actor ) {
		if ( isset( $actor['id'] ) ) {
			$url = $actor['id'];
		} elseif ( isset( $actor['url'] ) ) {
			$url = $actor['url'];
		} else {
			return;
		}

		$url = \ActivityPub\object_to_uri( $url );
		$server = wp_parse_url( $url, PHP_URL_HOST );
		$following = User_Feed::get_by_url( $url );
		if ( ! $following || is_wp_error( $following ) ) {
			$following = User_Feed::get_by_url( str_replace( '/users/', '/@', $url ) );
		}
		if ( $following && ! is_wp_error( $following ) ) {
			$following = $following->get_friend_user();
		} else {
			$following = false;
		}
		echo '<p>';
		if ( $following ) {
			echo esc_html__( 'You are already following them!', 'friends' );
			echo ' ';
			// translators: %s is a URL.
			echo wp_kses( sprintf( __( 'Go to their <a href="%s">friends page</a> to see what they recently posted about.', 'friends' ), esc_url( $following->get_local_friends_page_url() ) ), array( 'a' => array( 'href' => array() ) ) );
		} else {
			// translators: %s is a URL.
			echo wp_kses( sprintf( __( 'Maybe you want to <a href="%s">follow them back</a> using the Friends plugin?', 'friends' ), esc_url( add_query_arg( 'url', $url, admin_url( 'admin.php?page=add-friend' ) ) ) ), array( 'a' => array( 'href' => array() ) ) );
		}
		echo '</p>';
	}

	/**
	 * Notify of a follower
	 *
	 * @param string                     $actor    The Actor URL.
	 * @param array                      $activitypub_object   The Activity object.
	 * @param int                        $user_id  The ID of the WordPress User.
	 * @param Activitypub\Model\Follower $follower The Follower object.
	 *
	 * @return void
	 */
	public function activitypub_followers_post_follow( $actor, $activitypub_object, $user_id, $follower ) {
		if ( ! get_user_option( 'friends_no_friend_follower_notification', $user_id ) ) {
			return;
		}
		$user = new User( $user_id );
		if ( defined( 'WP_TESTS_EMAIL' ) ) {
			$user->user_email = WP_TESTS_EMAIL;
		}
		if ( ! $user->user_email ) {
			return;
		}

		if ( ! apply_filters( 'notify_user_about_new_follower', true, $user, $actor, $activitypub_object, $follower ) ) {
			return;
		}

		$url = \ActivityPub\object_to_uri( $follower->get( 'id' ) );
		$server = wp_parse_url( $url, PHP_URL_HOST );
		$following = User_Feed::get_by_url( $url );
		if ( ! $following || is_wp_error( $following ) ) {
			$following = User_Feed::get_by_url( $follower->get_url() );
		}
		if ( $following && ! is_wp_error( $following ) ) {
			$following = $following->get_friend_user();
		} else {
			$following = false;
		}

		// translators: %s is a user display name.
		$email_title = sprintf( __( 'New Follower: %s', 'friends' ), $follower->get_name() . '@' . $server );

		$params = array(
			'user'      => $user,
			'url'       => $url,
			'follower'  => $follower,
			'server'    => $server,
			'following' => $following,
		);

		$email_message = array();
		ob_start();
		Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
		Friends::template_loader()->get_template_part( 'email/new-follower', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer' );
		$email_message['html'] = ob_get_contents();
		ob_end_clean();

		ob_start();
		Friends::template_loader()->get_template_part( 'email/new-follower-text', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer-text' );
		$email_message['text'] = ob_get_contents();
		ob_end_clean();

		$this->send_mail( $user->user_email, $email_title, $email_message );
	}

	/**
	 * Notify of a lost follower
	 *
	 * @param Activitypub\Model\Follower $follower The Follower object.
	 * @param int                        $user_id  The ID of the WordPress User.
	 * @param string                     $actor    The Actor URL.
	 *
	 * @return void
	 */
	public function activitypub_followers_pre_remove_follower( $follower, $user_id, $actor ) {
		if ( ! get_user_option( 'friends_no_friend_follower_notification', $user_id ) ) {
			return;
		}
		$user = new User( $user_id );
		if ( defined( 'WP_TESTS_EMAIL' ) ) {
			$user->user_email = WP_TESTS_EMAIL;
		}
		if ( ! $user->user_email ) {
			return;
		}

		if ( ! apply_filters( 'notify_user_about_lost_follower', true, $user, $actor, $follower ) ) {
			return;
		}

		$url = \ActivityPub\object_to_uri( $follower->get( 'id' ) );
		$server = wp_parse_url( $url, PHP_URL_HOST );

		// translators: %s is a user display name.
		$email_title = sprintf( __( 'Lost Follower: %s', 'friends' ), $follower->get_name() . '@' . $server );

		$params = array(
			'user'     => $user,
			'url'      => $url,
			'follower' => $follower,
			'server'   => $server,
			'duration' => human_time_diff( strtotime( $follower->get_published() ) ) . ' (' . sprintf(
				// translators: %s is a time duration.
				__( 'since %s', 'friends' ),
				$follower->get_published()
			) . ')',

		);

		$email_message = array();
		ob_start();
		Friends::template_loader()->get_template_part( 'email/header', null, array( 'email_title' => $email_title ) );
		Friends::template_loader()->get_template_part( 'email/lost-follower', null, $params );
		Friends::template_loader()->get_template_part( 'email/footer' );
		$email_message['html'] = ob_get_contents();
		ob_end_clean();

		ob_start();
		Friends::template_loader()->get_template_part( 'email/lost-follower-text', null, $params );
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

	public function highlight_keywords( $the_content, $args ) {
		if ( ! isset( $args['keyword'] ) ) {
			return $the_content;
		}

		$tag_stack = array();
		$protected_tags = array(
			'pre',
			'code',
			'textarea',
			'style',
		);
		$new_content = '';
		$in_protected_tag = false;
		foreach ( wp_html_split( $the_content ) as $chunk ) {
			if ( preg_match( '#^<!--[\s\S]*-->$#i', $chunk, $m ) ) {
				$new_content .= $chunk;
				continue;
			}

			if ( preg_match( '#^<(/)?([a-z-]+)\b[^>]*>$#i', $chunk, $m ) ) {
				$tag = strtolower( $m[2] );
				if ( '/' === $m[1] ) {
					// Closing tag.
					$i = array_search( $tag, $tag_stack );
					// We can only remove the tag from the stack if it is in the stack.
					if ( false !== $i ) {
						$tag_stack = array_slice( $tag_stack, 0, $i );
					}
				} else {
					// Opening tag, add it to the stack.
					$tag_stack[] = $tag;
				}

				// If we're in a protected tag, the tag_stack contains at least one protected tag string.
				// The protected tag state can only change when we encounter a start or end tag.
				$in_protected_tag = array_intersect( $tag_stack, $protected_tags );

				// Never inspect tags.
				$new_content .= $chunk;
				continue;
			}

			if ( $in_protected_tag ) {
				// Don't inspect a chunk inside an inspected tag.
				$new_content .= $chunk;
				continue;
			}
			// Only reachable when there is no protected tag in the stack.
			$new_content .= preg_replace( '/(' . preg_quote( $args['keyword'], '/' ) . ')/i', '<mark>$1</mark>', $chunk );
		}

		return $new_content;
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
