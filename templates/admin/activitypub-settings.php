<?php
/**
 * This template contains the Friends Settings.
 *
 * @package Friends
 */

?>
<form method="post">
	<?php wp_nonce_field( 'friends-activitypub-settings' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Boosts', 'friends' ); ?></th>
				<td>
					<fieldset>
						<label for="activitypub_reblog">
							<input name="activitypub_reblog" type="checkbox" id="activitypub_reblog" value="1" <?php checked( $args['reblog'] ); ?> />
							<span><?php esc_html_e( 'When you boost a status, also reblog it.', 'friends' ); ?></span>
						</label>
					</fieldset>
					<p class="description"><?php esc_html_e( 'If unchecked, the boosting will only happen via ActivityPub.', 'friends' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>

	<p class="description">
	<?php
	echo wp_kses(
		sprintf(
			// translators: %s is the URL of the Friends admin menu.
			__( 'Find further settings around ActivityPub in the <a href=%s>ActivityPub plugin settings</a>.', 'friends' ),
			'"' . admin_url( 'options-general.php?page=activitypub&tab=settings' ) . '"'
		),
		array(
			'a' => array( 'href' => array() ),
		)
	);
	?>
	</p>
</form>
<?php
