<?php
/**
 * This template shows the Unfriend page.
 *
 * @version 1.0
 * @package Friends
 */

$heading = sprintf(
	// translators: %s is a username.
	_x( 'Unfriend %s', 'heading', 'friends' ),
	$args['friend']->user_login
);

$button_text = sprintf(
	// translators: %s is a username.
	_x( 'Delete %s', 'action', 'friends' ),
	$args['friend']->user_login
);
if ( is_multisite() ) {
	$button_text = sprintf(
	// translators: %s is a username.
		_x( 'Remove %s', 'action', 'friends' ),
		$args['friend']->user_login
	);
}
$numfeeds = count( $args['friend']->get_active_feeds() );

?>
<div class="wrap">
<h3><?php echo esc_html( $heading ); ?></h3>
<form method="post">
	<?php wp_nonce_field( 'unfriend-' . $args['friend']->user_login ); ?>
	<p><?php esc_html_e( 'Since friends correspond to WordPress users, unfriending a user means to delete the user.', 'friends' ); ?></p>
	<h4>
	<?php
			echo get_avatar( $args['friend']->user_login, 24 );
			echo ' ';
			echo esc_html( $args['friend']->user_login );
	?>
	</h4>
	<ul class="ul-disc">
		<li>
			<a href="<?php echo esc_url( $args['friend']->get_local_friends_page_url() ); ?>">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: Number of posts. */
					_n( '%s post', '%s posts', $args['friend_posts'] ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					number_format_i18n( $args['friend_posts'] )
				)
			);
			?>
		</li>
		<li>
			<a href="<?php echo esc_url( self_admin_url( 'admin.php?page=edit-friend-feeds&user=' . $args['friend']->user_login ) ); ?>">
								<?php
								echo esc_html(
									sprintf(
									/* translators: %s is the number of feeds */
										_n( '%s feed', '%s feeds', $numfeeds, 'friends' ),
										number_format_i18n( $numfeeds )
									)
								);
								?>
			</a>
		</li>
	</ul>
	<p class="description">
	<?php
	esc_html_e( 'This will delete all cached posts, feeds, and other metadata for this user on this site.', 'friends' );
	if ( is_multisite() ) {
		echo ' ';
		esc_html_e( 'Since this is a multisite install, the user will only be removed from this site.', 'friends' );
	}
	?>
	</p>

	<p class="submit">
		<input type="submit" id="submit" class="button button-primary" value="<?php echo esc_html( $button_text ); ?>">
	</p>

</form>
</div>
