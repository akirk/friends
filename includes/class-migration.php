<?php
/**
 * Friends Migration
 *
 * This contains the migration functions for the Friends plugin.
 * This class is only loaded when migrations are needed during plugin upgrades.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This class handles plugin migrations during upgrades.
 *
 * @package Friends
 * @author Alex Kirk
 */
class Migration {

	/**
	 * Registry of all migrations.
	 * Each migration has: id, version, title, description, method, status_option, batched, batch_method
	 *
	 * @var array
	 */
	private static $registry = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register the WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'wp_ajax_friends_run_migration', array( $this, 'ajax_run_migration' ) );
		add_action( 'wp_ajax_friends_get_migration_status', array( $this, 'ajax_get_migration_status' ) );
		add_action( 'wp_ajax_friends_process_migration_batch', array( $this, 'ajax_process_migration_batch' ) );
		add_action( 'wp_ajax_friends_get_migration_debug', array( $this, 'ajax_get_migration_debug' ) );
		add_action( 'wp_ajax_friends_clear_failed_url', array( $this, 'ajax_clear_failed_url' ) );

		// Register debug output hooks for migrations.
		add_action( 'friends_migration_debug_link_activitypub_feeds_to_actors', array( $this, 'debug_link_activitypub_feeds_to_actors' ) );
	}

	/**
	 * Register all migrations.
	 */
	public static function register_migrations() {
		if ( ! empty( self::$registry ) ) {
			return;
		}

		self::register(
			array(
				'id'            => 'gravatar_to_user_icon',
				'version'       => '0.20.1',
				'title'         => 'Migrate Gravatar to User Icon',
				'description'   => 'Migrates user gravatar option to the new user_icon_url format.',
				'method'        => 'migrate_gravatar_to_user_icon_url',
				'status_option' => null, // No status tracking for simple migrations.
			)
		);

		self::register(
			array(
				'id'            => 'feed_options_to_user',
				'version'       => '2.6.0',
				'title'         => 'Migrate Feed Options',
				'description'   => 'Moves feed options from global options to user-specific options.',
				'method'        => 'migrate_feed_options_to_user_options',
				'status_option' => null,
			)
		);

		self::register(
			array(
				'id'            => 'wp_friendships',
				'version'       => '2.8.7',
				'title'         => 'Enable WP Friendships Flag',
				'description'   => 'Enables the WordPress friendships flag if friendship functionality was used.',
				'method'        => 'enable_wp_friendships_if_used',
				'status_option' => null,
			)
		);

		self::register(
			array(
				'id'            => 'external_user_and_cron',
				'version'       => '2.9.4',
				'title'         => 'Migrate External User and Cron',
				'description'   => 'Renames external-mentions user to external and upgrades cron schedule.',
				'method'        => 'migrate_external_user_and_cron',
				'status_option' => null,
			)
		);

		self::register(
			array(
				'id'            => 'frontend_default_view',
				'version'       => '3.1.8',
				'title'         => 'Migrate Frontend Default View',
				'description'   => 'Moves frontend default view option to user-specific setting.',
				'method'        => 'migrate_frontend_default_view_to_user_option',
				'status_option' => null,
			)
		);

		self::register(
			array(
				'id'            => 'post_tags_to_friend_tags',
				'version'       => '4.0.0',
				'title'         => 'Migrate Post Tags to Friend Tags',
				'description'   => 'Converts post_tag taxonomy to friend_tag for Friends posts. Runs in batches.',
				'method'        => 'migrate_post_tags_to_friend_tags',
				'status_option' => 'friends_tag_migration_completed',
				'batched'       => true,
				'batch_method'  => 'migrate_post_tags_batch',
				'cron_hook'     => 'friends_migrate_post_tags_batch',
				'progress'      => array(
					'in_progress' => 'friends_tag_migration_in_progress',
					'total'       => 'friends_tag_migration_total',
					'processed'   => 'friends_tag_migration_processed',
				),
			)
		);

		self::register(
			array(
				'id'            => 'backfill_mention_tags',
				'version'       => '4.1.0',
				'title'         => 'Backfill Mention Tags',
				'description'   => 'Extracts mention tags from Mastodon HTML content in existing posts.',
				'method'        => 'backfill_mention_tags_from_mastodon_html',
				'status_option' => null,
			)
		);

		self::register(
			array(
				'id'            => 'activitypub_attributed_to',
				'version'       => '4.1.0',
				'title'         => 'Migrate ActivityPub AttributedTo',
				'description'   => 'Converts attributedTo URLs to ap_actor post IDs for better reliability. Runs in batches.',
				'method'        => 'migrate_activitypub_attributed_to',
				'status_option' => 'friends_ap_attributed_to_migration_completed',
				'batched'       => true,
				'batch_method'  => 'migrate_activitypub_attributed_to_batch',
				'cron_hook'     => 'friends_migrate_ap_attributed_to_batch',
				'progress'      => array(
					'in_progress' => 'friends_ap_attributed_to_migration_in_progress',
					'total'       => 'friends_ap_attributed_to_migration_total',
					'processed'   => 'friends_ap_attributed_to_migration_processed',
				),
			)
		);

		self::register(
			array(
				'id'            => 'import_activitypub_followings',
				'version'       => '4.1.0',
				'title'         => 'Import ActivityPub Followings',
				'description'   => 'Imports existing ActivityPub plugin followings as Friends subscriptions.',
				'method'        => 'import_activitypub_followings',
				'status_option' => 'friends_activitypub_followings_imported',
			)
		);

		self::register(
			array(
				'id'            => 'link_activitypub_feeds_to_actors',
				'version'       => '4.1.0',
				'title'         => 'Link ActivityPub Feeds to Actors',
				'description'   => 'Links existing ActivityPub feeds to their ap_actor posts for URL synchronization. Runs in batches.',
				'method'        => 'link_activitypub_feeds_to_actors',
				'status_option' => 'friends_ap_feeds_linked_to_actors',
				'batched'       => true,
				'batch_method'  => 'link_activitypub_feeds_to_actors_batch',
				'cron_hook'     => 'friends_link_ap_feeds_batch',
				'progress'      => array(
					'in_progress' => 'friends_ap_feeds_link_migration_in_progress',
					'total'       => 'friends_ap_feeds_link_migration_total',
					'processed'   => 'friends_ap_feeds_link_migration_processed',
				),
			)
		);
	}

	/**
	 * Register a single migration.
	 *
	 * @param array $migration The migration data.
	 */
	private static function register( $migration ) {
		$defaults = array(
			'batched'      => false,
			'batch_method' => null,
			'progress'     => array(),
		);
		self::$registry[ $migration['id'] ] = array_merge( $defaults, $migration );
	}

	/**
	 * Get all registered migrations.
	 *
	 * @return array The migration registry.
	 */
	public static function get_registry() {
		self::register_migrations();
		return self::$registry;
	}

	/**
	 * Get a single migration by ID.
	 *
	 * @param string $id The migration ID.
	 * @return array|null The migration data or null.
	 */
	public static function get_migration( $id ) {
		self::register_migrations();
		return isset( self::$registry[ $id ] ) ? self::$registry[ $id ] : null;
	}

	/**
	 * Get the status of a migration.
	 *
	 * @param string $id The migration ID.
	 * @return array Status information.
	 */
	public static function get_migration_status_by_id( $id ) {
		$migration = self::get_migration( $id );
		if ( ! $migration ) {
			return array( 'status' => 'unknown' );
		}

		$status = array(
			'id'          => $id,
			'title'       => $migration['title'],
			'description' => $migration['description'],
			'version'     => $migration['version'],
			'batched'     => $migration['batched'],
		);

		// Check completion status.
		if ( $migration['status_option'] ) {
			$status['completed'] = (bool) get_option( $migration['status_option'] );
		} else {
			// No status tracking - assume completed if we're past the version.
			$current_version = get_option( 'friends_plugin_version', '0' );
			$status['completed'] = version_compare( $current_version, $migration['version'], '>=' );
			$status['no_tracking'] = true;
		}

		// Check progress for batched migrations.
		if ( $migration['batched'] && ! empty( $migration['progress'] ) ) {
			$status['in_progress'] = (bool) get_option( $migration['progress']['in_progress'] );
			$status['total'] = (int) get_option( $migration['progress']['total'], 0 );
			$status['processed'] = (int) get_option( $migration['progress']['processed'], 0 );
			if ( $status['total'] > 0 ) {
				$status['percent'] = min( 100, round( ( $status['processed'] / $status['total'] ) * 100, 1 ) );
			} else {
				$status['percent'] = 0;
			}

			// Check for next scheduled batch.
			if ( $status['in_progress'] && ! empty( $migration['cron_hook'] ) ) {
				$next_scheduled = wp_next_scheduled( $migration['cron_hook'] );
				if ( $next_scheduled ) {
					$status['next_scheduled'] = $next_scheduled;
				}
				$status['cron_hook'] = $migration['cron_hook'];
			}
		}

		return $status;
	}

	/**
	 * Get status of all migrations.
	 *
	 * @return array Status information for all migrations.
	 */
	public static function get_all_statuses() {
		self::register_migrations();
		$statuses = array();
		foreach ( array_keys( self::$registry ) as $id ) {
			$statuses[ $id ] = self::get_migration_status_by_id( $id );
		}
		return $statuses;
	}

	/**
	 * Run a specific migration manually.
	 *
	 * @param string $id The migration ID.
	 * @return array Result of the migration.
	 */
	public static function run_migration( $id ) {
		$migration = self::get_migration( $id );
		if ( ! $migration ) {
			return array(
				'success' => false,
				'message' => 'Migration not found: ' . $id,
			);
		}

		// Reset status for re-running.
		if ( $migration['status_option'] ) {
			delete_option( $migration['status_option'] );
		}
		if ( $migration['batched'] && ! empty( $migration['progress'] ) ) {
			foreach ( $migration['progress'] as $option ) {
				delete_option( $option );
			}
		}

		// Call the migration method.
		$method = $migration['method'];
		if ( method_exists( __CLASS__, $method ) ) {
			self::$method();
			return array(
				'success' => true,
				'message' => 'Migration started: ' . $migration['title'],
				'batched' => $migration['batched'],
			);
		}

		return array(
			'success' => false,
			'message' => 'Migration method not found: ' . $method,
		);
	}

	/**
	 * Process a single batch for a migration.
	 *
	 * @param string $id The migration ID.
	 * @return array Result of the batch processing.
	 */
	public static function process_batch( $id ) {
		$migration = self::get_migration( $id );
		if ( ! $migration ) {
			return array(
				'success' => false,
				'message' => 'Migration not found: ' . $id,
			);
		}

		if ( ! $migration['batched'] || empty( $migration['batch_method'] ) ) {
			return array(
				'success' => false,
				'message' => 'Migration is not batched: ' . $id,
			);
		}

		$batch_method = $migration['batch_method'];
		if ( ! method_exists( __CLASS__, $batch_method ) ) {
			return array(
				'success' => false,
				'message' => 'Batch method not found: ' . $batch_method,
			);
		}

		// Clear any scheduled cron for this batch to avoid double-processing.
		if ( ! empty( $migration['cron_hook'] ) ) {
			wp_clear_scheduled_hook( $migration['cron_hook'] );
		}

		// Process the batch.
		self::$batch_method();

		return array(
			'success' => true,
			'message' => 'Batch processed',
		);
	}

	/**
	 * AJAX handler for clearing failed URLs to allow retry.
	 */
	public function ajax_clear_failed_url() {
		check_ajax_referer( 'friends_clear_failed_url' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';

		if ( '__all__' === $url ) {
			// Clear all failed URLs.
			delete_option( 'friends_ap_feeds_failed_urls' );
			delete_option( 'friends_ap_feeds_failed_count' );
			delete_option( 'friends_ap_feeds_link_migration_failed_urls' );
			delete_option( 'friends_ap_feeds_link_migration_failed' );
			delete_option( 'friends_ap_feeds_link_migration_current_url' );
			delete_option( 'friends_ap_feeds_linked_to_actors' );
			wp_send_json_success( 'All failed URLs cleared' );
		}

		if ( empty( $url ) ) {
			wp_send_json_error( 'No URL provided' );
		}

		// Remove the specific URL from both failed URL lists.
		$failed_urls = get_option( 'friends_ap_feeds_failed_urls', array() );
		$failed_urls = array_filter(
			$failed_urls,
			function ( $item ) use ( $url ) {
				return $item['url'] !== $url;
			}
		);
		update_option( 'friends_ap_feeds_failed_urls', array_values( $failed_urls ), false );
		update_option( 'friends_ap_feeds_failed_count', count( $failed_urls ), false );

		$in_progress_failed = get_option( 'friends_ap_feeds_link_migration_failed_urls', array() );
		$in_progress_failed = array_filter(
			$in_progress_failed,
			function ( $item ) use ( $url ) {
				return $item['url'] !== $url;
			}
		);
		update_option( 'friends_ap_feeds_link_migration_failed_urls', array_values( $in_progress_failed ), false );
		update_option( 'friends_ap_feeds_link_migration_failed', count( $in_progress_failed ), false );

		// Clear the current URL marker if it matches.
		if ( get_option( 'friends_ap_feeds_link_migration_current_url' ) === $url ) {
			delete_option( 'friends_ap_feeds_link_migration_current_url' );
		}

		// Reset migration completed flag so it can run again.
		delete_option( 'friends_ap_feeds_linked_to_actors' );

		wp_send_json_success( 'URL cleared: ' . $url );
	}

	/**
	 * AJAX handler for running a migration.
	 */
	public function ajax_run_migration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'friends' ) ) );
		}

		if ( ! check_ajax_referer( 'friends-run-migration', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'friends' ) ) );
		}

		$migration_id = isset( $_POST['migration_id'] ) ? sanitize_key( $_POST['migration_id'] ) : '';
		if ( empty( $migration_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No migration ID provided.', 'friends' ) ) );
		}

		$result = self::run_migration( $migration_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for getting migration status.
	 */
	public function ajax_get_migration_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'friends' ) ) );
		}

		if ( ! check_ajax_referer( 'friends-migration-status', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'friends' ) ) );
		}

		$migration_id = isset( $_POST['migration_id'] ) ? sanitize_key( $_POST['migration_id'] ) : '';
		if ( empty( $migration_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No migration ID provided.', 'friends' ) ) );
		}

		$status = self::get_migration_status_by_id( $migration_id );

		ob_start();
		self::render_status_badge( $status );
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'status'      => $status,
				'html'        => $html,
				'in_progress' => ! empty( $status['in_progress'] ),
			)
		);
	}

	/**
	 * AJAX handler for processing a migration batch.
	 */
	public function ajax_process_migration_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'friends' ) ) );
		}

		if ( ! check_ajax_referer( 'friends-process-batch', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'friends' ) ) );
		}

		$migration_id = isset( $_POST['migration_id'] ) ? sanitize_key( $_POST['migration_id'] ) : '';
		if ( empty( $migration_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No migration ID provided.', 'friends' ) ) );
		}

		$result = self::process_batch( $migration_id );

		if ( ! $result['success'] ) {
			wp_send_json_error( $result );
		}

		// Get updated status.
		$status = self::get_migration_status_by_id( $migration_id );

		ob_start();
		self::render_status_badge( $status );
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'status'      => $status,
				'html'        => $html,
				'in_progress' => ! empty( $status['in_progress'] ),
			)
		);
	}

	/**
	 * AJAX handler for getting migration debug output.
	 */
	public function ajax_get_migration_debug() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'friends' ) ) );
		}

		if ( ! check_ajax_referer( 'friends-migration-debug', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'friends' ) ) );
		}

		$migration_id = isset( $_POST['migration_id'] ) ? sanitize_key( $_POST['migration_id'] ) : '';
		if ( empty( $migration_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No migration ID provided.', 'friends' ) ) );
		}

		$status = self::get_migration_status_by_id( $migration_id );

		ob_start();
		/**
		 * Hook to output debug content for a specific migration via AJAX.
		 *
		 * @param array $status The migration status.
		 */
		do_action( 'friends_migration_debug_' . $migration_id, $status );
		$html = ob_get_clean();

		if ( empty( $html ) ) {
			wp_send_json_error( array( 'message' => __( 'No debug handler registered for this migration.', 'friends' ) ) );
		}

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Render a migration status badge.
	 *
	 * @param array $status The migration status.
	 */
	public static function render_status_badge( $status ) {
		if ( ! empty( $status['in_progress'] ) ) {
			echo '<span class="status-badge status-in-progress">' . esc_html__( 'In Progress', 'friends' ) . '</span>';
			if ( isset( $status['percent'] ) ) {
				echo '<div class="progress-bar"><div class="progress-bar-fill" style="width: ' . esc_attr( $status['percent'] ) . '%;"></div></div>';
				echo '<small>' . esc_html( $status['processed'] ) . ' / ' . esc_html( $status['total'] ) . '</small>';
			}
			// Show next scheduled time if available.
			if ( ! empty( $status['next_scheduled'] ) ) {
				echo '<br><small>' . esc_html(
					sprintf(
						/* translators: %s is a relative time like "in 5 seconds" */
						__( 'Next batch: %s', 'friends' ),
						human_time_diff( time(), $status['next_scheduled'] )
					)
				) . '</small>';
			} else {
				echo '<br><small class="warning">' . esc_html__( 'No batch scheduled!', 'friends' ) . '</small>';
			}
		} elseif ( $status['completed'] ) {
			if ( ! empty( $status['no_tracking'] ) ) {
				echo '<span class="status-badge status-no-tracking">' . esc_html__( 'Completed', 'friends' ) . '</span>';
				echo '<br><small>' . esc_html__( '(no tracking)', 'friends' ) . '</small>';
			} else {
				echo '<span class="status-badge status-completed">' . esc_html__( 'Completed', 'friends' ) . '</span>';
			}
		} else {
			echo '<span class="status-badge status-pending">' . esc_html__( 'Pending', 'friends' ) . '</span>';
		}
	}

	/**
	 * Migrate user gravatar option to user_icon_url (version 0.20.1)
	 */
	public static function migrate_gravatar_to_user_icon_url() {
		$users = User_Query::all_associated_users();
		foreach ( $users->get_results() as $user ) {
			$gravatar = get_user_option( 'friends_gravatar', $user->ID );
			$user_icon_url = get_user_option( 'friends_user_icon_url', $user->ID );
			if ( $gravatar ) {
				if ( ! $user_icon_url ) {
					update_user_option( $user->ID, 'friends_user_icon_url', $gravatar );
				}
				delete_user_option( $user->ID, 'friends_gravatar' );
			}
		}
	}

	/**
	 * Migrate feed options from global to user options (version 2.6.0)
	 */
	public static function migrate_feed_options_to_user_options() {
		$users = User_Query::all_associated_users();
		foreach ( $users->get_results() as $user ) {
			if ( get_option( 'friends_feed_rules_' . $user->ID ) ) {
				$user->update_user_option( 'friends_feed_rules', get_option( 'friends_feed_rules_' . $user->ID ) );
			}
			if ( get_option( 'friends_feed_catch_all_' . $user->ID ) ) {
				$user->update_user_option( 'friends_feed_catch_all', get_option( 'friends_feed_catch_all_' . $user->ID ) );
			}
		}
	}

	/**
	 * Enable WordPress friendships flag if used (version 2.8.7)
	 */
	public static function enable_wp_friendships_if_used() {
		$users = User_Query::all_associated_users();
		foreach ( $users->get_results() as $user ) {
			if ( ! ( $user instanceof Subscription ) ) {
				// We have a user that is not a virtual user, so the friendship functionality had been used.
				update_option( 'friends_enable_wp_friendships', 1 );
				break;
			}
		}
	}

	/**
	 * Migrate external mentions user and upgrade cron schedule (version 2.9.4)
	 */
	public static function migrate_external_user_and_cron() {
		// Migrate to the new External user.
		$user = User::get_by_username( 'external-mentions' );
		if ( $user && $user instanceof Subscription ) {
			wp_update_term(
				$user->get_term_id(),
				Subscription::TAXONOMY,
				array(
					'slug' => 'external',
					'name' => _x( 'External', 'user name', 'friends' ),
				)
			);
			$user->update_user_option( 'display_name', _x( 'External', 'user name', 'friends' ) );
		}

		// Upgrade cron schedule.
		$next_scheduled = wp_next_scheduled( 'cron_friends_refresh_feeds' );
		if ( $next_scheduled ) {
			$event = wp_get_scheduled_event( 'cron_friends_refresh_feeds' );
			if ( $event && 'fifteen-minutes' !== $event->schedule ) {
				wp_unschedule_event( $next_scheduled, 'cron_friends_refresh_feeds' );
				$next_scheduled = false;
			}
		}
		if ( ! $next_scheduled ) {
			wp_schedule_event( time(), 'fifteen-minutes', 'cron_friends_refresh_feeds' );
		}
	}

	/**
	 * Migrate frontend default view option to user option (version 3.1.8)
	 */
	public static function migrate_frontend_default_view_to_user_option() {
		$users = User_Query::all_admin_users();
		foreach ( $users->get_results() as $user ) {
			$default_view = get_option( 'friends_frontend_default_view' );
			if ( $default_view ) {
				$user->update_user_option( 'friends_frontend_default_view', $default_view );
			}
		}
	}

	/**
	 * Migrate post_tag taxonomy to friend_tag taxonomy for Friends CPT posts (version 4.0.0)
	 * Initiates batched migration for large datasets.
	 */
	public static function migrate_post_tags_to_friend_tags() {
		// Check if migration is already in progress.
		if ( get_option( 'friends_tag_migration_in_progress' ) ) {
			return;
		}

		// Check if migration has already been completed.
		if ( get_option( 'friends_tag_migration_completed' ) ) {
			return;
		}

		// Count total posts to migrate.
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_posts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE p.post_type = %s AND tt.taxonomy = 'post_tag'",
				Friends::CPT
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $total_posts ) {
			return;
		}

		// Set migration progress tracking (not autoloaded).
		update_option( 'friends_tag_migration_in_progress', true, false );
		update_option( 'friends_tag_migration_total', $total_posts, false );
		update_option( 'friends_tag_migration_processed', 0, false );
		update_option( 'friends_tag_migration_offset', 0, false );

		// Schedule the first batch.
		wp_schedule_single_event( time(), 'friends_migrate_post_tags_batch' );
	}

	/**
	 * Process a single batch of post tag migration.
	 */
	public static function migrate_post_tags_batch() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		$batch_size = apply_filters( 'friends_tag_migration_batch_size', 100 );
		$offset = (int) get_option( 'friends_tag_migration_offset', 0 );

		// Get a batch of Friends CPT posts that have post_tag terms.
		$friends_posts_with_tags = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, tr.term_taxonomy_id, t.term_id, t.name, t.slug
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE p.post_type = %s AND tt.taxonomy = 'post_tag'
				ORDER BY p.ID, t.term_id
				LIMIT %d OFFSET %d",
				Friends::CPT,
				$batch_size,
				$offset
			)
		);

		if ( empty( $friends_posts_with_tags ) ) {
			// Migration complete - run cleanup.
			self::finalize_post_tag_migration();
			return;
		}

		// Group posts by ID to handle multiple tags per post.
		$posts_by_id = array();
		foreach ( $friends_posts_with_tags as $tagged_post ) {
			if ( ! isset( $posts_by_id[ $tagged_post->ID ] ) ) {
				$posts_by_id[ $tagged_post->ID ] = array();
			}
			$posts_by_id[ $tagged_post->ID ][] = $tagged_post;
		}

		// Process each post with all its tags.
		$term_taxonomy_ids = array();
		$term_ids_to_check = array();

		foreach ( $posts_by_id as $post_id => $post_tags ) {
			// Collect all tag names for this post.
			$tag_names = array();
			foreach ( $post_tags as $tagged_post ) {
				$tag_names[] = $tagged_post->name;
				$term_taxonomy_ids[] = $tagged_post->term_taxonomy_id;
				$term_ids_to_check[] = $tagged_post->term_id;
			}

			// Add all friend_tags to this post at once.
			wp_set_post_terms( $post_id, $tag_names, Friends::TAG_TAXONOMY, true );
		}

		// Bulk delete post_tag relationships for this batch of Friends posts.
		if ( ! empty( $term_taxonomy_ids ) ) {
			$processed_post_ids = array_keys( $posts_by_id );
			$post_placeholders = implode( ',', array_fill( 0, count( $processed_post_ids ), '%d' ) );
			$term_placeholders = implode( ',', array_fill( 0, count( $term_taxonomy_ids ), '%d' ) );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->term_relationships}
					WHERE object_id IN ($post_placeholders)
					AND term_taxonomy_id IN ($term_placeholders)",
					array_merge( $processed_post_ids, $term_taxonomy_ids )
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		// Update progress.
		$processed = (int) get_option( 'friends_tag_migration_processed', 0 );
		$processed += count( $posts_by_id );
		update_option( 'friends_tag_migration_processed', $processed, false );
		update_option( 'friends_tag_migration_offset', $offset + $batch_size, false );

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Schedule next batch.
		wp_schedule_single_event( time() + 1, 'friends_migrate_post_tags_batch' );
	}

	/**
	 * Finalize post tag migration by cleaning up orphaned terms.
	 */
	public static function finalize_post_tag_migration() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Get all post_tag terms that were potentially used by Friends posts.
		$all_post_tag_terms = $wpdb->get_results(
			"SELECT DISTINCT t.term_id
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			WHERE tt.taxonomy = 'post_tag'"
		);

		// Clean up orphaned post_tag terms.
		foreach ( $all_post_tag_terms as $term_data ) {
			// Calculate count excluding revisions, ap_actor, post_collection, and Friends CPT.
			$real_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE tt.term_id = %d
					AND tt.taxonomy = 'post_tag'
					AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')
					AND p.post_type NOT IN ('revision', 'ap_actor', 'post_collection', %s)",
					$term_data->term_id,
					Friends::CPT
				)
			);

			if ( 0 === (int) $real_count ) {
				wp_delete_term( $term_data->term_id, 'post_tag' );
			}
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Clear migration progress flags.
		delete_option( 'friends_tag_migration_in_progress' );
		delete_option( 'friends_tag_migration_total' );
		delete_option( 'friends_tag_migration_processed' );
		delete_option( 'friends_tag_migration_offset' );

		// Mark migration as completed (not autoloaded).
		update_option( 'friends_tag_migration_completed', time(), false );

		// Log completion.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Friends: Post tag migration completed successfully.' );
	}

	/**
	 * Get migration status information.
	 *
	 * @return array Array with migration status details.
	 */
	public static function get_migration_status() {
		$status = array(
			'completed'      => (bool) get_option( 'friends_tag_migration_completed' ),
			'in_progress'    => (bool) get_option( 'friends_tag_migration_in_progress' ),
			'total'          => (int) get_option( 'friends_tag_migration_total', 0 ),
			'processed'      => (int) get_option( 'friends_tag_migration_processed', 0 ),
			'completed_time' => get_option( 'friends_tag_migration_completed' ),
		);

		if ( $status['total'] > 0 && $status['processed'] > 0 ) {
			$status['progress_percent'] = min( 100, round( ( $status['processed'] / $status['total'] ) * 100, 1 ) );
		} else {
			$status['progress_percent'] = 0;
		}

		return $status;
	}

	/**
	 * Reset migration status to allow re-running migration.
	 * Useful for manual triggers or development.
	 */
	public static function reset_migration_status() {
		delete_option( 'friends_tag_migration_completed' );
		delete_option( 'friends_tag_migration_in_progress' );
		delete_option( 'friends_tag_migration_total' );
		delete_option( 'friends_tag_migration_processed' );
		delete_option( 'friends_tag_migration_offset' );

		// Clear any scheduled migration events.
		wp_clear_scheduled_hook( 'friends_migrate_post_tags_batch' );
	}

	/**
	 * Manually trigger post tag migration.
	 * Resets status first to allow re-running.
	 */
	public static function trigger_migration_manually() {
		self::reset_migration_status();
		self::migrate_post_tags_to_friend_tags();
	}

	/**
	 * Clean up orphaned post_tag terms that exist in friend_tag taxonomy.
	 * These are post_tag terms that were originally created by Friends posts
	 * but are now orphaned after migration to friend_tag taxonomy.
	 *
	 * @return array Cleanup results with counts.
	 */
	public static function cleanup_orphaned_post_tags() {
		// Use the comprehensive cleanup approach instead of Friends-focused.
		return self::recalculate_all_post_tag_counts();
	}

	/**
	 * Recalculate post_tag counts and cleanup orphaned tags.
	 * This recalculates counts for ALL post_tag terms excluding Friends CPT posts,
	 * and removes any with zero count after recalculation.
	 *
	 * @return array Cleanup results with counts.
	 */
	public static function recalculate_all_post_tag_counts() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Get ALL post_tag terms (not just those with friend_tag equivalents)
		$all_post_tags = $wpdb->get_results(
			"SELECT t.term_id, t.name, t.slug, tt.count
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			WHERE tt.taxonomy = 'post_tag'"
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$cleanup_results = array(
			'checked'        => 0,
			'recalculated'   => 0,
			'deleted'        => 0,
			'tags_processed' => array(),
		);

		foreach ( $all_post_tags as $tag_data ) {
			++$cleanup_results['checked'];

			$old_count = $tag_data->count;

			// Calculate the REAL count excluding revisions, ap_actor, post_collection, and Friends CPT.
			global $wpdb;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			$real_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE tt.term_id = %d
					AND tt.taxonomy = 'post_tag'
					AND p.post_status IN ('publish', 'private', 'draft', 'pending', 'future')
					AND p.post_type NOT IN ('revision', 'ap_actor', 'post_collection', %s)",
					$tag_data->term_id,
					Friends::CPT
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

			// Update the term count in the database to reflect reality (excluding Friends posts and other irrelevant types)
			$update_result = $wpdb->update(
				$wpdb->term_taxonomy,
				array( 'count' => $real_count ),
				array(
					'term_id'  => $tag_data->term_id,
					'taxonomy' => 'post_tag',
				)
			);
			// Clear term cache so get_term() returns the updated count.
			clean_term_cache( $tag_data->term_id, 'post_tag' );

			++$cleanup_results['recalculated'];
			$new_count = (int) $real_count;

			// Log if count changed or if we're about to delete.
			if ( $old_count !== $new_count || 0 === $new_count ) {
				$cleanup_results['tags_processed'][] = array(
					'name'        => $tag_data->name,
					'slug'        => $tag_data->slug,
					'old_count'   => $old_count,
					'new_count'   => $new_count,
					'action'      => 0 === $new_count ? 'deleted' : 'count_updated',
					'posts_using' => $new_count,
				);
			}

			// If count is 0 after recalculation, delete the orphaned post_tag.
			if ( 0 === $new_count ) {
				$deleted = wp_delete_term( $tag_data->term_id, 'post_tag' );
				if ( ! is_wp_error( $deleted ) && $deleted ) {
					++$cleanup_results['deleted'];
				}
			}
		}

		return $cleanup_results;
	}

	/**
	 * Backfill mention tags from Mastodon HTML content (version 4.1.0)
	 */
	public static function backfill_mention_tags_from_mastodon_html() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Build lookup table of local user ActivityPub URLs before processing posts.
		$local_user_urls = self::build_local_user_activitypub_lookup();

		if ( empty( $local_user_urls ) ) {
			return; // No local users to check for mentions.
		}

		// Get all Friends posts that might contain Mastodon mentions in HTML.
		$posts_with_html = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status IN ('publish', 'private')
				AND post_content LIKE %s",
				Friends::CPT,
				'%u-url mention%'
			)
		);

		if ( empty( $posts_with_html ) ) {
			return;
		}

		$processed_count = 0;
		$mention_tags_added = 0;

		foreach ( $posts_with_html as $post ) {
			$mention_tags = self::extract_mention_tags_from_html( $post->post_content, $local_user_urls );

			if ( ! empty( $mention_tags ) ) {
				wp_set_post_terms( $post->ID, $mention_tags, Friends::TAG_TAXONOMY, true );
				$mention_tags_added += count( $mention_tags );
			}

			++$processed_count;
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $processed_count > 0 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Friends Migration: Processed %d posts, added %d mention tags', $processed_count, $mention_tags_added ) );
		}
	}

	/**
	 * Build a lookup table of local user ActivityPub URLs to usernames
	 *
	 * @return array Array mapping ActivityPub URLs to usernames
	 */
	private static function build_local_user_activitypub_lookup() {
		$lookup = array();

		// Check if ActivityPub plugin is available.
		if ( ! class_exists( '\Activitypub\Collection\Actors' ) ) {
			return $lookup;
		}

		$users = get_users();
		foreach ( $users as $user ) {
			try {
				// Use ActivityPub plugin to get the proper actor URL.
				$actor = \Activitypub\Collection\Actors::get_by_id( $user->ID );
				if ( ! is_wp_error( $actor ) && $actor ) {
					$activitypub_url = $actor->get_id();
					if ( $activitypub_url ) {
						$lookup[ $activitypub_url ] = $user->user_login;
					}
				}
			} catch ( Exception $e ) {
				// Skip this user if there's an error getting their ActivityPub ID.
				continue;
			}
		}

		return $lookup;
	}

	/**
	 * Extract mention tags from Mastodon HTML content
	 *
	 * @param string $html_content The HTML content to parse.
	 * @param array  $local_user_urls Lookup table of local user URLs to usernames.
	 * @return array Array of mention tag names
	 */
	private static function extract_mention_tags_from_html( $html_content, $local_user_urls ) {
		$mention_tags = array();

		// Parse HTML to find mention links:
		// Pattern: <a href="ACTIVITYPUB_URL" class="u-url mention">@<span>USERNAME</span></a>.
		$pattern = '/<a\s+[^>]*href=["\'](https?:\/\/[^"\']+)["\'][^>]*class=["\'][^"\']*u-url mention[^"\']*["\'][^>]*>@<span>([^<]+)<\/span><\/a>/i';

		if ( preg_match_all( $pattern, $html_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$activitypub_url = $match[1];

				// Look up the URL in our pre-built lookup table.
				if ( isset( $local_user_urls[ $activitypub_url ] ) ) {
					$username = $local_user_urls[ $activitypub_url ];
					$mention_tag = 'mention-' . $username;
					$mention_tags[] = $mention_tag;
				}
			}
		}

		return array_unique( $mention_tags );
	}

	/**
	 * Import existing followings from the ActivityPub plugin (version 4.1.0)
	 *
	 * This method runs on upgrade and imports any existing follows from
	 * the ActivityPub plugin that don't already exist in Friends.
	 */
	public static function import_activitypub_followings() {
		// Check if the Following class is available (ActivityPub plugin 7.x+).
		if ( ! class_exists( '\Activitypub\Collection\Following' ) ) {
			return;
		}

		// Check if already imported.
		if ( get_option( 'friends_activitypub_followings_imported' ) ) {
			return;
		}

		// Get the ActivityPub user ID.
		$user_id = Feed_Parser_ActivityPub::get_activitypub_actor_id( Friends::get_main_friend_user_id() );

		// Get all followings from ActivityPub.
		$actors = \Activitypub\Collection\Following::get_all( $user_id );
		if ( is_wp_error( $actors ) || ! is_array( $actors ) ) {
			update_option( 'friends_activitypub_followings_imported', true, false );
			return;
		}

		$imported = 0;
		$feed_parser = new Feed_Parser_ActivityPub( Friends::get_instance()->feed );

		foreach ( $actors as $actor ) {
			if ( ! $actor instanceof \WP_Post ) {
				continue;
			}

			// Get URL from the post's guid field (canonical actor URL).
			$actor_url = $actor->guid;
			if ( empty( $actor_url ) || ! is_string( $actor_url ) ) {
				continue;
			}

			// Check if a Friends subscription already exists for this actor.
			$existing_feed = User_Feed::get_by_url( $actor_url );
			if ( $existing_feed instanceof User_Feed ) {
				continue;
			}

			// Create a new Friends subscription for this actor.
			$result = $feed_parser->create_friend_subscription_from_actor( $actor_url );
			if ( ! is_wp_error( $result ) ) {
				++$imported;
			}
		}

		update_option( 'friends_activitypub_followings_imported', true, false );
		update_option( 'friends_activitypub_followings_imported_count', $imported, false );
	}

	/**
	 * Migrate ActivityPub attributedTo from URL format to ap_actor post ID format (version 4.1.0)
	 * Initiates batched migration for large datasets.
	 */
	public static function migrate_activitypub_attributed_to() {
		// Check if the Remote_Actors class is available (ActivityPub plugin 7.x+).
		if ( ! class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			// Cannot migrate without the Remote_Actors class.
			return;
		}

		// Check if migration is already in progress.
		if ( get_option( 'friends_ap_attributed_to_migration_in_progress' ) ) {
			return;
		}

		// Check if migration has already been completed.
		if ( get_option( 'friends_ap_attributed_to_migration_completed' ) ) {
			return;
		}

		// Count total posts to migrate (posts with attributedTo.id but not ap_actor_id).
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_posts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->postmeta}
				WHERE meta_key = %s
				AND meta_value LIKE %s
				AND meta_value NOT LIKE %s",
				Feed_Parser_ActivityPub::SLUG,
				'%"attributedTo"%"id"%',
				'%"ap_actor_id"%'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $total_posts ) {
			// No posts to migrate.
			update_option( 'friends_ap_attributed_to_migration_completed', true, false );
			return;
		}

		// Set migration progress tracking (not autoloaded).
		update_option( 'friends_ap_attributed_to_migration_in_progress', true, false );
		update_option( 'friends_ap_attributed_to_migration_total', $total_posts, false );
		update_option( 'friends_ap_attributed_to_migration_processed', 0, false );
		update_option( 'friends_ap_attributed_to_migration_offset', 0, false );

		// Schedule the first batch.
		wp_schedule_single_event( time(), 'friends_migrate_ap_attributed_to_batch' );
	}

	/**
	 * Process a single batch of attributedTo migration.
	 */
	public static function migrate_activitypub_attributed_to_batch() {
		// Check if the Remote_Actors class is available.
		if ( ! class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			self::finalize_ap_attributed_to_migration();
			return;
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		$batch_size = apply_filters( 'friends_ap_attributed_to_migration_batch_size', 50 );
		$offset = (int) get_option( 'friends_ap_attributed_to_migration_offset', 0 );

		// Get a batch of posts that need migration.
		$posts_to_migrate = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value
				FROM {$wpdb->postmeta}
				WHERE meta_key = %s
				AND meta_value LIKE %s
				AND meta_value NOT LIKE %s
				ORDER BY post_id
				LIMIT %d OFFSET %d",
				Feed_Parser_ActivityPub::SLUG,
				'%"attributedTo"%"id"%',
				'%"ap_actor_id"%',
				$batch_size,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $posts_to_migrate ) ) {
			// Migration complete.
			self::finalize_ap_attributed_to_migration();
			return;
		}

		$processed = (int) get_option( 'friends_ap_attributed_to_migration_processed', 0 );

		foreach ( $posts_to_migrate as $row ) {
			$meta = maybe_unserialize( $row->meta_value );

			if ( ! is_array( $meta ) || ! isset( $meta['attributedTo']['id'] ) ) {
				++$processed;
				continue;
			}

			$actor_url = $meta['attributedTo']['id'];

			// Try to get or create the ap_actor post ID.
			$ap_actor_id = Feed_Parser_ActivityPub::get_or_create_remote_actor_id( $actor_url );

			if ( $ap_actor_id ) {
				// Update to new format.
				$meta['attributedTo']['ap_actor_id'] = $ap_actor_id;
				unset( $meta['attributedTo']['id'] );
				update_post_meta( $row->post_id, Feed_Parser_ActivityPub::SLUG, $meta );
			}

			++$processed;
		}

		// Update progress.
		update_option( 'friends_ap_attributed_to_migration_processed', $processed, false );
		update_option( 'friends_ap_attributed_to_migration_offset', $offset + $batch_size, false );

		// Schedule next batch.
		wp_schedule_single_event( time() + 1, 'friends_migrate_ap_attributed_to_batch' );
	}

	/**
	 * Finalize the attributedTo migration.
	 */
	private static function finalize_ap_attributed_to_migration() {
		delete_option( 'friends_ap_attributed_to_migration_in_progress' );
		delete_option( 'friends_ap_attributed_to_migration_offset' );
		update_option( 'friends_ap_attributed_to_migration_completed', true, false );
	}

	/**
	 * Link existing ActivityPub feeds to their ap_actor posts (version 4.1.0)
	 *
	 * This migration finds all User_Feed entries with parser='activitypub'
	 * and links them to their corresponding ap_actor posts by setting
	 * the ap-actor-id term meta.
	 */
	public static function link_activitypub_feeds_to_actors() {
		// Check if the Remote_Actors class is available (ActivityPub plugin 7.x+).
		if ( ! class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			return;
		}

		// Check if migration is already in progress.
		if ( get_option( 'friends_ap_feeds_link_migration_in_progress' ) ) {
			return;
		}

		// Check if migration has already been completed.
		if ( get_option( 'friends_ap_feeds_linked_to_actors' ) ) {
			return;
		}

		// Get all ActivityPub feeds to count them.
		$activitypub_feeds = User_Feed::get_by_parser( Feed_Parser_ActivityPub::SLUG );

		if ( empty( $activitypub_feeds ) ) {
			update_option( 'friends_ap_feeds_linked_to_actors', true, false );
			return;
		}

		$total = count( $activitypub_feeds );

		// Set migration progress tracking (not autoloaded).
		update_option( 'friends_ap_feeds_link_migration_in_progress', true, false );
		update_option( 'friends_ap_feeds_link_migration_total', $total, false );
		update_option( 'friends_ap_feeds_link_migration_processed', 0, false );
		update_option( 'friends_ap_feeds_link_migration_linked', 0, false );
		update_option( 'friends_ap_feeds_link_migration_skipped', 0, false );
		update_option( 'friends_ap_feeds_link_migration_failed', 0, false );
		update_option( 'friends_ap_feeds_link_migration_failed_urls', array(), false );

		// Clear any previous run's stats.
		delete_option( 'friends_ap_feeds_failed_count' );
		delete_option( 'friends_ap_feeds_failed_urls' );

		// Schedule the first batch.
		wp_schedule_single_event( time(), 'friends_link_ap_feeds_batch' );
	}

	/**
	 * Reduce ActivityPub HTTP timeout during migration.
	 *
	 * @return int Timeout in seconds.
	 */
	public static function reduce_activitypub_timeout() {
		return 15; // 15 seconds instead of default 100.
	}

	/**
	 * Process a single batch of linking ActivityPub feeds to actors.
	 */
	public static function link_activitypub_feeds_to_actors_batch() {
		// Check if the Remote_Actors class is available.
		if ( ! class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			self::finalize_ap_feeds_link_migration();
			return;
		}

		// Reduce HTTP timeout to avoid PHP max_execution_time issues.
		add_filter( 'activitypub_remote_get_timeout', array( __CLASS__, 'reduce_activitypub_timeout' ) );

		// Process one at a time since network requests can timeout.
		$batch_size = apply_filters( 'friends_ap_feeds_link_batch_size', 1 );

		// Get all ActivityPub feeds.
		$activitypub_feeds = User_Feed::get_by_parser( Feed_Parser_ActivityPub::SLUG );

		if ( empty( $activitypub_feeds ) ) {
			self::finalize_ap_feeds_link_migration();
			return;
		}

		$processed = (int) get_option( 'friends_ap_feeds_link_migration_processed', 0 );
		$linked = (int) get_option( 'friends_ap_feeds_link_migration_linked', 0 );
		$skipped = (int) get_option( 'friends_ap_feeds_link_migration_skipped', 0 );
		$failed = (int) get_option( 'friends_ap_feeds_link_migration_failed', 0 );
		$failed_urls = get_option( 'friends_ap_feeds_link_migration_failed_urls', array() );

		// Check for crash recovery - if we were processing a URL and crashed, skip it.
		$current_url = get_option( 'friends_ap_feeds_link_migration_current_url', '' );
		if ( $current_url ) {
			// We crashed while processing this URL - mark it as failed and move on.
			++$failed;
			$failed_urls[] = array(
				'url'    => $current_url,
				'time'   => time(),
				'feed'   => 'Unknown (crashed)',
				'reason' => 'timeout_or_crash',
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Friends Migration: Recovered from crash on %s - skipping', $current_url ) );
			delete_option( 'friends_ap_feeds_link_migration_current_url' );

			// Update failed counts.
			update_option( 'friends_ap_feeds_link_migration_failed', $failed, false );
			update_option( 'friends_ap_feeds_link_migration_failed_urls', $failed_urls, false );
		}

		// Get the batch to process.
		$batch = array_slice( $activitypub_feeds, $processed, $batch_size );

		if ( empty( $batch ) ) {
			self::finalize_ap_feeds_link_migration();
			return;
		}

		foreach ( $batch as $feed ) {
			++$processed;

			// Skip if already linked.
			if ( $feed->get_ap_actor_id() ) {
				++$skipped;
				continue;
			}

			// Get the feed URL (stored as term name).
			$actor_url = $feed->get_url();
			if ( empty( $actor_url ) ) {
				continue;
			}

			// Check if this URL was already marked as failed (from a previous crash).
			$already_failed = false;
			foreach ( $failed_urls as $failed_item ) {
				if ( $failed_item['url'] === $actor_url ) {
					$already_failed = true;
					break;
				}
			}
			if ( $already_failed ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'Friends Migration: Skipping previously failed URL: %s', $actor_url ) );
				continue;
			}

			// Log which URL we're about to process.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Friends Migration: Processing feed %d/%d: %s', $processed, count( $activitypub_feeds ), $actor_url ) );

			// First try local lookup to avoid network request if possible.
			$ap_actor_id = Feed_Parser_ActivityPub::get_remote_actor_id_local( $actor_url );

			if ( $ap_actor_id ) {
				$feed->set_ap_actor_id( $ap_actor_id );
				if ( $feed->get_active() ) {
					self::ensure_activitypub_following( $ap_actor_id );
				}
				++$linked;
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'Friends Migration: Linked %s to ap_actor %d (local)', $actor_url, $ap_actor_id ) );
			} else {
				// Not found locally - store current URL for crash recovery before network request.
				update_option( 'friends_ap_feeds_link_migration_current_url', $actor_url, false );

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'Friends Migration: About to call fetch_remote_actor_id for %s', $actor_url ) );

				// Try to fetch from network (explicit network request).
				$ap_actor_id = Feed_Parser_ActivityPub::fetch_remote_actor_id( $actor_url );

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'Friends Migration: Returned from fetch_remote_actor_id: %s', $ap_actor_id ? $ap_actor_id : 'null' ) );

				// Clear crash recovery marker.
				delete_option( 'friends_ap_feeds_link_migration_current_url' );

				if ( $ap_actor_id ) {
					$feed->set_ap_actor_id( $ap_actor_id );
					if ( $feed->get_active() ) {
						self::ensure_activitypub_following( $ap_actor_id );
					}
					++$linked;
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( 'Friends Migration: Linked %s to ap_actor %d (fetched)', $actor_url, $ap_actor_id ) );
				} else {
					++$failed;
					$failed_urls[] = array(
						'url'    => $actor_url,
						'time'   => time(),
						'feed'   => $feed->get_friend_user()->display_name ?? 'Unknown',
						'reason' => 'could_not_resolve',
					);
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( 'Friends Migration: Failed to link %s - could not resolve actor', $actor_url ) );
				}
			}
		}

		// Update progress.
		update_option( 'friends_ap_feeds_link_migration_processed', $processed, false );
		update_option( 'friends_ap_feeds_link_migration_linked', $linked, false );
		update_option( 'friends_ap_feeds_link_migration_skipped', $skipped, false );
		update_option( 'friends_ap_feeds_link_migration_failed', $failed, false );
		update_option( 'friends_ap_feeds_link_migration_failed_urls', $failed_urls, false );

		// Check if we're done.
		$total = (int) get_option( 'friends_ap_feeds_link_migration_total', 0 );
		if ( $processed >= $total ) {
			self::finalize_ap_feeds_link_migration();
			return;
		}

		// Schedule next batch.
		wp_schedule_single_event( time() + 1, 'friends_link_ap_feeds_batch' );
	}

	/**
	 * Ensure the main Friends user is following the given ap_actor in the ActivityPub plugin.
	 *
	 * @param int $ap_actor_id The ap_actor post ID.
	 */
	private static function ensure_activitypub_following( $ap_actor_id ) {
		if ( ! class_exists( '\Activitypub\Collection\Following' ) ) {
			return;
		}

		$user_id = Feed_Parser_ActivityPub::get_activitypub_actor_id( null );

		// Check if already following.
		$status = \Activitypub\Collection\Following::check_status( $user_id, $ap_actor_id );
		if ( $status ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Friends Migration: Already following ap_actor %d (status: %s)', $ap_actor_id, $status ) );
			return;
		}

		// Trigger a follow.
		$result = \Activitypub\Collection\Following::follow( $ap_actor_id, $user_id );
		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Friends Migration: Failed to follow ap_actor %d: %s', $ap_actor_id, $result->get_error_message() ) );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Friends Migration: Sent follow request for ap_actor %d', $ap_actor_id ) );
		}
	}

	/**
	 * Finalize the AP feeds link migration.
	 */
	private static function finalize_ap_feeds_link_migration() {
		$linked = (int) get_option( 'friends_ap_feeds_link_migration_linked', 0 );
		$skipped = (int) get_option( 'friends_ap_feeds_link_migration_skipped', 0 );
		$failed = (int) get_option( 'friends_ap_feeds_link_migration_failed', 0 );
		$failed_urls = get_option( 'friends_ap_feeds_link_migration_failed_urls', array() );

		// Clean up progress options.
		delete_option( 'friends_ap_feeds_link_migration_in_progress' );
		delete_option( 'friends_ap_feeds_link_migration_total' );
		delete_option( 'friends_ap_feeds_link_migration_processed' );

		// Keep the counts and failed URLs for reference.
		update_option( 'friends_ap_feeds_linked_to_actors', true, false );
		update_option( 'friends_ap_feeds_linked_count', $linked, false );
		update_option( 'friends_ap_feeds_skipped_count', $skipped, false );
		update_option( 'friends_ap_feeds_failed_count', $failed, false );
		update_option( 'friends_ap_feeds_failed_urls', $failed_urls, false );

		// Clean up batch counts and crash recovery.
		delete_option( 'friends_ap_feeds_link_migration_linked' );
		delete_option( 'friends_ap_feeds_link_migration_skipped' );
		delete_option( 'friends_ap_feeds_link_migration_failed' );
		delete_option( 'friends_ap_feeds_link_migration_failed_urls' );
		delete_option( 'friends_ap_feeds_link_migration_current_url' );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( 'Friends Migration: Completed - Linked %d, Already linked %d, Failed %d', $linked, $skipped, $failed ) );

		if ( $failed > 0 && ! empty( $failed_urls ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Friends Migration: Failed URLs: ' . wp_json_encode( array_column( $failed_urls, 'url' ) ) );
		}
	}

	/**
	 * Debug output hook for the link_activitypub_feeds_to_actors migration.
	 *
	 * @param array $status The migration status (unused but required by hook signature).
	 */
	public function debug_link_activitypub_feeds_to_actors( $status ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$failed_urls = get_option( 'friends_ap_feeds_failed_urls', array() );
		$failed_count = get_option( 'friends_ap_feeds_failed_count', 0 );
		$linked_count = get_option( 'friends_ap_feeds_linked_count', 0 );
		$skipped_count = get_option( 'friends_ap_feeds_skipped_count', 0 );

		// Also check in-progress failed URLs.
		$in_progress_failed = get_option( 'friends_ap_feeds_link_migration_failed_urls', array() );
		if ( ! empty( $in_progress_failed ) ) {
			$failed_urls = array_merge( $failed_urls, $in_progress_failed );
		}

		echo '<div class="migration-debug-content" style="padding: 15px; background: #f9f9f9;">';

		echo '<h4>' . esc_html__( 'Migration Statistics', 'friends' ) . '</h4>';
		echo '<ul>';
		echo '<li><strong>' . esc_html__( 'Linked:', 'friends' ) . '</strong> ' . esc_html( $linked_count ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Already linked (skipped):', 'friends' ) . '</strong> ' . esc_html( $skipped_count ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Failed:', 'friends' ) . '</strong> ' . esc_html( $failed_count ) . '</li>';
		echo '</ul>';

		// Show current URL being processed (if migration is in progress).
		$current_url = get_option( 'friends_ap_feeds_link_migration_current_url', '' );
		if ( $current_url ) {
			echo '<div class="notice notice-warning" style="margin: 10px 0; padding: 10px;">';
			echo '<strong>' . esc_html__( 'Currently processing:', 'friends' ) . '</strong> ';
			echo '<code>' . esc_html( $current_url ) . '</code>';
			echo '<br><small>' . esc_html__( 'If this URL appears stuck, it may be causing a timeout.', 'friends' ) . '</small>';
			echo '</div>';
		}

		if ( ! empty( $failed_urls ) ) {
			echo '<h4>' . esc_html__( 'Failed URLs', 'friends' ) . '</h4>';
			$nonce = wp_create_nonce( 'friends_clear_failed_url' );
			echo '<script>
			function clearFailedUrl(url) {
				jQuery.post(ajaxurl, {action: "friends_clear_failed_url", url: url, _wpnonce: "' . esc_js( $nonce ) . '"}, function(r) { location.reload(); });
			}
			function clearAllFailedUrls() {
				jQuery.post(ajaxurl, {action: "friends_clear_failed_url", url: "__all__", _wpnonce: "' . esc_js( $nonce ) . '"}, function(r) { location.reload(); });
			}
			</script>';
			echo '<p><button type="button" class="button" onclick="clearAllFailedUrls()">' . esc_html__( 'Clear All Failed URLs', 'friends' ) . '</button></p>';
			echo '<table class="widefat" style="margin-top: 10px;">';
			echo '<thead><tr><th>' . esc_html__( 'Feed/User', 'friends' ) . '</th><th>' . esc_html__( 'URL', 'friends' ) . '</th><th>' . esc_html__( 'Reason', 'friends' ) . '</th><th>' . esc_html__( 'Time', 'friends' ) . '</th><th>' . esc_html__( 'Actions', 'friends' ) . '</th></tr></thead>';
			echo '<tbody>';
			foreach ( $failed_urls as $item ) {
				$reason = $item['reason'] ?? 'unknown';
				$reason_label = 'timeout_or_crash' === $reason ? __( 'Timeout/Crash', 'friends' ) : __( 'Could not resolve', 'friends' );
				echo '<tr>';
				echo '<td>' . esc_html( $item['feed'] ?? 'Unknown' ) . '</td>';
				echo '<td><code style="word-break: break-all;">' . esc_html( $item['url'] ) . '</code></td>';
				echo '<td>' . esc_html( $reason_label ) . '</td>';
				echo '<td>' . esc_html( isset( $item['time'] ) ? human_time_diff( $item['time'] ) . ' ago' : 'N/A' ) . '</td>';
				echo '<td><button type="button" class="button button-small" onclick="clearFailedUrl(\'' . esc_js( $item['url'] ) . '\')">' . esc_html__( 'Retry', 'friends' ) . '</button></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p><em>' . esc_html__( 'No failed URLs recorded.', 'friends' ) . '</em></p>';
		}

		// Show feeds that still need linking.
		$activitypub_feeds = User_Feed::get_by_parser( Feed_Parser_ActivityPub::SLUG );
		$unlinked = array();
		foreach ( $activitypub_feeds as $feed ) {
			if ( ! $feed->get_ap_actor_id() ) {
				$unlinked[] = array(
					'url'  => $feed->get_url(),
					'user' => $feed->get_friend_user()->display_name ?? 'Unknown',
				);
			}
		}

		if ( ! empty( $unlinked ) ) {
			// translators: %d is the number of unlinked feeds.
			echo '<h4>' . sprintf( esc_html__( 'Feeds Still Unlinked (%d)', 'friends' ), count( $unlinked ) ) . '</h4>';
			echo '<table class="widefat" style="margin-top: 10px;">';
			echo '<thead><tr><th>' . esc_html__( 'User', 'friends' ) . '</th><th>' . esc_html__( 'Feed URL', 'friends' ) . '</th></tr></thead>';
			echo '<tbody>';
			foreach ( array_slice( $unlinked, 0, 20 ) as $item ) {
				echo '<tr>';
				echo '<td>' . esc_html( $item['user'] ) . '</td>';
				echo '<td><code style="word-break: break-all;">' . esc_html( $item['url'] ) . '</code></td>';
				echo '</tr>';
			}
			if ( count( $unlinked ) > 20 ) {
				// translators: %d is the number of additional items not shown.
				echo '<tr><td colspan="2"><em>' . sprintf( esc_html__( '... and %d more', 'friends' ), count( $unlinked ) - 20 ) . '</em></td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '</div>';
	}
}
