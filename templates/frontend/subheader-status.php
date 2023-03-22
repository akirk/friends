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

			<div class="form-group blocks-everywhere iso-editor__loading">
				<textarea class="form-input friends-status-content" name="content" rows="5" cols="70" placeholder="<?php echo /* translators: %s is a user display name. */ esc_attr( sprintf( __( "What's on your mind, %s?", 'friends' ), wp_get_current_user()->display_name ) ); ?>"></textarea><br />
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
