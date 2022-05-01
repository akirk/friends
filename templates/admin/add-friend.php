<?php
/**
 * This template contains the Admin Send Friend Request form.
 *
 * @version 1.0
 * @package Friends
 */

$quick_subscribe = _x( 'Quick Subscribe', 'button', 'friends' );

$links = get_bookmarks(
	array(
		'orderby' => 'updated',
		'limit'   => 15,
	)
);

?><div class="wrap"><form method="post">
	<?php wp_nonce_field( 'add-friend' ); ?>
	<p>
		<?php esc_html_e( 'To follow a friend or to subscribe to any other feed, enter their URL here.', 'friends' ); ?>
		<?php esc_html_e( 'You can either enter a feed URL directly or the main URL which will contain pointers to the available feeds.', 'friends' ); ?>
		<?php
		// translators: %s is a URL.
		echo wp_kses( sprintf( __( 'For quick adding or following you can also <a href=%s>use a bookmarklet</a>.', 'friends' ), esc_url( self_admin_url( 'tools.php' ) ) ), array( 'a' => array( 'href' => array() ) ) );
		?>
	</p>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="friend_url"><?php esc_html_e( 'Site', 'friends' ); ?></label></th>
				<td>
					<input type="text" autofocus id="friend_url" name="friend_url" value="<?php echo esc_attr( $args['friend_url'] ); ?>" required placeholder="<?php esc_attr_e( 'Enter URL or search suggestions', 'friends' ); ?>" class="regular-text" />
					<p class="description" id="friend_url-description">
						<?php esc_html_e( "In the next step we'll give you a selection of available feeds.", 'friends' ); ?>
					</p>
				</td>
			</tr>
			<tr class="friend-suggestions" data-nonce="<?php echo esc_attr( wp_create_nonce( 'friends-links' ) ); ?>">
				<th scope="row"><?php esc_html_e( 'Suggestions', 'friends' ); ?></label></th>
				<td>
					<div>
						<?php
						if ( empty( $links ) ) {
							esc_html_e( 'No suggestions available. You can import an OPML.', 'friends' );
						} else {
							Friends\Friends::template_loader()->get_template_part( 'admin/links', null, array( 'links' => $links ) );
						}
						?>
					</div>
					<p class="description" id="friend-suggestions">
						<?php
						printf(
							// translators: %s is a URL.
							__( 'You can manage the available suggestions in the <a href="%s">Link Manager</a>.', 'friends' ),
							esc_url( self_admin_url( 'link-manager.php' ) )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<input type="submit" name="step2" class="button button-primary" value="<?php echo esc_attr_x( 'Next »', 'button', 'friends' ); ?>" />

					<input type="submit" name="quick-subscribe" class="button" value="<?php echo esc_attr( $quick_subscribe ); ?>" />

					<p class="description" id="quick-subscribe-description">
						<?php
						// translators: %s is the text for the Quick Subscribe button.
						echo wp_kses( sprintf( __( '<em>%s</em> will skip the next step and just subscribe you.', 'friends' ), $quick_subscribe ), array( 'em' => array() ) );
						?>
					</p>

				</td>
			</tr>
		</tbody>
	</table>
</form>
