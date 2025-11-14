<?php
/**
 * Admin ActivityPub Sync
 *
 * This contains the functions for checking and syncing ActivityPub follows with Friends.
 *
 * @package Friends
 */

namespace Friends;

/**
 * This is the class for the ActivityPub Sync admin page.
 *
 * @since 4.0.0
 *
 * @package Friends
 * @author Friends Plugin Team
 */
class Admin_ActivityPub_Sync {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 50 );
		add_action( 'admin_post_friends_activitypub_sync', array( $this, 'handle_manual_sync' ) );
	}

	/**
	 * Register the admin menu
	 */
	public function register_admin_menu() {
		if ( ! class_exists( '\Activitypub\Activitypub' ) ) {
			return;
		}

		add_submenu_page(
			'friends',
			__( 'ActivityPub Sync', 'friends' ),
			'- ' . __( 'ActivityPub Sync', 'friends' ),
			'edit_users',
			'friends-activitypub-sync',
			array( $this, 'render_sync_page' )
		);
	}

	/**
	 * Render the sync check page
	 */
	public function render_sync_page() {
		if ( ! class_exists( '\Activitypub\Activitypub' ) ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'ActivityPub Sync', 'friends' ); ?></h1>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'The ActivityPub plugin is not installed or activated.', 'friends' ); ?></p>
				</div>
			</div>
			<?php
			return;
		}

		$user_id = Feed_Parser_ActivityPub::get_activitypub_actor_id( Friends::get_main_friend_user_id() );
		$sync_status = $this->get_sync_status( $user_id );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ActivityPub Sync Status', 'friends' ); ?></h1>

			<div class="notice notice-info">
				<p><?php esc_html_e( 'This page helps you debug and synchronize follows between the ActivityPub plugin and the Friends plugin.', 'friends' ); ?></p>
			</div>

			<?php if ( isset( $_GET['synced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %1$d: number of follows imported, %2$d: number of follows exported */
							esc_html__( 'Sync completed! Imported %1$d follows from ActivityPub, exported %2$d follows to ActivityPub.', 'friends' ),
							isset( $_GET['imported'] ) ? absint( wp_unslash( $_GET['imported'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							isset( $_GET['exported'] ) ? absint( wp_unslash( $_GET['exported'] ) ) : 0 // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['dry_run'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php
				$imported_count = isset( $_GET['imported'] ) ? absint( wp_unslash( $_GET['imported'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$exported_count = isset( $_GET['exported'] ) ? absint( wp_unslash( $_GET['exported'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				?>
				<div class="notice notice-info is-dismissible" style="border-left-width: 4px;">
					<h3 style="margin-top: 0.5em;"><?php esc_html_e( '🔍 Dry Run Results (Preview)', 'friends' ); ?></h3>
					<p><strong><?php esc_html_e( 'No changes were made. This was a simulation only.', 'friends' ); ?></strong></p>

					<?php if ( $imported_count > 0 ) : ?>
						<p>
							<?php
							printf(
								/* translators: %d: number of follows that would be imported */
								esc_html__( '📥 Would import %d follows FROM ActivityPub TO Friends:', 'friends' ),
								esc_html( $imported_count )
							);
							?>
						</p>
						<ul style="margin-left: 2em;">
							<?php foreach ( array_slice( $sync_status['only_activitypub'], 0, 10 ) as $actor_data ) : ?>
								<li>
									<strong><?php echo esc_html( $actor_data['name'] ?? $actor_data['preferredUsername'] ?? 'Unknown' ); ?></strong>
									<br><code><?php echo esc_html( $actor_data['url'] ); ?></code>
								</li>
							<?php endforeach; ?>
							<?php if ( count( $sync_status['only_activitypub'] ) > 10 ) : ?>
								<li><em>
									<?php
									printf(
										/* translators: %d: number of additional accounts */
										esc_html__( '...and %d more (see table below)', 'friends' ),
										count( $sync_status['only_activitypub'] ) - 10
									);
									?>
								</em></li>
							<?php endif; ?>
						</ul>
					<?php else : ?>
						<p><?php esc_html_e( '📥 No follows to import from ActivityPub.', 'friends' ); ?></p>
					<?php endif; ?>

					<?php if ( $exported_count > 0 ) : ?>
						<p>
							<?php
							printf(
								/* translators: %d: number of follows that would be exported */
								esc_html__( '📤 Would export %d follows FROM Friends TO ActivityPub:', 'friends' ),
								esc_html( $exported_count )
							);
							?>
						</p>
						<ul style="margin-left: 2em;">
							<?php foreach ( array_slice( $sync_status['only_friends'], 0, 10 ) as $user_feed ) : ?>
								<li>
									<strong><?php echo esc_html( $user_feed->get_title() ); ?></strong>
									(<?php echo esc_html( $user_feed->get_friend_user()->display_name ); ?>)
									<br><code><?php echo esc_html( $user_feed->get_url() ); ?></code>
								</li>
							<?php endforeach; ?>
							<?php if ( count( $sync_status['only_friends'] ) > 10 ) : ?>
								<li><em>
									<?php
									printf(
										/* translators: %d: number of additional accounts */
										esc_html__( '...and %d more (see table below)', 'friends' ),
										count( $sync_status['only_friends'] ) - 10
									);
									?>
								</em></li>
							<?php endif; ?>
						</ul>
					<?php else : ?>
						<p><?php esc_html_e( '📤 No follows to export to ActivityPub.', 'friends' ); ?></p>
					<?php endif; ?>

					<p style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin-top: 1em;">
						<strong><?php esc_html_e( '⚠️ To actually perform this sync, click "Sync Now (Bidirectional)" below.', 'friends' ); ?></strong>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Summary', 'friends' ); ?></h2>
			<table class="widefat">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'ActivityPub Following', 'friends' ); ?></th>
						<td><?php echo esc_html( $sync_status['activitypub_count'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Friends ActivityPub Feeds', 'friends' ); ?></th>
						<td><?php echo esc_html( $sync_status['friends_count'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'In Sync', 'friends' ); ?></th>
						<td><?php echo esc_html( $sync_status['in_sync_count'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Only in ActivityPub (missing in Friends)', 'friends' ); ?></th>
						<td><strong><?php echo esc_html( $sync_status['only_activitypub_count'] ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Only in Friends (missing in ActivityPub)', 'friends' ); ?></th>
						<td><strong><?php echo esc_html( $sync_status['only_friends_count'] ); ?></strong></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $sync_status['only_activitypub_count'] > 0 || $sync_status['only_friends_count'] > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block;">
					<input type="hidden" name="action" value="friends_activitypub_sync">
					<?php wp_nonce_field( 'friends-activitypub-sync' ); ?>
					<?php submit_button( __( 'Sync Now (Bidirectional)', 'friends' ), 'primary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block; margin-left: 10px;">
					<input type="hidden" name="action" value="friends_activitypub_sync">
					<input type="hidden" name="dry_run" value="1">
					<?php wp_nonce_field( 'friends-activitypub-sync' ); ?>
					<?php submit_button( __( 'Dry Run (Preview Only)', 'friends' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<?php if ( ! empty( $sync_status['only_activitypub'] ) ) : ?>
				<h2><?php esc_html_e( 'Only in ActivityPub (will be imported to Friends)', 'friends' ); ?></h2>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Actor URL', 'friends' ); ?></th>
							<th><?php esc_html_e( 'Name', 'friends' ); ?></th>
							<th><?php esc_html_e( 'Username', 'friends' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sync_status['only_activitypub'] as $actor_data ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $actor_data['url'] ); ?>" target="_blank"><?php echo esc_html( $actor_data['url'] ); ?></a></td>
								<td><?php echo esc_html( $actor_data['name'] ?? 'N/A' ); ?></td>
								<td><?php echo esc_html( $actor_data['preferredUsername'] ?? 'N/A' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $sync_status['only_friends'] ) ) : ?>
				<h2><?php esc_html_e( 'Only in Friends (will be exported to ActivityPub)', 'friends' ); ?></h2>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feed URL', 'friends' ); ?></th>
							<th><?php esc_html_e( 'Title', 'friends' ); ?></th>
							<th><?php esc_html_e( 'Friend', 'friends' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sync_status['only_friends'] as $user_feed ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $user_feed->get_url() ); ?>" target="_blank"><?php echo esc_html( $user_feed->get_url() ); ?></a></td>
								<td><?php echo esc_html( $user_feed->get_title() ); ?></td>
								<td><?php echo esc_html( $user_feed->get_friend_user()->display_name ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $sync_status['in_sync'] ) ) : ?>
				<h2><?php esc_html_e( 'In Sync', 'friends' ); ?></h2>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'URL', 'friends' ); ?></th>
							<th><?php esc_html_e( 'Name', 'friends' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sync_status['in_sync'] as $url => $name ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></td>
								<td><?php echo esc_html( $name ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Debug Information', 'friends' ); ?></h2>
			<table class="widefat">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'ActivityPub Plugin Version', 'friends' ); ?></th>
						<td><?php echo esc_html( defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ? ACTIVITYPUB_PLUGIN_VERSION : 'Unknown' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Friends Plugin Version', 'friends' ); ?></th>
						<td><?php echo esc_html( Friends::VERSION ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'ActivityPub User ID', 'friends' ); ?></th>
						<td><?php echo esc_html( $user_id ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Last Import Count (on upgrade)', 'friends' ); ?></th>
						<td><?php echo esc_html( get_option( 'friends_activitypub_import_count', '0' ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Get the current sync status
	 *
	 * @param int $user_id The ActivityPub user ID.
	 * @return array Sync status information.
	 */
	private function get_sync_status( $user_id ) {
		$status = array(
			'activitypub_count'      => 0,
			'friends_count'          => 0,
			'in_sync_count'          => 0,
			'only_activitypub_count' => 0,
			'only_friends_count'     => 0,
			'only_activitypub'       => array(),
			'only_friends'           => array(),
			'in_sync'                => array(),
		);

		// Get ActivityPub follows.
		$activitypub_follows = array();
		if ( class_exists( '\Activitypub\Collection\Following' ) ) {
			$actors = \Activitypub\Collection\Following::get_all( $user_id );
			if ( ! is_wp_error( $actors ) && is_array( $actors ) ) {
				foreach ( $actors as $actor ) {
					// Convert WP_Post to actor URL.
					$actor_url = \Activitypub\object_to_uri( $actor );
					if ( empty( $actor_url ) || ! is_string( $actor_url ) ) {
						continue;
					}

					$actor_data = \Activitypub\get_remote_metadata_by_actor( $actor_url );
					if ( ! is_wp_error( $actor_data ) ) {
						$activitypub_follows[ $actor_url ] = $actor_data;
					}
				}
			}
		}

		// Get Friends feeds.
		$friends_feeds = array();
		foreach ( User_Feed::get_by_parser( Feed_Parser_ActivityPub::SLUG ) as $user_feed ) {
			$friends_feeds[ $user_feed->get_url() ] = $user_feed;
		}

		$status['activitypub_count'] = count( $activitypub_follows );
		$status['friends_count'] = count( $friends_feeds );

		// Find matches and differences.
		foreach ( $activitypub_follows as $url => $actor_data ) {
			if ( isset( $friends_feeds[ $url ] ) ) {
				$status['in_sync'][ $url ] = $actor_data['name'] ?? $actor_data['preferredUsername'];
				++$status['in_sync_count'];
			} else {
				$status['only_activitypub'][] = $actor_data;
				++$status['only_activitypub_count'];
			}
		}

		foreach ( $friends_feeds as $url => $user_feed ) {
			if ( ! isset( $activitypub_follows[ $url ] ) ) {
				$status['only_friends'][] = $user_feed;
				++$status['only_friends_count'];
			}
		}

		return $status;
	}

	/**
	 * Handle manual sync request
	 */
	public function handle_manual_sync() {
		check_admin_referer( 'friends-activitypub-sync' );

		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'friends' ) );
		}

		$dry_run = isset( $_POST['dry_run'] ) && '1' === $_POST['dry_run']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$user_id = Feed_Parser_ActivityPub::get_activitypub_actor_id( Friends::get_main_friend_user_id() );
		$result = $this->perform_sync( $user_id, $dry_run );

		$redirect_args = array(
			'page'     => 'friends-activitypub-sync',
			'imported' => $result['imported'],
			'exported' => $result['exported'],
		);

		if ( $dry_run ) {
			$redirect_args['dry_run'] = 1;
		} else {
			$redirect_args['synced'] = 1;
		}

		wp_safe_redirect(
			add_query_arg(
				$redirect_args,
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Perform bidirectional sync
	 *
	 * @param int  $user_id The ActivityPub user ID.
	 * @param bool $dry_run Whether to perform a dry run (simulation only).
	 * @return array Results with imported and exported counts.
	 */
	public function perform_sync( $user_id, $dry_run = false ) {
		$imported = 0;
		$exported = 0;

		// Get ActivityPub follows.
		$activitypub_follows = array();
		if ( class_exists( '\Activitypub\Collection\Following' ) ) {
			$actors = \Activitypub\Collection\Following::get_all( $user_id );
			if ( ! is_wp_error( $actors ) && is_array( $actors ) ) {
				foreach ( $actors as $actor ) {
					// Convert WP_Post to actor URL.
					$actor_url = \Activitypub\object_to_uri( $actor );
					if ( empty( $actor_url ) || ! is_string( $actor_url ) ) {
						continue;
					}

					$activitypub_follows[] = $actor_url;
				}
			}
		}

		// Get Friends feeds.
		$friends_feeds = array();
		foreach ( User_Feed::get_by_parser( Feed_Parser_ActivityPub::SLUG ) as $user_feed ) {
			$friends_feeds[] = $user_feed->get_url();
		}

		// Import: ActivityPub -> Friends.
		foreach ( $activitypub_follows as $actor_url ) {
			if ( ! in_array( $actor_url, $friends_feeds, true ) ) {
				$existing = User_Feed::get_by_url( $actor_url );
				if ( is_wp_error( $existing ) ) {
					$actor_data = \Activitypub\get_remote_metadata_by_actor( $actor_url );
					if ( ! is_wp_error( $actor_data ) && ! empty( $actor_data ) ) {
						if ( $dry_run ) {
							// Dry run: just count what would be imported.
							++$imported;
						} else {
							// Actually perform the import.
							$user_login = sanitize_title( $actor_data['preferredUsername'] . '.' . wp_parse_url( $actor_url, PHP_URL_HOST ) );
							$subscription = Subscription::create(
								$user_login,
								'subscription',
								$actor_data['url'],
								$actor_data['name'] ?? $actor_data['preferredUsername'],
								isset( $actor_data['icon']['url'] ) ? $actor_data['icon']['url'] : null,
								$actor_data['summary'] ?? null
							);

							if ( ! is_wp_error( $subscription ) ) {
								$subscription->save_feed(
									$actor_url,
									array(
										'parser' => Feed_Parser_ActivityPub::SLUG,
										'active' => true,
										'title'  => $actor_data['name'] ?? $actor_data['preferredUsername'],
									)
								);
								++$imported;
							}
						}
					}
				}
			}
		}

		// Export: Friends -> ActivityPub.
		// Populate ActivityPub's Following collection directly without sending Follow activities.
		if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			foreach ( $friends_feeds as $feed_url ) {
				if ( ! in_array( $feed_url, $activitypub_follows, true ) ) {
					if ( $dry_run ) {
						// Dry run: just count what would be exported.
						++$exported;
					} else {
						// Actually perform the export.
						$actor_post = \Activitypub\Collection\Remote_Actors::fetch_by_uri( $feed_url );
						if ( ! is_wp_error( $actor_post ) && $actor_post instanceof \WP_Post ) {
							add_post_meta( $actor_post->ID, '_activitypub_followed_by', $user_id );
							++$exported;
						}
					}
				}
			}
		}

		return array(
			'imported' => $imported,
			'exported' => $exported,
		);
	}
}
