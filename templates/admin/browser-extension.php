<?php
/**
 * This template contains the browser extension settings
 *
 * @version 1.1
 * @package Friends
 */

?>
<div class="friends-browser-extension-promo card" style="max-width: 100%; padding: 1em 1.5em;">
	<h2><?php esc_html_e( 'Friends Browser Extension', 'friends' ); ?></h2>
	<p><?php esc_html_e( 'The browser extension lets you subscribe to any site you are visiting with one click — no more copy-pasting URLs into the Add Friend page.', 'friends' ); ?></p>
	<p><?php esc_html_e( 'With your API key (below), the extension also exposes quick actions from your toolbar — for example, saving the current page to a collection or sending it to your eReader (if you have those companion plugins installed).', 'friends' ); ?></p>
	<p>
		<a class="button button-primary" href="https://chromewebstore.google.com/detail/friends/ledbghpaplkpclndlommpbokndieflhl" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Install for Chrome', 'friends' ); ?>
		</a>
		<a class="button button-primary" href="https://addons.mozilla.org/en-US/firefox/addon/wpfriends/" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Install for Firefox', 'friends' ); ?>
		</a>
	</p>
</div>

<form method="post">
	<?php wp_nonce_field( 'friends-browser-extension' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="api-key"><?php esc_html_e( 'API Key', 'friends' ); ?></label></th>
				<td>
					<input type="text" id="api-key" value="<?php echo esc_attr( $args['browser-api-key'] ); ?>" class="regular-text" readonly />
					<button class="button" id="copy-api-key"><?php esc_html_e( 'Copy', 'friends' ); ?></button>
					<!-- Revoke API Key -->
					<?php if ( ! empty( $args['browser-api-key'] ) ) : ?>
						<button class="button button-destructive" name="revoke-api-key"><?php esc_html_e( 'Revoke', 'friends' ); ?></button>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Paste this API key into the browser extension to enable additional features such as plugin-provided actions.', 'friends' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
</form>
