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

	/**
	 * Test migration status methods
	 */
	public function test_migration_status_methods() {
		// Reset any existing status
		Migration::reset_migration_status();
		
		// Test initial status
		$status = Migration::get_migration_status();
		$this->assertFalse( $status['completed'] );
		$this->assertFalse( $status['in_progress'] );
		$this->assertEquals( 0, $status['total'] );
		$this->assertEquals( 0, $status['processed'] );
		$this->assertEquals( 0, $status['progress_percent'] );
		
		// Test manual trigger
		$this->setup_pre_migration_environment();
		
		// Create test data
		$test_post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Test Status',
			'post_status' => 'publish',
		) );
		
		$tag = wp_insert_term( 'status-test', 'post_tag' );
		$this->assertNotWPError( $tag );
		wp_set_post_terms( $test_post_id, array( $tag['term_id'] ), 'post_tag' );
		
		$this->setup_post_migration_environment();
		
		// Test manual trigger
		Migration::trigger_migration_manually();
		$status = Migration::get_migration_status();
		$this->assertTrue( $status['in_progress'] );
		$this->assertEquals( 1, $status['total'] );
		
		// Complete the migration
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}
		
		// Test completed status
		$status = Migration::get_migration_status();
		$this->assertTrue( $status['completed'] );
		$this->assertFalse( $status['in_progress'] );
		$this->assertGreaterThan( 0, $status['completed_time'] );
	}

	/**
	 * Test Site Health integration
	 */
	public function test_site_health_integration() {
		require_once FRIENDS_PLUGIN_DIR . 'includes/class-site-health.php';
		$site_health = new \Friends\Site_Health();

		// Test that Site Health tests are added
		$tests = array();
		$tests = $site_health->add_tests( $tests );
		$this->assertArrayHasKey( 'direct', $tests );
		$this->assertArrayHasKey( 'friends_migration', $tests['direct'] );

		// Test Site Health test when no migration needed (default state)
		Migration::reset_migration_status();
		$result = $site_health->test_migration();
		// Without any posts to migrate, it should show good status
		$this->assertEquals( 'good', $result['status'] );
		$this->assertStringContainsString( 'No Friends tag migration needed', $result['label'] );

		// Test with posts needing migration
		$this->setup_pre_migration_environment();

		$test_post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Site Health Test',
			'post_status' => 'publish',
		) );

		$tag = wp_insert_term( 'site-health-test', 'post_tag' );
		$this->assertNotWPError( $tag );
		wp_set_post_terms( $test_post_id, array( $tag['term_id'] ), 'post_tag' );

		$this->setup_post_migration_environment();

		// Test Site Health when migration is recommended
		$result = $site_health->test_migration();
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'need tag migration', $result['label'] );
		$this->assertStringContainsString( 'Start Migration', $result['description'] );

		// Start migration and test in-progress status
		Migration::migrate_post_tags_to_friend_tags();
		$result = $site_health->test_migration();
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'in progress', $result['label'] );

		// Complete migration
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}

		// Test completed status
		$result = $site_health->test_migration();
		$this->assertEquals( 'good', $result['status'] );
		$this->assertStringContainsString( 'completed', $result['label'] );
		$this->assertStringContainsString( 'completed', $result['description'] );
	}

	/**
	 * Test post_tag cleanup functionality
	 */
	public function test_cleanup_orphaned_post_tags() {
		// Setup both taxonomies
		$this->setup_pre_migration_environment();
		$this->setup_post_migration_environment();
		
		// Create a post_tag and a friend_tag with the same slug
		$post_tag = wp_insert_term( 'duplicate-tag', 'post_tag' );
		$friend_tag = wp_insert_term( 'duplicate-tag', Friends::TAG_TAXONOMY );
		$this->assertNotWPError( $post_tag );
		$this->assertNotWPError( $friend_tag );
		
		// Create another post_tag that will be orphaned
		$orphaned_tag = wp_insert_term( 'orphaned-duplicate', 'post_tag' );
		$orphaned_friend_tag = wp_insert_term( 'orphaned-duplicate', Friends::TAG_TAXONOMY );
		$this->assertNotWPError( $orphaned_tag );
		$this->assertNotWPError( $orphaned_friend_tag );
		
		// Create a regular post and assign the first post_tag (so it won't be deleted)
		$regular_post = $this->factory->post->create( array(
			'post_type' => 'post',
		) );
		wp_set_post_terms( $regular_post, array( $post_tag['term_id'] ), 'post_tag' );
		
		// Assign the friend_tag to a Friends post
		$friends_post = $this->factory->post->create( array(
			'post_type' => Friends::CPT,
		) );
		wp_set_post_terms( $friends_post, array( $friend_tag['term_id'] ), Friends::TAG_TAXONOMY );
		
		// The orphaned post_tag should have count 0, while the first should have count 1
		wp_update_term_count( $post_tag['term_id'], 'post_tag' );
		wp_update_term_count( $orphaned_tag['term_id'], 'post_tag' );
		
		// Run cleanup
		$results = Migration::cleanup_orphaned_post_tags();
		
		// Should have checked all post_tag terms (comprehensive approach)
		$this->assertEquals( 2, $results['checked'] );
		$this->assertEquals( 2, $results['recalculated'] );
		
		// The orphaned one should be deleted
		$this->assertEquals( 1, $results['deleted'] );
		
		// Verify the tag with posts still exists
		$surviving_tag = get_term( $post_tag['term_id'], 'post_tag' );
		$this->assertNotWPError( $surviving_tag );
		$this->assertEquals( 'duplicate-tag', $surviving_tag->name );
		
		// Verify the orphaned tag was deleted
		$deleted_tag = get_term( $orphaned_tag['term_id'], 'post_tag' );
		$this->assertTrue( is_wp_error( $deleted_tag ) || is_null( $deleted_tag ) );
	}

	/**
	 * Test post_tag cleanup Site Health integration
	 */
	public function test_post_tag_cleanup_site_health() {
		$friends = Friends::get_instance();
		
		// Setup both taxonomies
		$this->setup_pre_migration_environment();
		$this->setup_post_migration_environment();
		
		// Test when no orphaned tags exist
		$site_health = new \Friends\Site_Health();
		$result = $site_health->test_post_tag_cleanup();
		$this->assertEquals( 'good', $result['status'] );
		$this->assertStringContainsString( 'No orphaned post tags found', $result['label'] );
		
		// Create orphaned post_tag that also exists in friend_tag
		$post_tag = wp_insert_term( 'orphaned-site-health', 'post_tag' );
		$friend_tag = wp_insert_term( 'orphaned-site-health', Friends::TAG_TAXONOMY );
		$this->assertNotWPError( $post_tag );
		$this->assertNotWPError( $friend_tag );
		
		// Test Site Health when orphaned tags exist
		$result = $site_health->test_post_tag_cleanup();
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'Orphaned post tags found', $result['label'] );
		$this->assertStringContainsString( 'Clean Up Orphaned Tags', $result['description'] );
		$this->assertStringContainsString( 'Tags to be deleted:', $result['description'] );
		$this->assertStringContainsString( 'orphaned-site-health', $result['description'] );
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

	/**
	 * Test comprehensive post_tag count recalculation and cleanup
	 */
	public function test_recalculate_all_post_tag_counts() {
		// Setup environment
		$this->setup_post_migration_environment();
		
		// Create regular posts with post_tags
		$regular_post1 = $this->factory->post->create( array( 'post_type' => 'post' ) );
		$regular_post2 = $this->factory->post->create( array( 'post_type' => 'post' ) );
		
		// Create various post_tag terms
		$active_tag = wp_insert_term( 'active-tag', 'post_tag' );
		$orphaned_tag1 = wp_insert_term( 'orphaned-tag-1', 'post_tag' );
		$orphaned_tag2 = wp_insert_term( 'orphaned-tag-2', 'post_tag' );
		$shared_tag = wp_insert_term( 'shared-tag', 'post_tag' );
		
		$this->assertNotWPError( $active_tag );
		$this->assertNotWPError( $orphaned_tag1 );
		$this->assertNotWPError( $orphaned_tag2 );
		$this->assertNotWPError( $shared_tag );
		
		// Assign tags to posts
		wp_set_post_terms( $regular_post1, array( $active_tag['term_id'], $shared_tag['term_id'] ), 'post_tag' );
		wp_set_post_terms( $regular_post2, array( $shared_tag['term_id'] ), 'post_tag' );
		
		// Manually mess up some counts to simulate outdated data
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'count' => 99 ),
			array( 'term_id' => $active_tag['term_id'], 'taxonomy' => 'post_tag' )
		);
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'count' => 5 ),
			array( 'term_id' => $orphaned_tag1['term_id'], 'taxonomy' => 'post_tag' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		
		// Verify messed up counts
		$messed_up_term = get_term( $active_tag['term_id'], 'post_tag' );
		$this->assertEquals( 99, $messed_up_term->count );
		
		// Run comprehensive recalculation
		$results = Migration::recalculate_all_post_tag_counts();
		
		// Should check all tags that exist (may be fewer if WordPress cleaned some up)
		$this->assertGreaterThanOrEqual( 3, $results['checked'] );
		$this->assertEquals( $results['checked'], $results['recalculated'] );
		
		// Should delete the 2 orphaned tags (with 0 count after recalculation)
		$this->assertEquals( 2, $results['deleted'] );
		
		// Verify active tag count was corrected
		$corrected_term = get_term( $active_tag['term_id'], 'post_tag' );
		$this->assertEquals( 1, $corrected_term->count );
		
		// Verify shared tag count is correct
		$shared_term = get_term( $shared_tag['term_id'], 'post_tag' );
		$this->assertEquals( 2, $shared_term->count );
		
		// Verify orphaned tags were deleted
		$deleted_tag1 = get_term( $orphaned_tag1['term_id'], 'post_tag' );
		$deleted_tag2 = get_term( $orphaned_tag2['term_id'], 'post_tag' );
		$this->assertTrue( is_wp_error( $deleted_tag1 ) || is_null( $deleted_tag1 ) );
		$this->assertTrue( is_wp_error( $deleted_tag2 ) || is_null( $deleted_tag2 ) );
		
		// Verify results contain detailed information
		$this->assertArrayHasKey( 'tags_processed', $results );
		// Only tags with count changes or deletions are logged
		$this->assertGreaterThanOrEqual( 2, count( $results['tags_processed'] ) );
		
		// Check that both orphaned and count-updated tags are in results
		$processed_actions = array_column( $results['tags_processed'], 'action' );
		$this->assertContains( 'deleted', $processed_actions );
		$this->assertContains( 'count_updated', $processed_actions );
	}

	/**
	 * Test migration cleanup excludes revisions and other irrelevant post types
	 */
	public function test_migration_cleanup_excludes_irrelevant_post_types() {
		// Include Migration class
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';

		// Step 1: Setup pre-migration environment (old CPT with post_tag)
		$this->setup_pre_migration_environment();

		// Step 2: Create test data using the old configuration
		$test_tag = wp_insert_term( 'test-exclusion-tag', 'post_tag' );
		$this->assertNotWPError( $test_tag );

		// Create a Friends post with this tag using old configuration
		$friends_post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Friends Post',
			'post_status' => 'publish',
		) );
		wp_set_post_terms( $friends_post_id, array( $test_tag['term_id'] ), 'post_tag' );

		// Create a revision with the same tag (simulating WordPress auto-save behavior)
		$revision_id = $this->factory->post->create( array(
			'post_type'   => 'revision',
			'post_title'  => 'Revision',
			'post_status' => 'inherit',
			'post_parent' => $friends_post_id,
		) );
		wp_set_post_terms( $revision_id, array( $test_tag['term_id'] ), 'post_tag' );

		// Create an ap_actor post with the same tag
		$ap_actor_id = $this->factory->post->create( array(
			'post_type'   => 'ap_actor',
			'post_title'  => 'AP Actor',
			'post_status' => 'publish',
		) );
		wp_set_post_terms( $ap_actor_id, array( $test_tag['term_id'] ), 'post_tag' );

		// Create a post_collection post with the same tag
		$post_collection_id = $this->factory->post->create( array(
			'post_type'   => 'post_collection',
			'post_title'  => 'Post Collection',
			'post_status' => 'publish',
		) );
		wp_set_post_terms( $post_collection_id, array( $test_tag['term_id'] ), 'post_tag' );

		// Verify the tag exists and has posts associated
		$term_before = get_term( $test_tag['term_id'], 'post_tag' );
		$this->assertNotWPError( $term_before );

		// Step 3: Simulate plugin upgrade - unregister old, register new
		$this->setup_post_migration_environment();

		// Step 4: Run migration
		Migration::migrate_post_tags_to_friend_tags();
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}

		// Step 5: Verify migration results
		// Verify the tag was deleted (because only Friends post used it, and that was migrated)
		$term_after = get_term( $test_tag['term_id'], 'post_tag' );
		$this->assertTrue( is_wp_error( $term_after ) || is_null( $term_after ) );

		// Verify Friends post now has friend_tag
		$friend_tags = wp_get_post_terms( $friends_post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertContains( 'test-exclusion-tag', $friend_tags );

		// Verify Friends post no longer has post_tag
		$post_tags = wp_get_post_terms( $friends_post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertEmpty( $post_tags );
	}

	/**
	 * Test migration cleanup preserves tags used by regular posts
	 */
	public function test_migration_cleanup_preserves_regular_post_tags() {
		// Include Migration class
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';

		// Step 1: Setup pre-migration environment (old CPT with post_tag)
		$this->setup_pre_migration_environment();

		// Step 2: Create test data using the old configuration
		$test_tag = wp_insert_term( 'shared-with-regular-post', 'post_tag' );
		$this->assertNotWPError( $test_tag );

		// Create a Friends post with this tag using old configuration
		$friends_post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Friends Post',
			'post_status' => 'publish',
		) );
		wp_set_post_terms( $friends_post_id, array( $test_tag['term_id'] ), 'post_tag' );

		// Create a regular post with the same tag
		$regular_post_id = $this->factory->post->create( array(
			'post_type'   => 'post',
			'post_title'  => 'Regular Post',
			'post_status' => 'publish',
		) );
		wp_set_post_terms( $regular_post_id, array( $test_tag['term_id'] ), 'post_tag' );

		// Create a revision for the regular post with the same tag
		$revision_id = $this->factory->post->create( array(
			'post_type'   => 'revision',
			'post_title'  => 'Revision',
			'post_status' => 'inherit',
			'post_parent' => $regular_post_id,
		) );
		wp_set_post_terms( $revision_id, array( $test_tag['term_id'] ), 'post_tag' );

		// Verify the tag exists before migration
		$term_before = get_term( $test_tag['term_id'], 'post_tag' );
		$this->assertNotWPError( $term_before );

		// Step 3: Simulate plugin upgrade - unregister old, register new
		$this->setup_post_migration_environment();

		// Step 4: Run migration
		Migration::migrate_post_tags_to_friend_tags();
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}

		// Step 5: Verify migration results
		// Verify the tag still exists (because regular post uses it)
		$term_after = get_term( $test_tag['term_id'], 'post_tag' );
		$this->assertNotWPError( $term_after );
		$this->assertEquals( 'shared-with-regular-post', $term_after->name );

		// Verify Friends post has friend_tag
		$friend_tags = wp_get_post_terms( $friends_post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertContains( 'shared-with-regular-post', $friend_tags );

		// Verify regular post still has post_tag
		$regular_post_tags = wp_get_post_terms( $regular_post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertContains( 'shared-with-regular-post', $regular_post_tags );
	}

	/**
	 * Test that batch migration processes ALL posts, not just the first batch_size.
	 * This verifies the offset pagination fix: with 150+ posts and batch_size of 100,
	 * all posts should be migrated, not just the first 100.
	 */
	public function test_migrate_post_tags_batch_boundary() {
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';

		$this->setup_pre_migration_environment();

		// Create 150 Friends posts with tags (more than the default batch_size of 100).
		$post_ids = array();
		$tag = wp_insert_term( 'boundary-test-tag', 'post_tag' );
		$this->assertNotWPError( $tag );

		for ( $i = 0; $i < 150; $i++ ) {
			$post_id = $this->factory->post->create( array(
				'post_type'   => Friends::CPT,
				'post_title'  => 'Batch Boundary Post ' . $i,
				'post_status' => 'publish',
			) );
			wp_set_post_terms( $post_id, array( $tag['term_id'] ), 'post_tag' );
			$post_ids[] = $post_id;
		}

		$this->setup_post_migration_environment();

		// Run migration.
		Migration::migrate_post_tags_to_friend_tags();
		$batches = 0;
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
			++$batches;
			if ( $batches > 10 ) {
				$this->fail( 'Migration did not complete within expected number of batches.' );
			}
		}

		// Should take at least 2 batches.
		$this->assertGreaterThanOrEqual( 2, $batches );

		// Verify ALL 150 posts have friend_tags.
		$migrated_count = 0;
		foreach ( $post_ids as $post_id ) {
			$friend_tags = wp_get_post_terms( $post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
			if ( in_array( 'boundary-test-tag', $friend_tags, true ) ) {
				++$migrated_count;
			}
		}
		$this->assertEquals( 150, $migrated_count, 'All 150 posts should have been migrated, not just the first batch.' );

		// Verify no post_tag relationships remain.
		foreach ( $post_ids as $post_id ) {
			$remaining = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
			$this->assertEmpty( $remaining, "Post $post_id should have no remaining post_tag terms." );
		}
	}

	/**
	 * Test undo functionality for post tags migration.
	 */
	public function test_undo_post_tags_migration() {
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';

		$this->setup_pre_migration_environment();

		$post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Undo Test Post',
			'post_status' => 'publish',
		) );

		$test_tags = array( 'undo-tag-1', 'undo-tag-2' );
		$tag_ids = array();
		foreach ( $test_tags as $tag_name ) {
			$tag = wp_insert_term( $tag_name, 'post_tag' );
			$this->assertNotWPError( $tag );
			$tag_ids[] = $tag['term_id'];
		}
		wp_set_post_terms( $post_id, $tag_ids, 'post_tag' );

		$this->setup_post_migration_environment();

		// Run migration.
		Migration::migrate_post_tags_to_friend_tags();
		while ( get_option( 'friends_tag_migration_in_progress' ) ) {
			Migration::migrate_post_tags_batch();
		}

		// Verify migration completed.
		$friend_tags = wp_get_post_terms( $post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertCount( 2, $friend_tags );
		$remaining_post_tags = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertEmpty( $remaining_post_tags );

		// Verify recovery data exists.
		$recovery = get_option( 'friends_tag_migration_recovery', array() );
		$this->assertNotEmpty( $recovery );
		$this->assertArrayHasKey( $post_id, $recovery );

		// Undo the migration.
		$result = Migration::undo_post_tags_migration();
		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['restored'] );

		// Verify post_tag terms are restored.
		$restored_tags = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertEqualSets( $test_tags, $restored_tags );

		// Verify recovery data is cleaned up.
		$this->assertEmpty( get_option( 'friends_tag_migration_recovery', array() ) );

		// Verify completion status is reset.
		$this->assertFalse( get_option( 'friends_tag_migration_completed' ) );
	}

	/**
	 * Test the full upgrade path from 3.6.0 to current version.
	 */
	public function test_full_upgrade_path_from_3_6_0() {
		require_once dirname( __DIR__ ) . '/includes/class-migration.php';

		$this->setup_pre_migration_environment();

		// Create pre-migration state: posts with post_tags.
		$post_id = $this->factory->post->create( array(
			'post_type'   => Friends::CPT,
			'post_title'  => 'Upgrade Path Test',
			'post_status' => 'publish',
		) );

		$tag = wp_insert_term( 'upgrade-test-tag', 'post_tag' );
		$this->assertNotWPError( $tag );
		wp_set_post_terms( $post_id, array( $tag['term_id'] ), 'post_tag' );

		$this->setup_post_migration_environment();

		// Simulate upgrade from 3.6.0.
		update_option( 'friends_plugin_version', '3.6.0' );
		Friends::upgrade_plugin(
			null,
			array(
				'action'  => 'update',
				'plugins' => array( FRIENDS_PLUGIN_BASENAME ),
				'type'    => 'plugin',
			)
		);

		// Run all scheduled batches to completion.
		$max_iterations = 20;
		$iterations = 0;
		while ( get_option( 'friends_tag_migration_in_progress' ) && $iterations < $max_iterations ) {
			Migration::migrate_post_tags_batch();
			++$iterations;
		}

		// Verify tag migration completed.
		$this->assertTrue( (bool) get_option( 'friends_tag_migration_completed' ), 'Tag migration should be completed.' );

		// Verify the post was migrated.
		$friend_tags = wp_get_post_terms( $post_id, Friends::TAG_TAXONOMY, array( 'fields' => 'names' ) );
		$this->assertContains( 'upgrade-test-tag', $friend_tags );

		// Verify post_tag was removed.
		$remaining = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
		$this->assertEmpty( $remaining );
	}

	/**
	 * Test orphaned post tags display with many tags
	 */
	public function test_post_tag_cleanup_site_health_with_many_tags() {
		$this->setup_post_migration_environment();

		// Create 20 orphaned post_tag terms
		for ( $i = 1; $i <= 20; $i++ ) {
			$tag = wp_insert_term( "orphaned-tag-{$i}", 'post_tag' );
			$this->assertNotWPError( $tag );
		}

		// Test Site Health when many orphaned tags exist
		$site_health = new \Friends\Site_Health();
		$result = $site_health->test_post_tag_cleanup();
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'Orphaned post tags found', $result['label'] );
		$this->assertStringContainsString( 'Tags to be deleted:', $result['description'] );
		$this->assertStringContainsString( 'orphaned-tag-1', $result['description'] );
		$this->assertStringContainsString( 'and 5 more', $result['description'] );
	}

}