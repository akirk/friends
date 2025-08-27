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
	 */
	public static function migrate_post_tags_to_friend_tags() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Get all Friends CPT posts that have post_tag terms.
		$friends_posts_with_tags = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, tr.term_taxonomy_id, t.term_id, t.name, t.slug
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE p.post_type = %s AND tt.taxonomy = 'post_tag'",
				Friends::CPT
			)
		);

		if ( empty( $friends_posts_with_tags ) ) {
			return;
		}

		// Collect term taxonomy IDs for bulk deletion and track terms for cleanup.
		$term_taxonomy_ids = array();
		$term_ids_to_check = array();

		// Process each tagged post.
		foreach ( $friends_posts_with_tags as $tagged_post ) {
			// Add the friend_tag to the post using the tag name (wp_set_post_terms will create it if needed).
			wp_set_post_terms( $tagged_post->ID, array( $tagged_post->name ), Friends::TAG_TAXONOMY, true );

			// Collect term taxonomy IDs for bulk deletion.
			$term_taxonomy_ids[] = $tagged_post->term_taxonomy_id;
			$term_ids_to_check[] = $tagged_post->term_id;
		}

		// Bulk delete post_tag relationships for Friends posts only.
		if ( ! empty( $term_taxonomy_ids ) ) {
			$friends_post_ids = wp_list_pluck( $friends_posts_with_tags, 'ID' );
			$post_placeholders = implode( ',', array_fill( 0, count( $friends_post_ids ), '%d' ) );
			$term_placeholders = implode( ',', array_fill( 0, count( $term_taxonomy_ids ), '%d' ) );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->term_relationships}
					WHERE object_id IN ($post_placeholders)
					AND term_taxonomy_id IN ($term_placeholders)",
					array_merge( $friends_post_ids, $term_taxonomy_ids )
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		// Clean up orphaned post_tag terms that no longer have associations.
		$unique_term_ids = array_unique( $term_ids_to_check );
		foreach ( $unique_term_ids as $term_id ) {
			// Update term counts after our bulk deletion, then check if orphaned.
			wp_update_term_count( $term_id, 'post_tag' );
			$term = get_term( $term_id, 'post_tag' );

			// If term exists and has no count, delete it.
			if ( $term && ! is_wp_error( $term ) && 0 === $term->count ) {
				wp_delete_term( $term_id, 'post_tag' );
			}
		}
	}
}
