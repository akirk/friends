<?php
/**
 * ActivityPub Transformer Message Class
 *
 * @package Friends
 */

namespace Friends;

/**
 * ActivityPub Transformer Message Class
 *
 * This is to get a properly formated outgoing message derived from a post.
 *
 * @author Alex Kirk
 */
class ActivityPub_Transformer_Message extends \Activitypub\Transformer\Post {
	public $in_reply_to = null;
	public $to;
	public $cc = array();
	private $mentions;

	protected function get_in_reply_to() {
		return $this->in_reply_to;
	}

	protected function get_to() {
		return $this->to;
	}

	protected function get_cc() {
		return $this->cc;
	}

	protected function get_mentions() {
		if ( ! isset( $this->mentions ) ) {
			$this->mentions = array();
			foreach ( (array) $this->to as $to ) {
				$acct = \Activitypub\Webfinger::uri_to_acct( $to );
				if ( $acct && ! is_wp_error( $acct ) ) {
					$acct = str_replace( 'acct:', '@', $acct );
				}
				$this->mentions[ $acct ] = $to;
			}
		}
		return $this->mentions;
	}

	protected function get_content() {
		$post    = $this->item;
		$content = $post->post_content;

		$mentions = '';
		foreach ( $this->get_mentions() as $acct => $to ) {
			$mentions .= sprintf(
				'<a rel="mention" class="u-url mention" href="%s">%s</a> ',
				esc_url( $to ),
				esc_html( $acct )
			);
		}

		$content = $mentions . $content;

		$content = \wpautop( $content );
		$content = \preg_replace( '/[\n\r\t]/', '', $content );
		$content = \trim( $content );

		/**
		 * Filter the content of the comment.
		 *
		 * @param string      $content The content of the comment.
		 * @param \WP_Comment $comment The comment object.
		 *
		 * @return string The filtered content of the comment.
		 */
		return \apply_filters( 'activitypub_the_content', $content );
	}

	public function get_likes() {
		// You can't fetch likes for a message.
		return null;
	}

	public function get_shares() {
		// You can't fetch shares for a message.
		return null;
	}
}
