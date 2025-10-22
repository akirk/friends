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
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
		add_filter( 'debug_information', array( $this, 'site_health_debug' ) );
		add_action( 'wp_ajax_friends_restart_migration', array( $this, 'ajax_restart_migration' ) );
		add_action( 'wp_ajax_friends_cleanup_post_tags', array( $this, 'ajax_cleanup_post_tags' ) );
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

		$tests['direct']['friends-roles'] = array(
			'label' => __( 'Friend roles were created', 'friends' ),
			'test'  => array( $this, 'friend_roles_test' ),
		);

		$tests['direct']['friends-cron'] = array(
			'label' => __( 'Friends cron job is enabled', 'friends' ),
			'test'  => array( $this, 'friends_cron_test' ),
		);

		$tests['direct']['friends-delete-cron'] = array(
			'label' => __( 'Friends delete old posts cron job is enabled', 'friends' ),
			'test'  => array( $this, 'friends_cron_delete_test' ),
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
			// No migration status options are set, check the database to see current state
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			$friends_posts_with_post_tags = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE p.post_type = %s
					AND tt.taxonomy = 'post_tag'
					AND p.post_status IN ('publish', 'private')",
					Friends::CPT
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( $friends_posts_with_post_tags > 0 ) {
				$result = array(
					'label'       => __( 'Friends posts need tag migration', 'friends' ),
					'status'      => 'recommended',
					'badge'       => array(
						'label' => __( 'Friends', 'friends' ),
						'color' => 'orange',
					),
					'description' => sprintf(
						'<p>%s</p><p><button type="button" class="button button-primary" onclick="friendsRestartMigration(this)">%s</button></p>',
						sprintf(
							/* translators: %d: number of posts */
							_n(
								'%d Friends post is still using the post_tag taxonomy. A migration to the friend_tag taxonomy is recommended for better organization.',
								'%d Friends posts are still using the post_tag taxonomy. A migration to the friend_tag taxonomy is recommended for better organization.',
								$friends_posts_with_post_tags,
								'friends'
							),
							$friends_posts_with_post_tags
						),
						__( 'Start Migration', 'friends' )
					),
					'test'        => 'friends_migration',
				);
			} else {
				$result = array(
					'label'       => __( 'No Friends tag migration needed', 'friends' ),
					'status'      => 'good',
					'badge'       => array(
						'label' => __( 'Friends', 'friends' ),
						'color' => 'green',
					),
					'description' => sprintf(
						'<p>%s</p>',
						__( 'No Friends posts are using the post_tag taxonomy. All Friends posts are properly using the friend_tag taxonomy.', 'friends' )
					),
					'test'        => 'friends_migration',
				);
			}
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
			$orphaned_tags = $wpdb->get_results(
				"SELECT t.term_id, t.name, t.slug
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = 'post_tag'
				ORDER BY t.name ASC"
			);
		} else {
			$placeholders = implode( ',', array_fill( 0, count( $post_tag_post_types ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$orphaned_tags = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.term_id, t.name, t.slug
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
					WHERE tt.taxonomy = 'post_tag' AND used_terms.term_id IS NULL
					ORDER BY t.name ASC",
					$post_tag_post_types
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		$orphaned_count = count( $orphaned_tags );

		if ( $orphaned_count > 0 ) {
			$description = sprintf(
				/* translators: %d: number of orphaned tags */
				_n(
					'%d orphaned post_tag term was found with no associated posts. These tags may have been created by Friends posts before migration or other sources and can be safely removed to keep your tag cloud clean.',
					'%d orphaned post_tag terms were found with no associated posts. These tags may have been created by Friends posts before migration or other sources and can be safely removed to keep your tag cloud clean.',
					$orphaned_count,
					'friends'
				),
				$orphaned_count
			);

			$tag_list_limit = 15;
			if ( $orphaned_count <= $tag_list_limit ) {
				$tag_names = array_map(
					function ( $tag ) {
						return '<code>' . esc_html( $tag->name ) . '</code>';
					},
					$orphaned_tags
				);
				$description .= '<br><br><strong>' . __( 'Tags to be deleted:', 'friends' ) . '</strong> ' . implode( ', ', $tag_names );
			} else {
				$shown_tags = array_slice( $orphaned_tags, 0, $tag_list_limit );
				$tag_names = array_map(
					function ( $tag ) {
						return '<code>' . esc_html( $tag->name ) . '</code>';
					},
					$shown_tags
				);
				$remaining = $orphaned_count - $tag_list_limit;
				$description .= '<br><br><strong>' . __( 'Tags to be deleted:', 'friends' ) . '</strong> ' . implode( ', ', $tag_names ) . sprintf(
					/* translators: %d: number of additional tags */
					_n( ' and %d more', ' and %d more', $remaining, 'friends' ),
					$remaining
				);
			}

			$result = array(
				'label'       => __( 'Orphaned post tags found', 'friends' ),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __( 'Friends', 'friends' ),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p><p><button type="button" class="button button-primary" onclick="friendsCleanupPostTags(this)">%s</button></p>',
					$description,
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
	 * Get missing Friends plugin roles.
	 *
	 * @return array Missing role names.
	 */
	public function get_missing_friends_plugin_roles() {
		$missing = Friends::get_friends_plugin_roles();
		$roles = new \WP_Roles();
		foreach ( $roles->roles as $role => $data ) {
			if ( isset( $data['capabilities']['friends_plugin'] ) ) {
				foreach ( $missing as $k => $cap ) {
					if ( isset( $data['capabilities'][ $cap ] ) ) {
						unset( $missing[ $k ] );
						break;
					}
				}
			}
		}

		return array_values( $missing );
	}

	/**
	 * Site Health test for friend roles.
	 *
	 * @return array Site Health test result.
	 */
	public function friend_roles_test() {
		$result = array(
			'label'       => __( 'The friend roles have been installed', 'friends' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Friends', 'friends' ),
				'color' => 'green',
			),
			'description' =>
				'<p>' .
				__( 'The Friends Plugin uses users and user roles to determine friendship status between sites.', 'friends' ) .
				'</p>' .
				'<p>' .
				sprintf(
					/* translators: %s is a list of roles. */
					__( 'These are the roles required for the friends plugin: %s', 'friends' ),
					implode( ', ', Friends::get_friends_plugin_roles() )
				) .
				'</p>',
			'test'        => 'friends-roles',
		);

		$missing_friend_roles = $this->get_missing_friends_plugin_roles();
		if ( ! empty( $missing_friend_roles ) ) {

			$result['label'] = sprintf(
				/* translators: %s is a list of missing roles. */
				__( 'Not all friend roles have been installed. Missing: %s', 'friends' ),
				implode( ', ', $missing_friend_roles )
			);
			$result['badge']['color'] = 'red';
			$result['status'] = 'critical';
			$result['description'] .= '<p>';
			$result['description'] .= wp_kses_post(
				sprintf(
					/* translators: %s is a URL. */
					__( '<strong>To fix this:</strong> <a href="%s">Re-run activation of the Friends plugin</a>.', 'friends' ),
					esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), admin_url( 'admin.php?page=friends-settings&rerun-activate' ) ), 'friends-settings' ) )
				)
			);
			$result['description'] .= '</p>';
		}

		return $result;
	}

	/**
	 * Site Health test for Friends cron job.
	 *
	 * @return array Site Health test result.
	 */
	public function friends_cron_test() {
		$result = array(
			'label'       => __( 'The refresh cron job is enabled', 'friends' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Friends', 'friends' ),
				'color' => 'green',
			),
			'description' =>
				'<p>' .
				__( 'The Friends Plugin uses a cron job to fetch your friends\' feeds.', 'friends' ) .
				'</p>',
			'test'        => 'friends-cron',
		);
		if ( ! wp_next_scheduled( 'cron_friends_refresh_feeds' ) ) {
			$result['label'] = __( 'The refresh cron job is not enabled', 'friends' );
			$result['badge']['color'] = 'red';
			$result['status'] = 'critical';
			$result['description'] .= '<p>';
			$result['description'] .= wp_kses_post(
				sprintf(
					/* translators: %s is a URL. */
					__( '<strong>To fix this:</strong> <a href="%s">Enable the Friends cron job</a>.', 'friends' ),
					esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), admin_url( 'admin.php?page=friends-settings&rerun-activate' ) ), 'friends-settings' ) )
				)
			);
			$result['description'] .= '</p>';
		}

		return $result;
	}

	/**
	 * Site Health test for Friends delete old posts cron job.
	 *
	 * @return array Site Health test result.
	 */
	public function friends_cron_delete_test() {
		$result = array(
			'label'       => __( 'The cron job to delete old posts is enabled', 'friends' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Friends', 'friends' ),
				'color' => 'green',
			),
			'description' =>
				'<p>' .
				__( 'The Friends Plugin uses a cron job to delete old posts your friends.', 'friends' ) .
				'</p>',
			'test'        => 'friends-delete-cron',
		);

		if ( ! wp_next_scheduled( 'cron_friends_delete_old_posts' ) ) {
			$result['label'] = __( 'The cron job to delete old posts is not enabled', 'friends' );
			$result['badge']['color'] = 'orange';
			$result['status'] = 'recommended';
			$result['description'] .= '<p>';
			$result['description'] .= wp_kses_post(
				sprintf(
					/* translators: %s is a URL. */
					__( '<strong>To fix this:</strong> <a href="%s">Enable the Friends cron job</a>.', 'friends' ),
					esc_url( wp_nonce_url( add_query_arg( '_wp_http_referer', remove_query_arg( '_wp_http_referer' ), admin_url( 'admin.php?page=friends-settings&rerun-activate' ) ), 'friends-settings' ) )
				)
			);
			$result['description'] .= '</p>';
		}

		return $result;
	}

	/**
	 * Add debug information to Site Health.
	 *
	 * @param array $debug_info Debug information array.
	 * @return array Modified debug information.
	 */
	public function site_health_debug( $debug_info ) {
		$missing_friend_roles = $this->get_missing_friends_plugin_roles();
		$debug_info['friends'] = array(
			'label'  => __( 'Friends', 'friends' ),
			'fields' => array(
				'version'   => array(
					'label' => __( 'Friends Version', 'friends' ),
					'value' => Friends::VERSION,
				),
				'mbstring'  => array(
					'label' => __( 'mbstring is available', 'friends' ),
					'value' => function_exists( 'mb_check_encoding' ) ? __( 'Yes' ) : __( 'No' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				),
				'roles'     => array(
					'label' => __( 'Friend roles missing', 'friends' ),
					'value' => empty( $missing_friend_roles ) ? sprintf(
						/* translators: %s is a list of roles. */
						__( 'All roles found: %s', 'friends' ),
						implode( ', ', Friends::get_friends_plugin_roles() )
					) : implode( ', ', $missing_friend_roles ),
				),
				'main_user' => array(
					'label' => __( 'Main Friend User', 'friends' ),
					'value' => $this->human_readable_main_user(),
				),
				'parsers'   => array(
					'label' => __( 'Registered Parsers', 'friends' ),
					'value' => wp_strip_all_tags( implode( ', ', Friends::get_instance()->feed->get_registered_parsers() ) ),
				),
			),
		);

		return $debug_info;
	}

	/**
	 * Returns a human readable string for which user is the main user.
	 *
	 * @return string
	 */
	private function human_readable_main_user() {
		$main_user = Friends::get_main_friend_user_id();

		if ( ! $main_user ) {
			/* translators: %d is the number of users. */
			return esc_html( sprintf( __( 'No main user set. Admin users: %d', 'friends' ), User_Query::all_admin_users()->get_total() ) );
		}

		$user = new \WP_User( $main_user );

		if ( ! $user->exists() ) {
			/* translators: %d is the user ID. */
			return esc_html( sprintf( __( 'Main user ID %d does not exist', 'friends' ), $main_user ) );
		}

		/* translators: %1$s is the user login, %2$d is the user ID. */
		return esc_html( sprintf( __( '%1$s (#%2$d)', 'friends' ), $user->user_login, $main_user ) );
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
		Migration::trigger_migration_manually();

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
				'restartMigrationNonce' => wp_create_nonce( 'friends_restart_migration' ),
				'cleanupPostTagsNonce'  => wp_create_nonce( 'friends_cleanup_post_tags' ),
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'confirmRestart'        => __( 'This will restart the migration process. Any posts that were previously migrated will be re-processed. Continue?', 'friends' ),
				'confirmCleanup'        => __( 'This will clean up orphaned post_tag terms that were created by Friends posts. Only unused tags will be deleted. Continue?', 'friends' ),
			)
		);
	}
}
