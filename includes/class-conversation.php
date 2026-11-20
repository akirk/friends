<?php
/**
 * Local context for ActivityPub conversations.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Walk reply relationships without fetching remote content during page rendering.
 */
class Conversation {
	const REPLY_TO_META = '_friends_in_reply_to';

	/**
	 * Find the parent URL, including for posts received before it was stored as meta.
	 *
	 * @param int $post_id Cached post ID.
	 * @return string Parent object URL, or an empty string.
	 */
	public static function get_parent_url( $post_id ) {
		$url = get_post_meta( $post_id, self::REPLY_TO_META, true );
		if ( metadata_exists( 'post', $post_id, self::REPLY_TO_META ) ) {
			return is_string( $url ) ? $url : '';
		}
		if ( ! is_callable( array( '\Activitypub\Collection\Inbox', 'get_by_type_and_object' ) ) ) {
			return '';
		}
		$object_id = get_post_meta( $post_id, 'external-id', true );
		if ( ! $object_id ) {
			return '';
		}
		$inbox = \Activitypub\Collection\Inbox::get_by_type_and_object( 'Create', $object_id );
		if ( is_wp_error( $inbox ) || ! $inbox instanceof \WP_Post ) {
			return '';
		}
		$activity = json_decode( $inbox->post_content, true );
		$url = $activity['object']['inReplyTo'] ?? '';
		if ( is_array( $url ) ) {
			$url = $url['id'] ?? reset( $url );
		}
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Get readable ancestors, oldest first. Hidden cached posts remain hidden in feeds.
	 *
	 * @param int $post_id The reply being viewed.
	 * @return array Ancestor posts and comments.
	 */
	public static function get_ancestors( $post_id ) {
		if ( ! Friends::get_instance()->frontend->can_view_feed() || Friends::CPT !== get_post_type( $post_id ) ) {
			return array();
		}
		$ancestors = array();
		$seen = array( 'post:' . $post_id => true );
		$url = self::get_parent_url( $post_id );
		for ( $depth = 0; $url && $depth < 20; ++$depth ) {
			$parent = self::resolve_url( $url );
			if ( ! $parent ) {
				break;
			}
			$is_comment = $parent instanceof \WP_Comment;
			$key = $is_comment ? 'comment:' . $parent->comment_ID : 'post:' . $parent->ID;
			if ( isset( $seen[ $key ] ) ) {
				break;
			}
			$seen[ $key ] = true;
			$ancestors[] = $parent;
			if ( $is_comment ) {
				if ( $parent->comment_parent ) {
					$url = add_query_arg( 'c', $parent->comment_parent, home_url( '/' ) );
				} else {
					$root = get_post( $parent->comment_post_ID );
					$url = $root ? $root->guid : '';
				}
			} else {
				$url = self::get_parent_url( $parent->ID );
			}
		}
		return array_reverse( $ancestors );
	}

	/**
	 * Resolve only locally stored, readable conversation content.
	 *
	 * @param string $url Object URL.
	 * @return \WP_Post|\WP_Comment|null The parent, if available.
	 */
	private static function resolve_url( $url ) {
		if ( is_callable( array( '\Activitypub\Comment', 'url_to_commentid' ) ) ) {
			$comment_id = \Activitypub\Comment::url_to_commentid( $url );
			$comment = $comment_id ? get_comment( $comment_id ) : null;
			if ( $comment ) {
				$root = get_post( $comment->comment_post_ID );
				if ( '1' === (string) $comment->comment_approved && self::can_read_post( $root ) ) {
					return $comment;
				}
				return null;
			}
		}
		$post_id = Feed::url_to_postid( $url );
		if ( ! $post_id ) {
			$post_id = url_to_postid( $url );
		}
		if ( ! $post_id ) {
			$posts = get_posts(
				array(
					'post_type'      => Friends::CPT,
					'post_status'    => array( 'publish', 'private', 'trash' ),
					'posts_per_page' => 1,
					'meta_key'       => 'external-id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			$post_id = $posts ? $posts[0]->ID : 0;
		}
		$post = $post_id ? get_post( $post_id ) : null;
		return self::can_read_post( $post ) ? $post : null;
	}

	/**
	 * Check visibility without exposing drafts or unrelated private post types.
	 *
	 * @param \WP_Post|null $post Potential conversation post.
	 * @return bool Whether the post is readable in this conversation.
	 */
	private static function can_read_post( $post ) {
		if ( ! $post || post_password_required( $post ) ) {
			return false;
		}
		if ( Friends::CPT === $post->post_type ) {
			return in_array( $post->post_status, array( 'publish', 'private', 'trash' ), true );
		}
		return 'post' === $post->post_type && ( 'publish' === $post->post_status || ( 'private' === $post->post_status && current_user_can( 'read_post', $post->ID ) ) );
	}
}
