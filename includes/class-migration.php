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
			wp_update_term_count( $term_data->term_id, 'post_tag' );
			$term = get_term( $term_data->term_id, 'post_tag' );

			if ( $term && ! is_wp_error( $term ) && 0 === $term->count ) {
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
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Find post_tag terms that also exist in friend_tag taxonomy by slug.
		$orphaned_tags = $wpdb->get_results(
			"SELECT pt.term_id as post_tag_id, pt.name, pt.slug, ptt.count as post_tag_count,
					ft.term_id as friend_tag_id, ftt.count as friend_tag_count
			FROM {$wpdb->terms} pt
			INNER JOIN {$wpdb->term_taxonomy} ptt ON pt.term_id = ptt.term_id AND ptt.taxonomy = 'post_tag'
			INNER JOIN {$wpdb->terms} ft ON pt.slug = ft.slug
			INNER JOIN {$wpdb->term_taxonomy} ftt ON ft.term_id = ftt.term_id AND ftt.taxonomy = 'friend_tag'
			WHERE ptt.count = 0"
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$cleanup_results = array(
			'checked'        => 0,
			'recalculated'   => 0,
			'deleted'        => 0,
			'tags_processed' => array(),
		);

		foreach ( $orphaned_tags as $tag_data ) {
			++$cleanup_results['checked'];

			// Recalculate the post_tag count to be sure it's accurate.
			wp_update_term_count( $tag_data->post_tag_id, 'post_tag' );

			// Get the updated term to check the real count.
			$updated_term = get_term( $tag_data->post_tag_id, 'post_tag' );

			if ( $updated_term && ! is_wp_error( $updated_term ) ) {
				++$cleanup_results['recalculated'];

				// If count is still 0 after recalculation, delete the orphaned post_tag.
				if ( 0 === $updated_term->count ) {
					$deleted = wp_delete_term( $tag_data->post_tag_id, 'post_tag' );
					if ( ! is_wp_error( $deleted ) && $deleted ) {
						++$cleanup_results['deleted'];
						$cleanup_results['tags_processed'][] = array(
							'name'   => $tag_data->name,
							'slug'   => $tag_data->slug,
							'action' => 'deleted',
						);
					}
				} else {
					$cleanup_results['tags_processed'][] = array(
						'name'   => $tag_data->name,
						'slug'   => $tag_data->slug,
						'action' => 'kept',
						'count'  => $updated_term->count,
					);
				}
			}
		}

		return $cleanup_results;
	}

	/**
	 * Recalculate post_tag counts and cleanup for Friends-related tags.
	 * This recalculates counts for post_tag terms that have friend_tag equivalents
	 * and removes any with zero count after recalculation.
	 *
	 * @return array Cleanup results with counts.
	 */
	public static function recalculate_all_post_tag_counts() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Get post_tag terms that also exist in friend_tag taxonomy by slug.
		$all_post_tags = $wpdb->get_results(
			"SELECT pt.term_id, pt.name, pt.slug, ptt.count
			FROM {$wpdb->terms} pt
			INNER JOIN {$wpdb->term_taxonomy} ptt ON pt.term_id = ptt.term_id AND ptt.taxonomy = 'post_tag'
			INNER JOIN {$wpdb->terms} ft ON pt.slug = ft.slug
			INNER JOIN {$wpdb->term_taxonomy} ftt ON ft.term_id = ftt.term_id AND ftt.taxonomy = 'friend_tag'"
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

			// Debug: Log what we're checking
			error_log( sprintf( 'Friends Debug: Checking post_tag "%s" (ID: %d, old count: %d)', $tag_data->name, $tag_data->term_id, $old_count ) );

			// Recalculate the post_tag count.
			wp_update_term_count( $tag_data->term_id, 'post_tag' );

			// Get the updated term to check the real count.
			$updated_term = get_term( $tag_data->term_id, 'post_tag' );

			if ( $updated_term && ! is_wp_error( $updated_term ) ) {
				++$cleanup_results['recalculated'];

				$new_count = $updated_term->count;

				// Debug: Log count changes
				if ( $old_count !== $new_count ) {
					error_log( sprintf( 'Friends Debug: Count changed for "%s" from %d to %d', $tag_data->name, $old_count, $new_count ) );
				}

				// Debug: Check what posts are using this tag
				global $wpdb;
				$posts_using_tag = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
					WHERE tt.term_id = %d AND tt.taxonomy = 'post_tag' AND p.post_status IN ('publish', 'private')",
						$tag_data->term_id
					)
				);
				error_log( sprintf( 'Friends Debug: Tag "%s" has %d posts using it (recalculated count: %d)', $tag_data->name, $posts_using_tag, $new_count ) );

				// Log if count changed or if we're about to delete.
				if ( $old_count !== $new_count || 0 === $new_count ) {
					$cleanup_results['tags_processed'][] = array(
						'name'        => $tag_data->name,
						'slug'        => $tag_data->slug,
						'old_count'   => $old_count,
						'new_count'   => $new_count,
						'action'      => 0 === $new_count ? 'deleted' : 'count_updated',
						'posts_using' => $posts_using_tag,
					);
				}

				// If count is 0 after recalculation, delete the orphaned post_tag.
				if ( 0 === $new_count ) {
					error_log( sprintf( 'Friends Debug: Deleting orphaned post_tag "%s"', $tag_data->name ) );
					$deleted = wp_delete_term( $tag_data->term_id, 'post_tag' );
					if ( ! is_wp_error( $deleted ) && $deleted ) {
						++$cleanup_results['deleted'];
						error_log( sprintf( 'Friends Debug: Successfully deleted post_tag "%s"', $tag_data->name ) );
					} else {
						error_log( sprintf( 'Friends Debug: Failed to delete post_tag "%s": %s', $tag_data->name, is_wp_error( $deleted ) ? $deleted->get_error_message() : 'Unknown error' ) );
					}
				} else {
					error_log( sprintf( 'Friends Debug: Not deleting "%s" because count is %d (not 0)', $tag_data->name, $new_count ) );
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
}
