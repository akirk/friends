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

			<?php if ( isset( $_GET['backfilled'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: number of URLs backfilled */
							esc_html__( 'Backfill completed! Updated %d actor records with Friends feed URLs.', 'friends' ),
							isset( $_GET['backfilled'] ) ? absint( wp_unslash( $_GET['backfilled'] ) ) : 0 // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
					<h3 style="margin-top: 0.5em;"><?php esc_html_e( 'Dry Run Results (Preview)', 'friends' ); ?></h3>
					<p><strong><?php esc_html_e( 'No changes were made. This was a simulation only.', 'friends' ); ?></strong></p>

					<?php if ( $imported_count > 0 ) : ?>
						<h4><?php esc_html_e( 'Import: ActivityPub -> Friends', 'friends' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: %d: number of follows that would be imported */
								esc_html__( 'Would import %d follows:', 'friends' ),
								esc_html( $imported_count )
							);
							?>
						</p>
						<table class="widefat" style="margin-bottom: 1em;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Actor', 'friends' ); ?></th>
									<th><?php esc_html_e( 'ActivityPub Post ID', 'friends' ); ?></th>
									<th><?php esc_html_e( 'Action', 'friends' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_slice( $sync_status['only_activitypub'], 0, 20 ) as $actor_data ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $actor_data['name'] ?? $actor_data['preferredUsername'] ?? 'Unknown' ); ?></strong><br>
											<code style="font-size: 11px;"><?php echo esc_html( $actor_data['url'] ); ?></code>
										</td>
										<td><code><?php echo esc_html( $actor_data['post_id'] ); ?></code></td>
										<td>
											<span style="color: #0073aa;">
												<?php esc_html_e( 'Create new Subscription user', 'friends' ); ?><br>
												<?php esc_html_e( 'Add User_Feed term (parser: activitypub)', 'friends' ); ?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
								<?php if ( count( $sync_status['only_activitypub'] ) > 20 ) : ?>
									<tr>
										<td colspan="3"><em>
											<?php
											printf(
												/* translators: %d: number of additional accounts */
												esc_html__( '...and %d more (see table below)', 'friends' ),
												count( $sync_status['only_activitypub'] ) - 20
											);
											?>
										</em></td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php esc_html_e( 'Import: No follows to import from ActivityPub.', 'friends' ); ?></p>
					<?php endif; ?>

					<?php if ( $exported_count > 0 ) : ?>
						<h4><?php esc_html_e( 'Export: Friends -> ActivityPub', 'friends' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: %d: number of follows that would be exported */
								esc_html__( 'Would export %d follows:', 'friends' ),
								esc_html( $exported_count )
							);
							?>
						</p>
						<table class="widefat" style="margin-bottom: 1em;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Friend / Feed', 'friends' ); ?></th>
									<th><?php esc_html_e( 'User Feed Term ID', 'friends' ); ?></th>
									<th><?php esc_html_e( 'Action', 'friends' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_slice( $sync_status['only_friends'], 0, 20 ) as $feed_data ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $feed_data['title'] ); ?></strong>
											(<?php echo esc_html( $feed_data['friend_user'] ); ?>)<br>
											<code style="font-size: 11px;"><?php echo esc_html( $feed_data['url'] ); ?></code>
										</td>
										<td><code><?php echo esc_html( $feed_data['term_id'] ); ?></code></td>
										<td>
											<span style="color: #0073aa;">
												<?php esc_html_e( 'Fetch/create Remote Actor post', 'friends' ); ?><br>
												<?php
												printf(
													/* translators: %d: ActivityPub user ID */
													esc_html__( 'Add post_meta _activitypub_followed_by = %d', 'friends' ),
													esc_html( $user_id )
												);
												?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
								<?php if ( count( $sync_status['only_friends'] ) > 20 ) : ?>
									<tr>
										<td colspan="3"><em>
											<?php
											printf(
												/* translators: %d: number of additional accounts */
												esc_html__( '...and %d more (see table below)', 'friends' ),
												count( $sync_status['only_friends'] ) - 20
											);
											?>
										</em></td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php esc_html_e( 'Export: No follows to export to ActivityPub.', 'friends' ); ?></p>
					<?php endif; ?>

					<p style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin-top: 1em;">
						<strong><?php esc_html_e( 'To actually perform this sync, click "Sync Now (Bidirectional)" below.', 'friends' ); ?></strong>
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
				<?php if ( $sync_status['only_friends_count'] > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block; margin-left: 10px;">
						<input type="hidden" name="action" value="friends_activitypub_sync">
						<input type="hidden" name="export_only" value="1">
						<?php wp_nonce_field( 'friends-activitypub-sync' ); ?>
						<?php submit_button( __( 'Export Only (Friends → ActivityPub)', 'friends' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block; margin-left: 10px;">
					<input type="hidden" name="action" value="friends_activitypub_sync">
					<input type="hidden" name="dry_run" value="1">
					<?php wp_nonce_field( 'friends-activitypub-sync' ); ?>
					<?php submit_button( __( 'Dry Run (Preview Only)', 'friends' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<?php if ( $sync_status['in_sync_count'] > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block; margin-left: 10px;">
					<input type="hidden" name="action" value="friends_activitypub_sync">
					<input type="hidden" name="backfill_urls" value="1">
					<?php wp_nonce_field( 'friends-activitypub-sync' ); ?>
					<?php submit_button( __( 'Backfill URLs (update existing)', 'friends' ), 'secondary', 'submit', false ); ?>
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
							<th><?php esc_html_e( 'ActivityPub Post ID', 'friends' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sync_status['only_activitypub'] as $actor_data ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $actor_data['url'] ); ?>" target="_blank"><?php echo esc_html( $actor_data['url'] ); ?></a></td>
								<td><?php echo esc_html( $actor_data['name'] ?? 'N/A' ); ?></td>
								<td><?php echo esc_html( $actor_data['preferredUsername'] ?? 'N/A' ); ?></td>
								<td><code><?php echo esc_html( $actor_data['post_id'] ); ?></code></td>
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
							<th><?php esc_html_e( 'User Feed Term ID', 'friends' ); ?></th>
							<th><?php esc_html_e( 'Parser', 'friends' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sync_status['only_friends'] as $feed_data ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $feed_data['url'] ); ?>" target="_blank"><?php echo esc_html( $feed_data['url'] ); ?></a></td>
								<td><?php echo esc_html( $feed_data['title'] ); ?></td>
								<td><?php echo esc_html( $feed_data['friend_user'] ); ?></td>
								<td><code><?php echo esc_html( $feed_data['term_id'] ); ?></code></td>
								<td><code><?php echo esc_html( $feed_data['parser'] ); ?></code></td>
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
							<th><?php esc_html_e( 'Friend', 'friends' ); ?></th>
							<th><?php esc_html_e( 'User Feed Term ID', 'friends' ); ?></th>
							<th><?php esc_html_e( 'ActivityPub Post ID', 'friends' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sync_status['in_sync'] as $url => $data ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></td>
								<td><?php echo esc_html( $data['name'] ); ?></td>
								<td><?php echo esc_html( $data['friend_user'] ); ?></td>
								<td><code><?php echo esc_html( $data['term_id'] ); ?></code></td>
								<td><code><?php echo esc_html( $data['post_id'] ); ?></code></td>
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
					<?php
					// Debug: Check for posts with _activitypub_followed_by meta.
					global $wpdb;
					$meta_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_followed_by' AND meta_value = %s",
							$user_id
						)
					);
					$pending_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_activitypub_followed_by_pending' AND meta_value = %s",
							$user_id
						)
					);
					?>
					<tr>
						<th><?php esc_html_e( 'Posts with _activitypub_followed_by meta (DB query)', 'friends' ); ?></th>
						<td><code><?php echo esc_html( $meta_count ); ?></code></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Posts with _activitypub_followed_by_pending meta (DB query)', 'friends' ); ?></th>
						<td><code><?php echo esc_html( $pending_count ); ?></code></td>
					</tr>
					<?php
					// Debug: Count ap_actor posts.
					if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
						$ap_actor_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
							"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ap_actor'"
						);
						?>
						<tr>
							<th><?php esc_html_e( 'Total ap_actor posts in database', 'friends' ); ?></th>
							<td><code><?php echo esc_html( $ap_actor_count ); ?></code></td>
						</tr>
						<?php
					}
					// Debug: Raw Following::get_all() output.
					if ( class_exists( '\Activitypub\Collection\Following' ) ) {
						$raw_following = \Activitypub\Collection\Following::get_all( $user_id );
						$raw_count = is_wp_error( $raw_following ) ? 'Error: ' . $raw_following->get_error_message() : count( $raw_following );
						?>
						<tr>
							<th><?php esc_html_e( 'Following::get_all() raw result count', 'friends' ); ?></th>
							<td><code><?php echo esc_html( $raw_count ); ?></code></td>
						</tr>
						<?php
						// Debug: Show sample URLs from ActivityPub - try multiple methods.
						if ( ! is_wp_error( $raw_following ) && is_array( $raw_following ) && count( $raw_following ) > 0 ) {
							$sample_ap_urls = array();
							foreach ( array_slice( $raw_following, 0, 5 ) as $actor ) {
								if ( $actor instanceof \WP_Post ) {
									$url_from_uri = \Activitypub\object_to_uri( $actor );
									$url_from_guid = $actor->guid;
									$url_from_meta = get_post_meta( $actor->ID, '_activitypub_actor_id', true );
									$url_from_meta2 = get_post_meta( $actor->ID, 'activitypub_actor_id', true );
									$sample_ap_urls[] = array(
										'post_id'    => $actor->ID,
										'post_title' => $actor->post_title,
										'guid'       => $url_from_guid,
										'meta_1'     => $url_from_meta,
										'meta_2'     => $url_from_meta2,
										'object_uri' => $url_from_uri,
									);
								}
							}
							?>
							<tr>
								<th><?php esc_html_e( 'Sample ap_actor posts (debugging URL source)', 'friends' ); ?></th>
								<td>
									<?php foreach ( $sample_ap_urls as $sample ) : ?>
										<strong>ID:<?php echo esc_html( $sample['post_id'] ); ?></strong> - <?php echo esc_html( $sample['post_title'] ); ?><br>
										&nbsp;&nbsp;guid: <code><?php echo esc_html( $sample['guid'] ? $sample['guid'] : '(empty)' ); ?></code><br>
										&nbsp;&nbsp;_activitypub_actor_id: <code><?php echo esc_html( $sample['meta_1'] ? $sample['meta_1'] : '(empty)' ); ?></code><br>
										&nbsp;&nbsp;activitypub_actor_id: <code><?php echo esc_html( $sample['meta_2'] ? $sample['meta_2'] : '(empty)' ); ?></code><br>
										&nbsp;&nbsp;object_to_uri(): <code><?php echo esc_html( $sample['object_uri'] ? $sample['object_uri'] : '(empty/NULL)' ); ?></code><br>
										<hr style="margin: 5px 0;">
									<?php endforeach; ?>
								</td>
							</tr>
							<?php
						}
					}
					// Debug: Show sample URLs from Friends with normalization.
					$debug_lookup_map = $this->build_actor_lookup_map();
					$sample_friends_data = array();
					foreach ( array_slice( User_Feed::get_by_parser( Feed_Parser_ActivityPub::SLUG ), 0, 5 ) as $feed ) {
						$original_url = $feed->get_url();
						$normalized_url = $this->normalize_actor_url( $original_url, $debug_lookup_map, false );
						$sample_friends_data[] = array(
							'original'   => $original_url,
							'normalized' => $normalized_url,
						);
					}
					if ( ! empty( $sample_friends_data ) ) {
						?>
						<tr>
							<th><?php esc_html_e( 'Sample Friends Feed URLs (with normalization)', 'friends' ); ?></th>
							<td>
								<?php foreach ( $sample_friends_data as $data ) : ?>
									<strong>Original:</strong> <code><?php echo esc_html( $data['original'] ); ?></code><br>
									<strong>Normalized:</strong> <code><?php echo esc_html( $data['normalized'] ? $data['normalized'] : '(failed)' ); ?></code><br>
									<hr style="margin: 5px 0;">
								<?php endforeach; ?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Build a lookup map from all ap_actor posts for fast URL matching.
	 * Maps guid (canonical URL), _activitypub_acct (webfinger), and _friends_feed_url to canonical URL.
	 *
	 * @return array Map of various URL formats to canonical actor URLs.
	 */
	private function build_actor_lookup_map() {
		global $wpdb;

		$lookup = array();

		// Get all ap_actor posts with their guid, acct meta, and friends feed URL.
		$actors = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT p.ID, p.guid,
				MAX(CASE WHEN pm.meta_key = '_activitypub_acct' THEN pm.meta_value END) as acct,
				MAX(CASE WHEN pm.meta_key = '_friends_feed_url' THEN pm.meta_value END) as friends_url
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'ap_actor'
			GROUP BY p.ID, p.guid"
		);

		foreach ( $actors as $actor ) {
			$canonical_url = $actor->guid;
			if ( empty( $canonical_url ) ) {
				continue;
			}

			// Map canonical URL to itself.
			$lookup[ $canonical_url ] = $canonical_url;

			// Map webfinger acct to canonical URL (with and without leading @).
			if ( ! empty( $actor->acct ) ) {
				$lookup[ $actor->acct ] = $canonical_url;
				$lookup[ '@' . ltrim( $actor->acct, '@' ) ] = $canonical_url;
				$lookup[ ltrim( $actor->acct, '@' ) ] = $canonical_url;
			}

			// Map stored Friends feed URL to canonical URL.
			if ( ! empty( $actor->friends_url ) ) {
				$lookup[ $actor->friends_url ] = $canonical_url;
			}
		}

		return $lookup;
	}

	/**
	 * Normalize a URL or webfinger address to a canonical actor URL.
	 * Uses a pre-built lookup map for fast local matching, with optional network fallback.
	 *
	 * @param string $url        The URL or webfinger address to normalize.
	 * @param array  $lookup_map Optional pre-built lookup map from build_actor_lookup_map().
	 * @param bool   $network_fallback Whether to fall back to network requests if not found locally.
	 * @return string|null The normalized URL or null if resolution fails.
	 */
	private function normalize_actor_url( $url, $lookup_map = null, $network_fallback = false ) {
		if ( empty( $url ) ) {
			return null;
		}

		// If we have a lookup map, try it first (fast local lookup).
		if ( null !== $lookup_map ) {
			// Try exact match.
			if ( isset( $lookup_map[ $url ] ) ) {
				return $lookup_map[ $url ];
			}
			// Try with/without @ prefix for webfinger.
			if ( isset( $lookup_map[ '@' . ltrim( $url, '@' ) ] ) ) {
				return $lookup_map[ '@' . ltrim( $url, '@' ) ];
			}
		}

		// If no network fallback requested, return null for unmatched URLs.
		if ( ! $network_fallback ) {
			// For valid URLs, return as-is (might match later).
			if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
				return $url;
			}
			return null;
		}

		// Network fallback for webfinger addresses.
		if ( str_starts_with( $url, '@' ) ) {
			if ( class_exists( '\Activitypub\Webfinger' ) && method_exists( '\Activitypub\Webfinger', 'resolve' ) ) {
				$resolved = \Activitypub\Webfinger::resolve( $url );
				if ( ! is_wp_error( $resolved ) && ! empty( $resolved ) ) {
					return $resolved;
				}
			}
		}

		// For URLs, return as-is.
		if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $url;
		}

		return null;
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

		// Build lookup map for fast URL normalization (single DB query).
		$lookup_map = $this->build_actor_lookup_map();

		// Get ActivityPub follows - keep the WP_Post objects and derive URLs from them.
		$activitypub_follows = array(); // Keyed by canonical URL for comparison.
		$activitypub_posts = array();   // Keyed by post ID for reference.
		if ( class_exists( '\Activitypub\Collection\Following' ) ) {
			$actors = \Activitypub\Collection\Following::get_all( $user_id );
			if ( ! is_wp_error( $actors ) && is_array( $actors ) ) {
				foreach ( $actors as $actor ) {
					if ( ! $actor instanceof \WP_Post ) {
						continue;
					}

					// Get URL from the post's guid field (canonical actor URL).
					$actor_url = $actor->guid;
					if ( empty( $actor_url ) || ! is_string( $actor_url ) ) {
						continue;
					}

					// Store post reference.
					$activitypub_posts[ $actor->ID ] = array(
						'post'    => $actor,
						'post_id' => $actor->ID,
						'url'     => $actor_url,
						'name'    => $actor->post_title,
					);

					// Map URL to post ID for comparison.
					$activitypub_follows[ $actor_url ] = $actor->ID;
				}
			}
		}

		// Get Friends feeds and normalize their URLs using the lookup map (no network calls).
		$friends_feeds = array();         // Keyed by canonical URL.
		$friends_feed_objects = array();  // Original User_Feed objects keyed by canonical URL.
		foreach ( User_Feed::get_by_parser( Feed_Parser_ActivityPub::SLUG ) as $user_feed ) {
			$feed_url = $user_feed->get_url();
			$canonical_url = $this->normalize_actor_url( $feed_url, $lookup_map, false );
			if ( $canonical_url ) {
				$friends_feeds[ $canonical_url ] = $user_feed;
				$friends_feed_objects[ $canonical_url ] = array(
					'user_feed'    => $user_feed,
					'original_url' => $feed_url,
				);
			} else {
				// Could not normalize, use original URL.
				$friends_feeds[ $feed_url ] = $user_feed;
				$friends_feed_objects[ $feed_url ] = array(
					'user_feed'    => $user_feed,
					'original_url' => $feed_url,
				);
			}
		}

		$status['activitypub_count'] = count( $activitypub_follows );
		$status['friends_count'] = count( $friends_feeds );

		// Find matches and differences.
		foreach ( $activitypub_follows as $url => $post_id ) {
			$post_data = $activitypub_posts[ $post_id ];
			if ( isset( $friends_feeds[ $url ] ) ) {
				$user_feed = $friends_feeds[ $url ];
				$original_url = isset( $friends_feed_objects[ $url ]['original_url'] ) ? $friends_feed_objects[ $url ]['original_url'] : $url;
				$status['in_sync'][ $url ] = array(
					'name'           => $post_data['name'] ?? $post_data['preferredUsername'] ?? 'Unknown',
					'post_id'        => $post_id,
					'term_id'        => $user_feed->get_id(),
					'parser'         => $user_feed->get_parser(),
					'active'         => $user_feed->is_active(),
					'friend_user'    => $user_feed->get_friend_user()->display_name,
					'friend_user_id' => $user_feed->get_friend_user()->ID,
					'original_url'   => $original_url,
				);
				++$status['in_sync_count'];
			} else {
				$status['only_activitypub'][] = $post_data;
				++$status['only_activitypub_count'];
			}
		}

		foreach ( $friends_feeds as $url => $user_feed ) {
			if ( ! isset( $activitypub_follows[ $url ] ) ) {
				// Include detailed User_Feed info for better dry run display.
				$status['only_friends'][] = array(
					'user_feed'      => $user_feed,
					'url'            => $url,
					'term_id'        => $user_feed->get_id(),
					'title'          => $user_feed->get_title(),
					'parser'         => $user_feed->get_parser(),
					'active'         => $user_feed->is_active(),
					'friend_user'    => $user_feed->get_friend_user()->display_name,
					'friend_user_id' => $user_feed->get_friend_user()->ID,
				);
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
		$export_only = isset( $_POST['export_only'] ) && '1' === $_POST['export_only']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$backfill_urls = isset( $_POST['backfill_urls'] ) && '1' === $_POST['backfill_urls']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$user_id = Feed_Parser_ActivityPub::get_activitypub_actor_id( Friends::get_main_friend_user_id() );

		if ( $backfill_urls ) {
			$result = $this->backfill_friends_urls( $user_id );
			$redirect_args = array(
				'page'       => 'friends-activitypub-sync',
				'backfilled' => $result['updated'],
			);
		} else {
			$result = $this->perform_sync( $user_id, $dry_run, $export_only );
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
	 * Backfill _friends_feed_url meta for existing and potential matches.
	 *
	 * @param int $user_id The ActivityPub user ID.
	 * @return array Results with updated count.
	 */
	private function backfill_friends_urls( $user_id ) {
		$updated = 0;
		$sync_status = $this->get_sync_status( $user_id );

		// Update existing in-sync items.
		foreach ( $sync_status['in_sync'] as $canonical_url => $data ) {
			$post_id = $data['post_id'];
			$original_url = isset( $data['original_url'] ) ? $data['original_url'] : null;

			if ( $original_url && $original_url !== $canonical_url ) {
				$existing = get_post_meta( $post_id, '_friends_feed_url', true );
				if ( $existing !== $original_url ) {
					update_post_meta( $post_id, '_friends_feed_url', $original_url );
					++$updated;
				}
			}
		}

		// Try to match "only in ActivityPub" with "only in Friends" by URL transformation.
		// Build a map of Friends URLs and their variations.
		$friends_url_map = array();
		foreach ( $sync_status['only_friends'] as $feed_data ) {
			$url = $feed_data['url'];
			$friends_url_map[ $url ] = $url;

			// Add /@username variant for /users/username URLs.
			if ( preg_match( '#^(https?://[^/]+)/users/([^/]+)$#', $url, $matches ) ) {
				$alt_url = $matches[1] . '/@' . $matches[2];
				$friends_url_map[ $alt_url ] = $url;
			}
			// Add /users/username variant for /@username URLs.
			if ( preg_match( '#^(https?://[^/]+)/@([^/]+)$#', $url, $matches ) ) {
				$alt_url = $matches[1] . '/users/' . $matches[2];
				$friends_url_map[ $alt_url ] = $url;
			}
		}

		// Check each "only in ActivityPub" actor against the Friends URL map.
		foreach ( $sync_status['only_activitypub'] as $actor_data ) {
			$canonical_url = $actor_data['url'];
			$post_id = $actor_data['post_id'];

			// Check if canonical URL matches any Friends URL variation.
			if ( isset( $friends_url_map[ $canonical_url ] ) ) {
				$friends_url = $friends_url_map[ $canonical_url ];
				if ( $friends_url !== $canonical_url ) {
					$existing = get_post_meta( $post_id, '_friends_feed_url', true );
					if ( $existing !== $friends_url ) {
						update_post_meta( $post_id, '_friends_feed_url', $friends_url );
						++$updated;
					}
				}
			}
		}

		return array( 'updated' => $updated );
	}

	/**
	 * Perform bidirectional sync
	 *
	 * @param int  $user_id The ActivityPub user ID.
	 * @param bool $dry_run Whether to perform a dry run (simulation only).
	 * @param bool $export_only Whether to only export (skip import).
	 * @return array Results with imported and exported counts.
	 */
	public function perform_sync( $user_id, $dry_run = false, $export_only = false ) {
		$imported = 0;
		$exported = 0;

		// Build lookup map for fast URL normalization.
		$lookup_map = $this->build_actor_lookup_map();

		// Get ActivityPub follows - keep post objects and derive URLs from them.
		$activitypub_follows = array(); // URL -> post_id mapping.
		$activitypub_posts = array();   // post_id -> post data.
		if ( class_exists( '\Activitypub\Collection\Following' ) ) {
			$actors = \Activitypub\Collection\Following::get_all( $user_id );
			if ( ! is_wp_error( $actors ) && is_array( $actors ) ) {
				foreach ( $actors as $actor ) {
					if ( ! $actor instanceof \WP_Post ) {
						continue;
					}

					// Get URL from the post's guid field (canonical actor URL).
					$actor_url = $actor->guid;
					if ( empty( $actor_url ) || ! is_string( $actor_url ) ) {
						continue;
					}

					$activitypub_follows[ $actor_url ] = $actor->ID;
					$activitypub_posts[ $actor->ID ] = array(
						'post' => $actor,
						'url'  => $actor_url,
					);
				}
			}
		}

		// Get Friends feeds and normalize their URLs for comparison (no network calls).
		$friends_feeds = array();           // Keyed by canonical URL.
		$friends_feed_original = array();   // Keyed by canonical URL, stores original URL.
		foreach ( User_Feed::get_by_parser( Feed_Parser_ActivityPub::SLUG ) as $user_feed ) {
			$feed_url = $user_feed->get_url();
			$canonical_url = $this->normalize_actor_url( $feed_url, $lookup_map, false );
			if ( $canonical_url ) {
				$friends_feeds[ $canonical_url ] = $user_feed;
				$friends_feed_original[ $canonical_url ] = $feed_url;
			} else {
				// Could not normalize, use original URL.
				$friends_feeds[ $feed_url ] = $user_feed;
				$friends_feed_original[ $feed_url ] = $feed_url;
			}
		}

		// Import: ActivityPub -> Friends (skip if export_only).
		// Use the post data we already have from ActivityPub.
		if ( ! $export_only ) {
			foreach ( $activitypub_posts as $post_id => $post_data ) {
				$actor_url = $post_data['url'];
				if ( ! isset( $friends_feeds[ $actor_url ] ) ) {
					$existing = User_Feed::get_by_url( $actor_url );
					if ( is_wp_error( $existing ) ) {
						// Fetch metadata for creating the subscription.
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
		} // End export_only check.

		// Export: Friends -> ActivityPub.
		// Populate ActivityPub's Following collection directly without sending Follow activities.
		if ( class_exists( '\Activitypub\Collection\Remote_Actors' ) ) {
			foreach ( $friends_feeds as $feed_url => $user_feed ) {
				if ( ! isset( $activitypub_follows[ $feed_url ] ) ) {
					if ( $dry_run ) {
						// Dry run: just count what would be exported.
						++$exported;
					} else {
						// Get the original Friends feed URL (might differ from canonical).
						$original_url = isset( $friends_feed_original[ $feed_url ] ) ? $friends_feed_original[ $feed_url ] : $feed_url;

						// Actually perform the export - fetch/create the actor post.
						$actor_post = \Activitypub\Collection\Remote_Actors::fetch_by_uri( $original_url );
						if ( ! is_wp_error( $actor_post ) && $actor_post instanceof \WP_Post ) {
							add_post_meta( $actor_post->ID, '_activitypub_followed_by', $user_id );
							// Store the original Friends URL for future lookups.
							if ( $original_url !== $actor_post->guid ) {
								update_post_meta( $actor_post->ID, '_friends_feed_url', $original_url );
							}
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
