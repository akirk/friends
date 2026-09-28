<?php
/**
 * Polls for ActivityPub integration.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Let Polls for ActivityPub own locally created private-message polls.
 */
class Integration_Polls_For_ActivityPub {
	/**
	 * Meta key connecting a Friends message to its authoritative poll post.
	 */
	const POLL_ID_META_KEY = 'friends_activitypub_poll_id';

	/**
	 * Register integration hooks.
	 */
	public function __construct() {
		add_filter( 'friends_send_direct_message', array( $this, 'create_private_poll' ), 15, 7 );
		add_filter( 'mastodon_api_poll_post_id', array( $this, 'map_message_to_poll' ), 10, 2 );
	}

	/**
	 * Whether Polls for ActivityPub is available.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( '\\Polls_For_ActivityPub\\Poll_Repository' );
	}

	/**
	 * Create the authoritative poll associated with an outgoing message.
	 *
	 * The poll starts private, so it can never be published before Friends has
	 * addressed and queued the direct ActivityPub activity.
	 *
	 * @param mixed  $post_id          Message post ID or prior error.
	 * @param User   $friend_user      Message recipient's Friends user.
	 * @param string $to               Recipient URL.
	 * @param string $message          Message text and poll question.
	 * @param string $subject          Message subject.
	 * @param int    $reply_to_post_id Reply target post ID.
	 * @param array  $poll             Normalized poll submission.
	 * @return mixed
	 */
	public function create_private_poll( $post_id, $friend_user, $to, $message, $subject = '', $reply_to_post_id = null, $poll = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( is_wp_error( $post_id ) || ! is_int( $post_id ) || ! is_array( $poll ) ) {
			return $post_id;
		}

		if ( ! self::is_available() ) {
			return new \WP_Error(
				'friends_poll_provider_required',
				__( 'Install and activate Polls for ActivityPub to create polls in private messages.', 'friends' ),
				array( 'status' => 422 )
			);
		}

		$options = wp_list_pluck( $poll['options'] ?? array(), 'title' );
		$expires = isset( $poll['expires_at'] ) ? strtotime( $poll['expires_at'] ) : false;
		$duration = $expires ? max( 1, $expires - time() ) : 0;

		$args = array(
			'question'    => $poll['question'] ?? wp_strip_all_tags( $message ),
			'options'     => $options,
			'duration'    => $duration,
			'multiple'    => ! empty( $poll['multiple'] ),
			'post_author' => get_current_user_id(),
			'status'      => 'private',
			'visibility'  => defined( 'ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE' ) ? ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE : 'private',
		);

		if ( $reply_to_post_id ) {
			$reply_to_poll_id = self::get_poll_post_id( $reply_to_post_id );
			$args['in_reply_to_id'] = get_permalink( $reply_to_poll_id ? $reply_to_poll_id : $reply_to_post_id );
		}

		$created_poll = \Polls_For_ActivityPub\Poll_Repository::get_instance()->create( $args );
		if ( is_wp_error( $created_poll ) ) {
			return $created_poll;
		}

		update_post_meta( $post_id, self::POLL_ID_META_KEY, $created_poll->get_post_id() );
		update_post_meta( $created_poll->get_post_id(), 'friends_activitypub_message_id', $post_id );

		return $post_id;
	}

	/**
	 * Map a Friends message status to its Polls for ActivityPub post.
	 *
	 * @param int $poll_id   Current candidate poll ID.
	 * @param int $object_id Containing status ID.
	 * @return int
	 */
	public function map_message_to_poll( $poll_id, $object_id ) {
		if ( Messages::CPT !== get_post_type( $object_id ) ) {
			return $poll_id;
		}

		$message_poll_id = self::get_poll_post_id( $object_id );
		return $message_poll_id ? $message_poll_id : $poll_id;
	}

	/**
	 * Get the poll post associated with a Friends message.
	 *
	 * @param int $message_id Friends message post ID.
	 * @return int
	 */
	public static function get_poll_post_id( $message_id ) {
		return (int) get_post_meta( $message_id, self::POLL_ID_META_KEY, true );
	}
}
