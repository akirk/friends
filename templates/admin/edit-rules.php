<?php
/**
 * This template contains the rules editor.
 *
 * @package Friends
 */

?><form method="post" id="edit-rules">
	<?php wp_nonce_field( 'edit-friend-rules-' . $friend->ID ); ?>
	<input type="hidden" name="friend" value="<?php echo esc_attr( $friend->ID ); ?>" />
	<p class="description"><?php esc_html_e( "By specifying rules for this feed, you can automatically accept or trash individual feed items, thus filter this friend's post according to your interest.", 'friends' ); ?></p>
	<p class="description"><?php esc_html_e( 'Save changes to add another rule, leave the rule text empty to delete the rule.', 'friends' ); ?></p>
	<table>
		<tbody>
			<?php foreach ( $rules as $rule ) : ?>
			<tr>
				<th>
					<select name="rules[field][]">
						<option value="title" <?php selected( 'title', $rule['field'] ); ?>><?php echo esc_html_e( 'If the title contains', 'friends' ); ?></option>
						<option value="content" <?php selected( 'content', $rule['field'] ); ?>><?php echo esc_html_e( 'If the content contains', 'friends' ); ?></option>
						<option value="author" <?php selected( 'author', $rule['field'] ); ?>><?php echo esc_html_e( 'If the author contains', 'friends' ); ?></option>
						<option value="permalink" <?php selected( 'permalink', $rule['field'] ); ?>><?php echo esc_html_e( 'If the URL contains', 'friends' ); ?></option>
					</select>
				</th>
				<td><input type="text" name="rules[regex][]" value="<?php echo esc_attr( $rule['regex'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter a text or regular expression', 'friends' ); ?>" /></td>
				<td>
					<select name="rules[action][]" class="rule-action">
						<option value="accept" <?php selected( 'accept', $rule['action'] ); ?>><?php echo esc_html_e( 'accept the item', 'friends' ); ?></option>
						<option value="trash" <?php selected( 'trash', $rule['action'] ); ?>><?php echo esc_html_e( 'trash the item', 'friends' ); ?></option>
						<option value="delete" <?php selected( 'delete', $rule['action'] ); ?>><?php echo esc_html_e( 'delete the item', 'friends' ); ?></option>
						<option value="replace" <?php selected( 'replace', $rule['action'] ); ?>><?php echo esc_html_e( 'replace the match with this:', 'friends' ); ?></option>
					</select>
				</td>
				<td style="
				<?php
				if ( 'replace' !== $rule['action'] ) {
					echo 'display: none';}
				?>
				" class="replace-with"><input type="text" name="rules[replace][]" value="<?php echo esc_attr( $rule['replace'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter the text to replace it with', 'friends' ); ?>" /></td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<th><label for="field"><?php esc_html_e( 'In any other case', 'friends' ); ?></label></th>
			<td colspan="3">
				<select name="catch_all">
					<option value="accept" <?php selected( 'accept', $catch_all ); ?>><?php echo esc_html_e( 'accept the item', 'friends' ); ?></option>
					<option value="trash" <?php selected( 'trash', $catch_all ); ?>><?php echo esc_html_e( 'trash the item', 'friends' ); ?></option>
					<option value="delete" <?php selected( 'delete', $catch_all ); ?>><?php echo esc_html_e( 'delete the item', 'friends' ); ?></option>
				</select>
			</td>
		</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
