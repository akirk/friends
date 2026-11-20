<?php
/**
 * Conversation context regression tests.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Follow local reply relationships without changing stored content.
 */
class ConversationTest extends \WP_UnitTestCase {
	/**
	 * Set up a reader who can view the Friends feed.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Create a cached post with a distinct remote URL.
	 *
	 * @param array $args Post overrides.
	 * @return int Post ID.
	 */
	private function create_post( $args = array() ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => Friends::CPT,
					'post_status' => 'publish',
					'guid'        => 'https://remote.example/objects/' . wp_generate_uuid4(),
				),
				$args
			)
		);
	}

	/**
	 * A remote reply to a local comment includes that comment and its hidden root.
	 */
	public function test_reply_to_local_comment_includes_hidden_root() {
		if ( ! class_exists( '\Activitypub\Comment' ) ) {
			$this->markTestSkipped( 'ActivityPub is required to resolve comment URLs.' );
		}
		$root = $this->create_post( array( 'post_status' => 'trash' ) );
		$comment = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $root,
				'comment_approved' => 1,
				'user_id'          => get_current_user_id(),
			)
		);
		$reply = $this->create_post();
		update_post_meta( $reply, Conversation::REPLY_TO_META, add_query_arg( 'c', $comment, home_url( '/' ) ) );

		$context = Conversation::get_ancestors( $reply );
		$this->assertCount( 2, $context );
		$this->assertSame( $root, $context[0]->ID );
		$this->assertSame( $comment, (int) $context[1]->comment_ID );
		$this->assertSame( 'trash', get_post_status( $root ) );
		$this->assertSame( 0, (int) get_post( $reply )->post_parent );

		Friends::get_instance()->convert_single_reply_to_comment( $reply );
		$this->assertSame( 'publish', get_post_status( $reply ) );
	}

	/**
	 * Existing received posts can recover the relationship from the local inbox.
	 */
	public function test_reads_parent_from_existing_inbox_activity() {
		if ( ! is_callable( array( '\Activitypub\Collection\Inbox', 'get_by_type_and_object' ) ) ) {
			$this->markTestSkipped( 'ActivityPub inbox lookup is required.' );
		}
		$root = $this->create_post();
		$reply = $this->create_post();
		$object_id = 'https://remote.example/objects/reply';
		update_post_meta( $reply, 'external-id', $object_id );
		self::factory()->post->create(
			array(
				'post_type'    => 'ap_inbox',
				'post_status'  => 'publish',
				'post_content' => wp_json_encode(
					array(
						'type'   => 'Create',
						'object' => array(
							'id'        => $object_id,
							'inReplyTo' => get_post( $root )->guid,
						),
					)
				),
				'meta_input'   => array(
					'_activitypub_activity_type' => 'Create',
					'_activitypub_object_id'     => $object_id,
				),
			)
		);
		$context = Conversation::get_ancestors( $reply );
		$this->assertCount( 1, $context );
		$this->assertSame( $root, $context[0]->ID );
		$this->assertSame( '', get_post_meta( $reply, Conversation::REPLY_TO_META, true ) );
	}

	/**
	 * New incoming replies persist their parent URL as feed-item metadata.
	 */
	public function test_incoming_reply_preserves_parent_metadata() {
		$parser = Friends::get_instance()->feed->get_feed_parser( 'activitypub' );
		if ( ! $parser ) {
			$this->markTestSkipped( 'ActivityPub parser is required.' );
		}
		$method = new \ReflectionMethod( $parser, 'handle_incoming_create' );
		$method->setAccessible( true );
		$item = $method->invoke(
			$parser,
			array(
				'id'        => 'https://remote.example/reply/1',
				'type'      => 'Note',
				'content'   => '<p>A reply.</p>',
				'published' => '2026-11-20T12:00:00Z',
				'inReplyTo' => array( 'id' => 'https://remote.example/parent/1' ),
			)
		);
		$this->assertSame( 'https://remote.example/parent/1', $item->meta[ Conversation::REPLY_TO_META ] );
	}

	/**
	 * Remote object IDs can differ from their human-readable permalinks.
	 */
	public function test_resolves_canonical_object_id_and_stops_at_cycle() {
		$root = $this->create_post();
		$reply = $this->create_post();
		$canonical_url = 'https://remote.example/users/alice/statuses/123';
		update_post_meta( $root, 'external-id', $canonical_url );
		update_post_meta( $reply, Conversation::REPLY_TO_META, $canonical_url );
		update_post_meta( $root, Conversation::REPLY_TO_META, get_post( $reply )->guid );

		$context = Conversation::get_ancestors( $reply );
		$this->assertCount( 1, $context );
		$this->assertSame( $root, $context[0]->ID );
	}

	/**
	 * Anonymous readers cannot use context to see hidden or private content.
	 */
	public function test_context_is_restricted_to_feed_readers() {
		$root = $this->create_post();
		$reply = $this->create_post();
		update_post_meta( $reply, Conversation::REPLY_TO_META, get_post( $root )->guid );
		wp_set_current_user( 0 );
		$this->assertSame( array(), Conversation::get_ancestors( $reply ) );
	}

	/**
	 * Drafts and missing ancestors must not appear in the conversation.
	 */
	public function test_unavailable_parent_is_not_rendered() {
		$root = $this->create_post( array( 'post_status' => 'draft' ) );
		$reply = $this->create_post();
		update_post_meta( $reply, Conversation::REPLY_TO_META, get_post( $root )->guid );
		$this->assertSame( array(), Conversation::get_ancestors( $reply ) );
		update_post_meta( $reply, Conversation::REPLY_TO_META, 'https://missing.example/123' );
		$this->assertSame( array(), Conversation::get_ancestors( $reply ) );
	}

	/**
	 * Comments awaiting moderation must not be exposed in context.
	 */
	public function test_unapproved_comment_is_not_rendered() {
		if ( ! class_exists( '\Activitypub\Comment' ) ) {
			$this->markTestSkipped( 'ActivityPub is required to resolve comment URLs.' );
		}
		$root = $this->create_post();
		$comment = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $root,
				'comment_approved' => 0,
			)
		);
		$reply = $this->create_post();
		update_post_meta( $reply, Conversation::REPLY_TO_META, add_query_arg( 'c', $comment, home_url( '/' ) ) );
		$this->assertSame( array(), Conversation::get_ancestors( $reply ) );
	}

	/**
	 * Context retains content warnings and sanitizes remote HTML.
	 */
	public function test_context_template_preserves_warning_and_sanitizes_html() {
		$root = $this->create_post(
			array(
				'post_content' => '<p>Earlier message.</p><img src="https://remote.example/image.png" onerror="alert(1)">',
				'meta_input'   => array( 'friends_content_warning' => 'A sensitive topic' ),
			)
		);
		ob_start();
		Friends::template_loader()->get_template_part(
			'frontend/parts/conversation-context',
			null,
			array( 'context' => array( get_post( $root ) ) )
		);
		$html = ob_get_clean();
		$this->assertStringContainsString( '<details><summary>A sensitive topic</summary>', $html );
		$this->assertStringContainsString( 'Earlier message.', $html );
		$this->assertStringNotContainsString( 'onerror=', $html );
	}
}
