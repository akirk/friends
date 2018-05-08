<form method="post">
	<?php wp_nonce_field( 'friends-settings' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Friend Requests', 'friends' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><span><?php esc_html_e( 'Membership', 'friends' ); ?></span></legend>
						<label for="friends_ignore_incoming_friend_requests">
							<input name="friends_ignore_incoming_friend_requests" type="checkbox" id="friends_ignore_incoming_friend_requests" value="1" <?php checked( '1', get_option( 'friends_ignore_incoming_friend_requests' ) ); ?>>
							<?php esc_html_e( 'Ignore incoming friend requests' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
