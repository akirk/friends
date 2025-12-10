<?php
/**
 * This template contains the admin migrations page.
 *
 * @package Friends
 */

namespace Friends;

?><div class="wrap">
	<h1><?php esc_html_e( 'Friends Migrations', 'friends' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'This page shows all migrations and allows you to run them manually if needed.', 'friends' ); ?>
		<?php esc_html_e( 'Migrations run automatically when upgrading from a version lower than the one shown.', 'friends' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped" id="friends-migrations-table">
		<thead>
			<tr>
				<th style="width: 15%;"><?php esc_html_e( 'Runs when upgrading to', 'friends' ); ?></th>
				<th style="width: 25%;"><?php esc_html_e( 'Migration', 'friends' ); ?></th>
				<th style="width: 35%;"><?php esc_html_e( 'Description', 'friends' ); ?></th>
				<th style="width: 15%;"><?php esc_html_e( 'Status', 'friends' ); ?></th>
				<th style="width: 10%;"><?php esc_html_e( 'Actions', 'friends' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $args['statuses'] as $migration_id => $migration_status ) : ?>
			<tr data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
				<td><code><?php echo esc_html( $migration_status['version'] ); ?></code></td>
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
							<?php esc_html_e( 'Process Batch Now', 'friends' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button button-secondary run-migration" data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
							<?php esc_html_e( 'Run', 'friends' ); ?>
						</button>
					<?php endif; ?>
					<?php if ( has_action( 'friends_migration_debug_' . $migration_id ) ) : ?>
						<button type="button" class="button button-small toggle-debug" data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
							<?php esc_html_e( 'Debug', 'friends' ); ?>
						</button>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="migration-debug-row" data-migration-id="<?php echo esc_attr( $migration_id ); ?>" style="display: none;">
				<td colspan="5" style="padding: 0;"></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Plugin Information', 'friends' ); ?></h2>
	<form method="post" id="friends-version-form">
		<?php wp_nonce_field( 'friends-update-version' ); ?>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Plugin Version', 'friends' ); ?></th>
				<td><code><?php echo esc_html( Friends::VERSION ); ?></code></td>
			</tr>
			<tr>
				<th><label for="stored_version"><?php esc_html_e( 'Stored Version', 'friends' ); ?></label></th>
				<td>
					<input type="text" name="stored_version" id="stored_version" value="<?php echo esc_attr( get_option( 'friends_plugin_version', '' ) ); ?>" class="regular-text" />
					<p class="description">
						<?php esc_html_e( 'This is the version the plugin thinks it was last upgraded to.', 'friends' ); ?>
						<?php esc_html_e( 'Set to a lower version (e.g., "4.0.0") to re-run migrations for newer versions.', 'friends' ); ?>
						<?php esc_html_e( 'Clear it to run all migrations on next page load.', 'friends' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="update_version" class="button button-primary" value="<?php esc_attr_e( 'Update Stored Version', 'friends' ); ?>" />
			<button type="button" class="button button-secondary" id="reset-to-current"><?php esc_html_e( 'Reset to Current', 'friends' ); ?></button>
			<button type="button" class="button button-secondary" id="clear-version"><?php esc_html_e( 'Clear (Force All Migrations)', 'friends' ); ?></button>
		</p>
	</form>

	<?php
	/**
	 * Hook to add debug information to the migrations page.
	 * Used by friends-debugger to show detailed migration diagnostics.
	 *
	 * @param array $statuses All migration statuses.
	 */
	do_action( 'friends_migrations_debug', $args['statuses'] );
	?>
</div>

<script>
jQuery(document).ready(function($) {
	$('#reset-to-current').on('click', function() {
		$('#stored_version').val('<?php echo esc_js( Friends::VERSION ); ?>');
	});

	$('#clear-version').on('click', function() {
		$('#stored_version').val('');
	});

	$('.toggle-debug').on('click', function() {
		var $button = $(this);
		var migrationId = $button.data('migration-id');
		var $debugRow = $('.migration-debug-row[data-migration-id="' + migrationId + '"]');

		if ($debugRow.is(':visible')) {
			$debugRow.hide().find('td').html('');
			$button.text('<?php echo esc_js( __( 'Debug', 'friends' ) ); ?>');
			return;
		}

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Loading...', 'friends' ) ); ?>');

		$.post(ajaxurl, {
			action: 'friends_get_migration_debug',
			migration_id: migrationId,
			_wpnonce: '<?php echo esc_js( wp_create_nonce( 'friends-migration-debug' ) ); ?>'
		}, function(response) {
			if (response.success) {
				$debugRow.find('td').html(response.data.html);
				$debugRow.show();
				$button.text('<?php echo esc_js( __( 'Hide Debug', 'friends' ) ); ?>');
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Failed to load debug info.', 'friends' ) ); ?>');
			}
			$button.prop('disabled', false);
		}).fail(function() {
			alert('<?php echo esc_js( __( 'Request failed.', 'friends' ) ); ?>');
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Debug', 'friends' ) ); ?>');
		});
	});

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
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Process Batch Now', 'friends' ) ); ?>');
				} else {
					$button.removeClass('button-primary process-batch').addClass('button-secondary run-migration')
						.prop('disabled', false).text('<?php echo esc_js( __( 'Run', 'friends' ) ); ?>');
				}
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Processing failed.', 'friends' ) ); ?>');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Process Batch Now', 'friends' ) ); ?>');
			}
		}).fail(function() {
			alert('<?php echo esc_js( __( 'Request failed.', 'friends' ) ); ?>');
			$button.prop('disabled', false).text('<?php echo esc_js( __( 'Process Batch Now', 'friends' ) ); ?>');
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

<style>
#friends-migrations-table .status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}
#friends-migrations-table .status-completed {
	background: #d4edda;
	color: #155724;
}
#friends-migrations-table .status-pending {
	background: #fff3cd;
	color: #856404;
}
#friends-migrations-table .status-in-progress {
	background: #cce5ff;
	color: #004085;
}
#friends-migrations-table .status-no-tracking {
	background: #e2e3e5;
	color: #383d41;
}
#friends-migrations-table .progress-bar {
	width: 100%;
	height: 8px;
	background: #e0e0e0;
	border-radius: 4px;
	margin-top: 5px;
	overflow: hidden;
}
#friends-migrations-table .progress-bar-fill {
	height: 100%;
	background: #0073aa;
	transition: width 0.3s ease;
}
</style>
