<?php
/**
 * This template contains the Friends 4.0 news entry.
 *
 * @package Friends
 */

namespace Friends;

$statuses        = isset( $args['statuses'] ) ? $args['statuses'] : array();
$all_complete    = isset( $args['all_complete'] ) ? $args['all_complete'] : true;
$has_in_progress = isset( $args['has_in_progress'] ) ? $args['has_in_progress'] : false;

?>
<div class="friends-news-entry-body">
	<h2><?php esc_html_e( 'Friends 4.0: A Major Update', 'friends' ); ?></h2>

	<div class="friends-news-changes">
		<h3><?php esc_html_e( 'Themes', 'friends' ); ?></h3>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Dark Mode', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'The default Friends theme now has a dark mode that follows your system preferences.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Block Theme', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'A block-based theme that integrates with your site\'s active theme, letting you customize the Friends page templates via the Site Editor.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Google Reader Theme', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'A Google Reader-inspired theme with a compact list view where posts expand accordion-style. Includes keyboard shortcuts (j/k to navigate, s to star, v to open original, ? to see all shortcuts) and full dark mode support.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Mastodon Theme', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'A Mastodon-style theme with a 3-column layout: user info and search on the left, timeline in the center, and navigation on the right. Features card-based posts with avatars, collapsible filter chips, and full light/dark mode support.', 'friends' ); ?></p>
		</div>

		<h3><?php esc_html_e( 'Subscriptions', 'friends' ); ?></h3>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Subscription Folders', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'Organize your subscriptions into folders. You can move subscriptions into folders from the author page. The friends list can be grouped by folder with collapsible sections. Starred subscriptions are shown separately for quick access.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Improved Subscriptions and Followers Pages', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'The subscriptions and followers pages now have filtering, sorting, and pagination. Browse subscriptions by starred or folder, sort by name, date, or post count, and navigate large lists with ease.', 'friends' ); ?></p>
		</div>

		<h3><?php esc_html_e( 'Compatibility', 'friends' ); ?></h3>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Friendship Functionality Removed', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'The two-way friendship functionality has been removed. It only worked between WordPress sites with the Friends plugin installed. Your existing friends are now subscriptions. Similar functionality could be implemented via ActivityPub in the future, which would work across the whole Fediverse.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Friend Tags', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'Posts from friends now use a dedicated taxonomy instead of sharing post tags with your blog. This prevents other people\'s tags from polluting your own tag list.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'ActivityPub 7.0 Compatibility', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'ActivityPub feeds are now linked to actor records from the ActivityPub plugin, improving reliability and enabling better URL synchronization.', 'friends' ); ?></p>
		</div>

		<div class="friends-news-change">
			<h4><?php esc_html_e( 'Header Images', 'friends' ); ?></h4>
			<p><?php esc_html_e( 'The ActivityPub header image is now used if available, giving friend profiles a richer look.', 'friends' ); ?></p>
		</div>

	</div>

	<?php if ( ! empty( $statuses ) ) : ?>
	<h2><?php esc_html_e( 'Migration Status', 'friends' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'These migrations update your data for Friends 4.0. Batched migrations process data in the background and may take a while to complete. Reload the page to check the current status.', 'friends' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped" id="friends-migrations-table">
		<thead>
			<tr>
				<th style="width: 30%;"><?php esc_html_e( 'Migration', 'friends' ); ?></th>
				<th style="width: 40%;"><?php esc_html_e( 'Description', 'friends' ); ?></th>
				<th style="width: 15%;"><?php esc_html_e( 'Status', 'friends' ); ?></th>
				<th style="width: 15%;"><?php esc_html_e( 'Actions', 'friends' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $statuses as $migration_id => $migration_status ) : ?>
			<tr data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
				<td>
					<strong><?php echo esc_html( $migration_status['title'] ); ?></strong>
					<?php if ( $migration_status['batched'] ) : ?>
						<span class="dashicons dashicons-database" title="<?php esc_attr_e( 'Batched migration', 'friends' ); ?>"></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $migration_status['description'] ); ?></td>
				<td class="migration-status">
					<?php Migration::render_status_badge( $migration_status ); ?>
				</td>
				<td>
					<?php if ( ! empty( $migration_status['in_progress'] ) ) : ?>
						<button type="button" class="button button-primary process-batch" data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
							<?php esc_html_e( 'Process Now', 'friends' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button button-secondary run-migration" data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
							<?php esc_html_e( 'Run', 'friends' ); ?>
						</button>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

		<?php if ( $all_complete ) : ?>
		<div class="friends-news-status-complete">
			<p>
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'All migrations have completed successfully. You are all set!', 'friends' ); ?>
			</p>
		</div>
		<?php elseif ( $has_in_progress ) : ?>
		<div class="friends-news-status-progress">
			<p>
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Some migrations are still processing in the background. You can continue using the plugin while they complete.', 'friends' ); ?>
			</p>
		</div>
		<?php endif; ?>

	<p class="submit">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=friends-migrations' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Migrations Overview', 'friends' ); ?>
		</a>
	</p>
	<?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
	$('.process-batch').on('click', function() {
		var $button = $(this);
		var $row = $button.closest('tr');
		var migrationId = $button.data('migration-id');

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'friends' ) ); ?>');

		$.post(ajaxurl, {
			action: 'friends_process_migration_batch',
			migration_id: migrationId,
			_wpnonce: '<?php echo esc_js( wp_create_nonce( 'friends-process-batch' ) ); ?>'
		}, function(response) {
			if (response.success) {
				$row.find('.migration-status').html(response.data.html);
				if (response.data.in_progress) {
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Process Now', 'friends' ) ); ?>');
				} else {
					$button.removeClass('button-primary process-batch').addClass('button-secondary run-migration')
						.prop('disabled', false).text('<?php echo esc_js( __( 'Run', 'friends' ) ); ?>');
				}
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Processing failed.', 'friends' ) ); ?>');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Process Now', 'friends' ) ); ?>');
			}
		}).fail(function() {
			alert('<?php echo esc_js( __( 'Request failed.', 'friends' ) ); ?>');
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Process Now', 'friends' ) ); ?>');
		});
	});

	$('.run-migration').on('click', function() {
		var $button = $(this);
		var $row = $button.closest('tr');
		var migrationId = $button.data('migration-id');

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Running...', 'friends' ) ); ?>');

		$.post(ajaxurl, {
			action: 'friends_run_migration',
			migration_id: migrationId,
			_wpnonce: '<?php echo esc_js( wp_create_nonce( 'friends-run-migration' ) ); ?>'
		}, function(response) {
			if (response.success) {
				if (response.data.batched) {
					pollMigrationStatus(migrationId, $row, $button);
				} else {
					refreshMigrationStatus(migrationId, $row, $button);
				}
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Migration failed.', 'friends' ) ); ?>');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Run', 'friends' ) ); ?>');
			}
		}).fail(function() {
			alert('<?php echo esc_js( __( 'Request failed.', 'friends' ) ); ?>');
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Run', 'friends' ) ); ?>');
		});
	});

	function pollMigrationStatus(migrationId, $row, $button) {
		$.post(ajaxurl, {
			action: 'friends_get_migration_status',
			migration_id: migrationId,
			_wpnonce: '<?php echo esc_js( wp_create_nonce( 'friends-migration-status' ) ); ?>'
		}, function(response) {
			if (response.success) {
				$row.find('.migration-status').html(response.data.html);
				if (response.data.in_progress) {
					setTimeout(function() {
						pollMigrationStatus(migrationId, $row, $button);
					}, 2000);
				} else {
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Run', 'friends' ) ); ?>');
				}
			}
		});
	}

	function refreshMigrationStatus(migrationId, $row, $button) {
		$.post(ajaxurl, {
			action: 'friends_get_migration_status',
			migration_id: migrationId,
			_wpnonce: '<?php echo esc_js( wp_create_nonce( 'friends-migration-status' ) ); ?>'
		}, function(response) {
			if (response.success) {
				$row.find('.migration-status').html(response.data.html);
			}
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Run', 'friends' ) ); ?>');
		});
	}
});
</script>
