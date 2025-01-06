<?php
/**
 * This template contains the browser extension settings
 *
 * @version 1.0
 * @package Friends
 */

?><form method="post">
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
					<p class="description"><?php esc_html_e( 'With this API key, more features will be enabled in the browser extension.', 'friends' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
</form>
