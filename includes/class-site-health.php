<?php
/**
 * Site Health Integration for Friends Plugin
 *
 * This class handles all Site Health related functionality including
 * migration status checks and post tag cleanup diagnostics.
 *
 * @package Friends
 */

namespace Friends;

/**
 * Site Health integration class
 */
class Site_Health {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'site_health_tests', array( $this, 'add_tests' ) );
		add_action( 'wp_ajax_friends_restart_migration', array( $this, 'ajax_restart_migration' ) );
		add_action( 'wp_ajax_friends_cleanup_post_tags', array( $this, 'ajax_cleanup_post_tags' ) );
		add_action( 'wp_ajax_friends_recalculate_post_tag_counts', array( $this, 'ajax_recalculate_post_tag_counts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Site Health tests.
	 *
	 * @param array $tests Existing tests.
	 * @return array Modified tests.
	 */
	public function add_tests( $tests ) {
		$tests['direct']['friends_migration'] = array(
			'label' => __( 'Friends Post Tag Migration', 'friends' ),
			'test'  => array( $this, 'test_migration' ),
		);

		$tests['direct']['friends_orphaned_post_tags'] = array(
			'label' => __( 'Friends Orphaned Post Tags', 'friends' ),
			'test'  => array( $this, 'test_post_tag_cleanup' ),
		);

		$tests['direct']['friends_post_tag_count_recalculation'] = array(
			'label' => __( 'Post Tag Count Recalculation', 'friends' ),
			'test'  => array( $this, 'test_post_tag_count_recalculation' ),
		);

		return $tests;
	}

	/**
	 * Site Health test for post tag migration status.
	 *
	 * @return array Site Health test result.
	 */
	public function test_migration() {
		require_once __DIR__ . '/class-migration.php';
		$status = Migration::get_migration_status();

		if ( $status['completed'] ) {
			$result = array(
				'label'       => __( 'Post tag migration completed', 'friends' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'green',
				),
				'description' => sprintf(
					'<p>%s</p>',
					$status['completed_time'] ? sprintf(
						/* translators: %s: completion time */
						__( 'The post tag migration was completed on %s.', 'friends' ),
						$status['completed_time']
					) : __( 'The post tag migration has been completed.', 'friends' )
				),
				'test'        => 'friends_migration',
			);
		} elseif ( $status['in_progress'] ) {
			$result = array(
				'label'       => __( 'Post tag migration in progress', 'friends' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p><p>%s</p>',
					sprintf(
						/* translators: %1$d: migrated count, %2$d: total count */
						__( 'Migration progress: %1$d/%2$d posts processed.', 'friends' ),
						isset( $status['migrated'] ) ? $status['migrated'] : 0,
						isset( $status['total'] ) ? $status['total'] : 0
					),
					__( 'The migration will continue automatically in the background.', 'friends' )
				),
				'test'        => 'friends_migration',
			);
		} else {
			$result = array(
				'label'       => __( 'Post tag migration available', 'friends' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p><p><button type="button" class="button button-primary" onclick="friendsRestartMigration(this)">%s</button></p>',
					__( 'Friends posts are still using the post_tag taxonomy. A migration to the friend_tag taxonomy is recommended for better organization.', 'friends' ),
					__( 'Start Migration', 'friends' )
				),
				'test'        => 'friends_migration',
			);
		}

		return $result;
	}

	/**
	 * Site Health test for orphaned post tags cleanup.
	 *
	 * @return array Site Health test result.
	 */
	public function test_post_tag_cleanup() {
		require_once __DIR__ . '/class-migration.php';

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Count ALL post_tag terms that have no actual post associations from supported post types.

		// Get post types that support the post_tag taxonomy
		$post_types = get_post_types_by_support( array(), 'and' );
		$post_tag_post_types = array();
		foreach ( $post_types as $post_type ) {
			if ( is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
				$post_tag_post_types[] = $post_type;
			}
		}

		if ( empty( $post_tag_post_types ) ) {
			$orphaned_count = $wpdb->get_var(
				"SELECT COUNT(*)
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = 'post_tag'"
			);
		} else {
			$placeholders = implode( ',', array_fill( 0, count( $post_tag_post_types ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$orphaned_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$wpdb->terms} t
					INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
					LEFT JOIN (
						SELECT DISTINCT tt2.term_id
						FROM {$wpdb->term_relationships} tr
						INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr.term_taxonomy_id = tt2.term_taxonomy_id
						INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
						WHERE tt2.taxonomy = 'post_tag'
						AND p.post_status IN ('publish', 'private')
						AND p.post_type IN ($placeholders)
					) used_terms ON t.term_id = used_terms.term_id
					WHERE tt.taxonomy = 'post_tag' AND used_terms.term_id IS NULL",
					$post_tag_post_types
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $orphaned_count > 0 ) {
			$result = array(
				'label'       => __( 'Orphaned post tags found', 'friends' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p><p><button type="button" class="button button-primary" onclick="friendsCleanupPostTags(this)">%s</button></p>',
					sprintf(
						/* translators: %d: number of orphaned tags */
						_n(
							'%d orphaned post_tag term was found with no associated posts. These tags may have been created by Friends posts before migration or other sources and can be safely removed to keep your tag cloud clean.',
							'%d orphaned post_tag terms were found with no associated posts. These tags may have been created by Friends posts before migration or other sources and can be safely removed to keep your tag cloud clean.',
							$orphaned_count,
							'friends'
						),
						$orphaned_count
					),
					__( 'Clean Up Orphaned Tags', 'friends' )
				),
				'test'        => 'friends_orphaned_post_tags',
			);
		} else {
			$result = array(
				'label'       => __( 'No orphaned post tags found', 'friends' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'green',
				),
				'description' => sprintf(
					'<p>%s</p>',
					__( 'All post_tag terms have associated posts. Your tag system is clean.', 'friends' )
				),
				'test'        => 'friends_orphaned_post_tags',
			);
		}

		return $result;
	}

	/**
	 * Site Health test for post tag count recalculation.
	 *
	 * @return array Site Health test result.
	 */
	public function test_post_tag_count_recalculation() {
		require_once __DIR__ . '/class-migration.php';

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Count all post_tag terms.
		$total_post_tags = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			WHERE tt.taxonomy = 'post_tag'"
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $total_post_tags > 0 ) {
			$result = array(
				'label'       => __( 'Post tag count recalculation available', 'friends' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'blue',
				),
				'description' => sprintf(
					'<p>%s</p><p><button type="button" class="button button-primary" onclick="friendsRecalculatePostTagCounts(this)">%s</button></p>',
					sprintf(
						/* translators: %d: number of post tags */
						_n(
							'You have %d post_tag term. You can recalculate all post tag counts to ensure they accurately reflect only posts from supported post types, excluding Friends posts.',
							'You have %d post_tag terms. You can recalculate all post tag counts to ensure they accurately reflect only posts from supported post types, excluding Friends posts.',
							$total_post_tags,
							'friends'
						),
						$total_post_tags
					),
					__( 'Recalculate All Tag Counts', 'friends' )
				),
				'test'        => 'friends_post_tag_count_recalculation',
			);
		} else {
			$result = array(
				'label'       => __( 'No post tags to recalculate', 'friends' ),
				'status'      => 'good',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'green',
				),
				'description' => sprintf(
					'<p>%s</p>',
					__( 'No post_tag terms found in your system.', 'friends' )
				),
				'test'        => 'friends_post_tag_count_recalculation',
			);
		}

		return $result;
	}

	/**
	 * AJAX handler for restarting migration.
	 */
	public function ajax_restart_migration() {
		check_ajax_referer( 'friends_restart_migration', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'friends' ) );
		}

		require_once __DIR__ . '/class-migration.php';
		Migration::restart_migration();

		wp_send_json_success( array( 'message' => __( 'Migration restarted successfully.', 'friends' ) ) );
	}

	/**
	 * AJAX handler for cleaning up orphaned post tags.
	 */
	public function ajax_cleanup_post_tags() {
		check_ajax_referer( 'friends_cleanup_post_tags', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'friends' ) );
		}

		require_once __DIR__ . '/class-migration.php';
		$results = Migration::cleanup_orphaned_post_tags();

		if ( $results['deleted'] > 0 ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
					/* translators: %d: number of deleted tags */
						_n(
							'Successfully deleted %d orphaned post tag.',
							'Successfully deleted %d orphaned post tags.',
							$results['deleted'],
							'friends'
						),
						$results['deleted']
					),
				)
			);
		} else {
			wp_send_json_success( array( 'message' => __( 'No orphaned post_tags were found to clean up.', 'friends' ) ) );
		}
	}

	/**
	 * AJAX handler for recalculating post tag counts.
	 */
	public function ajax_recalculate_post_tag_counts() {
		check_ajax_referer( 'friends_recalculate_post_tag_counts', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'friends' ) );
		}

		require_once __DIR__ . '/class-migration.php';
		$results = Migration::recalculate_all_post_tag_counts();

		if ( $results['deleted'] > 0 ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
					/* translators: %1$d: recalculated count, %2$d: deleted count */
						_n(
							'Recalculated %1$d tag count and deleted %2$d orphaned tag.',
							'Recalculated %1$d tag counts and deleted %2$d orphaned tags.',
							$results['recalculated'],
							'friends'
						),
						$results['recalculated'],
						$results['deleted']
					),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message' => sprintf(
					/* translators: %d: recalculated count */
						_n(
							'Recalculated %d tag count. No orphaned tags found.',
							'Recalculated %d tag counts. No orphaned tags found.',
							$results['recalculated'],
							'friends'
						),
						$results['recalculated']
					),
				)
			);
		}
	}

	/**
	 * Enqueue scripts for Site Health pages.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'site-health.php' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'friends-site-health',
			plugins_url( 'site-health.js', __DIR__ ),
			array( 'jquery' ),
			filemtime( plugin_dir_path( __DIR__ ) . 'site-health.js' ),
			true
		);

		wp_localize_script(
			'friends-site-health',
			'friendsSiteHealth',
			array(
				'restartMigrationNonce'         => wp_create_nonce( 'friends_restart_migration' ),
				'cleanupPostTagsNonce'          => wp_create_nonce( 'friends_cleanup_post_tags' ),
				'recalculatePostTagCountsNonce' => wp_create_nonce( 'friends_recalculate_post_tag_counts' ),
				'ajaxUrl'                       => admin_url( 'admin-ajax.php' ),
				'confirmRestart'                => __( 'This will restart the migration process. Any posts that were previously migrated will be re-processed. Continue?', 'friends' ),
				'confirmCleanup'                => __( 'This will clean up orphaned post_tag terms that were created by Friends posts. Only unused tags will be deleted. Continue?', 'friends' ),
				'confirmRecalculate'            => __( 'This will recalculate all post_tag counts and delete any orphaned tags. Continue?', 'friends' ),
			)
		);
	}
}
