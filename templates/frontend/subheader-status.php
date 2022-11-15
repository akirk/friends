<?php
/**
 * The /friends/ header for the status feed
 *
 * @version 1.0
 * @package Friends
 */

?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" class="friends-post-inline columns">
			<?php wp_nonce_field( 'friends_publish' ); ?>
			<input type="hidden" name="action" value="friends_publish" />
			<input type="hidden" name="format" value="status" />

			<div class="column col-6 col-sm-12 col-mx-auto">
				<div class="form-group blocks-everywhere iso-editor__loading">
					<textarea class="form-input friends-status-content" name="content" rows="5" cols="70" placeholder="<?php echo /* translators: %s is a user display name. */ esc_attr( sprintf( __( "What's on your mind, %s?", 'friends' ), wp_get_current_user()->display_name ) ); ?>"></textarea><br />
					<?php
					do_action( 'friends_post_status_form' );
					?>

				</div>

				<div class="form-group" style="float:right">
					<label class="form-checkbox">
						<input type="checkbox" name="status" value="private" />
						<i class="form-icon"></i> <?php esc_html_e( 'Post just to friends', 'friends' ); ?>
					</label>
				</div>

				<div class="form-group">
					<button class="btn"><?php esc_html_e( 'Post Status', 'friends' ); ?></button>
				</div>

			</div>
		</form>
