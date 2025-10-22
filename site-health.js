/**
 * Site Health JavaScript functionality for Friends plugin
 */

function friendsRestartMigration(button) {
	if (confirm(friendsSiteHealth.confirmRestart)) {
		button.disabled = true;
		button.textContent = 'Starting...';
		friendsRunMigration();
	}
}

function friendsCleanupPostTags(button) {
	if (confirm(friendsSiteHealth.confirmCleanup)) {
		button.disabled = true;
		button.textContent = 'Cleaning up...';
		friendsRunCleanup();
	}
}

function friendsRunMigration() {
	fetch(friendsSiteHealth.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams({
			action: 'friends_restart_migration',
			nonce: friendsSiteHealth.restartMigrationNonce
		})
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			alert(data.data.message);
			location.reload();
		} else {
			alert('Error: ' + (data.data || 'Unknown error'));
		}
	})
	.catch(error => {
		console.error('Error:', error);
		alert('An error occurred while starting the migration.');
	});
}

function friendsRunCleanup() {
	fetch(friendsSiteHealth.ajaxUrl, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		body: new URLSearchParams({
			action: 'friends_cleanup_post_tags',
			nonce: friendsSiteHealth.cleanupPostTagsNonce
		})
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			alert(data.data.message);
			location.reload();
		} else {
			alert('Error: ' + (data.data || 'Unknown error'));
		}
	})
	.catch(error => {
		console.error('Error:', error);
		alert('An error occurred while cleaning up tags.');
	});
}