<?php
/**
 * The /friends/ header for the status feed
 *
 * @version 1.0
 * @package Friends
 */

?>
<details class="column col-8 col-sm-12 col-mx-auto">
	<summary class="quick-status-panel-opener"><?php esc_html_e( 'Quick post panel', 'friends' ); ?></summary>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" class="friends-post-inline">
		<?php wp_nonce_field( 'friends_publish' ); ?>
		<input type="hidden" name="action" value="friends_publish" />
		<input type="hidden" name="format" value="status" />
		<input type="hidden" name="status" value="publish" />
			<div class="form-group">
				<div class="has-icon-right">
					Reply to: <input class="form-input" type="url" name="in_reply_to" placeholder="<?php esc_attr_e( 'In reply to https://...', 'friends' ); ?>" value="<?php echo esc_attr( ! empty( $args['in_reply_to'] ) ? $args['in_reply_to']['url'] : '' ); ?>" id="friends_in_reply_to" autocomplete="off"/>
					<i class="form-icon"></i>
				</div>
			</div>
			<div id="in_reply_to_preview"><?php echo wp_kses_post( ! empty( $args['in_reply_to'] ) ? $args['in_reply_to']['html'] : '' ); ?></div>
			<p class="description">Click the mentions to copy them into your reply.</p>
			<div class="form-group blocks-everywhere iso-editor__loading">
				<textarea class="form-input friends-status-content" name="content" rows="5" cols="70" placeholder="<?php echo /* translators: %s is a user display name. */ esc_attr( sprintf( __( "What's on your mind, %s?", 'friends' ), wp_get_current_user()->display_name ) ); ?>"><?php echo wp_kses_post( ! empty( $args['in_reply_to'] ) ? '<!-- wp:paragraph -->' . PHP_EOL . '<p>' . $args['in_reply_to']['mention'] . PHP_EOL . '</p>' . PHP_EOL . '<!-- /wp:paragraph -->' . PHP_EOL : '' ); ?></textarea><br />
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
				<button class="btn"><?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Publish' ); ?></button>
			</div>

	</form>
</details>
