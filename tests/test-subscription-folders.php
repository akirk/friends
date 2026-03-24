<?php
/**
 * Class SubscriptionFoldersTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Subscription Folders functionality.
 */
class SubscriptionFoldersTest extends \WP_UnitTestCase {

	/**
	 * A subscription user.
	 *
	 * @var Subscription
	 */
	private $sub_a;

	/**
	 * Another subscription user.
	 *
	 * @var Subscription
	 */
	private $sub_b;

	/**
	 * A third subscription user.
	 *
	 * @var Subscription
	 */
	private $sub_c;

	/**
	 * Setup the unit tests.
	 */
	public function set_up() {
		parent::set_up();
		User_Query::$cache = false;

		$this->sub_a = User::create( 'alpha.example', 'subscription', 'https://alpha.example/' );
		$this->sub_b = User::create( 'beta.example', 'subscription', 'https://beta.example/' );
		$this->sub_c = User::create( 'gamma.example', 'subscription', 'https://gamma.example/' );
	}

	/**
	 * Test creating a folder.
	 */
	public function test_create_folder() {
		$folder = Subscription::create_folder( 'Tech' );
		$this->assertNotWPError( $folder );
		$this->assertEquals( 'Tech', $folder->name );
		$this->assertTrue( Subscription::is_folder( $folder->term_id ) );
	}

	/**
	 * Test creating a subfolder.
	 */
	public function test_create_subfolder() {
		$parent = Subscription::create_folder( 'News' );
		$child  = Subscription::create_folder( 'Local', $parent->term_id );

		$this->assertNotWPError( $child );
		$this->assertEquals( $parent->term_id, $child->parent );
	}

	/**
	 * Test moving a subscription to a folder.
	 */
	public function test_move_to_folder() {
		$folder = Subscription::create_folder( 'Tech' );
		$result = $this->sub_a->move_to_folder( $folder->term_id );

		$this->assertTrue( $result );
		$this->assertNotNull( $this->sub_a->get_folder() );
		$this->assertEquals( $folder->term_id, $this->sub_a->get_folder()->term_id );
	}

	/**
	 * Test moving a subscription back to root.
	 */
	public function test_move_to_root() {
		$folder = Subscription::create_folder( 'Tech' );
		$this->sub_a->move_to_folder( $folder->term_id );
		$this->sub_a->move_to_folder( 0 );

		$this->assertNull( $this->sub_a->get_folder() );
	}

	/**
	 * Test querying subscriptions in a folder.
	 */
	public function test_subscriptions_in_folder() {
		$folder = Subscription::create_folder( 'Tech' );
		$this->sub_a->move_to_folder( $folder->term_id );
		$this->sub_b->move_to_folder( $folder->term_id );

		$in_folder = User_Query::subscriptions_in_folder( $folder->term_id );
		$this->assertEquals( 2, $in_folder->get_total() );
	}

	/**
	 * Test querying unfoldered subscriptions.
	 */
	public function test_unfoldered_subscriptions() {
		$folder = Subscription::create_folder( 'Tech' );
		$this->sub_a->move_to_folder( $folder->term_id );

		$unfoldered = User_Query::unfoldered_subscriptions();

		// sub_b and sub_c should be unfoldered.
		$names = array();
		foreach ( $unfoldered->get_results() as $sub ) {
			$names[] = $sub->user_login;
		}
		$this->assertContains( 'beta.example', $names );
		$this->assertContains( 'gamma.example', $names );
		$this->assertNotContains( 'alpha.example', $names );
	}

	/**
	 * Test that folders are not included in subscription lists.
	 */
	public function test_folders_excluded_from_subscriptions() {
		Subscription::create_folder( 'Tech' );
		Subscription::create_folder( 'News' );

		$all = User_Query::all_subscriptions();
		foreach ( $all->get_results() as $sub ) {
			$this->assertFalse(
				Subscription::is_folder( $sub->get_term_id() ),
				$sub->display_name . ' should not be a folder'
			);
		}
	}

	/**
	 * Test get_folders returns only folders.
	 */
	public function test_get_folders() {
		Subscription::create_folder( 'Tech' );
		Subscription::create_folder( 'News' );

		$folders = Subscription::get_folders();
		$this->assertCount( 2, $folders );

		foreach ( $folders as $folder ) {
			$this->assertTrue( Subscription::is_folder( $folder->term_id ) );
		}
	}

	/**
	 * Test that is_folder returns false for subscription terms.
	 */
	public function test_subscription_is_not_folder() {
		$this->assertFalse( Subscription::is_folder( $this->sub_a->get_term_id() ) );
	}

	/**
	 * Test the taxonomy is hierarchical.
	 */
	public function test_taxonomy_is_hierarchical() {
		$taxonomy = get_taxonomy( Subscription::TAXONOMY );
		$this->assertTrue( $taxonomy->hierarchical );
	}

	/**
	 * Test the friends-list block renders folder view.
	 */
	public function test_render_friends_list_folders_mode() {
		$folder = Subscription::create_folder( 'Tech' );
		$this->sub_a->move_to_folder( $folder->term_id );

		$blocks = new Blocks();
		$output = $blocks->render_friends_list_block( array( 'user_types' => 'folders' ) );

		$this->assertStringContainsString( 'Tech', $output );
		$this->assertStringContainsString( 'friends-folder', $output );
		$this->assertStringContainsString( '<details', $output );
	}

	/**
	 * Test the friends-list block folder filter.
	 */
	public function test_render_friends_list_specific_folder() {
		$folder = Subscription::create_folder( 'Tech' );
		$this->sub_a->move_to_folder( $folder->term_id );

		$blocks = new Blocks();
		$output = $blocks->render_friends_list_block( array( 'folder' => $folder->term_id ) );

		$this->assertStringContainsString( 'alpha.example', $output );
		$this->assertStringNotContainsString( 'beta.example', $output );
	}
}
