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
	echo wp_kses_post( $post->post_content );
	?>
</blockquote>

<p>
	<?php
	$post_title = $post->post_title;
	if ( '' === trim( $post_title ) ) {
		$post_title = $post->post_date;
	}
	printf(
		// translators: %1$s is a username, %2$s is a post title as a link.
		__( 'This post was published by your friend %1$s: %2$s', 'friends' ),
		'<em>' . esc_html( $author->display_name ) . '</em>',
		'<strong><a href="' . get_permalink( $post ) . '">' . esc_html( $post_title ) . '</a></strong>'
	);
	?>
	<a href="<?php echo site_url( '/friends/' . $post->ID . '/' ); ?>">
		<?php
			esc_html_e( 'View the post on your friends page', 'friends' );
		?>
	</a>
</p>

<p>
	<?php
	printf(
		// translators: %1$s is a URL, %2$s is a URL, %3$s is a username.
		__( 'Manage your <a href=%1$s>subscription settings</a> or <a href=%2$s>stop notifications for %3$s\'s posts</a>', 'friends' ),
		'"' . esc_url( self_admin_url( 'admin.php?page=friends-settings' ) ) . '"',
		'"' . esc_url( self_admin_url( 'admin.php?page=edit-friend&user=' . $author->ID ) ) . '"',
		'<em>' . $author->display_name . '</em>'
	);
	?>
</p>

<p>
	<?php
		// translators: %s is a site name.
		echo wp_kses( sprintf( __( 'This notification was sent by the Friends plugin on %s.', 'friends' ), '<a href="' . esc_attr( site_url() ) . '">' . ( is_multisite() ? get_site_option( 'site_name' ) : get_option( 'blogname' ) ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) );
	?>
</p>
<?php include __DIR__ . '/footer.php'; ?>
