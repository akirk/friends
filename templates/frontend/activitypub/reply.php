<?php
/**
 * This template contains the form for replying to a URL from the frontend.
 *
 * @version 1.0
 * @package Friends
 */

?><form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="quick-post-panel" class="<?php echo esc_html( $args['form_class'] ); ?>">
	<?php wp_nonce_field( 'friends_publish' ); ?>
	<input type="hidden" name="action" value="friends_publish" />
	<input type="hidden" name="format" value="status" />
	<input type="hidden" name="status" value="publish" />

	<label for="boost"><?php esc_html_e( 'Reply to this URL:', 'friends' ); ?></label>
	<small>
	<?php
	echo wp_kses(
		sprintf(
		// translators: %s is a URL.
			__( 'You could also <a href=%s>boost</a> this.', 'friends' ),
			'"' . ( ! empty( $args['in_reply_to'] ) ? ( '?boost=' . urlencode( $args['in_reply_to']['url'] ) ) : '' ) . '" class="boost-link"'
		),
		array(
			'a' => array(
				'href'  => array(),
				'class' => array(),
			),
		)
	);
	?>
	</small>
	<input class="form-input activitypub_preview_url" type="url" name="in_reply_to" placeholder="<?php esc_attr_e( 'In reply to https://...', 'friends' ); ?>" value="<?php echo esc_attr( ! empty( $args['in_reply_to'] ) ? $args['in_reply_to']['url'] : '' ); ?>" autocomplete="off"/>
	<div class="activitypub_preview"><?php echo wp_kses_post( ! empty( $args['in_reply_to'] ) ? $args['in_reply_to']['html'] : '' ); ?></div>
	<p class="description"><?php esc_html_e( 'Click the mentions to copy them into your reply.', 'friends' ); ?></p>
	<div class="form-group<?php echo esc_attr( $args['blocks-everywhere'] ? ' blocks-everywhere iso-editor__loading' : '' ); ?>">
		<label class="form-label" for="content"><?php esc_html_e( 'Text', 'friends' ); ?></label>
		<textarea class="form-input friends-status-content<?php echo esc_attr( $args['blocks-everywhere'] ? ' blocks-everywhere-enabled' : '' ); ?>" name="content" rows="5" cols="70" placeholder="<?php echo /* translators: %s is a user display name. */ esc_attr( sprintf( __( "What's on your mind, %s?", 'friends' ), wp_get_current_user()->display_name ) ); ?>"><?php echo wp_kses_post( ! empty( $args['in_reply_to'] ) ? ( $args['blocks-everywhere'] ? '<!-- wp:paragraph -->' . PHP_EOL . '<p>' . $args['in_reply_to']['mention'] . PHP_EOL . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL : $args['in_reply_to']['mention'] . ' ' ) : '' ); ?></textarea><br />
		<?php
		do_action( 'friends_post_status_form' );
		?>
	</div>

	<div class="form-group col-4">
		<select name="status" class="form-select">
			<option value="publish"><?php esc_html_e( 'Visible to everyone', 'friends' ); ?></option>
			<option value="private"><?php esc_html_e( 'Only visible to my friends', 'friends' ); ?></option>
		</select>
	</div>

	<div class="form-group col-4">
		<p>
		<?php
		echo esc_html(
			sprintf(
				// translators: %s is the name of a post format.
				__( 'Post Format: %s', 'friends' ),
				__( 'Status' ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			)
		);
		?>
	</p>
	</div>

	<div class="form-group">
		<button class="btn"><?php esc_html_e( 'Publish Reply', 'friends' ); ?></button>
		<a href="#" class="quick-post-panel-toggle"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Cancel' ); ?></a>
	</div>
</form>
