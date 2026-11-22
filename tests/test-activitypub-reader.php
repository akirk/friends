<?php
/**
 * Shared ActivityPub reader cache tests.
 *
 * @package Friends
 */

namespace Friends;

/** Test the opt-in transition without changing historical posts. */
class ActivitypubReaderTest extends \WP_UnitTestCase {
	private $user_id;
	private $actor_id;
	private $feed_term_id;

	public function set_up() {
		parent::set_up();
		if ( ! class_exists( '\Activitypub\Collection\Remote_Posts' ) ) {
			$this->markTestSkipped( 'ActivityPub reader storage is unavailable.' );
		}
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );
		update_option( 'friends_activitypub_reader_cache', 1 );
		update_option( 'activitypub_reader_ui', 1 );
		$this->actor_id = self::factory()->post->create( array( 'post_type' => 'ap_actor' ) );
		$term = wp_insert_term( 'https://example.org/reader', User_Feed::TAXONOMY );
		$this->assertNotWPError( $term );
		wp_set_object_terms( $this->actor_id, $term['term_id'], User_Feed::TAXONOMY );
		$this->feed_term_id = $term['term_id'];
		update_term_meta( $this->feed_term_id, 'parser', 'activitypub' );
	}

	/**
	 * Make a recipient-scoped reader post sharing a publication date with the fixtures.
	 *
	 * @param string $guid Remote object ID.
	 * @param int    $recipient Local actor.
	 * @return int Post ID.
	 */
	private function remote_post( $guid, $recipient ) {
		return self::factory()->post->create(
			array(
				'post_type' => 'ap_post',
				'post_status' => 'publish',
				'post_date' => '2026-01-01 12:00:00',
				'guid' => $guid,
				'meta_input' => array( '_activitypub_user_id' => $recipient, '_activitypub_remote_actor_id' => $this->actor_id ),
			)
		);
	}

	/** Merge sources before applying the page size, including timestamp ties. */
	public function test_merged_cursor_pages_and_duplicate_exclusion() {
		$expected = array();
		for ( $i = 1; $i <= 3; ++$i ) {
			$expected[] = self::factory()->post->create( array( 'post_type' => Friends::CPT, 'post_status' => 'publish', 'post_date' => '2026-01-01 12:00:00' ) );
			$expected[] = $this->remote_post( 'https://example.org/posts/' . $i, $this->user_id );
		}
		$duplicate = self::factory()->post->create( array( 'post_type' => Friends::CPT, 'post_status' => 'publish', 'post_date' => '2026-01-01 12:00:00', 'guid' => 'https://example.org/posts/1' ) );
		$other = $this->remote_post( 'https://example.org/posts/private', $this->user_id + 100 );
		rsort( $expected );
		$vars = array( 'post_type' => array( Friends::CPT, 'ap_post' ), 'post_status' => 'publish', 'posts_per_page' => 2, 'order' => 'DESC' );
		$cursor = null;
		$seen = array();
		for ( $page_number = 0; $page_number < 5; ++$page_number ) {
			$page = ( new Feed_Query() )->fetch( $vars, $cursor );
			$seen = array_merge( $seen, array_column( $page['query']->posts, 'ID' ) );
			if ( ! $page['has_more'] ) {
				break;
			}
			$cursor = json_decode( $page['cursor'], true );
		}
		$this->assertSame( $expected, $seen );
		$this->assertNotContains( $duplicate, $seen );
		$this->assertNotContains( $other, $seen );
		$this->assertSame( Friends::CPT, get_post_type( $duplicate ), 'History must not be converted or deleted.' );
	}

	/** Another actor's reader copy cannot hide a historical Friends item. */
	public function test_other_recipient_does_not_hide_history() {
		$url = 'https://example.org/posts/not-ours';
		$this->remote_post( $url, $this->user_id + 100 );
		$legacy = self::factory()->post->create( array( 'post_type' => Friends::CPT, 'post_status' => 'publish', 'guid' => $url ) );
		$page = ( new Feed_Query() )->fetch( array( 'post_type' => array( Friends::CPT ), 'post_status' => 'publish', 'posts_per_page' => 10 ) );
		$this->assertSame( array( $legacy ), array_column( $page['query']->posts, 'ID' ) );
	}

	/** Shared objects avoid duplicate writes even in logged-out inbox processing. */
	public function test_ingestion_uses_object_id_and_preserves_fallback() {
		if ( ! class_exists( 'Friends\Feed_Parser_ActivityPub' ) ) {
			$this->markTestSkipped( 'ActivityPub feed parser unavailable.' );
		}
		update_option( 'friends_main_user_id', $this->user_id );
		$recipient = Feed_Parser_ActivityPub::get_activitypub_actor_id( null );
		$id = $this->remote_post( 'https://example.org/posts/shared', $recipient );
		$feed = new User_Feed( get_term( $this->feed_term_id, User_Feed::TAXONOMY ) );
		$item = new Feed_Item( array( 'permalink' => 'https://example.org/@reader/shared', '_external_id' => 'https://example.org/posts/shared' ) );
		$user = new User( $this->user_id );
		wp_set_current_user( 0 );
		$this->assertFalse( Activitypub_Reader::use_cached_item( $item, $feed, $user ) );
		$this->assertContains( $this->feed_term_id, wp_get_object_terms( $id, User_Feed::TAXONOMY, array( 'fields' => 'ids' ) ) );
		$missing = new Feed_Item( array( 'permalink' => 'https://example.org/posts/missing' ) );
		$this->assertSame( $missing, Activitypub_Reader::use_cached_item( $missing, $feed, $user ) );
	}

	/** The prototype requires both explicit opt-in and authenticated actor access. */
	public function test_opt_in_and_permission_boundaries() {
		$this->assertSame( $this->user_id, Activitypub_Reader::actor_id() );
		update_option( 'friends_activitypub_reader_cache', 0 );
		$this->assertNull( Activitypub_Reader::actor_id() );
		update_option( 'friends_activitypub_reader_cache', 1 );
		update_option( 'activitypub_reader_ui', 0 );
		$this->assertNull( Activitypub_Reader::actor_id() );
		update_option( 'activitypub_reader_ui', 1 );
		wp_set_current_user( 0 );
		$this->assertNull( Activitypub_Reader::actor_id() );
		$this->assertNotContains( 'ap_post', Activitypub_Reader::post_types( array( Friends::CPT ) ) );
	}
}
