<?php
/**
 * This template contains the Automatic Status list table.
 *
 * @version 1.0
 * @package Friends
 */

// Much of this page has been adapted from wp-admin/edit.php.

?><div class="wrap">
<h1 class="wp-heading-inline">
<?php
esc_html_e( 'Automatically Generated Statuses', 'friends' );
?>
</h1>

<hr class="wp-header-end">

<p><?php esc_html_e( 'For certain actions that you take in the Friends plugin, a status post is created automatically. Here you can review them and publish them as you like.', 'friends' ); ?></p>
<details>
	<summary><?php esc_html_e( /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ 'Settings' ); ?></summary>
	<form method="post">
	<?php wp_nonce_field( 'friends-automatic-status' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<td><input type="checkbox" name="enabled" <?php checked( ! get_option( 'friends_automatic_status_disabled' ) ); ?> id="automatic-status-enabled" />
				<label for="automatic-status-enabled"><?php esc_html_e( 'Enable automatic status', 'friends' ); ?></label>
				<p class="description"><?php esc_html_e( 'When disabled, no draft posts will be automatically created.', 'friends' ); ?></p>
			</td>
			</tr>
		</tbody>
	</table>

	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ esc_html_e( 'Save Changes' ); ?>">
	</p>
</form>
</details>

<?php $args['wp_list_table']->views(); ?>

<form id="posts-filter" method="get" action="edit.php">

<input type="hidden" name="post_status" class="post_status_page" value="draft" />
<input type="hidden" name="post_format" value="status" />
<input type="hidden" name="post_type" class="post_type_page" value="<?php echo esc_attr( $args['post_type'] ); ?>" />


<?php $args['wp_list_table']->display(); ?>

</form>

<?php
if ( $args['wp_list_table']->has_items() ) {
	$args['wp_list_table']->inline_edit();
}
?>

	</div>
