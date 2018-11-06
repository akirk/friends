<?php
/**
 * This template contains the Admin Send Friend Request form.
 *
 * @package Friends
 */

?><div class="wrap"><form method="post">
	<?php wp_nonce_field( 'save-bookmark' ); ?>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="url"><?php esc_html_e( 'URL', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="url" name="url" value="<?php echo esc_attr( $url ); ?>" required placeholder="<?php _e( 'Enter the URL to bookmark', 'friends' ); ?>" class="regular-text" />
				</td>
			</tr>
		</tbody>
	</table>

	<input type="submit" name="save" class="button button-primary" value="<?php echo esc_attr_x( 'Save', 'button', 'friends' ); ?>" />
</form>

