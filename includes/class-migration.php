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

		// Set migration progress tracking.
		update_option( 'friends_tag_migration_in_progress', true );
		update_option( 'friends_tag_migration_total', $total_posts );
		update_option( 'friends_tag_migration_processed', 0 );
		update_option( 'friends_tag_migration_offset', 0 );

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
		update_option( 'friends_tag_migration_processed', $processed );
		update_option( 'friends_tag_migration_offset', $offset + $batch_size );

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

		// Log completion.

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Friends: Post tag migration completed successfully.' );
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
