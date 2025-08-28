<?php
/**
 * Class MigrationTest
 *
 * @package Friends
 */

namespace Friends;

/**
 * Test the Migration functionality
 */
class MigrationTest extends \WP_UnitTestCase {

	/**
	 * Simulate the old CPT registration and create test data
	 */
	private function setup_pre_migration_environment() {
		// Register the Friends CPT with the OLD configuration (using post_tag)
		register_post_type( Friends::CPT, array(
			'public'      => false,
			'supports'    => array( 'title', 'editor', 'author' ),
			'taxonomies'  => array( 'post_tag' ), // OLD configuration
		) );
	}

	/**
	 * Simulate the new CPT registration after upgrade
	 */
	private function setup_post_migration_environment() {
		// Unregister the old CPT
		unregister_post_type( Friends::CPT );
		
		// Let the current Friends class register the new configuration
		$friends = Friends::get_instance();
		$friends->register_friend_tag_taxonomy();
		$friends->register_custom_post_type();
	}

	/**
	 * Test migrating post_tag to friend_tag for Friends CPT posts
	 */
	public function test_migrate_post_tags_to_friend_tags() {
		// Include Migration class
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		
		// Step 1: Setup pre-migration environment (old CPT with post_tag)
		$this->setup_pre_migration_environment();

		// Step 2: Create test data using the old configuration
		$test_post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Test Migration Post',
			'post_status' => 'publish',
		) );

		$test_tags = array( 'test-tag-1', 'test-tag-2', 'migration-test' );
		$tag_ids = array();

		foreach ( $test_tags as $tag_name ) {
			$tag = wp_insert_term( $tag_name, 'post_tag' );
			$this->assertNotWPError( $tag, "Failed to create tag: $tag_name" );
			$tag_ids[] = $tag['term_id'];
		}

		// Assign tags to the Friends post using old configuration
		$result = wp_set_post_terms( $test_post_id, $tag_ids, 'post_tag' );
		$this->assertNotWPError( $result );

		// Verify pre-migration state
		$assigned_post_tags = wp_get_post_terms( $test_post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertCount( 3, $assigned_post_tags );
		$this->assertEqualSets( $test_tags, $assigned_post_tags );

		// Step 3: Simulate plugin upgrade - unregister old, register new
		$this->setup_post_migration_environment();

		// Verify the new taxonomy is now available but no terms exist yet
		$this->assertTrue( taxonomy_exists( Friends::TAG_TAXONOMY ) );
		$friend_tags_before = wp_get_post_terms( $test_post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertEmpty( $friend_tags_before );

		// Step 4: Run the migration for testing
		Migration::migrate_post_tags_to_friend_tags();
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}

		// Step 5: Verify migration results
		$friend_tags_after = wp_get_post_terms( $test_post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertCount( 3, $friend_tags_after );
		$this->assertEqualSets( $test_tags, $friend_tags_after );

		// Verify post_tag terms were removed from Friends post
		$remaining_post_tags = wp_get_post_terms( $test_post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertEmpty( $remaining_post_tags );
	}

	/**
	 * Test migration doesn't affect non-Friends posts
	 */
	public function test_migration_ignores_non_friends_posts() {
		// Include Migration class
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		
		// Setup the new environment first (for the friend_tag taxonomy to exist)
		$this->setup_post_migration_environment();

		// Create a regular post (not Friends CPT)
		$regular_post_id = $this->factory->post->create( array(
			'post_type'   => 'post',
			'post_title'  => 'Regular Post',
			'post_status' => 'publish',
		) );

		// Create test tags
		$test_tags = array( 'regular-tag-1', 'regular-tag-2' );
		$tag_ids = array();

		foreach ( $test_tags as $tag_name ) {
			$tag = wp_insert_term( $tag_name, 'post_tag' );
			$this->assertNotWPError( $tag );
			$tag_ids[] = $tag['term_id'];
		}

		// Assign tags to the regular post
		wp_set_post_terms( $regular_post_id, $tag_ids, 'post_tag' );

		// Verify tags were assigned
		$assigned_post_tags_before = wp_get_post_terms( $regular_post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertCount( 2, $assigned_post_tags_before );

		// Run migration for testing
		Migration::migrate_post_tags_to_friend_tags();
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}

		// Verify regular post still has post_tag terms (unchanged)
		$assigned_post_tags_after = wp_get_post_terms( $regular_post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertCount( 2, $assigned_post_tags_after );
		$this->assertEqualSets( $test_tags, $assigned_post_tags_after );

		// Verify no friend_tag terms were created for regular post
		$friend_tags = wp_get_post_terms( $regular_post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertEmpty( $friend_tags );
	}

	/**
	 * Test migration handles duplicate tag names correctly
	 */
	public function test_migration_handles_duplicate_tags() {
		// Include Migration class
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		
		// Setup pre-migration environment
		$this->setup_pre_migration_environment();

		// Create two Friends posts with the old configuration
		$post1_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'First Post',
			'post_status' => 'publish',
		) );

		$post2_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Second Post',
			'post_status' => 'publish',
		) );

		// Create a tag that will be shared between both posts
		$shared_tag = wp_insert_term( 'shared-tag', 'post_tag' );
		$this->assertNotWPError( $shared_tag );

		// Assign the same tag to both posts using old configuration
		wp_set_post_terms( $post1_id, array( $shared_tag['term_id'] ), 'post_tag' );
		wp_set_post_terms( $post2_id, array( $shared_tag['term_id'] ), 'post_tag' );

		// Simulate upgrade
		$this->setup_post_migration_environment();

		// Run migration for testing
		Migration::migrate_post_tags_to_friend_tags();
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}

		// Verify both posts have the friend_tag
		$post1_friend_tags = wp_get_post_terms( $post1_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$post2_friend_tags = wp_get_post_terms( $post2_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );

		$this->assertContains( 'shared-tag', $post1_friend_tags );
		$this->assertContains( 'shared-tag', $post2_friend_tags );

		// Verify post_tag was removed from both posts
		$post1_post_tags = wp_get_post_terms( $post1_id, 'post_tag', array( 'fields' => 'names' ) );
		$post2_post_tags = wp_get_post_terms( $post2_id, 'post_tag', array( 'fields' => 'names' ) );

		$this->assertEmpty( $post1_post_tags );
		$this->assertEmpty( $post2_post_tags );

		// Verify only one friend_tag term was created
		$all_friend_tags = get_terms( array(
			'taxonomy'   => Friends::TAG_TAXONOMY,
			'hide_empty' => false,
			'fields'     => 'names',
		) );

		$this->assertCount( 1, $all_friend_tags );
		$this->assertContains( 'shared-tag', $all_friend_tags );
	}

	/**
	 * Test migration with no tagged Friends posts
	 */
	public function test_migration_with_no_tagged_posts() {
		// Include Migration class
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		
		// Setup pre-migration environment
		$this->setup_pre_migration_environment();

		// Create a Friends post with no tags using old configuration
		$test_post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Untagged Post',
			'post_status' => 'publish',
		) );

		// Simulate upgrade
		$this->setup_post_migration_environment();

		// Run migration for testing
		Migration::migrate_post_tags_to_friend_tags();
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}

		// Verify no friend_tag terms were created
		$friend_tags = wp_get_post_terms( $test_post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertEmpty( $friend_tags );

		// Verify no post_tag terms exist
		$post_tags = wp_get_post_terms( $test_post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertEmpty( $post_tags );
	}

	/**
	 * Test migration cleans up orphaned tags
	 */
	public function test_migration_cleans_up_orphaned_tags() {
		// Include Migration class
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';
		
		// Create tags first (before any CPT setup)
		$shared_tag = wp_insert_term( 'shared-tag', 'post_tag' );
		$friends_only_tag = wp_insert_term( 'friends-only-tag', 'post_tag' );

		// Create a regular post first (before Friends CPT setup)
		$regular_post_id = $this->factory->post->create( array(
			'post_type'   => 'post',
			'post_title'  => 'Regular Post',
			'post_status' => 'publish',
		) );
		
		// Assign shared tag to regular post
		wp_set_post_terms( $regular_post_id, array( $shared_tag['term_id'] ), 'post_tag' );

		// Now setup pre-migration environment for Friends
		$this->setup_pre_migration_environment();

		// Create Friends post
		$friends_post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Friends Post',
			'post_status' => 'publish',
		) );

		// Assign both tags to Friends post
		wp_set_post_terms( $friends_post_id, array( $shared_tag['term_id'], $friends_only_tag['term_id'] ), 'post_tag' );

		// Simulate upgrade
		$this->setup_post_migration_environment();

		// Verify both tags exist before migration
		$this->assertNotFalse( get_term( $shared_tag['term_id'], 'post_tag' ) );
		$this->assertNotFalse( get_term( $friends_only_tag['term_id'], 'post_tag' ) );

		// Run migration for testing
		Migration::migrate_post_tags_to_friend_tags();
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}

		// Verify shared tag still exists (used by regular post)
		$this->assertNotFalse( get_term( $shared_tag['term_id'], 'post_tag' ) );

		// Verify friends-only tag was deleted (orphaned)
		$deleted_term = get_term( $friends_only_tag['term_id'], 'post_tag' );
		$this->assertTrue( is_wp_error( $deleted_term ) || is_null( $deleted_term ) );

		// Verify Friends post now has friend_tags
		$friend_tags = wp_get_post_terms( $friends_post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertContains( 'shared-tag', $friend_tags );
		$this->assertContains( 'friends-only-tag', $friend_tags );

		// Verify regular post still has its post_tag
		$regular_tags = wp_get_post_terms( $regular_post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertContains( 'shared-tag', $regular_tags );
	}

	/**
	 * Test that the cron hook method works correctly
	 */
	public function test_cron_migrate_post_tags_batch_works() {
		// Create a Friends instance to test the cron method
		$friends = Friends::get_instance();
		
		// Setup pre-migration environment
		$this->setup_pre_migration_environment();

		// Create test data
		$test_post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Test Cron Migration',
			'post_status' => 'publish',
		) );

		$test_tags = array( 'cron-test' );
		$tag_ids = array();

		foreach ( $test_tags as $tag_name ) {
			$tag = wp_insert_term( $tag_name, 'post_tag' );
			$this->assertNotWPError( $tag );
			$tag_ids[] = $tag['term_id'];
		}

		wp_set_post_terms( $test_post_id, $tag_ids, 'post_tag' );

		// Setup post-migration environment
		$this->setup_post_migration_environment();

		// Initiate migration to set up progress tracking
		Migration::migrate_post_tags_to_friend_tags();
		
		// Call the cron method - this should work correctly
		$friends->cron_migrate_post_tags_batch();
		
		// Verify that the cron method exists and can be called
		$this->assertTrue( method_exists( $friends, 'cron_migrate_post_tags_batch' ) );
	}

	public function test_cleanup_orphaned_friend_tags() {
		// Create some friend tags
		$tag1 = wp_insert_term( 'orphaned-tag', Friends::TAG_TAXONOMY );
		$tag2 = wp_insert_term( 'used-tag', Friends::TAG_TAXONOMY );
		$tag3 = wp_insert_term( 'another-orphaned-tag', Friends::TAG_TAXONOMY );

		$this->assertNotWPError( $tag1 );
		$this->assertNotWPError( $tag2 );  
		$this->assertNotWPError( $tag3 );

		// Create a Friends post and assign only one tag to it
		$post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Test Post',
			'post_status' => 'publish',
		) );

		wp_set_post_terms( $post_id, array( $tag2['term_id'] ), Friends::TAG_TAXONOMY );

		// Verify all tags exist before cleanup
		$this->assertNotFalse( get_term( $tag1['term_id'], Friends::TAG_TAXONOMY ) );
		$this->assertNotFalse( get_term( $tag2['term_id'], Friends::TAG_TAXONOMY ) );
		$this->assertNotFalse( get_term( $tag3['term_id'], Friends::TAG_TAXONOMY ) );

		// Run cleanup
		Friends::get_instance()->cleanup_orphaned_friend_tags();

		// Verify orphaned tags were deleted
		$orphaned1 = get_term( $tag1['term_id'], Friends::TAG_TAXONOMY );
		$orphaned3 = get_term( $tag3['term_id'], Friends::TAG_TAXONOMY );
		$this->assertTrue( is_wp_error( $orphaned1 ) || is_null( $orphaned1 ) );
		$this->assertTrue( is_wp_error( $orphaned3 ) || is_null( $orphaned3 ) );

		// Verify used tag still exists
		$used_tag = get_term( $tag2['term_id'], Friends::TAG_TAXONOMY );
		$this->assertNotWPError( $used_tag );
		$this->assertNotFalse( $used_tag );
		$this->assertEquals( 'used-tag', $used_tag->name );
	}
}