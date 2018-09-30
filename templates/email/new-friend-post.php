<?php
/**
 * This template contains the HTML for the New Friend Post notification e-mail.
 *
 * @package Friends
 */

?>
<?php include __DIR__ . '/header.php'; ?>
<blockquote>
	<?php
	echo $post->post_content;
	?>
</blockquote>

<p>
	<?php
	printf(
		// translators: %1$s is a username, %2$s is a post title as a link.
		__( 'This post was published by your friend %1$s: %2$s', 'friends' ),
		'<em>' . $author->display_name . '</em>',
		'<strong><a href="' . get_permalink( $post ) . '">' . $post->post_title . '</a></strong>'
	);
	?>
	<a href="<?php echo site_url( '/friends/' . $post->ID . '/' ); ?>">
		<?php
			esc_html_e( 'You can view post on your friends page', 'friends' );
		?>
	</a>
</p>

<p>
	<?php
	printf(
		// translators: %1$s is a URL, %2$s is a URL, %3$s is a username.
		__( 'Manage your <a href=%1$s>subscription settings</a> or <a href=%2$s>just unsubscribe from %3$s\'s posts</a>', 'friends' ),
		'"' . esc_url( self_admin_url( 'admin.php?page=friends-settings' ) ) . '"',
		'"' . esc_url( self_admin_url( 'admin.php?page=edit-friend&user=' . $author->ID ) ) . '"',
		'<em>' . $author->display_name . '</em>'
	);
	?>
</p>

<p>
	<?php
		esc_html_e( 'This notification was brought to you by the Friends plugin.', 'friends' );
	?>
</p>
<?php include __DIR__ . '/footer.php'; ?>
