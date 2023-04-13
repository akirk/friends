<?php
/**
 * ActivityPub Comment Model Class
 *
 * @package Friends
 */

namespace Activitypub\Model;

/**
 * ActivityPub Comment Model Class
 *
 * @author Alex Kirk
 */
class Comment extends Post {
	/**
	 * The WordPress Comment Object.
	 *
	 * @var \WP_Comment
	 */
	private $comment;

	/**
	 * The Post Author.
	 *
	 * @var string
	 */
	private $post_author;

	/**
	 * The Object ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The Object Summary
	 *
	 * @var string
	 */
	private $content;

	/**
	 * The Object Tags. This is usually the list of used Hashtags.
	 *
	 * @var array
	 */
	private $tags;

	/**
	 * The Onject Type
	 *
	 * @var string
	 */
	private $object_type;

	/**
	 * The Allowed Tags, used in the content.
	 *
	 * @var array
	 */
	private $allowed_tags = array(
		'a'          => array(
			'href'  => array(),
			'title' => array(),
			'class' => array(),
			'rel'   => array(),
		),
		'br'         => array(),
		'p'          => array(
			'class' => array(),
		),
		'span'       => array(
			'class' => array(),
		),
		'div'        => array(
			'class' => array(),
		),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'strong'     => array(
			'class' => array(),
		),
		'b'          => array(
			'class' => array(),
		),
		'i'          => array(
			'class' => array(),
		),
		'em'         => array(
			'class' => array(),
		),
		'blockquote' => array(),
		'cite'       => array(),
	);

	/**
	 * Constructor
	 *
	 * @param  WP_Comment|int $comment  The comment.
	 */
	public function __construct( $comment ) {
		$this->comment = \get_comment( $comment );
		if ( ! is_wp_error( $this->comment ) ) {
			parent::__construct( $this->comment->comment_post_ID );
		}
	}

	/**
	 * Magic function to implement getter and setter
	 *
	 * @param string $method The method.
	 * @param string $params The parameters.
	 *
	 * @return mixed The variable.
	 */
	public function __call( $method, $params ) {
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			if ( empty( $this->$var ) && ! empty( $this->comment->$var ) ) {
				return $this->comment->$var;
			}
			return $this->$var;
		}

		if ( \strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = $params[0];
		}
	}

	/**
	 * Converts this Object into an Array.
	 *
	 * @return array
	 */
	public function to_array() {
		$array = array(
			'id'           => $this->get_id(),
			'type'         => $this->get_object_type(),
			'published'    => \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( $this->comment->comment_date_gmt ) ),
			'attributedTo' => \get_author_posts_url( $this->comment->user_id ),
			'summary'      => $this->get_summary(),
			'inReplyTo'    => \get_permalink( $this->comment->comment_post_ID ),
			'content'      => $this->get_content(),
			'contentMap'   => array(
				\strstr( \get_locale(), '_', true ) => $this->get_content(),
			),
			'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'attachment'   => $this->get_attachments(),
			'tag'          => $this->get_tags(),
		);
		if ( $this->comment->comment_parent ) {
			$source_url = get_comment_meta( $this->comment->comment_parent, 'source_url', true );
			if ( $source_url ) {
				$array['inReplyTo'] = $source_url;
			}
		}

		return \apply_filters( 'activitypub_post', $array, $this->comment );
	}


	/**
	 * Returns the ID of an Activity Object
	 *
	 * @return string
	 */
	public function get_id() {
		if ( $this->id ) {
			return $this->id;
		}

		if ( 'trash' === $this->comment->post_status ) {
			$permalink = \get_post_meta( $this->comment->comment_post_ID, 'activitypub_canonical_url', true );
		} else {
			$permalink = \get_comment_link( $this->comment->comment_ID );
		}

		$this->id = $permalink;

		return $permalink;
	}

	/**
	 * Returns a list of Image Attachments
	 *
	 * @return array
	 */
	public function get_attachments() {
		return array();
	}

	/**
	 * Returns a list of Tags, used in the Post
	 *
	 * @return array
	 */
	public function get_tags() {
		if ( $this->tags ) {
			return $this->tags;
		}

		$tags = array();

		$mentions = apply_filters( 'activitypub_extract_mentions', array(), $this->comment->comment_content, $this );
		if ( $mentions ) {
			foreach ( $mentions as $mention => $url ) {
				$tag = array(
					'type' => 'Mention',
					'href' => $url,
					'name' => $mention,
				);
				$tags[] = $tag;
			}
		}

		$this->tags = $tags;

		return $tags;
	}

	/**
	 * Returns the as2 object-type for a given post
	 *
	 * @return string the object-type
	 */
	public function get_object_type() {
		if ( $this->object_type ) {
			return $this->object_type;
		}

		$this->object_type = 'Note';

		return $this->object_type;
	}

	/**
	 * Returns the content for the ActivityPub Item.
	 *
	 * @return string the content
	 */
	public function get_content() {
		if ( $this->content ) {
			return $this->content;
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$content = $this->comment->comment_content;

		$content = \wpautop( \wp_kses( $content, $this->allowed_tags ) );
		$content = \trim( \preg_replace( '/[\n\r\t]/', '', $content ) );

		$content = \apply_filters( 'activitypub_the_content', $content, $this->comment );
		$content = \html_entity_decode( $content, \ENT_QUOTES, 'UTF-8' );

		$this->content = $content;

		return $content;
	}
}
