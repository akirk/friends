<?php
/**
 * This template contains the raw rules editor.
 *
 * @version 1.0
 * @package Friends
 */

?>
<p class="description">
<?php
echo wp_kses(
	// translators: %s is a URL.
	sprintf( __( 'To allow copying rules between friends, <a href=%s>view the raw rules data</a>.', 'friends' ), '"#" id="toggle-raw-rules-data"' ),
	array(
		'a' => array(
			'href' => array(),
			'id'   => array(),
		),
	)
);
?>
</p>
<div id="raw-rules-data" style="display: none">
	<h2><?php esc_html_e( 'Raw Rules Data', 'friends' ); ?></h2>
	<form method="post">
		<?php wp_nonce_field( 'friend-rules-raw-' . $args['friend']->user_login ); ?>
		<table>
			<tbody>
				<tr>
					<th><label for="field"><?php esc_html_e( 'Rules as JSON', 'friends' ); ?></label></th>
					<td>
						<textarea name="rules" style="font-family: monospace" rows="5" cols="80"><?php echo esc_html( wp_json_encode( $args['rules'], JSON_PRETTY_PRINT ) ); ?></textarea>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="submit">
			<input type="submit" name="friend-rules-raw" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
		</p>
	</form>
</div>
