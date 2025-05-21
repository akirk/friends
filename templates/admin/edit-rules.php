<?php
/**
 * This template contains the rules editor.
 *
 * @version 1.0
 * @package Friends
 */

?><form method="post" id="edit-rules">
	<?php wp_nonce_field( 'edit-friend-rules-' . sanitize_user( $args['friend']->user_login ) ); ?>
	<input type="hidden" name="friend" value="<?php echo esc_attr( $args['friend']->user_login ); ?>" />
	<p class="description"><?php esc_html_e( 'By specifying rules, you can automatically accept, trash, or transform individual feed items, thus filter incoming posts according to your interest.', 'friends' ); ?></p>
	<p class="description"><?php esc_html_e( 'Save changes to add another rule, leave the rule text empty to delete the rule.', 'friends' ); ?></p>
	<table>
		<tbody>
			<?php foreach ( $args['rules'] as $rule ) : ?>
			<tr>
				<th>
					<select name="rules[field][]">
						<option value="title" <?php selected( 'title', $rule['field'] ); ?>><?php echo esc_html_e( 'If the title contains', 'friends' ); ?></option>
						<option value="content" <?php selected( 'content', $rule['field'] ); ?>><?php echo esc_html_e( 'If the content contains', 'friends' ); ?></option>
						<option value="author" <?php selected( 'author', $rule['field'] ); ?>><?php echo esc_html_e( 'If the author contains', 'friends' ); ?></option>
						<option value="permalink" <?php selected( 'permalink', $rule['field'] ); ?>><?php echo esc_html_e( 'If the URL contains', 'friends' ); ?></option>
					</select>
				</th>
				<td><input type="text" name="rules[regex][]" value="<?php echo esc_textarea( $rule['regex'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter a text or regular expression', 'friends' ); ?>" /></td>
				<td>
					<select name="rules[action][]" class="rule-action">
						<option value="accept" <?php selected( 'accept', $rule['action'] ); ?>><?php echo esc_html_e( 'accept the item', 'friends' ); ?></option>
						<option value="trash" <?php selected( 'trash', $rule['action'] ); ?>><?php echo esc_html_e( 'trash the item', 'friends' ); ?></option>
						<option value="delete" <?php selected( 'delete', $rule['action'] ); ?>><?php echo esc_html_e( 'delete the item', 'friends' ); ?></option>
						<option value="replace" <?php selected( 'replace', $rule['action'] ); ?>><?php echo esc_html_e( 'replace the match with this:', 'friends' ); ?></option>
					</select>
				</td>
				<td style="<?php echo esc_attr( 'replace' !== $rule['action'] ? 'display: none' : '' ); ?>" class="replace-with"><input type="text" name="rules[replace][]" value="<?php echo esc_textarea( isset( $rule['replace'] ) ? $rule['replace'] : '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter the text to replace it with', 'friends' ); ?>" /></td>
				<?php if ( empty( $rule['regex'] ) ) : ?>
					<td><span class="description">(<?php esc_html_e( 'Unsubmitted rule', 'friends' ); ?>)</span></td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
		<tr>
			<th><label for="field"><?php esc_html_e( 'In any other case', 'friends' ); ?></label></th>
			<td colspan="3">
				<select name="catch_all">
					<option value="accept" <?php selected( 'accept', $args['catch_all'] ); ?>><?php echo esc_html_e( 'accept the item', 'friends' ); ?></option>
					<option value="trash" <?php selected( 'trash', $args['catch_all'] ); ?>><?php echo esc_html_e( 'trash the item', 'friends' ); ?></option>
					<option value="delete" <?php selected( 'delete', $args['catch_all'] ); ?>><?php echo esc_html_e( 'delete the item', 'friends' ); ?></option>
				</select>
			</td>
		</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>

<p class="description"><?php esc_html_e( 'See how the rules apply to these items:', 'friends' ); ?> <button id="refresh-preview-rules" data-friend="<?php echo esc_attr( $args['friend']->user_login ); ?>" data-post="<?php echo $args['post'] ? esc_attr( $args['post']->ID ) : ''; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'edit-friend-rules-' . sanitize_user( $args['friend']->user_login ) ) ); ?>"><?php esc_html_e( 'Refresh', 'friends' ); ?></button></p>
